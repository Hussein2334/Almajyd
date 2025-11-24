<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get checkup ID from URL
$checkup_id = $_GET['id'] ?? 0;

if (!$checkup_id) {
    die('Invalid checkup ID');
}

// Get checkup details - updated query based on your table structure
$stmt = $pdo->prepare("SELECT cf.*, p.full_name as patient_name, p.card_no, p.age, p.gender, p.weight, 
                              u.full_name as doctor_name
                       FROM checking_forms cf 
                       JOIN patients p ON cf.patient_id = p.id 
                       LEFT JOIN users u ON cf.doctor_id = u.id 
                       WHERE cf.id = ?");
$stmt->execute([$checkup_id]);
$checkup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$checkup) {
    die('Checkup not found');
}

// Get prescriptions for this checkup
$prescriptions_stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE checking_form_id = ?");
$prescriptions_stmt->execute([$checkup_id]);
$prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lab tests for this checkup
$lab_tests_stmt = $pdo->prepare("SELECT lt.*, u.full_name as conducted_by_name 
                                FROM laboratory_tests lt 
                                LEFT JOIN users u ON lt.conducted_by = u.id 
                                WHERE lt.checking_form_id = ?");
$lab_tests_stmt->execute([$checkup_id]);
$lab_tests = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Checkup - Almajyd Dispensary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
            color: #333;
            line-height: 1.6;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header with Logo */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px double #333;
        }
        
        .logo-section {
            flex: 0 0 120px;
            text-align: center;
        }
        
        .clinic-logo {
            width: 100px;
            height: 100px;
            border: 2px solid #2c5aa0;
            border-radius: 10px;
            padding: 5px;
            background: white;
            object-fit: cover;
        }
        
        .logo-placeholder {
            width: 100px;
            height: 100px;
            border: 2px solid #2c5aa0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-size: 12px;
            color: #2c5aa0;
            text-align: center;
            padding: 5px;
            font-weight: bold;
        }
        
        .clinic-info {
            flex: 1;
            text-align: center;
            padding: 0 20px;
        }
        
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .clinic-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .header-side {
            flex: 0 0 120px;
            text-align: center;
        }
        
        /* Patient Info */
        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .info-group {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-size: 14px;
        }
        
        /* Sections */
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #2c5aa0;
        }
        
        /* Medical Content */
        .medical-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }
        
        .content-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .content-text {
            color: #333;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-line;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                font-size: 12pt;
            }
            
            .print-container {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        /* Action Buttons */
        .action-buttons {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-print {
            background: #2c5aa0;
            color: white;
        }
        
        .btn-print:hover {
            background: #1e3d6d;
        }
        
        .btn-back {
            background: #28a745;
            color: white;
        }
        
        .btn-back:hover {
            background: #1e7e34;
        }
        
        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 3px double #333;
            text-align: center;
        }
        
        .doctor-signature {
            margin-top: 40px;
            text-align: center;
        }
        
        .signature-line {
            width: 300px;
            border-bottom: 1px solid #333;
            margin: 0 auto 10px auto;
            padding-top: 40px;
        }
        
        .signature-name {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .signature-title {
            font-size: 12px;
            color: #666;
        }
        
        .print-date {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
    </style>
 <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">
            🖨️ Print Document
        </button>
        <a href="../doctor/dashboard.php" class="btn btn-back">
            ← Back to Dashboard
        </a>
    </div>

    <div class="print-container">
        <!-- Header with Logo -->
        <div class="header">
            <div class="logo-section">
                <!-- Logo path based on your structure -->
                <?php if (file_exists('../images/logo.jpg')): ?>
                    <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo" class="clinic-logo">
                <?php else: ?>
                    <div class="logo-placeholder">
                        ALMAJYD<br>DISPENSARY
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="clinic-info">
                <div class="clinic-name">ALMAJYD DISPENSARY</div>
                <div class="clinic-address">TOMONDO - ZANZIBAR</div>
                <div class="clinic-address">Tel: +255 777 123 456</div>
                <div class="clinic-address">Email: amrykassim@gmail.com</div>
                <div class="document-title">MEDICAL EXAMINATION REPORT</div>
            </div>
            
            <div class="header-side">
                <div class="status-badge status-<?php echo $checkup['status']; ?>">
                    <?php echo strtoupper($checkup['status']); ?>
                </div>
            </div>
        </div>

        <!-- Print Date -->
        <div class="print-date">
            Printed on: <?php echo date('F j, Y \a\t h:i A'); ?>
        </div>

        <!-- Patient Information -->
        <div class="section">
            <div class="section-title">PATIENT INFORMATION</div>
            <div class="patient-info">
                <div>
                    <div class="info-group">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($checkup['patient_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Card Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($checkup['card_no']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo $checkup['age'] ? $checkup['age'] . ' years' : 'N/A'; ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-group">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo $checkup['gender'] ? ucfirst($checkup['gender']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Weight:</span>
                        <span class="info-value"><?php echo $checkup['weight'] ? $checkup['weight'] . ' kg' : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Checkup Date:</span>
                        <span class="info-value"><?php echo date('F j, Y \a\t h:i A', strtotime($checkup['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Examination -->
        <div class="section">
            <div class="section-title">MEDICAL EXAMINATION</div>

            <!-- Symptoms -->
            <div class="medical-content">
                <div class="content-label">Chief Complaints / Symptoms:</div>
                <div class="content-text">
                    <?php echo !empty($checkup['symptoms']) ? htmlspecialchars($checkup['symptoms']) : 'No symptoms recorded'; ?>
                </div>
            </div>

            <!-- Diagnosis -->
            <div class="medical-content">
                <div class="content-label">Diagnosis:</div>
                <div class="content-text">
                    <?php echo !empty($checkup['diagnosis']) ? htmlspecialchars($checkup['diagnosis']) : 'No diagnosis recorded'; ?>
                </div>
            </div>

            <!-- Clinical Notes -->
            <?php if (!empty($checkup['notes'])): ?>
            <div class="medical-content">
                <div class="content-label">Clinical Notes:</div>
                <div class="content-text">
                    <?php echo htmlspecialchars($checkup['notes']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Prescriptions -->
        <div class="section">
            <div class="section-title">PRESCRIPTIONS</div>
            <?php if (!empty($prescriptions)): ?>
                <div class="medical-content">
                    <div class="content-text">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                                <strong><?php echo htmlspecialchars($prescription['medicine_name']); ?></strong><br>
                                <span style="color: #666;">
                                    Dosage: <?php echo htmlspecialchars($prescription['dosage'] ?: 'As prescribed'); ?> | 
                                    Frequency: <?php echo htmlspecialchars($prescription['frequency'] ?: 'As directed'); ?> | 
                                    Duration: <?php echo htmlspecialchars($prescription['duration'] ?: 'Until finished'); ?>
                                </span>
                                <?php if (!empty($prescription['instructions'])): ?>
                                    <br><em>Instructions: <?php echo htmlspecialchars($prescription['instructions']); ?></em>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="medical-content">
                    <div class="content-text" style="text-align: center; color: #666; font-style: italic;">
                        No prescriptions prescribed for this checkup
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Laboratory Tests -->
        <?php if (!empty($lab_tests)): ?>
        <div class="section">
            <div class="section-title">LABORATORY TESTS</div>
            <div class="medical-content">
                <div class="content-text">
                    <?php foreach ($lab_tests as $test): ?>
                        <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                            <strong><?php echo htmlspecialchars($test['test_type']); ?></strong><br>
                            <span style="color: #666;">
                                Description: <?php echo htmlspecialchars($test['test_description'] ?: 'N/A'); ?> |
                                Results: <?php echo htmlspecialchars($test['results'] ?: 'Pending'); ?> |
                                Status: <span class="status-badge status-<?php echo $test['status']; ?>">
                                    <?php echo ucfirst($test['status']); ?>
                                </span>
                            </span>
                            <?php if (!empty($test['conducted_by_name'])): ?>
                                <br><em>Conducted by: <?php echo htmlspecialchars($test['conducted_by_name']); ?></em>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer and Signature -->
        <div class="footer">
            <div class="doctor-signature">
                <div class="signature-line"></div>
                <div class="signature-name"><?php echo htmlspecialchars($checkup['doctor_name'] ?: 'Medical Doctor'); ?></div>
                <div class="signature-title">Medical Doctor</div>
                <div class="signature-title">ALMAJYD DISPENSARY - TOMONDO</div>
            </div>
            
            <div style="margin-top: 30px; font-size: 12px; color: #666;">
                <p><strong>Important Notes:</strong></p>
                <p>• This is an official medical document from Almajyd Dispensary</p>
                <p>• Keep this report for your medical records</p>
                <p>• Follow up as recommended by your doctor</p>
                <p>• In case of emergency, visit the nearest healthcare facility</p>
                <p>• For inquiries, contact: +255 777 567 478</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.onload = function() {
            // Uncomment the line below to auto-print when page loads
            // window.print();
        };
    </script>
</body>
</html>