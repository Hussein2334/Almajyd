<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pharmacy') {
    http_response_code(403);
    exit('Access denied');
}

if (!isset($_GET['patient_id'])) {
    http_response_code(400);
    exit('Patient ID required');
}

$patient_id = $_GET['patient_id'];
$print_type = $_GET['type'] ?? 'all'; // all, priced, summary

try {
    // Get patient details
    $patient_stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patient_stmt->execute([$patient_id]);
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        http_response_code(404);
        exit('Patient not found');
    }
    
    // Get patient's prescriptions
    $prescriptions_stmt = $pdo->prepare("SELECT pr.*, cf.created_at as visit_date, doc.full_name as doctor_name 
                                       FROM prescriptions pr 
                                       JOIN checking_forms cf ON pr.checking_form_id = cf.id 
                                       JOIN users doc ON cf.doctor_id = doc.id 
                                       WHERE cf.patient_id = ? 
                                       ORDER BY cf.created_at DESC");
    $prescriptions_stmt->execute([$patient_id]);
    $prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get patient's lab tests
    $lab_tests_stmt = $pdo->prepare("SELECT lt.*, cf.created_at as visit_date, doc.full_name as doctor_name 
                                   FROM laboratory_tests lt 
                                   JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                                   JOIN users doc ON cf.doctor_id = doc.id 
                                   WHERE cf.patient_id = ? 
                                   ORDER BY cf.created_at DESC");
    $lab_tests_stmt->execute([$patient_id]);
    $lab_tests = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter items based on print type
    $filtered_prescriptions = [];
    $filtered_lab_tests = [];
    
    if ($print_type === 'priced') {
        foreach ($prescriptions as $prescription) {
            if ($prescription['medicine_price'] > 0) {
                $filtered_prescriptions[] = $prescription;
            }
        }
        foreach ($lab_tests as $test) {
            if ($test['lab_price'] > 0) {
                $filtered_lab_tests[] = $test;
            }
        }
    } else {
        $filtered_prescriptions = $prescriptions;
        $filtered_lab_tests = $lab_tests;
    }
    
    // Calculate totals - INCLUDING CONSULTATION FEE
    $consultation_fee = $patient['consultation_fee'] ?? 0;
    $medicines_total = 0;
    $lab_tests_total = 0;
    
    foreach ($filtered_prescriptions as $prescription) {
        $medicines_total += $prescription['medicine_price'] ?? 0;
    }
    
    foreach ($filtered_lab_tests as $test) {
        $lab_tests_total += $test['lab_price'] ?? 0;
    }
    
    $grand_total = $consultation_fee + $medicines_total + $lab_tests_total;
    $total_items = count($filtered_prescriptions) + count($filtered_lab_tests);
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ripoti ya Matibabu - Almajyd Dispensary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 10px;
            font-size: 12px;
            line-height: 1.2;
            color: #000;
            background: white;
        }
        
        .container {
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15px;
            border: 1px solid #ddd;
            background: white;
            position: relative;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 10px;
        }
        
        .clinic-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 3px;
            color: #2E8B57;
        }
        
        .clinic-address {
            font-size: 11px;
            margin-bottom: 3px;
        }
        
        .report-title {
            font-size: 16px;
            font-weight: bold;
            margin: 8px 0;
            color: #2E8B57;
        }
        
        .payment-info {
            background: #f0fff0;
            padding: 8px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #32CD32;
            font-size: 11px;
            text-align: center;
            font-weight: bold;
        }
        
        .patient-info {
            margin: 12px 0;
            font-size: 11px;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
            border-left: 3px solid #FFD700;
        }
        
        .patient-details {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .items-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 15px 0;
            font-size: 10px;
        }
        
        .summary-box {
            text-align: center;
            padding: 8px 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .summary-box:nth-child(1) {
            border-top: 3px solid #6c757d;
        }
        
        .summary-box:nth-child(2) {
            border-top: 3px solid #28a745;
        }
        
        .summary-box:nth-child(3) {
            border-top: 3px solid #17a2b8;
        }
        
        .summary-box:nth-child(4) {
            border-top: 3px solid #ffc107;
        }
        
        .amount {
            font-weight: bold;
            font-size: 12px;
            margin-top: 3px;
            color: #2E8B57;
        }
        
        .consultation-amount {
            font-weight: bold;
            font-size: 12px;
            margin-top: 3px;
            color: #d97706;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 10px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        
        th {
            background: #2E8B57;
            color: white;
            font-weight: bold;
            font-size: 10px;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .totals {
            margin: 15px 0;
            font-size: 11px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .consultation-row {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            padding-left: 8px;
            font-weight: bold;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 13px;
            color: #2E8B57;
            border-bottom: none;
            background-color: #e8f5e8;
            border-left: 3px solid #28a745;
            padding-left: 8px;
        }
        
        .note {
            font-style: italic;
            margin: 12px 0;
            font-size: 10px;
            background-color: #fff3cd;
            padding: 8px;
            border-radius: 3px;
            border-left: 3px solid #ffc107;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
        }
        
        .department {
            margin: 15px 0;
            font-weight: bold;
            background: #e9ecef;
            padding: 8px;
            border-radius: 3px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin: 25px 0 15px 0;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 25px;
            padding-top: 3px;
            font-weight: bold;
            font-size: 10px;
        }
        
        .report-footer {
            margin-top: 15px;
            font-size: 9px;
            text-align: center;
            color: #666;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        
        .logo {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 50px;
            height: 50px;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .header-content {
            margin-left: 60px;
        }
        
        .print-info {
            background: #fff3cd;
            padding: 6px;
            border-radius: 3px;
            margin: 8px 0;
            border-left: 3px solid #ffc107;
            font-size: 9px;
        }
        
        .print-actions {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 3px;
            text-decoration: none;
            display: inline-block;
            font-size: 11px;
        }
        
        .btn:hover {
            background: #218838;
        }
        
        .btn-print {
            background: #28a745;
        }
        
        .btn-back {
            background: #17a2b8;
        }
        
        .compact-text {
            font-size: 10px;
        }
        
        .compact-section {
            margin-bottom: 8px;
        }
        
        /* Media query for printing */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .container {
                border: none;
                box-shadow: none;
                padding: 10mm;
                width: 210mm;
                min-height: 297mm;
                max-height: 297mm;
                page-break-inside: avoid;
            }
            
            .print-info, .print-actions {
                display: none;
            }
            
            @page {
                margin: 0;
                size: A4;
            }
        }

        /* Ensure everything fits in one page */
        .page-break {
            page-break-inside: avoid;
        }
        
        .no-break {
            page-break-inside: avoid;
        }
    </style>
    <link rel="icon" href="../images/logo.jpg">
</head>
<body onload="window.print()">
    <div class="container no-break">
        <!-- Logo -->
        <div class="logo">
            <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo" onerror="this.style.display='none'">
        </div>
        
        <div class="header">
            <div class="header-content">
                <div class="clinic-name">ALMAJYD DISPENSARY</div>
                <div class="clinic-address">TOMONDO - ZANZIBAR | Tel: +255 777 587 478</div>
                <div class="report-title">MEDICAL TREATMENT REPORT</div>
            </div>
        </div>
        
        <div class="payment-info">
            COMBINED PAYMENT (MEDICINE & LABORATORY) - PAID
        </div>
        
        <div class="print-info">
            <strong>Printed for:</strong> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?> | 
            <strong>Type:</strong> <?php echo strtoupper($print_type); ?> | 
            <strong>Items:</strong> <?php echo $total_items; ?>
        </div>
        
        <div class="patient-info">
            <div class="patient-details">
                <span><strong>Patient:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></span>
                <span><strong>Card No:</strong> <?php echo htmlspecialchars($patient['card_no']); ?></span>
                <span><strong>Age/Gender:</strong> <?php echo htmlspecialchars($patient['age'] ?? 'N/A'); ?>y / <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></span>
            </div>
        </div>
        
        <div class="items-summary">
            <div class="summary-box">
                <div style="font-weight: bold;"><?php echo $total_items; ?> Items</div>
                <div>Total Items</div>
            </div>
            <div class="summary-box">
                <div style="font-weight: bold;"><?php echo count($filtered_prescriptions); ?> Meds</div>
                <div class="amount">TSh <?php echo number_format($medicines_total, 2); ?></div>
                <div>Medicines</div>
            </div>
            <div class="summary-box">
                <div style="font-weight: bold;"><?php echo count($filtered_lab_tests); ?> Tests</div>
                <div class="amount">TSh <?php echo number_format($lab_tests_total, 2); ?></div>
                <div>Lab Tests</div>
            </div>
            <div class="summary-box">
                <div style="font-weight: bold;">Consultation</div>
                <div class="consultation-amount">TSh <?php echo number_format($consultation_fee, 2); ?></div>
                <div>Doctor Fee</div>
            </div>
        </div>
        
        <table class="no-break">
            <thead>
                <tr>
                    <th style="width: 8%">Type</th>
                    <th style="width: 22%">Item Name</th>
                    <th style="width: 20%">Specifications</th>
                    <th style="width: 25%">Details/Results</th>
                    <th style="width: 10%">Status</th>
                    <th style="width: 15%">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_prescriptions as $prescription): ?>
                <tr>
                    <td>MED</td>
                    <td><?php echo htmlspecialchars($prescription['medicine_name'] ?? 'Medicine'); ?></td>
                    <td class="compact-text">
                        Dose: <?php echo htmlspecialchars($prescription['dosage'] ?? 'N/A'); ?><br>
                        Freq: <?php echo htmlspecialchars($prescription['frequency'] ?? 'N/A'); ?><br>
                        Dur: <?php echo htmlspecialchars($prescription['duration'] ?? 'N/A'); ?>
                    </td>
                    <td class="compact-text"><?php echo htmlspecialchars($prescription['instructions'] ?? 'N/A'); ?></td>
                    <td>DISPENSED</td>
                    <td>TSh <?php echo number_format($prescription['medicine_price'] ?? 0, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php foreach ($filtered_lab_tests as $test): ?>
                <tr>
                    <td>LAB</td>
                    <td><?php echo htmlspecialchars($test['test_name'] ?? $test['test_type'] ?? 'Lab Test'); ?></td>
                    <td class="compact-text">Laboratory Test</td>
                    <td class="compact-text"><?php echo htmlspecialchars($test['results'] ?? $test['description'] ?? 'anayo ya kawaida tu'); ?></td>
                    <td>COMPLETED</td>
                    <td>TSh <?php echo number_format($test['lab_price'] ?? 0, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($total_items === 0): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No items found for this print type</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="totals no-break">
            <div class="total-row">
                <span>Medicines Total (<?php echo count($filtered_prescriptions); ?> items):</span>
                <span>TSh <?php echo number_format($medicines_total, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Laboratory Tests Total (<?php echo count($filtered_lab_tests); ?> tests):</span>
                <span>TSh <?php echo number_format($lab_tests_total, 2); ?></span>
            </div>
            <div class="total-row consultation-row">
                <span>Consultation Fee:</span>
                <span>TSh <?php echo number_format($consultation_fee, 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>GRAND TOTAL:</span>
                <span>TSh <?php echo number_format($grand_total, 2); ?></span>
            </div>
        </div>
        
        <div class="note">
            Note: Combined payment includes consultation fee, medicines and laboratory tests
        </div>
        
        <div class="print-actions no-print">
            <button class="btn btn-print" onclick="window.print()">
                🖨️ Print Report
            </button >
            <button class="btn btn-back" onclick="window.history.back()">
                ↩️ Back
            </button>
        </div>
        
        <div class="footer no-break">
            <div class="department">Pharmacy Department - ALMAJYD DISPENSARY</div>
            
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    Authorized Signature
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    Verified By
                </div>
            </div>
            
            <div class="report-footer">
                Report generated on: <?php echo date('d/m/Y H:i'); ?> | Printed by: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'System'); ?>
            </div>
        </div>
    </div>

    <script>
        // Auto print on load
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Redirect after printing
        window.onafterprint = function() {
            setTimeout(function() {
                window.history.back();
            }, 1000);
        };
    </script>
</body>
</html>