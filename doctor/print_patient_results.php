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

// Get patient name and card number from URL
$patient_name = $_GET['patient'] ?? '';
$card_no = $_GET['card'] ?? '';

if (!$patient_name || !$card_no) {
    die('Invalid patient information');
}

// Get all laboratory tests for this patient
$stmt = $pdo->prepare("SELECT lt.*, p.full_name as patient_name, p.card_no, p.age, p.gender,
                              u.full_name as doctor_name, lab_user.full_name as lab_technician,
                              cf.symptoms, cf.diagnosis, cf.created_at as checkup_date
                       FROM laboratory_tests lt 
                       JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                       JOIN patients p ON cf.patient_id = p.id 
                       LEFT JOIN users u ON cf.doctor_id = u.id 
                       LEFT JOIN users lab_user ON lt.conducted_by = lab_user.id 
                       WHERE p.full_name = ? AND p.card_no = ? 
                       AND lt.status = 'completed'
                       ORDER BY lt.updated_at DESC");
$stmt->execute([$patient_name, $card_no]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$lab_tests) {
    die('No laboratory results found for this patient');
}

// Get patient basic info from first record
$patient_info = $lab_tests[0];

// Group tests by checkup date
$grouped_tests = [];
foreach ($lab_tests as $test) {
    $checkup_date = date('Y-m-d', strtotime($test['checkup_date']));
    if (!isset($grouped_tests[$checkup_date])) {
        $grouped_tests[$checkup_date] = [
            'checkup_date' => $test['checkup_date'],
            'doctor_name' => $test['doctor_name'],
            'symptoms' => $test['symptoms'],
            'diagnosis' => $test['diagnosis'],
            'tests' => []
        ];
    }
    $grouped_tests[$checkup_date]['tests'][] = $test;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Patient Results - Almajyd Dispensary</title>
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
        
        /* Test Results */
        .test-results {
            margin-bottom: 20px;
        }
        
        .checkup-date {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
            color: #2c5aa0;
            border-left: 4px solid #2c5aa0;
        }
        
        .test-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
        }
        
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .test-type {
            font-weight: bold;
            color: #333;
            font-size: 15px;
        }
        
        .test-date {
            color: #666;
            font-size: 13px;
        }
        
        .test-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            font-style: italic;
        }
        
        .test-findings {
            background: #d4edda;
            padding: 12px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
            margin-top: 10px;
        }
        
        .findings-label {
            font-weight: bold;
            color: #155724;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .findings-content {
            color: #155724;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .lab-technician {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-top: 8px;
            font-style: italic;
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
            
            .test-results {
                page-break-inside: avoid;
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
        
        .lab-signature {
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
        
        /* Summary Section */
        .summary-section {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #b3d7ff;
            margin-bottom: 25px;
        }
        
        .summary-title {
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
    <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">
            🖨️ Print Laboratory Results
        </button>
        <a href="../doctor/dashboard.php" class="btn btn-back">
            ← Back to Dashboard
        </a>
    </div>

    <div class="print-container">
        <!-- Header with Logo -->
        <div class="header">
            <div class="logo-section">
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
                <div class="document-title">LABORATORY TEST RESULTS REPORT</div>
            </div>
            
            <div class="header-side">
                <div class="status-badge status-completed">
                    COMPLETED
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
                        <span class="info-value"><?php echo htmlspecialchars($patient_info['patient_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Card Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient_info['card_no']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo $patient_info['age'] ? $patient_info['age'] . ' years' : 'N/A'; ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-group">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo $patient_info['gender'] ? ucfirst($patient_info['gender']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Total Tests:</span>
                        <span class="info-value"><?php echo count($lab_tests); ?> completed tests</span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Report Period:</span>
                        <span class="info-value">
                            <?php 
                            $dates = array_keys($grouped_tests);
                            if (count($dates) > 1) {
                                echo date('M j, Y', strtotime(end($dates))) . ' - ' . date('M j, Y', strtotime($dates[0]));
                            } else {
                                echo date('M j, Y', strtotime($dates[0]));
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="summary-section">
            <div class="summary-title">LABORATORY RESULTS SUMMARY</div>
            <div class="summary-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($lab_tests); ?></div>
                    <div class="stat-label">Total Tests</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($grouped_tests); ?></div>
                    <div class="stat-label">Checkup Visits</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php
                        $test_types = array_unique(array_column($lab_tests, 'test_type'));
                        echo count($test_types);
                        ?>
                    </div>
                    <div class="stat-label">Test Types</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php
                        $latest_test = $lab_tests[0];
                        echo date('M j, Y', strtotime($latest_test['updated_at']));
                        ?>
                    </div>
                    <div class="stat-label">Latest Results</div>
                </div>
            </div>
        </div>

        <!-- Laboratory Test Results -->
        <div class="section">
            <div class="section-title">LABORATORY TEST RESULTS</div>
            
            <?php foreach ($grouped_tests as $checkup_date => $checkup_group): ?>
            <div class="test-results">
                <div class="checkup-date">
                    Checkup Date: <?php echo date('F j, Y \a\t h:i A', strtotime($checkup_group['checkup_date'])); ?>
                    <?php if (!empty($checkup_group['doctor_name'])): ?>
                        - Doctor: Dr. <?php echo htmlspecialchars($checkup_group['doctor_name']); ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($checkup_group['symptoms'])): ?>
                <div class="medical-content">
                    <div class="content-label">Patient Symptoms:</div>
                    <div class="content-text"><?php echo htmlspecialchars($checkup_group['symptoms']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($checkup_group['diagnosis'])): ?>
                <div class="medical-content">
                    <div class="content-label">Doctor Diagnosis:</div>
                    <div class="content-text"><?php echo htmlspecialchars($checkup_group['diagnosis']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php foreach ($checkup_group['tests'] as $test): ?>
                <div class="test-item">
                    <div class="test-header">
                        <div class="test-type">
                            <i class="fas fa-microscope"></i> <?php echo htmlspecialchars($test['test_type']); ?>
                        </div>
                        <div class="test-date">
                            Completed: <?php echo date('M j, Y H:i', strtotime($test['updated_at'])); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($test['test_description'])): ?>
                    <div class="test-description">
                        <?php echo htmlspecialchars($test['test_description']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($test['results'])): ?>
                    <div class="test-findings">
                        <div class="findings-label">
                            <i class="fas fa-file-medical"></i> Laboratory Findings:
                        </div>
                        <div class="findings-content">
                            <?php echo htmlspecialchars($test['results']); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="color: #666; font-style: italic; padding: 10px; background: #fff3cd; border-radius: 4px;">
                        No results recorded for this test.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($test['lab_technician'])): ?>
                    <div class="lab-technician">
                        Conducted by: <?php echo htmlspecialchars($test['lab_technician']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer and Signature -->
        <div class="footer">
            <div class="lab-signature">
                <div class="signature-line"></div>
                <div class="signature-name">Laboratory Department</div>
                <div class="signature-title">Certified Laboratory Technologist</div>
                <div class="signature-title">ALMAJYD DISPENSARY - TOMONDO</div>
            </div>
            
            <div style="margin-top: 30px; font-size: 12px; color: #666;">
                <p><strong>Important Laboratory Notes:</strong></p>
                <p>• This is an official laboratory report from Almajyd Dispensary</p>
                <p>• Results are based on samples collected and analyzed in our laboratory</p>
                <p>• Reference ranges may vary based on patient age, gender, and other factors</p>
                <p>• Consult your physician for interpretation of these results</p>
                <p>• Keep this report for your medical records</p>
                <p>• For inquiries, contact Laboratory: +255 777 567 478</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.onload = function() {
            // Uncomment the line below to auto-print when page loads
            // window.print();
        };

        // Add page breaks for better printing
        document.addEventListener('DOMContentLoaded', function() {
            const testResults = document.querySelectorAll('.test-results');
            testResults.forEach((result, index) => {
                if (index > 0 && index % 2 === 0) {
                    result.classList.add('page-break');
                }
            });
        });
    </script>
</body>
</html>