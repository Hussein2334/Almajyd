<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header('Location: ../login.php');
    exit;
}

// Handle status updates
if (isset($_POST['update_status'])) {
    $checkup_id = $_POST['checkup_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE checking_forms SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $checkup_id]);
        
        $success_message = "Checkup status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

// Handle delete checkup
if (isset($_POST['delete_checkup'])) {
    $checkup_id = $_POST['checkup_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete related prescriptions first
        $stmt1 = $pdo->prepare("DELETE FROM prescriptions WHERE checking_form_id = ?");
        $stmt1->execute([$checkup_id]);
        
        // Delete related laboratory tests
        $stmt2 = $pdo->prepare("DELETE FROM laboratory_tests WHERE checking_form_id = ?");
        $stmt2->execute([$checkup_id]);
        
        // Delete the checkup
        $stmt3 = $pdo->prepare("DELETE FROM checking_forms WHERE id = ? AND doctor_id = ?");
        $stmt3->execute([$checkup_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        $success_message = "Checkup deleted successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error deleting checkup: " . $e->getMessage();
    }
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for checkups
$query = "SELECT cf.*, p.full_name as patient_name, p.card_no, p.age, p.gender, 
                 u.full_name as doctor_name
          FROM checking_forms cf 
          JOIN patients p ON cf.patient_id = p.id 
          LEFT JOIN users u ON cf.doctor_id = u.id 
          WHERE cf.doctor_id = ?";

$params = [$_SESSION['user_id']];

// Add search filter
if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.card_no LIKE ? OR cf.diagnosis LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add status filter
if (!empty($status_filter)) {
    $query .= " AND cf.status = ?";
    $params[] = $status_filter;
}

// Add date filter
if (!empty($date_from)) {
    $query .= " AND DATE(cf.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(cf.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY cf.created_at DESC";

// Get checkups
$checkups_stmt = $pdo->prepare($query);
$checkups_stmt->execute($params);
$checkups = $checkups_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_checkups = count($checkups);
$pending_checkups = array_filter($checkups, function($c) { return $c['status'] == 'pending'; });

// Get today's checkups
$today_checkups = array_filter($checkups, function($c) {
    return date('Y-m-d', strtotime($c['created_at'])) == date('Y-m-d');
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Checkups - Almajyd Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-info h1 {
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .header-info p {
            color: #666;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #2c5aa0;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3d6d;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #2c5aa0;
        }
        
        .stat-card.pending {
            border-left-color: #ffc107;
        }
        
        .stat-card.today {
            border-left-color: #17a2b8;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .stat-card.pending .stat-number {
            color: #ffc107;
        }
        
        .stat-card.today .stat-number {
            color: #17a2b8;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 15px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        /* Checkups Grid */
        .checkups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .checkup-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #2c5aa0;
            transition: all 0.3s ease;
        }
        
        .checkup-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .checkup-card.pending {
            border-left-color: #ffc107;
        }
        
        .checkup-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .patient-info h3 {
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .patient-meta {
            color: #666;
            font-size: 13px;
        }
        
        .checkup-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .checkup-content {
            margin-bottom: 15px;
        }
        
        .content-group {
            margin-bottom: 10px;
        }
        
        .content-label {
            font-weight: bold;
            color: #555;
            font-size: 13px;
            margin-bottom: 3px;
        }
        
        .content-text {
            color: #333;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .checkup-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .delete-form {
            margin-left: auto;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ccc;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .checkups-grid {
                grid-template-columns: 1fr;
            }
            
            .checkup-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .checkup-actions {
                justify-content: center;
            }
            
            .delete-form {
                margin-left: 0;
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-info">
                <h1>My Medical Checkups</h1>
                <p>Manage and track all your patient checkups</p>
            </div>
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <a href="new_checkup.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> New Checkup
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_checkups; ?></div>
                <div class="stat-label">Total Checkups</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?php echo count($pending_checkups); ?></div>
                <div class="stat-label">Pending Checkups</div>
            </div>
            <div class="stat-card today">
                <div class="stat-number"><?php echo count($today_checkups); ?></div>
                <div class="stat-label">Today's Checkups</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">Filter Checkups</div>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Search Patient</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by patient name, card no, or diagnosis..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                    <a href="doctor_checkups.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Checkups Grid -->
        <div class="checkups-grid">
            <?php if (!empty($checkups)): ?>
                <?php foreach ($checkups as $checkup): ?>
                    <div class="checkup-card <?php echo $checkup['status']; ?>">
                        <div class="checkup-header">
                            <div class="patient-info">
                                <h3><?php echo htmlspecialchars($checkup['patient_name']); ?></h3>
                                <div class="patient-meta">
                                    Card: <?php echo htmlspecialchars($checkup['card_no']); ?> | 
                                    Age: <?php echo $checkup['age'] ?: 'N/A'; ?> | 
                                    Gender: <?php echo $checkup['gender'] ? ucfirst($checkup['gender']) : 'N/A'; ?>
                                </div>
                            </div>
                            <div class="checkup-status status-<?php echo $checkup['status']; ?>">
                                <?php echo strtoupper($checkup['status']); ?>
                            </div>
                        </div>
                        
                        <div class="checkup-content">
                            <div class="content-group">
                                <div class="content-label">Checkup Date:</div>
                                <div class="content-text">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('F j, Y \a\t h:i A', strtotime($checkup['created_at'])); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($checkup['symptoms'])): ?>
                                <div class="content-group">
                                    <div class="content-label">Symptoms:</div>
                                    <div class="content-text"><?php echo htmlspecialchars(substr($checkup['symptoms'], 0, 100)) . (strlen($checkup['symptoms']) > 100 ? '...' : ''); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($checkup['diagnosis'])): ?>
                                <div class="content-group">
                                    <div class="content-label">Diagnosis:</div>
                                    <div class="content-text"><?php echo htmlspecialchars($checkup['diagnosis']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($checkup['notes'])): ?>
                                <div class="content-group">
                                    <div class="content-label">Notes:</div>
                                    <div class="content-text"><?php echo htmlspecialchars(substr($checkup['notes'], 0, 100)) . (strlen($checkup['notes']) > 100 ? '...' : ''); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="checkup-actions">
                            <a href="print_checkup.php?id=<?php echo $checkup['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                <i class="fas fa-print"></i> Print
                            </a>
                            <a href="edit_checkup.php?id=<?php echo $checkup['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="patient_history.php?patient_id=<?php echo $checkup['patient_id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-history"></i> History
                            </a>
                            
                            <!-- Delete Form -->
                            <form method="POST" class="delete-form" onsubmit="return confirmDelete()">
                                <input type="hidden" name="checkup_id" value="<?php echo $checkup['id']; ?>">
                                <button type="submit" name="delete_checkup" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-stethoscope"></i>
                    <h3>No Checkups Found</h3>
                    <p>No medical checkups found matching your criteria.</p>
                    <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
                        <p>Try adjusting your filters or <a href="doctor_checkups.php">clear all filters</a>.</p>
                    <?php else: ?>
                        <a href="new_checkup.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Create Your First Checkup
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Confirm delete action
        function confirmDelete() {
            return confirm('Are you sure you want to delete this checkup? This action cannot be undone and will also delete all related prescriptions and lab tests.');
        }
        
        // Auto-refresh page every 60 seconds
        setInterval(function() {
            // You can implement auto-refresh here if needed
            // location.reload();
        }, 60000);
    </script>
</body>
</html>