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

// Get patient ID from URL
$patient_id = $_GET['patient_id'] ?? 0;

if (!$patient_id) {
    die('Invalid patient ID');
}

// Get patient details
$patient_stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$patient_stmt->execute([$patient_id]);
$patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient not found');
}

// Get checking_form_id for the patient
$checking_stmt = $pdo->prepare("SELECT id FROM checking_forms WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
$checking_stmt->execute([$patient_id]);
$checking_form = $checking_stmt->fetch(PDO::FETCH_ASSOC);
$checking_form_id = $checking_form['id'] ?? 0;

// Get all payments for this patient
$payments_stmt = $pdo->prepare("
    SELECT amount, payment_type, created_at 
    FROM payments 
    WHERE patient_id = ? AND checking_form_id = ? AND status = 'pending'
    ORDER BY created_at DESC
");
$payments_stmt->execute([$patient_id, $checking_form_id]);
$all_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescriptions data
$prescriptions_stmt = $pdo->prepare("
    SELECT 
        pr.id,
        pr.medicine_name as name,
        pr.dosage,
        pr.frequency,
        pr.duration,
        pr.instructions,
        pr.status,
        pr.created_at
    FROM prescriptions pr 
    JOIN checking_forms cf ON pr.checking_form_id = cf.id 
    WHERE cf.patient_id = ?
    ORDER BY pr.created_at DESC
");
$prescriptions_stmt->execute([$patient_id]);
$prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lab tests data
$lab_tests_stmt = $pdo->prepare("
    SELECT 
        lt.id,
        lt.test_type as name,
        lt.results as instructions,
        'completed' as status,
        lt.created_at
    FROM laboratory_tests lt 
    JOIN checking_forms cf ON lt.checking_form_id = cf.id 
    WHERE cf.patient_id = ? AND lt.status = 'completed'
    ORDER BY lt.created_at DESC
");
$lab_tests_stmt->execute([$patient_id]);
$lab_tests = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals based on payment types
$medicine_total = 0;
$lab_total = 0;
$combined_total = 0;

// Check for different payment types
foreach ($all_payments as $payment) {
    switch ($payment['payment_type']) {
        case 'medicine':
            $medicine_total += $payment['amount'];
            break;
        case 'lab_test':
            $lab_total += $payment['amount'];
            break;
        case 'medicine_and_lab':
            $combined_total += $payment['amount'];
            break;
    }
}

// If we have combined payment, use that as the main total
if ($combined_total > 0) {
    $grand_total = $combined_total;
    // Distribute combined amount between medicine and lab for display
    $total_items = count($prescriptions) + count($lab_tests);
    if ($total_items > 0) {
        $medicine_total = ($combined_total * count($prescriptions)) / $total_items;
        $lab_total = ($combined_total * count($lab_tests)) / $total_items;
    }
} else {
    $grand_total = $medicine_total + $lab_total;
}

// Combine all data into items array with proper pricing
$items = [];

// Process prescriptions with prices
foreach ($prescriptions as $prescription) {
    $price = 0;
    
    if ($combined_total > 0) {
        // Distribute combined payment equally among prescriptions
        $price = count($prescriptions) > 0 ? $medicine_total / count($prescriptions) : 0;
    } else {
        // Use individual medicine payments
        $price = count($prescriptions) > 0 ? $medicine_total / count($prescriptions) : 0;
    }
    
    $items[] = [
        'type' => 'medicine',
        'id' => $prescription['id'],
        'name' => $prescription['name'],
        'dosage' => $prescription['dosage'],
        'frequency' => $prescription['frequency'],
        'duration' => $prescription['duration'],
        'instructions' => $prescription['instructions'],
        'status' => $prescription['status'],
        'price' => $price,
        'created_at' => $prescription['created_at']
    ];
}

// Process lab tests with prices
foreach ($lab_tests as $lab_test) {
    $price = 0;
    
    if ($combined_total > 0) {
        // Distribute combined payment equally among lab tests
        $price = count($lab_tests) > 0 ? $lab_total / count($lab_tests) : 0;
    } else {
        // Use individual lab payments
        $price = count($lab_tests) > 0 ? $lab_total / count($lab_tests) : 0;
    }
    
    $items[] = [
        'type' => 'lab_test',
        'id' => $lab_test['id'],
        'name' => $lab_test['name'],
        'dosage' => '',
        'frequency' => '',
        'duration' => '',
        'instructions' => $lab_test['instructions'],
        'status' => $lab_test['status'],
        'price' => $price,
        'created_at' => $lab_test['created_at']
    ];
}

// Sort items by creation date
usort($items, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Count items by type
$medicine_count = count($prescriptions);
$lab_count = count($lab_tests);
$total_items = $medicine_count + $lab_count;

// Debug information (remove in production)
$debug_info = [
    'patient_id' => $patient_id,
    'checking_form_id' => $checking_form_id,
    'payments_found' => count($all_payments),
    'medicine_payments' => array_filter($all_payments, fn($p) => $p['payment_type'] === 'medicine'),
    'lab_payments' => array_filter($all_payments, fn($p) => $p['payment_type'] === 'lab_test'),
    'combined_payments' => array_filter($all_payments, fn($p) => $p['payment_type'] === 'medicine_and_lab'),
    'prescriptions_count' => $medicine_count,
    'lab_tests_count' => $lab_count
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Report - Almajyd Dispensary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: white;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
        }
        
        .print-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 15px;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        
        .clinic-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 3px;
        }
        
        .clinic-address {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }
        
        .document-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            color: #333;
        }
        
        /* Payment Info */
        .payment-info {
            background: #f0f9ff;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #3b82f6;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .payment-type {
            font-weight: bold;
            color: #1e40af;
            font-size: 11px;
        }
        
        .payment-details {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Patient Info */
        .patient-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .info-group {
            margin-bottom: 3px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 10px;
        }
        
        .info-value {
            color: #333;
            font-size: 11px;
        }
        
        /* Summary */
        .summary {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .summary-item {
            padding: 6px;
            background: #e8f5e8;
            border-radius: 4px;
            border: 1px solid #10b981;
        }
        
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 2px;
        }
        
        .summary-label {
            font-size: 9px;
            color: #666;
            font-weight: 600;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th {
            background: #2c5aa0;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #1e4080;
        }
        
        .items-table td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            font-size: 10px;
            vertical-align: top;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .medicine-row {
            background: #f0fdf4 !important;
        }
        
        .lab-row {
            background: #f0f9ff !important;
        }
        
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-medicine {
            background: #d1fae5;
            color: #065f46;
        }
        
        .type-lab {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        
        .status-dispensed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .price {
            font-weight: bold;
            color: #10b981;
            text-align: right;
        }
        
        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #333;
        }
        
        .total-section {
            margin-bottom: 15px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        
        .total-label {
            font-weight: bold;
            color: #555;
            font-size: 11px;
        }
        
        .total-amount {
            font-weight: bold;
            color: #10b981;
            font-size: 11px;
        }
        
        .grand-total {
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 12px;
        }
        
        .grand-total .total-label {
            color: #2c5aa0;
            font-size: 12px;
        }
        
        .grand-total .total-amount {
            color: #2c5aa0;
            font-size: 14px;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .signature {
            text-align: center;
        }
        
        .signature-line {
            width: 150px;
            border-bottom: 1px solid #333;
            margin: 0 auto 5px auto;
            padding-top: 20px;
        }
        
        .signature-name {
            font-weight: bold;
            color: #333;
            font-size: 11px;
        }
        
        .signature-title {
            font-size: 9px;
            color: #666;
        }
        
        .print-info {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #eee;
        }
        
        /* Action Buttons */
        .action-buttons {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            margin: 0 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print {
            background: #10b981;
            color: white;
        }
        
        .btn-back {
            background: #3b82f6;
            color: white;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                font-size: 10pt;
            }
            
            .print-container {
                max-width: 100%;
                padding: 10px;
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 20px 10px;
            color: #666;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        /* Compact layout for instructions */
        .compact-instructions {
            font-size: 9px;
            color: #666;
            line-height: 1.3;
        }
        
        /* Debug info (remove in production) */
        .debug-info {
            background: #fff3cd;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ffc107;
            margin-bottom: 15px;
            font-size: 10px;
            display: none; /* Set to block to see debug info */
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Report
        </button>
        <a href="dashboard.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="print-container">
        <!-- Debug Information (Remove in production) -->
        <div class="debug-info" style="display: none;">
            <strong>Debug Information:</strong><br>
            <pre><?php echo json_encode($debug_info, JSON_PRETTY_PRINT); ?></pre>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="clinic-name">ALMAJYD DISPENSARY</div>
            <div class="clinic-address">TOMONDO - ZANZIBAR | Tel: +255 777 567 478</div>
            <div class="document-title">MEDICAL TREATMENT REPORT</div>
        </div>

        <!-- Payment Information -->
        <?php if ($combined_total > 0): ?>
        <div class="payment-info">
            <div class="payment-type">COMBINED PAYMENT (MEDICINE & LABORATORY)</div>
            <div class="payment-details">
                Total Combined Amount: TSh <?php echo number_format($combined_total, 2); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Patient Information -->
        <div class="patient-info">
            <div class="info-group">
                <span class="info-label">Patient Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
            </div>
            <div class="info-group">
                <span class="info-label">Card No:</span>
                <span class="info-value"><?php echo htmlspecialchars($patient['card_no']); ?></span>
            </div>
            <div class="info-group">
                <span class="info-label">Age/Gender:</span>
                <span class="info-value"><?php echo $patient['age'] ? $patient['age'] . ' yrs / ' . ucfirst($patient['gender']) : 'N/A'; ?></span>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-item">
                <div class="summary-value"><?php echo $total_items; ?></div>
                <div class="summary-label">Total Items</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $medicine_count; ?></div>
                <div class="summary-label">Medicines</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $lab_count; ?></div>
                <div class="summary-label">Lab Tests</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">TSh <?php echo number_format($medicine_total, 2); ?></div>
                <div class="summary-label">Medicines Total</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">TSh <?php echo number_format($lab_total, 2); ?></div>
                <div class="summary-label">Lab Tests Total</div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">Type</th>
                    <th width="25%">Item Name</th>
                    <th width="15%">Specifications</th>
                    <th width="30%">Details/Results</th>
                    <th width="10%">Status</th>
                    <th width="15%">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-file-medical-alt"></i><br>
                            No medical items found for this patient
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr class="<?php echo $item['type'] == 'medicine' ? 'medicine-row' : 'lab-row'; ?>">
                            <!-- Type -->
                            <td>
                                <span class="type-badge type-<?php echo $item['type']; ?>">
                                    <?php echo $item['type'] == 'medicine' ? 'MED' : 'LAB'; ?>
                                </span>
                            </td>
                            
                            <!-- Item Name -->
                            <td>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            </td>
                            
                            <!-- Specifications -->
                            <td>
                                <?php if ($item['type'] == 'medicine'): ?>
                                    <?php if (!empty($item['dosage'])): ?>
                                        <strong>Dose:</strong> <?php echo htmlspecialchars($item['dosage']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($item['frequency'])): ?>
                                        <strong>Freq:</strong> <?php echo htmlspecialchars($item['frequency']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($item['duration'])): ?>
                                        <strong>Dur:</strong> <?php echo htmlspecialchars($item['duration']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em>Laboratory Test</em>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Details/Results -->
                            <td class="compact-instructions">
                                <?php if (!empty($item['instructions'])): ?>
                                    <?php 
                                    $instructions = $item['instructions'];
                                    if (strlen($instructions) > 80) {
                                        $instructions = substr($instructions, 0, 80) . '...';
                                    }
                                    echo htmlspecialchars($instructions); 
                                    ?>
                                <?php else: ?>
                                    <em>No additional details</em>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Status -->
                            <td>
                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                    <?php echo strtoupper($item['status']); ?>
                                </span>
                            </td>
                            
                            <!-- Price -->
                            <td class="price">
                                <?php if ($item['price'] > 0): ?>
                                    TSh <?php echo number_format($item['price'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <!-- Total Amounts Breakdown -->
            <div class="total-section">
                <?php if ($combined_total > 0): ?>
                    <div class="total-row">
                        <span class="total-label">Combined Payment (Medicine & Lab):</span>
                        <span class="total-amount">TSh <?php echo number_format($combined_total, 2); ?></span>
                    </div>
                    <div class="total-row" style="font-size: 9px; color: #666; font-style: italic;">
                        <span>Note: Combined amount distributed between <?php echo $medicine_count; ?> medicines and <?php echo $lab_count; ?> lab tests</span>
                    </div>
                <?php else: ?>
                    <div class="total-row">
                        <span class="total-label">Medicines Total:</span>
                        <span class="total-amount">TSh <?php echo number_format($medicine_total, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Laboratory Tests Total:</span>
                        <span class="total-amount">TSh <?php echo number_format($lab_total, 2); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="total-row grand-total">
                    <span class="total-label">GRAND TOTAL:</span>
                    <span class="total-amount">TSh <?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>

            <!-- Signatures -->
            <div class="signatures">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Pharmacy Department</div>
                    <div class="signature-title">ALMAJYD DISPENSARY</div>
                </div>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Authorized Signature</div>
                    <div class="signature-title">Verified By</div>
                </div>
            </div>
            
            <!-- Print Info -->
            <div class="print-info">
                Report generated on: <?php echo date('F j, Y \a\t h:i A'); ?> | 
                This is an official medical report from Almajyd Dispensary
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            // Auto-print when page loads (optional)
            // setTimeout(() => { window.print(); }, 500);
        };
    </script>
</body>
</html>