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
    
    // Get patient's lab tests - FIXED: using correct column names
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
    
    // Calculate totals
    $medicines_total = 0;
    $lab_tests_total = 0;
    
    foreach ($filtered_prescriptions as $prescription) {
        $medicines_total += $prescription['medicine_price'] ?? 0;
    }
    
    foreach ($filtered_lab_tests as $test) {
        $lab_tests_total += $test['lab_price'] ?? 0;
    }
    
    $grand_total = $medicines_total + $lab_tests_total;
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
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.4;
            color: #000;
            background-color: #f8f8f8;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            position: relative;
            background: linear-gradient(135deg, #f0fff0 0%, #fffacd 50%, #e6f7e6 100%);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 10px;
            background: linear-gradient(90deg, #32CD32, #FFD700, #228B22);
            padding: 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 5px 5px 0 0;
        }
        
        .clinic-info {
            margin-bottom: 5px;
        }
        
        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .clinic-address {
            font-size: 12px;
            margin-bottom: 5px;
            color: #fff;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .payment-info {
            background: linear-gradient(90deg, #90EE90, #FFFACD, #98FB98);
            padding: 10px;
            margin: 15px 0;
            border-radius: 8px;
            font-size: 12px;
            border: 1px solid #32CD32;
            font-weight: bold;
        }
        
        .patient-info {
            margin: 15px 0;
            font-size: 12px;
            background-color: #f0fff0;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #FFD700;
        }
        
        .patient-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .items-summary {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            font-size: 12px;
            gap: 10px;
        }
        
        .summary-box {
            text-align: center;
            padding: 15px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            flex: 1;
            background: linear-gradient(135deg, #f0fff0, #fffacd);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-box:nth-child(1) {
            background: linear-gradient(135deg, #e6f7e6, #f0fff0);
            border-top: 3px solid #228B22;
        }
        
        .summary-box:nth-child(2) {
            background: linear-gradient(135deg, #fffacd, #fff8dc);
            border-top: 3px solid #FFD700;
        }
        
        .summary-box:nth-child(3) {
            background: linear-gradient(135deg, #f0fff0, #e6f7e6);
            border-top: 3px solid #32CD32;
        }
        
        .amount {
            font-weight: bold;
            font-size: 16px;
            margin-top: 5px;
            color: #2E8B57;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        th {
            background: linear-gradient(135deg, #32CD32, #228B22);
            color: white;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f0fff0;
        }
        
        tr:nth-child(odd) {
            background-color: #fffacd;
        }
        
        .totals {
            margin: 25px 0;
            font-size: 12px;
            background: linear-gradient(135deg, #f0fff0, #fffacd);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #32CD32;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 16px;
            color: #228B22;
            border-bottom: none;
        }
        
        .note {
            font-style: italic;
            margin: 15px 0;
            font-size: 11px;
            background-color: #fffacd;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #FFD700;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
        }
        
        .department {
            margin: 20px 0;
            font-weight: bold;
            background: linear-gradient(90deg, #90EE90, #FFFACD);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #32CD32;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin: 40px 0 20px 0;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-top: 2px solid #228B22;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
        }
        
        .report-footer {
            margin-top: 20px;
            font-size: 11px;
            text-align: center;
            color: #666;
            background-color: #f0fff0;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .logo {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 10px;
            padding: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            border: 2px solid #32CD32;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 5px;
        }
        
        .header-content {
            margin-left: 100px;
        }
        
        .color-strip {
            height: 5px;
            background: linear-gradient(90deg, #32CD32 0%, #FFD700 50%, #228B22 100%);
            margin: 10px 0;
            border-radius: 2px;
        }
        
        .print-info {
            background: #fffbeb;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #f59e0b;
            font-size: 11px;
        }
        
        .print-actions {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, #fffacd, #f0fff0);
            border-radius: 8px;
            border: 1px solid #FFD700;
        }
        
        .btn {
            background: #32CD32;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #228B22;
        }
        
        .btn-print {
            background: #059669;
        }
        
        .btn-back {
            background: #3b82f6;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border: none;
                padding: 10px;
            }
            .print-info, .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
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
        
        <div class="color-strip"></div>
        
        <div class="payment-info">
            COMBINED PAYMENT (MEDICINE & LABORATORY)<br>
            Payment Status: PAID | Date: <?php echo date('M d, Y'); ?>
        </div>
        
        <div class="print-info">
            <strong>Printed for:</strong> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?> | 
            <strong>Type:</strong> <?php echo strtoupper($print_type); ?> | 
            <strong>Items:</strong> <?php echo $total_items; ?>
        </div>
        
        <div class="patient-info">
            <div class="patient-details">
                <span><strong>Patient Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></span>
                <span><strong>Card No:</strong> <?php echo htmlspecialchars($patient['card_no']); ?></span>
                <span><strong>Age/Gender:</strong> <?php echo htmlspecialchars($patient['age'] ?? 'N/A'); ?> yrs / <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></span>
            </div>
        </div>
        
        <div class="items-summary">
            <div class="summary-box">
                <div style="font-weight: bold; font-size: 14px;"><?php echo $total_items; ?> Total Items</div>
            </div>
            <div class="summary-box">
                <div style="font-weight: bold;"><?php echo count($filtered_prescriptions); ?> Medicines</div>
                <div class="amount">TSh <?php echo number_format($medicines_total, 2); ?></div>
                <div>Medicines Total</div>
            </div>
            <div class="summary-box">
                <div style="font-weight: bold;"><?php echo count($filtered_lab_tests); ?> Lab Tests</div>
                <div class="amount">TSh <?php echo number_format($lab_tests_total, 2); ?></div>
                <div>Lab Tests Total</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item Name</th>
                    <th>Specifications</th>
                    <th>Details/Results</th>
                    <th>Status</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_prescriptions as $prescription): ?>
                <tr>
                    <td>MED</td>
                    <td><?php echo htmlspecialchars($prescription['medicine_name'] ?? 'Medicine'); ?></td>
                    <td>
                        Dose: <?php echo htmlspecialchars($prescription['dosage'] ?? 'N/A'); ?><br>
                        Freq: <?php echo htmlspecialchars($prescription['frequency'] ?? 'N/A'); ?><br>
                        Dur: <?php echo htmlspecialchars($prescription['duration'] ?? 'N/A'); ?>
                    </td>
                    <td><?php echo htmlspecialchars($prescription['instructions'] ?? 'N/A'); ?></td>
                    <td>DISPENSED</td>
                    <td>TSh <?php echo number_format($prescription['medicine_price'] ?? 0, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php foreach ($filtered_lab_tests as $test): ?>
                <tr>
                    <td>LAB</td>
                    <td><?php echo htmlspecialchars($test['test_name'] ?? $test['test_type'] ?? 'Laboratory Test'); ?></td>
                    <td>Laboratory Test</td>
                    <td><?php echo htmlspecialchars($test['results'] ?? $test['description'] ?? 'anayo ya kawaida tu'); ?></td>
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
        
        <div class="totals">
            <div class="total-row">
                <span>Medicines Total (<?php echo count($filtered_prescriptions); ?> items):</span>
                <span>TSh <?php echo number_format($medicines_total, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Laboratory Tests Total (<?php echo count($filtered_lab_tests); ?> tests):</span>
                <span>TSh <?php echo number_format($lab_tests_total, 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>GRAND TOTAL:</span>
                <span>TSh <?php echo number_format($grand_total, 2); ?></span>
            </div>
        </div>
        
        <div class="note">
            Note: Combined payment distributed equally among items
        </div>
        
        <div class="print-actions">
            <button class="btn btn-print" onclick="window.print()">
                🖨️ Print Report
            </button>
            <button class="btn btn-back" onclick="window.history.back()">
                ↩️ Back
            </button>
            <button class="btn" onclick="window.close()">
                ❌ Close
            </button>
        </div>
        
        <div class="footer">
            <div class="department">Pharmacy Department<br>ALMAJYD DISPENSARY</div>
            
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
                Report generated on: <?php echo date('F d, Y \a\t h:i A'); ?> | This is an official medical report from Almajyd Dispensary<br>
                Printed by: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'System'); ?> | 
                https://almajyd-dispensary.com/pharmacy/print_patient_prescriptions.php?patient_id=<?php echo $patient_id; ?>
            </div>
        </div>
    </div>

    <script>
        // Display column names for debugging (remove in production)
        console.log('Lab Tests Data:', <?php echo json_encode($lab_tests); ?>);
    </script>
</body>
</html>