<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // Set success message for SweetAlert
        $_SESSION['login_success'] = true;
        
        switch($user['role']) {
            case 'admin':
                redirect('admin/dashboard.php');
                break;
            case 'doctor':
                redirect('doctor/dashboard.php');
                break;
            case 'receptionist':
                redirect('receptionist/dashboard.php');
                break;
            case 'laboratory':
                redirect('laboratory/dashboard.php');
                break;
            case 'pharmacy':
                redirect('pharmacy/dashboard.php');
                break;
            case 'cashier':
                redirect('cashier/dashboard.php');
                break;
            default:
                $error = "Invalid role";
        }
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Almajyd Dispensary - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-green: #4CAF50;
            --dark-green: #2E7D32;
            --light-green: #E8F5E8;
            --accent-green: #81C784;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
        }
        
        body {
            background: linear-gradient(135deg, #1a2f1a 0%, #0d1f0d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
            overflow-x: hidden;
            padding: 15px;
        }
        
        /* Background elements for desktop */
        .scene {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .parallax-bg {
            position: absolute;
            width: 120%;
            height: 120%;
            top: -10%;
            left: -10%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(76, 175, 80, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(46, 125, 50, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(129, 199, 132, 0.1) 0%, transparent 50%);
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 50%;
            animation: floatElement 15s infinite linear;
        }
        
        .floating-element:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 10%;
            animation-duration: 20s;
        }
        
        .floating-element:nth-child(2) {
            width: 150px;
            height: 150px;
            top: 60%;
            left: 80%;
            animation-duration: 25s;
        }
        
        .floating-element:nth-child(3) {
            width: 70px;
            height: 70px;
            top: 80%;
            left: 20%;
            animation-duration: 15s;
        }
        
        /* Main login container */
        .login-wrapper {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 10px 0 rgba(76, 175, 80, 0.1),
                0 0 30px rgba(76, 175, 80, 0.2);
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
        }
        
        .logo-section {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            padding: 40px 20px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .logo-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        .logo-3d {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 
                0 15px 30px rgba(0,0,0,0.3),
                0 0 0 8px rgba(255,255,255,0.2),
                0 0 0 16px rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
            animation: float 6s ease-in-out infinite;
        }
        
        .logo-3d img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .pulse-ring {
            position: absolute;
            width: 160px;
            height: 160px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 3s infinite;
        }
        
        .form-section {
            padding: 40px;
            position: relative;
            z-index: 2;
            background: white;
        }
        
        .btn-3d {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-weight: 600;
            color: white;
            box-shadow: 
                0 10px 20px rgba(0,0,0,0.2),
                0 6px 0 var(--dark-green),
                inset 0 1px 0 rgba(255,255,255,0.3);
            transform: translateY(0);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
        }
        
        .btn-3d:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 15px 25px rgba(0,0,0,0.3),
                0 8px 0 var(--dark-green),
                inset 0 1px 0 rgba(255,255,255,0.3);
        }
        
        .btn-3d:active {
            transform: translateY(2px);
            box-shadow: 
                0 5px 15px rgba(0,0,0,0.2),
                0 2px 0 var(--dark-green),
                inset 0 1px 0 rgba(255,255,255,0.3);
        }
        
        .btn-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-3d:hover::before {
            left: 100%;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            background: rgba(248, 249, 250, 0.8);
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
            background: white;
        }
        
        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            z-index: 20;
        }
        
        .back-to-home:hover {
            transform: translateX(-5px);
            color: white;
            background: rgba(255,255,255,0.3);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .input-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            z-index: 3;
            font-size: 1.2rem;
        }
        
        .welcome-text {
            font-size: 1.1rem;
            color: #6c757d;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .feature-badge {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 0.8rem;
            margin: 5px;
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .feature-badge:hover {
            transform: translateY(-5px);
        }
        
        .role-grid {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
            width: 100%;
            max-width: 450px;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-15px);
            }
        }
        
        @keyframes shine {
            0% {
                transform: rotate(45deg) translateX(-100%);
            }
            100% {
                transform: rotate(45deg) translateX(200%);
            }
        }
        
        @keyframes floatElement {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-30px) rotate(180deg);
            }
            100% {
                transform: translateY(0) rotate(360deg);
            }
        }
        
        @keyframes pulse {
            0% {
                transform: translate(-50%, -50%) scale(0.8);
                opacity: 1;
            }
            100% {
                transform: translate(-50%, -50%) scale(1.5);
                opacity: 0;
            }
        }
        
        /* MOBILE FIXES - HII NI SEHEMU MUHIMU SANA */
        @media (max-width: 768px) {
            body {
                padding: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                height: auto;
            }
            
            .login-wrapper {
                width: 100%;
                max-width: 400px;
                margin: 0 auto;
            }
            
            .login-container {
                width: 100%;
                box-shadow: 
                    0 15px 30px rgba(0, 0, 0, 0.2),
                    0 5px 0 rgba(76, 175, 80, 0.1);
            }
            
            .logo-3d {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .form-section {
                padding: 30px 20px;
            }
            
            .back-to-home {
                position: fixed;
                top: 15px;
                left: 15px;
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            /* Disable complex background on mobile for better performance */
            .scene, .parallax-bg, .floating-elements {
                display: none;
            }
            
            /* Simple background for mobile */
            body {
                background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            }
            
            .role-grid {
                max-width: 400px;
                margin: 20px auto 0;
            }
        }
        
        @media (max-width: 480px) {
            .login-wrapper {
                max-width: 350px;
            }
            
            .logo-3d {
                width: 90px;
                height: 90px;
                font-size: 2.2rem;
            }
            
            .form-section {
                padding: 25px 15px;
            }
            
            .logo-section {
                padding: 30px 15px;
            }
            
            .welcome-text {
                font-size: 1rem;
                margin-bottom: 25px;
            }
            
            .feature-badge {
                padding: 6px 12px;
                font-size: 0.75rem;
                margin: 3px;
            }
            
            .role-grid {
                max-width: 350px;
                margin-top: 15px;
            }
        }
        
        /* Fix for very small screens */
        @media (max-width: 360px) {
            .login-wrapper {
                max-width: 320px;
            }
            
            .logo-3d {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            
            .form-section {
                padding: 20px 12px;
            }
            
            .form-control {
                padding: 12px 12px 12px 45px;
            }
            
            .input-group .icon {
                left: 12px;
                font-size: 1rem;
            }
            
            .btn-3d {
                padding: 12px;
            }
            
            .role-grid {
                max-width: 320px;
            }
        }
        
        /* Extra insurance for centering */
        .centered-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <!-- 3D Background Scene (hidden on mobile) -->
    <div class="scene">
        <div class="parallax-bg"></div>
        
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
    </div>
    
    <!-- Back to Home Arrow -->
    <a href="index.php" class="back-to-home">
        <i class="fas fa-arrow-left me-2"></i>Back to Home
    </a>

    <!-- Main Content Container -->
    <div class="centered-container">
        <div class="login-wrapper">
            <div class="login-container">
                <!-- Logo Section -->
                <div class="logo-section">
                    <div class="logo-3d">
                        <?php
                        // Check if logo image exists
                        $logoPath = 'images/logo.jpg';
                        if (file_exists($logoPath)) {
                            echo '<img src="' . $logoPath . '" alt="Hospital Logo">';
                        } else {
                            echo '<i class="fas fa-hospital-alt"></i>';
                        }
                        ?>
                        <div class="pulse-ring"></div>
                    </div>
                    <h3 class="mb-2">Almajyd Dispensary</h3>
                    <p class="mb-0 opacity-75">Advanced Healthcare Portal</p>
                </div>
                
                <!-- Form Section -->
                <div class="form-section">
                    <div class="welcome-text">
                        <i class="fas fa-shield-alt text-success me-2"></i>
                        Secure Access Portal
                    </div>
                    
                    <form method="POST" id="loginForm">
                        <div class="mb-4">
                            <div class="input-group">
                                <i class="fas fa-user icon"></i>
                                <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="input-group">
                                <i class="fas fa-lock icon"></i>
                                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-3d mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Access System
                        </button>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-key me-1"></i>
                                Protected by advanced encryption
                            </small>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Role Badges -->
            <div class="role-grid">
                <div class="feature-badge">
                    <i class="fas fa-user-shield me-1"></i>Admin
                </div>
                <div class="feature-badge">
                    <i class="fas fa-user-md me-1"></i>Doctor
                </div>
                <div class="feature-badge">
                    <i class="fas fa-user-tie me-1"></i>Receptionist
                </div>
                <div class="feature-badge">
                    <i class="fas fa-vial me-1"></i>Laboratory
                </div>
                <div class="feature-badge">
                    <i class="fas fa-pills me-1"></i>Pharmacy
                </div>
                <div class="feature-badge">
                    <i class="fas fa-cash-register me-1"></i>Cashier
                </div>
            </div>
        </div>
    </div>

    <script>
        // SweetAlert for error messages
        <?php if (isset($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#4CAF50',
                confirmButtonText: 'Try Again',
                backdrop: 'rgba(0,0,0,0.4)'
            });
        });
        <?php endif; ?>

        // SweetAlert for successful login (if redirected back for some reason)
        <?php if (isset($_SESSION['login_success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Access Granted!',
                text: 'Welcome back, <?php echo $_SESSION['full_name']; ?>!',
                confirmButtonColor: '#4CAF50',
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['login_success']); ?>
        });
        <?php endif; ?>

        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
            submitBtn.disabled = true;
        });

        // Check if device is mobile
        function isMobileDevice() {
            return (typeof window.orientation !== "undefined") || (navigator.userAgent.indexOf('IEMobile') !== -1);
        };

        // Only apply 3D effects on non-mobile devices
        if (!isMobileDevice()) {
            // Add any desktop-specific JavaScript here if needed
        }
        
        // Force centering on load
        window.addEventListener('load', function() {
            const centeredContainer = document.querySelector('.centered-container');
            centeredContainer.style.minHeight = window.innerHeight + 'px';
        });
        
        // Adjust on resize
        window.addEventListener('resize', function() {
            const centeredContainer = document.querySelector('.centered-container');
            centeredContainer.style.minHeight = window.innerHeight + 'px';
        });
    </script>
</body>
</html>