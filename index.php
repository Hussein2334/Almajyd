<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/logo.jpg">
    <title>Almajyd Dispensary - Advanced Healthcare Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #4CAF50;
            --dark-green: #2E7D32;
            --light-green: #E8F5E8;
            --accent-green: #81C784;
            --hover-green: #45a049;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a2f1a 0%, #0d1f0d 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
            overflow-x: hidden;
            perspective: 1000px;
        }
        
        .scene {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
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
            transform-style: preserve-3d;
            transform: translateZ(-50px) scale(2);
        }
        
        .hero-section {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 10;
            transform-style: preserve-3d;
        }
        
        .logo-container {
            position: relative;
            margin-bottom: 2rem;
            transform-style: preserve-3d;
            animation: float 6s ease-in-out infinite;
        }
        
        .logo-3d {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            box-shadow: 
                0 20px 40px rgba(0,0,0,0.3),
                0 0 0 8px rgba(76, 175, 80, 0.2),
                0 0 0 16px rgba(76, 175, 80, 0.1);
            transform: rotateX(10deg) rotateY(5deg);
            transition: transform 0.5s ease;
            overflow: hidden;
            position: relative;
        }
        
        .logo-3d img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .logo-3d::before {
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
        
        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transform: translateZ(30px);
            background: linear-gradient(to right, #fff, #e0f7e0);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            transform: translateZ(20px);
        }
        
        .btn-3d {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border: none;
            border-radius: 50px;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            box-shadow: 
                0 10px 20px rgba(0,0,0,0.2),
                0 6px 0 var(--dark-green),
                inset 0 1px 0 rgba(255,255,255,0.3);
            transform: translateY(0) translateZ(20px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-3d:hover {
            transform: translateY(-5px) translateZ(25px);
            box-shadow: 
                0 15px 25px rgba(0,0,0,0.3),
                0 8px 0 var(--dark-green),
                inset 0 1px 0 rgba(255,255,255,0.3);
        }
        
        .btn-3d:active {
            transform: translateY(2px) translateZ(15px);
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
        
        .features-section {
            position: relative;
            padding: 100px 0;
            background: rgba(10, 20, 10, 0.7);
            backdrop-filter: blur(10px);
            transform-style: preserve-3d;
            z-index: 5;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            transform: translateZ(30px);
        }
        
        .feature-cards {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 30px;
            perspective: 1000px;
        }
        
        .feature-card {
            width: 300px;
            height: 350px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(76, 175, 80, 0.2);
            box-shadow: 
                0 15px 35px rgba(0,0,0,0.2),
                0 5px 0 rgba(76, 175, 80, 0.1);
            transform-style: preserve-3d;
            transition: transform 0.5s ease, box-shadow 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-green), var(--accent-green));
        }
        
        .feature-card:hover {
            transform: translateY(-15px) rotateX(5deg) rotateY(5deg) translateZ(20px);
            box-shadow: 
                0 25px 50px rgba(0,0,0,0.3),
                0 10px 0 rgba(76, 175, 80, 0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transform: translateZ(30px);
        }
        
        .feature-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            text-align: center;
            transform: translateZ(20px);
        }
        
        .feature-description {
            text-align: center;
            opacity: 0.8;
            line-height: 1.6;
            transform: translateZ(15px);
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 1;
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
        
        .floating-element:nth-child(4) {
            width: 120px;
            height: 120px;
            top: 30%;
            left: 70%;
            animation-duration: 30s;
        }
        
        .footer {
            position: relative;
            padding: 40px 0;
            text-align: center;
            background: rgba(5, 15, 5, 0.8);
            backdrop-filter: blur(5px);
            z-index: 5;
        }
        
        .pulse-ring {
            position: absolute;
            width: 200px;
            height: 200px;
            border: 2px solid rgba(76, 175, 80, 0.3);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 3s infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotateX(10deg) rotateY(5deg);
            }
            50% {
                transform: translateY(-20px) rotateX(10deg) rotateY(5deg);
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
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .feature-card {
                width: 100%;
                max-width: 300px;
            }
            
            .logo-3d {
                width: 150px;
                height: 150px;
                font-size: 3rem;
            }
        }
        
        /* Mouse move parallax effect */
        .parallax-element {
            transition: transform 0.1s ease-out;
        }
    </style>
</head>
<body>
    <div class="scene">
        <div class="parallax-bg"></div>
        
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        
        <div class="hero-section">
            <div class="container">
                <div class="logo-container parallax-element">
                    <div class="logo-3d">
                        <img src="images/logo.jpg" alt="">
                    </div>
                    <div class="pulse-ring"></div>
                </div>
                
                <h1 class="hero-title parallax-element">ALMAJYD DISPENSARY</h1>
                <p class="hero-subtitle parallax-element"> Healthcare Management System</p>
                
                <a href="login.php" class="btn-3d parallax-element">
                    <i class="fas fa-sign-in-alt me-2"></i>Enter The System
                </a>
            </div>
        </div>
    </div>
    
    <div class="features-section">
        <div class="container">
            <h2 class="section-title">Our Advanced Features</h2>
            
            <div class="feature-cards">
                <div class="feature-card parallax-element">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3 class="feature-title">Doctor Portal</h3>
                    <p class="feature-description">
                        Advanced patient management with real-time analytics, electronic health records, and AI-powered diagnostics.
                    </p>
                </div>
                
                <div class="feature-card parallax-element">
                    <div class="feature-icon">
                        <i class="fas fa-user-nurse"></i>
                    </div>
                    <h3 class="feature-title">Staff Management</h3>
                    <p class="feature-description">
                        Efficient coordination between reception, laboratory, pharmacy, and administrative teams with role-based access.
                    </p>
                </div>
                
                <div class="feature-card parallax-element">
                    <div class="feature-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <h3 class="feature-title">Pharmacy & Lab</h3>
                    <p class="feature-description">
                        Streamlined medicine inventory management and laboratory test processing with automated reporting.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <div class="container">
            <p class="mb-2">
                <i class="fas fa-hospital me-2"></i>
                Almajyd Hospital Management System v3.0
            </p>
            <small>© 2025 All rights reserved. Revolutionizing healthcare with cutting-edge technology.</small>
        </div>
    </div>

    <script>
        // Mouse move parallax effect
        document.addEventListener('mousemove', (e) => {
            const parallaxElements = document.querySelectorAll('.parallax-element');
            const x = (window.innerWidth - e.pageX * 2) / 100;
            const y = (window.innerHeight - e.pageY * 2) / 100;
            
            parallaxElements.forEach(element => {
                const speed = element.getAttribute('data-speed') || 1;
                element.style.transform = `translateX(${x * speed}px) translateY(${y * speed}px)`;
            });
        });
        
        // Add data-speed attributes for different parallax intensities
        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.parallax-element');
            elements.forEach((element, index) => {
                // Different speeds for different elements
                const speeds = [0.5, 1, 1.5, 2];
                element.setAttribute('data-speed', speeds[index % speeds.length]);
            });
            
            // Add 3D tilt effect to feature cards on mouse move
            const cards = document.querySelectorAll('.feature-card');
            cards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const cardRect = card.getBoundingClientRect();
                    const x = e.clientX - cardRect.left;
                    const y = e.clientY - cardRect.top;
                    
                    const centerX = cardRect.width / 2;
                    const centerY = cardRect.height / 2;
                    
                    const angleY = (x - centerX) / 25;
                    const angleX = (centerY - y) / 25;
                    
                    card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateZ(20px)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
                });
            });
        });
    </script>
</body>
</html>