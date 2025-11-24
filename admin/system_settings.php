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

// Get current user information
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Profile Information Update
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    try {
        // Check if updated_at column exists
        $check_column = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'updated_at'");
        $check_column->execute();
        $column_exists = $check_column->fetch();
        
        if ($column_exists) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $user_id]);
        }
        
        // Update session
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        
        $success_message = "Profile information updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Handle Password Update
if (isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $error_message = "Current password is incorrect!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long!";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Check if updated_at column exists
            $check_column = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'updated_at'");
            $check_column->execute();
            $column_exists = $check_column->fetch();
            
            if ($column_exists) {
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
            
            $success_message = "Password updated successfully!";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error_message = "Error updating password: " . $e->getMessage();
        }
    }
}

// Get last update date - use created_at if updated_at doesn't exist
$last_update = isset($user['updated_at']) && !empty($user['updated_at']) ? 
    date('M j, Y', strtotime($user['updated_at'])) : 
    date('M j, Y', strtotime($user['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Almajyd Dispensary</title>
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
            max-width: 1200px; 
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

        /* PERSISTENT NAVIGATION STEPS */
        .steps-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .steps-title {
            text-align: center;
            margin-bottom: 25px;
            color: #1e293b;
            font-size: 1.4rem;
        }
        
        .steps {
            display: flex;
            align-items: center;
            margin: 30px 0;
            position: relative;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e2e8f0;
            border-radius: 3px;
            z-index: 1;
        }
        
        .step {
            width: 60px;
            height: 60px;
            background: white;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: #64748b;
            z-index: 2;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .step:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .step.active {
            border-color: #10b981;
            color: white;
            background: #10b981;
            box-shadow: 0 0 15px rgba(16,185,129,0.4);
        }
        
        .step-label {
            position: absolute;
            top: 100%;
            margin-top: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }
        
        .spacer { 
            flex-grow: 1; 
            min-width: 30px; 
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

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .settings-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .settings-card h3 {
            margin-bottom: 20px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }

        /* Profile Info */
        .profile-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
        }
        
        .profile-details h4 {
            color: #1e293b;
            margin-bottom: 5px;
            font-size: 1.3rem;
        }
        
        .profile-details p {
            color: #64748b;
            margin-bottom: 3px;
            font-size: 0.9rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
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

        /* Password Strength */
        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }
        
        .strength-weak {
            color: #ef4444;
        }
        
        .strength-medium {
            color: #f59e0b;
        }
        
        .strength-strong {
            color: #10b981;
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
            
            .steps::before {
                display: none;
            }
            
            .steps {
                justify-content: space-around;
            }
            
            .spacer {
                display: none;
            }
            
            .step {
                width: 50px;
                height: 50px;
                font-size: 16px;
            }
            
            .step-label {
                font-size: 10px;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-card {
                padding: 15px;
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
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
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size: 0.9rem;"><?php echo $user['full_name']; ?></div>
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
                    <i class="fas fa-user-cog"></i>
                    Admin Settings
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Manage your profile information and password</p>
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

        <!-- PERSISTENT NAVIGATION STEPS -->
        <div class="steps-container">
            <h2 class="steps-title">Admin Control Panel</h2>
            
            <div class="steps">
                <div class="step" onclick="window.location.href='dashboard.php'">
                    1
                    <div class="step-label">Dashboard</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="window.location.href='view_patients.php'">
                    2
                    <div class="step-label">Patients</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="window.location.href='patient_reports.php'">
                    3
                    <div class="step-label">Reports</div>
                </div>
                <div class="spacer"></div>
                <div class="step active">
                    4
                    <div class="step-label">My Settings</div>
                </div>
            </div>

            <!-- Profile Overview -->
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="profile-details">
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-user-tag"></i> Administrator</p>
                    <p><i class="fas fa-calendar"></i> Member since: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- Profile Information -->
            <div class="settings-card">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number">
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Password Update -->
            <div class="settings-card">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" id="newPassword" required minlength="6">
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" id="confirmPassword" required minlength="6">
                        <div class="password-strength" id="passwordMatch"></div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Account Security -->
            <div class="settings-card">
                <h3><i class="fas fa-shield-alt"></i> Account Security</h3>
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; font-size: 1rem;">Security Status</h4>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <span>Last update: <?php echo $last_update; ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <span>Account created: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
                
                <div style="border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <h4 style="margin-bottom: 10px; font-size: 1rem;">Security Tips</h4>
                    <ul style="color: #64748b; font-size: 0.9rem; line-height: 1.6;">
                        <li>Use a strong, unique password</li>
                        <li>Change your password regularly</li>
                        <li>Never share your login credentials</li>
                        <li>Log out when using public computers</li>
                    </ul>
                </div>
            </div>

            <!-- Session Information -->
            <div class="settings-card">
                <h3><i class="fas fa-info-circle"></i> Session Information</h3>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: between; margin-bottom: 8px;">
                        <span style="font-weight: 600;">Current Session:</span>
                        <span style="color: #10b981;">Active</span>
                    </div>
                    <div style="display: flex; justify-content: between; margin-bottom: 8px;">
                        <span style="font-weight: 600;">Login Time:</span>
                        <span><?php echo date('M j, Y h:i A'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: between; margin-bottom: 8px;">
                        <span style="font-weight: 600;">IP Address:</span>
                        <span><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: between;">
                        <span style="font-weight: 600;">Browser:</span>
                        <span><?php echo $_SERVER['HTTP_USER_AGENT']; ?></span>
                    </div>
                </div>
                
                <button onclick="location.reload()" class="btn btn-warning" style="width: 100%;">
                    <i class="fas fa-sync"></i> Refresh Session Info
                </button>
            </div>
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

        // Password strength checker
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthElement = document.getElementById('passwordStrength');
            
            let strength = 'Weak';
            let strengthClass = 'strength-weak';
            
            if (password.length >= 8) {
                strength = 'Medium';
                strengthClass = 'strength-medium';
            }
            
            if (password.length >= 10 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                strength = 'Strong';
                strengthClass = 'strength-strong';
            }
            
            strengthElement.textContent = `Password strength: ${strength}`;
            strengthElement.className = `password-strength ${strengthClass}`;
        });

        // Password match checker
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            const matchElement = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchElement.textContent = '';
            } else if (newPassword === confirmPassword) {
                matchElement.textContent = '✓ Passwords match';
                matchElement.className = 'password-strength strength-strong';
            } else {
                matchElement.textContent = '✗ Passwords do not match';
                matchElement.className = 'password-strength strength-weak';
            }
        });

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>