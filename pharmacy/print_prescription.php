<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// Get prescription ID from URL
$prescription_id = $_GET['id'] ?? 0;

if (!$prescription_id) {
    die('Invalid prescription ID');
}

// Get prescription details with patient and doctor information
$stmt = $pdo->prepare("SELECT pr.*, p.full_name as patient_name, p.card_no, p.age, p.gender, 
                              p.weight, p.phone, p.address, cf.symptoms, cf.diagnosis, 
                              cf.notes, cf.patient_id, u.full_name as doctor_name,
                              pr.created_at as prescribed_date
                       FROM prescriptions pr 
                       JOIN checking_forms cf ON pr.checking_form_id = cf.id 
                       JOIN patients p ON cf.patient_id = p.id 
                       JOIN users u ON cf.doctor_id = u.id 
                       WHERE pr.id = ?");
$stmt->execute([$prescription_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    die('Prescription not found');
}

// Get patient's recent prescriptions for reference
$recent_prescriptions_stmt = $pdo->prepare("SELECT pr.* 
                                           FROM prescriptions pr 
                                           JOIN checking_forms cf ON pr.checking_form_id = cf.id 
                                           WHERE cf.patient_id = ? 
                                           AND pr.id != ? 
                                           ORDER BY pr.created_at DESC 
                                           LIMIT 3");
$recent_prescriptions_stmt->execute([$prescription['patient_id'], $prescription_id]);
$recent_prescriptions = $recent_prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pharmacy user details
$pharmacist_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$pharmacist_stmt->execute([$_SESSION['user_id']]);
$pharmacist = $pharmacist_stmt->fetch(PDO::FETCH_ASSOC);

// Safely handle updated_at field
$dispensed_date = 'Not dispensed';
if ($prescription['status'] == 'dispensed') {
    // If there's no updated_at column, use created_at or current time
    $dispensed_date = isset($prescription['updated_at']) && !empty($prescription['updated_at']) 
        ? date('M j, Y H:i', strtotime($prescription['updated_at']))
        : date('M j, Y H:i', strtotime($prescription['created_at']));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Prescription - Almajyd Dispensary</title>
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
            border: 2px solid #10b981;
            border-radius: 10px;
            padding: 5px;
            background: white;
            object-fit: cover;
        }
        
        .logo-placeholder {
            width: 100px;
            height: 100px;
            border: 2px solid #10b981;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            font-size: 12px;
            color: #10b981;
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
            color: #10b981;
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
            color: #10b981;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #10b981;
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
        
        /* Prescription Details */
        .prescription-container {
            background: #f0fdf4;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #10b981;
            margin-bottom: 20px;
        }
        
        .prescription-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .medicine-name {
            font-size: 20px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 10px;
        }
        
        .prescription-status {
            display: inline-block;
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .prescription-content {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #dcfce7;
        }
        
        .prescription-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-group {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .detail-value {
            color: #333;
            font-size: 15px;
            font-weight: 600;
        }
        
        /* Instructions */
        .instructions-box {
            background: #fefce8;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #fef08a;
            margin-top: 15px;
        }
        
        .instructions-title {
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
            background: #10b981;
            color: white;
        }
        
        .btn-print:hover {
            background: #059669;
        }
        
        .btn-back {
            background: #3b82f6;
            color: white;
        }
        
        .btn-back:hover {
            background: #2563eb;
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
        
        .status-dispensed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Recent Prescriptions */
        .recent-prescriptions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }
        
        .prescription-item {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .prescription-item:last-child {
            border-bottom: none;
        }
        
        /* Warning Box */
        .warning-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .warning-title {
            color: #dc2626;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Medicine Details Grid */
        .medicine-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .medicine-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .medicine-property {
            margin-bottom: 5px;
        }
        
        .property-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
        }
        
        .property-value {
            color: #1f2937;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
 <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">
            🖨️ Print Prescription
        </button>
        <a href="dashboard.php" class="btn btn-back">
            ← Back to Pharmacy Dashboard
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
                <div class="clinic-address">Tel: +255 777 567 478</div>
                <div class="clinic-address">PHARMACY DEPARTMENT</div>
                <div class="document-title">MEDICAL PRESCRIPTION</div>
            </div>
            
            <div class="header-side">
                <div class="status-badge status-<?php echo $prescription['status']; ?>">
                    <?php echo strtoupper($prescription['status']); ?>
                </div>
                <div style="font-size: 10px; margin-top: 5px; color: #666;">
                    ID: RX-<?php echo str_pad($prescription_id, 6, '0', STR_PAD_LEFT); ?>
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
                        <span class="info-value"><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Card Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($prescription['card_no']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo $prescription['age'] ? $prescription['age'] . ' years' : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo $prescription['gender'] ? ucfirst($prescription['gender']) : 'N/A'; ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-group">
                        <span class="info-label">Weight:</span>
                        <span class="info-value"><?php echo $prescription['weight'] ? $prescription['weight'] . ' kg' : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo $prescription['phone'] ? htmlspecialchars($prescription['phone']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Prescribed by:</span>
                        <span class="info-value">Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Prescribed Date:</span>
                        <span class="info-value"><?php echo date('F j, Y \a\t h:i A', strtotime($prescription['prescribed_date'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clinical Information -->
        <div class="section">
            <div class="section-title">CLINICAL INFORMATION</div>
            
            <?php if (!empty($prescription['symptoms'])): ?>
            <div class="medical-content">
                <div class="content-label">Presenting Symptoms:</div>
                <div class="content-text">
                    <?php echo htmlspecialchars($prescription['symptoms']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($prescription['diagnosis'])): ?>
            <div class="medical-content">
                <div class="content-label">Medical Diagnosis:</div>
                <div class="content-text">
                    <?php echo htmlspecialchars($prescription['diagnosis']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Prescription Details -->
        <div class="section">
            <div class="section-title">PRESCRIPTION DETAILS</div>
            
            <div class="prescription-container">
                <div class="prescription-header">
                    <div class="medicine-name"><?php echo htmlspecialchars($prescription['medicine_name']); ?></div>
                    <div class="prescription-status"><?php echo strtoupper($prescription['status']); ?></div>
                </div>
                
                <div class="prescription-content">
                    <!-- Medicine Details Grid -->
                    <div class="medicine-grid">
                        <div class="medicine-card">
                            <div class="medicine-property">
                                <div class="property-label">Dosage</div>
                                <div class="property-value">
                                    <?php echo !empty($prescription['dosage']) ? htmlspecialchars($prescription['dosage']) : 'Not specified'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="medicine-card">
                            <div class="medicine-property">
                                <div class="property-label">Frequency</div>
                                <div class="property-value">
                                    <?php echo !empty($prescription['frequency']) ? htmlspecialchars($prescription['frequency']) : 'Not specified'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="medicine-card">
                            <div class="medicine-property">
                                <div class="property-label">Duration</div>
                                <div class="property-value">
                                    <?php echo !empty($prescription['duration']) ? htmlspecialchars($prescription['duration']) : 'Not specified'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="medicine-card">
                            <div class="medicine-property">
                                <div class="property-label">Quantity</div>
                                <div class="property-value">
                                    <?php echo !empty($prescription['quantity']) ? htmlspecialchars($prescription['quantity']) : 'Not specified'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Special Instructions -->
                    <?php if (!empty($prescription['instructions'])): ?>
                    <div class="instructions-box">
                        <div class="instructions-title">
                            💊 Special Instructions
                        </div>
                        <div class="content-text">
                            <?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Warning Box -->
                    <div class="warning-box">
                        <div class="warning-title">
                            ⚠️ Important Information
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            <p><strong>Note:</strong> This prescription should be taken exactly as directed by the physician.</p>
                            <p>• Do not share medication with others</p>
                            <p>• Complete the full course of treatment</p>
                            <p>• Report any adverse reactions immediately</p>
                            <p>• Store medications properly as instructed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prescription Information -->
        <div class="section">
            <div class="section-title">PRESCRIPTION INFORMATION</div>
            <div class="medical-content">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <div class="info-group">
                            <span class="info-label">Medicine Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($prescription['medicine_name']); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Prescription Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                    <?php echo ucfirst($prescription['status']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Prescribed Date:</span>
                            <span class="info-value"><?php echo date('M j, Y H:i', strtotime($prescription['prescribed_date'])); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="info-group">
                            <span class="info-label">Dispensed Date:</span>
                            <span class="info-value">
                                <?php echo $dispensed_date; ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Dispensed by:</span>
                            <span class="info-value">
                                <?php echo $prescription['status'] == 'dispensed' ? ($pharmacist['full_name'] ?? 'Pharmacy Staff') : 'Pending'; ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Prescription ID:</span>
                            <span class="info-value">RX-<?php echo str_pad($prescription_id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Prescriptions -->
        <?php if (!empty($recent_prescriptions)): ?>
        <div class="section">
            <div class="section-title">RECENT PRESCRIPTIONS</div>
            <div class="recent-prescriptions">
                <?php foreach ($recent_prescriptions as $rx): ?>
                    <div class="prescription-item">
                        <strong><?php echo htmlspecialchars($rx['medicine_name']); ?></strong>
                        <span style="float: right; color: #666; font-size: 12px;">
                            <?php echo date('M j, Y', strtotime($rx['created_at'])); ?>
                            <span class="status-badge status-<?php echo $rx['status']; ?>" style="margin-left: 10px;">
                                <?php echo ucfirst($rx['status']); ?>
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
                    <div class="signature-name">Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></div>
                    <div class="signature-title">Medical Doctor</div>
                    <div class="signature-title">ALMAJYD DISPENSARY</div>
                </div>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name"><?php echo htmlspecialchars($pharmacist['full_name'] ?? 'Pharmacy Staff'); ?></div>
                    <div class="signature-title">Pharmacist</div>
                    <div class="signature-title">ALMAJYD DISPENSARY PHARMACY</div>
                </div>
            </div>
            
            <div style="margin-top: 30px; font-size: 12px; color: #666;">
                <p><strong>Pharmacy Notes:</strong></p>
                <p>• This prescription has been verified and dispensed by Almajyd Dispensary Pharmacy</p>
                <p>• Medication should be stored according to manufacturer's instructions</p>
                <p>• Follow-up appointment recommended after completing medication</p>
                <p>• Keep this prescription for your medical records</p>
                <p>• For medication inquiries, contact Pharmacy: +255 777 567 478</p>
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