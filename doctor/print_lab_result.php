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

// Get laboratory test ID from URL
$test_id = $_GET['id'] ?? 0;

if (!$test_id) {
    die('Invalid laboratory test ID');
}

// Get laboratory test details
$stmt = $pdo->prepare("SELECT lt.*, p.full_name as patient_name, p.card_no, p.age, p.gender, p.weight,
                              p.phone, p.address, cf.symptoms, cf.diagnosis, cf.notes, cf.patient_id,
                              u.full_name as doctor_name, lab_user.full_name as lab_technician
                       FROM laboratory_tests lt 
                       JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                       JOIN patients p ON cf.patient_id = p.id 
                       JOIN users u ON cf.doctor_id = u.id 
                       LEFT JOIN users lab_user ON lt.conducted_by = lab_user.id 
                       WHERE lt.id = ?");
$stmt->execute([$test_id]);
$lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lab_test) {
    die('Laboratory test not found');
}

// Get patient's other recent lab tests for reference
$recent_tests_stmt = $pdo->prepare("SELECT lt.* 
                                   FROM laboratory_tests lt 
                                   JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                                   WHERE cf.patient_id = ? 
                                   AND lt.id != ? 
                                   ORDER BY lt.created_at DESC 
                                   LIMIT 3");
$recent_tests_stmt->execute([$lab_test['patient_id'], $test_id]);
$recent_tests = $recent_tests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Laboratory Result - Almajyd Dispensary</title>
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
        .results-container {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #0ea5e9;
            margin-bottom: 20px;
        }
        
        .results-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .test-type {
            font-size: 20px;
            font-weight: bold;
            color: #0ea5e9;
            margin-bottom: 10px;
        }
        
        .test-status {
            display: inline-block;
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .results-content {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .results-text {
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
        }
        
        /* Reference Ranges */
        .reference-ranges {
            background: #fefce8;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #fef08a;
            margin-top: 15px;
        }
        
        .reference-title {
            font-weight: bold;
            color: #854d0e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        
        .signature {
            text-align: center;
        }
        
        .signature-line {
            width: 250px;
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
        
        /* Recent Tests */
        .recent-tests {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }
        
        .test-item {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .test-item:last-child {
            border-bottom: none;
        }
    </style>
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
                <div class="clinic-address">LABORATORY DEPARTMENT</div>
                <div class="document-title">LABORATORY TEST REPORT</div>
            </div>
            
            <div class="header-side">
                <div class="status-badge status-<?php echo $lab_test['status']; ?>">
                    <?php echo strtoupper($lab_test['status']); ?>
                </div>
                <div style="font-size: 10px; margin-top: 5px; color: #666;">
                    ID: LAB-<?php echo str_pad($test_id, 6, '0', STR_PAD_LEFT); ?>
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
                        <span class="info-value"><?php echo htmlspecialchars($lab_test['patient_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Card Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($lab_test['card_no']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo $lab_test['age'] ? $lab_test['age'] . ' years' : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo $lab_test['gender'] ? ucfirst($lab_test['gender']) : 'N/A'; ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-group">
                        <span class="info-label">Weight:</span>
                        <span class="info-value"><?php echo $lab_test['weight'] ? $lab_test['weight'] . ' kg' : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo $lab_test['phone'] ? htmlspecialchars($lab_test['phone']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Requested by:</span>
                        <span class="info-value">Dr. <?php echo htmlspecialchars($lab_test['doctor_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Request Date:</span>
                        <span class="info-value"><?php echo date('F j, Y \a\t h:i A', strtotime($lab_test['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clinical Information -->
        <div class="section">
            <div class="section-title">CLINICAL INFORMATION</div>
            
            <?php if (!empty($lab_test['symptoms'])): ?>
            <div class="medical-content">
                <div class="content-label">Presenting Symptoms:</div>
                <div class="content-text">
                    <?php echo htmlspecialchars($lab_test['symptoms']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($lab_test['diagnosis'])): ?>
            <div class="medical-content">
                <div class="content-label">Provisional Diagnosis:</div>
                <div class="content-text">
                    <?php echo htmlspecialchars($lab_test['diagnosis']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Laboratory Test Results -->
        <div class="section">
            <div class="section-title">LABORATORY FINDINGS</div>
            
            <div class="results-container">
                <div class="results-header">
                    <div class="test-type"><?php echo htmlspecialchars($lab_test['test_type']); ?></div>
                    <div class="test-status"><?php echo strtoupper($lab_test['status']); ?></div>
                </div>
                
                <div class="results-content">
                    <?php if (!empty($lab_test['test_description'])): ?>
                    <div class="content-label">Test Description:</div>
                    <div class="content-text" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($lab_test['test_description']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="content-label">Laboratory Results:</div>
                    <div class="results-text">
                        <?php if (!empty($lab_test['results'])): ?>
                            <?php echo nl2br(htmlspecialchars($lab_test['results'])); ?>
                        <?php else: ?>
                            <div style="text-align: center; color: #666; font-style: italic; padding: 20px;">
                                No results available
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Reference Ranges Section -->
                    <div class="reference-ranges">
                        <div class="reference-title">
                            📋 Reference Information
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            <p><strong>Note:</strong> Results should be interpreted in the context of clinical findings.</p>
                            <p>Abnormal results are highlighted. Please consult with the laboratory for any questions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Details -->
        <div class="section">
            <div class="section-title">TEST DETAILS</div>
            <div class="medical-content">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <div class="info-group">
                            <span class="info-label">Test Type:</span>
                            <span class="info-value"><?php echo htmlspecialchars($lab_test['test_type']); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Test Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $lab_test['status']; ?>">
                                    <?php echo ucfirst($lab_test['status']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Requested Date:</span>
                            <span class="info-value"><?php echo date('M j, Y H:i', strtotime($lab_test['created_at'])); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="info-group">
                            <span class="info-label">Completed Date:</span>
                            <span class="info-value">
                                <?php echo $lab_test['updated_at'] && $lab_test['status'] == 'completed' ? date('M j, Y H:i', strtotime($lab_test['updated_at'])) : 'Not completed'; ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Conducted by:</span>
                            <span class="info-value">
                                <?php echo $lab_test['lab_technician'] ? htmlspecialchars($lab_test['lab_technician']) : 'Not assigned'; ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Report ID:</span>
                            <span class="info-value">LAB-<?php echo str_pad($test_id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Laboratory Tests -->
        <?php if (!empty($recent_tests)): ?>
        <div class="section">
            <div class="section-title">RECENT LABORATORY TESTS</div>
            <div class="recent-tests">
                <?php foreach ($recent_tests as $test): ?>
                    <div class="test-item">
                        <strong><?php echo htmlspecialchars($test['test_type']); ?></strong>
                        <span style="float: right; color: #666; font-size: 12px;">
                            <?php echo date('M j, Y', strtotime($test['created_at'])); ?>
                            <span class="status-badge status-<?php echo $test['status']; ?>" style="margin-left: 10px;">
                                <?php echo ucfirst($test['status']); ?>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer and Signatures -->
        <div class="footer">
            <div class="signatures">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name"><?php echo htmlspecialchars($lab_test['lab_technician'] ?: 'Laboratory Technologist'); ?></div>
                    <div class="signature-title">Laboratory Technologist</div>
                    <div class="signature-title">ALMAJYD DISPENSARY LABORATORY</div>
                </div>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Dr. <?php echo htmlspecialchars($lab_test['doctor_name']); ?></div>
                    <div class="signature-title">Medical Doctor</div>
                    <div class="signature-title">ALMAJYD DISPENSARY</div>
                </div>
            </div>
            
            <div style="margin-top: 30px; font-size: 12px; color: #666;">
                <p><strong>Important Notes:</strong></p>
                <p>• This is an official laboratory report from Almajyd Dispensary</p>
                <p>• Results are valid only for the specimen tested</p>
                <p>• Clinical correlation is recommended for proper interpretation</p>
                <p>• Keep this report for your medical records</p>
                <p>• For inquiries, contact Laboratory: +255 777 123 456</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            // Uncomment the line below to auto-print when page loads
            // window.print();
        };
    </script>
</body>
</html>