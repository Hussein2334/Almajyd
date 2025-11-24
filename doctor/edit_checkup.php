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

// Get checkup ID from URL
$checkup_id = $_GET['id'] ?? 0;

if (!$checkup_id) {
    die('Checkup ID not specified');
}

// Get checkup details
$checkup_stmt = $pdo->prepare("SELECT cf.*, p.full_name as patient_name, p.card_no, p.age, p.gender, p.weight,
                              u.full_name as doctor_name
                       FROM checking_forms cf 
                       JOIN patients p ON cf.patient_id = p.id 
                       LEFT JOIN users u ON cf.doctor_id = u.id 
                       WHERE cf.id = ? AND cf.doctor_id = ?");
$checkup_stmt->execute([$checkup_id, $_SESSION['user_id']]);
$checkup = $checkup_stmt->fetch(PDO::FETCH_ASSOC);

if (!$checkup) {
    die('Checkup not found or you do not have permission to edit it');
}

// Handle form submission
$success_message = '';
$error_message = '';

if (isset($_POST['update_checkup'])) {
    $symptoms = $_POST['symptoms'];
    $diagnosis = $_POST['diagnosis'];
    $notes = $_POST['notes'];
    
    try {
        $stmt = $pdo->prepare("UPDATE checking_forms 
                              SET symptoms = ?, diagnosis = ?, notes = ?, updated_at = NOW() 
                              WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$symptoms, $diagnosis, $notes, $checkup_id, $_SESSION['user_id']]);
        
        $success_message = "Checkup updated successfully!";
        
        // Refresh checkup data
        $checkup_stmt->execute([$checkup_id, $_SESSION['user_id']]);
        $checkup = $checkup_stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error_message = "Error updating checkup: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Checkup - Almajyd Dispensary</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Header */
        .header {
            background: #2c5aa0;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 5px;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
            margin: 15px;
            border-radius: 5px;
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
        
        /* Patient Info */
        .patient-info {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .detail-group {
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .detail-value {
            color: #333;
            font-size: 14px;
        }
        
        /* Form */
        .form-section {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 2px rgba(44, 90, 160, 0.1);
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        /* Responsive */
        @media (max-width: 600px) {
            .patient-details {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
     <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Edit Checkup</h1>
            <p>Update patient examination details</p>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✓ <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ✗ <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Patient Information -->
        <div class="patient-info">
            <h3 style="color: #2c5aa0; margin-bottom: 15px;">Patient Information</h3>
            <div class="patient-details">
                <div class="detail-group">
                    <div class="detail-label">Patient Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($checkup['patient_name']); ?></div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Card Number:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($checkup['card_no']); ?></div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Age:</div>
                    <div class="detail-value"><?php echo $checkup['age'] ?: 'N/A'; ?></div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Gender:</div>
                    <div class="detail-value"><?php echo $checkup['gender'] ? ucfirst($checkup['gender']) : 'N/A'; ?></div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Checkup Date:</div>
                    <div class="detail-value"><?php echo date('F j, Y \a\t h:i A', strtotime($checkup['created_at'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Medical Examination Form -->
        <form method="POST" action="">
            <div class="form-section">
                <div class="form-group">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control form-textarea" placeholder="Describe patient symptoms..."><?php echo htmlspecialchars($checkup['symptoms'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Diagnosis</label>
                    <textarea name="diagnosis" class="form-control form-textarea" placeholder="Enter medical diagnosis..."><?php echo htmlspecialchars($checkup['diagnosis'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Clinical Notes</label>
                    <textarea name="notes" class="form-control form-textarea" placeholder="Additional clinical notes and observations..."><?php echo htmlspecialchars($checkup['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="patient_history.php" class="btn btn-secondary">
                        ← Cancel
                    </a>
                    <button type="submit" name="update_checkup" class="btn btn-primary">
                        ✓ Update Checkup
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Auto-save confirmation
        let formChanged = false;
        const form = document.querySelector('form');
        
        form.addEventListener('input', function() {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Remove confirmation when form is submitted
        form.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>