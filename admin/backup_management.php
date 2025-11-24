<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle backup creation
$success_message = '';
$error_message = '';

if (isset($_POST['create_backup'])) {
    try {
        // Create backup directory if it doesn't exist
        if (!is_dir('../backups')) {
            mkdir('../backups', 0755, true);
        }
        
        // Generate backup filename
        $backup_file = '../backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Get database configuration
        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PASS;
        $dbname = DB_NAME;
        
        // Execute mysqldump command
        $command = "mysqldump --host={$host} --user={$user} --password={$pass} {$dbname} > {$backup_file} 2>&1";
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $success_message = "Database backup created successfully!";
        } else {
            $error_message = "Error creating database backup. Please check server configuration.";
        }
    } catch (Exception $e) {
        $error_message = "Backup error: " . $e->getMessage();
    }
}

// Handle backup download
if (isset($_GET['download'])) {
    $backup_file = '../backups/' . $_GET['download'];
    if (file_exists($backup_file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);
        exit;
    }
}

// Handle backup deletion
if (isset($_GET['delete'])) {
    $backup_file = '../backups/' . $_GET['delete'];
    if (file_exists($backup_file)) {
        if (unlink($backup_file)) {
            $success_message = "Backup deleted successfully!";
        } else {
            $error_message = "Error deleting backup file.";
        }
    }
}

// Get list of backups
$backups = [];
if (is_dir('../backups')) {
    $backup_files = glob('../backups/backup_*.sql');
    rsort($backup_files); // Sort by newest first
    $backups = $backup_files;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management - Almajyd Dispensary</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
            color: #334155;
        }
        
        /* Topbar */
        .topbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 3px solid #10b981;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .clinic-info h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .clinic-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        .user-area {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .time-display {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f1f5f9;
            padding: 8px 15px;
            border-radius: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Main Content */
        .main { 
            padding: 25px; 
            max-width: 1000px; 
            margin: 0 auto; 
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            color: #1e293b;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
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

        /* Backup Card */
        .backup-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            text-align: center;
        }
        
        .backup-card h3 {
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1.4rem;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Backup List */
        .backup-list {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .backup-list h3 {
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8fafc;
        }
        
        .backup-info {
            flex-grow: 1;
        }
        
        .backup-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .backup-date {
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.8rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #cbd5e1;
        }

        /* MOBILE RESPONSIVE DESIGN */
        @media (max-width: 768px) {
            .topbar {
                padding: 12px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .logo-section {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            
            .user-area {
                width: 100%;
                justify-content: space-between;
            }
            
            .main {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                justify-content: center;
            }
            
            .backup-item {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .backup-actions {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .user-area {
                flex-direction: column;
                gap: 10px;
            }
            
            .time-display, .user, .logout-btn {
                width: 100%;
                justify-content: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>TOMONDO - ZANZIBAR</p>
            </div>
        </div>
        
        <div class="user-area">
            <div class="time-display">
                <i class="fas fa-clock"></i>
                <span id="currentTime"><?php echo date('h:i A'); ?></span>
            </div>
            <div class="user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></div>
                    <small style="font-size: 0.75rem;">Administrator</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-database"></i>
                    Backup Management
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Create and manage database backups</p>
            </div>
            <div class="page-actions">
                <a href="dashboard.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Backup Creation Card -->
        <div class="backup-card">
            <h3><i class="fas fa-plus-circle"></i> Create New Backup</h3>
            <p style="margin-bottom: 20px; color: #64748b;">Create a complete backup of the database</p>
            
            <form method="POST" action="">
                <button type="submit" name="create_backup" class="btn btn-success">
                    <i class="fas fa-download"></i> Create Backup Now
                </button>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                <h4 style="margin-bottom: 10px; color: #475569; font-size: 1rem;">
                    <i class="fas fa-info-circle"></i> Backup Information
                </h4>
                <ul style="color: #64748b; text-align: left; line-height: 1.6;">
                    <li>Backup includes all patient records and system data</li>
                    <li>Backup files are stored in the /backups/ directory</li>
                    <li>Each backup is timestamped for easy identification</li>
                    <li>Regular backups are recommended for data safety</li>
                </ul>
            </div>
        </div>

        <!-- Backup List -->
        <div class="backup-list">
            <h3><i class="fas fa-list"></i> Available Backups</h3>
            
            <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <h4>No backups found</h4>
                    <p>Create your first backup to get started</p>
                </div>
            <?php else: ?>
                <?php foreach ($backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo basename($backup); ?></div>
                            <div class="backup-date">
                                File size: <?php echo round(filesize($backup) / 1024, 2); ?> KB | 
                                Created: <?php echo date('M j, Y H:i', filemtime($backup)); ?>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <a href="?download=<?php echo basename($backup); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="?delete=<?php echo basename($backup); ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this backup?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Update time every minute
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        updateTime();
        setInterval(updateTime, 60000);

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>