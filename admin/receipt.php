<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get payment ID from URL
$payment_id = $_GET['payment_id'] ?? 0;

if (!$payment_id) {
    die("Payment ID is required!");
}

// Fetch payment and receipt details with ALL items
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        pt.full_name as patient_name, 
        pt.card_no,
        pt.age,
        pt.gender,
        pt.phone,
        pt.consultation_fee,
        r.receipt_number, 
        r.amount_paid, 
        r.change_amount, 
        r.issued_at,
        u.full_name as cashier_name,
        doc.full_name as doctor_name,
        cf.diagnosis,
        cf.symptoms,
        cf.created_at as visit_date,
        -- Get all medications with details
        (SELECT GROUP_CONCAT(CONCAT(pr.medicine_name, ' (', pr.dosage, ') - TZS ', FORMAT(pr.medicine_price, 2)) SEPARATOR '; ') 
         FROM prescriptions pr 
         WHERE pr.checking_form_id = p.checking_form_id) as medications,
        -- Get all lab tests with details
        (SELECT GROUP_CONCAT(CONCAT(lt.test_type, ' - TZS ', FORMAT(lt.lab_price, 2)) SEPARATOR '; ') 
         FROM laboratory_tests lt 
         WHERE lt.checking_form_id = p.checking_form_id) as lab_tests,
        -- Calculate totals
        (SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = p.checking_form_id) as total_medicine_cost,
        (SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = p.checking_form_id) as total_lab_cost
    FROM payments p
    JOIN patients pt ON p.patient_id = pt.id
    JOIN checking_forms cf ON p.checking_form_id = cf.id
    JOIN users doc ON cf.doctor_id = doc.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    LEFT JOIN users u ON r.issued_by = u.id
    WHERE p.id = ?
");

$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Payment not found!");
}

// Calculate totals
$consultation_fee = $payment['consultation_fee'] ?? 0;
$medicines_total = $payment['total_medicine_cost'] ?? 0;
$lab_tests_total = $payment['total_lab_cost'] ?? 0;
$total_amount = $consultation_fee + $medicines_total + $lab_tests_total;

// GENERATE RECEIPT NUMBER IF NOT EXISTS
if (empty($payment['receipt_number'])) {
    $receipt_number = 'RCP' . date('Ymd') . str_pad($payment_id, 4, '0', STR_PAD_LEFT);
    
    $amount_paid = $payment['amount_paid'] ?? $total_amount;
    $change_amount = $amount_paid - $total_amount;
    
    if ($amount_paid === null || $amount_paid < $total_amount) {
        $amount_paid = $total_amount;
        $change_amount = 0;
    }
    
    $check_receipt = $pdo->prepare("SELECT * FROM receipts WHERE payment_id = ?");
    $check_receipt->execute([$payment_id]);
    
    if (!$check_receipt->fetch()) {
        try {
            $insert_receipt = $pdo->prepare("
                INSERT INTO receipts (payment_id, receipt_number, total_amount, amount_paid, change_amount, issued_by, issued_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insert_receipt->execute([
                $payment_id,
                $receipt_number,
                $total_amount,
                $amount_paid,
                $change_amount,
                $_SESSION['user_id']
            ]);
            
            $payment['receipt_number'] = $receipt_number;
            $payment['amount_paid'] = $amount_paid;
            $payment['change_amount'] = $change_amount;
            $payment['issued_at'] = date('Y-m-d H:i:s');
            $payment['cashier_name'] = $_SESSION['full_name'];
            
        } catch (PDOException $e) {
            $receipt_number = 'TEMP' . date('YmdHis') . $payment_id;
            $payment['receipt_number'] = $receipt_number;
            $payment['amount_paid'] = $amount_paid;
            $payment['change_amount'] = $change_amount;
        }
    }
} else {
    $receipt_number = $payment['receipt_number'];
}

// Use current date/time for receipt
$current_date = date('d/m/Y H:i:s');
$issued_date = !empty($payment['issued_at']) ? date('d/m/Y H:i', strtotime($payment['issued_at'])) : $current_date;
$cashier_name = !empty($payment['cashier_name']) ? $payment['cashier_name'] : $_SESSION['full_name'];

// Ensure all required values are set
$amount_paid = $payment['amount_paid'] ?? $total_amount;
$change_amount = $payment['change_amount'] ?? 0;
$payment_method = $payment['payment_method'] ?? 'cash';

// Parse medications and lab tests for display
$medications_list = [];
if (!empty($payment['medications'])) {
    $meds = explode('; ', $payment['medications']);
    foreach ($meds as $med) {
        if (!empty(trim($med))) {
            $medications_list[] = $med;
        }
    }
}

$lab_tests_list = [];
if (!empty($payment['lab_tests'])) {
    $tests = explode('; ', $payment['lab_tests']);
    foreach ($tests as $test) {
        if (!empty(trim($test))) {
            $lab_tests_list[] = $test;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Almajyd Dispensary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: white;
            color: black;
            padding: 10px;
            font-size: 12px;
            line-height: 1.2;
        }

        /* Print-specific styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
                background: white !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .receipt-container {
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 8px !important;
                box-shadow: none !important;
                border: none !important;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }

        .receipt-container {
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            background: white;
            padding: 10px;
            border: 1px solid #000;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .receipt-header h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .receipt-header p {
            font-size: 9px;
            margin: 1px 0;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            padding-bottom: 3px;
            border-bottom: 1px dashed #ccc;
        }

        .receipt-section {
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid #000;
        }

        .receipt-section-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 4px;
            background: #f0f0f0;
            padding: 2px;
            font-size: 11px;
        }

        .receipt-total {
            border-top: 2px solid #000;
            padding-top: 6px;
            margin-top: 6px;
            font-weight: bold;
            font-size: 12px;
        }

        .barcode {
            text-align: center;
            margin: 8px 0;
            padding: 4px;
        }

        .thank-you {
            text-align: center;
            font-style: italic;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #000;
            font-size: 10px;
        }

        .footer {
            text-align: center;
            font-size: 8px;
            margin-top: 6px;
            color: #666;
        }

        .actions {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .btn {
            padding: 8px 15px;
            margin: 3px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-print {
            background: #007bff;
            color: white;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-dashboard {
            background: #28a745;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }
        
        .receipt-number {
            font-size: 12px;
            font-weight: bold;
            color: #000;
            background: #f8f9fa;
            padding: 4px;
            border: 1px solid #000;
            text-align: center;
            margin-bottom: 8px;
        }
        
        .items-list {
            margin: 5px 0;
        }
        
        .item-row {
            margin-bottom: 2px;
            padding: 1px 0;
            font-size: 9px;
        }
        
        .compact-text {
            font-size: 9px;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 6px;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            margin-bottom: 8px;
            font-size: 9px;
        }
        
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 6px;
            border: 1px solid #a7f3d0;
            border-radius: 3px;
            margin-bottom: 8px;
            font-size: 9px;
        }
        
        .cost-breakdown {
            margin: 6px 0;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 10px;
        }
    </style>
    <link rel="icon" href="../images/logo.jpg">
</head>
<body onload="window.print()">
    <!-- Action buttons - hidden when printing -->
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">
            🖨️ Print
        </button>
        <a href="dashboard.php" class="btn btn-dashboard">
            📊 Dashboard
        </a>
    </div>

    <!-- Receipt Content -->
    <div class="receipt-container">
        <!-- Status Messages -->
        <?php if (strpos($receipt_number, 'TEMP') === 0): ?>
        <div class="warning">
            ⚠️ TEMPORARY RECEIPT
        </div>
        <?php else: ?>
        <div class="success">
            ✅ OFFICIAL RECEIPT
        </div>
        <?php endif; ?>

        <!-- Clinic Header -->
        <div class="receipt-header">
            <h1>ALMAJYD DISPENSARY</h1>
            <p>Quality Healthcare Services</p>
            <p>Tel: +255 777 567 478</p>
            <p>OFFICIAL RECEIPT</p>
        </div>

        <!-- Receipt Number -->
        <div class="receipt-number">
            RECEIPT: <?php echo $receipt_number; ?>
        </div>

        <!-- Receipt Details -->
        <div class="receipt-item">
            <span>Date & Time:</span>
            <span><?php echo $current_date; ?></span>
        </div>
        <div class="receipt-item">
            <span>Issued By:</span>
            <span><?php echo htmlspecialchars($cashier_name); ?></span>
        </div>

        <!-- Patient Information -->
        <div class="receipt-section">
            <div class="receipt-section-title">PATIENT INFORMATION</div>
            <div class="receipt-item">
                <span>Name:</span>
                <span><?php echo htmlspecialchars($payment['patient_name']); ?></span>
            </div>
            <div class="receipt-item">
                <span>Card No:</span>
                <span><?php echo htmlspecialchars($payment['card_no']); ?></span>
            </div>
            <div class="receipt-item">
                <span>Age/Gender:</span>
                <span><?php echo $payment['age'] . 'y / ' . ucfirst($payment['gender']); ?></span>
            </div>
            <div class="receipt-item">
                <span>Doctor:</span>
                <span>Dr. <?php echo htmlspecialchars($payment['doctor_name']); ?></span>
            </div>
        </div>

        <!-- Medical Information -->
        <?php if (!empty($payment['diagnosis'])): ?>
        <div class="receipt-section">
            <div class="receipt-section-title">MEDICAL INFORMATION</div>
            <div class="receipt-item" style="flex-direction: column; align-items: flex-start;">
                <div><strong>Diagnosis:</strong></div>
                <div style="margin-top: 2px;" class="compact-text"><?php echo htmlspecialchars($payment['diagnosis']); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cost Breakdown -->
        <div class="receipt-section">
            <div class="receipt-section-title">COST BREAKDOWN</div>
            
            <div class="cost-breakdown">
                <!-- Consultation Fee -->
                <?php if ($consultation_fee > 0): ?>
                <div class="cost-item">
                    <span>Consultation Fee:</span>
                    <span>TZS <?php echo number_format($consultation_fee, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Medications -->
                <?php if (!empty($medications_list)): ?>
                <div style="margin: 4px 0;">
                    <div style="font-weight: bold; margin-bottom: 2px;">Medications:</div>
                    <?php foreach ($medications_list as $med): ?>
                    <div class="item-row">• <?php echo htmlspecialchars($med); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Laboratory Tests -->
                <?php if (!empty($lab_tests_list)): ?>
                <div style="margin: 4px 0;">
                    <div style="font-weight: bold; margin-bottom: 2px;">Lab Tests:</div>
                    <?php foreach ($lab_tests_list as $test): ?>
                    <div class="item-row">• <?php echo htmlspecialchars($test); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="receipt-section">
            <div class="receipt-section-title">PAYMENT SUMMARY</div>
            
            <div class="cost-breakdown">
                <?php if ($consultation_fee > 0): ?>
                <div class="cost-item">
                    <span>Consultation:</span>
                    <span>TZS <?php echo number_format($consultation_fee, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($medicines_total > 0): ?>
                <div class="cost-item">
                    <span>Medicines:</span>
                    <span>TZS <?php echo number_format($medicines_total, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($lab_tests_total > 0): ?>
                <div class="cost-item">
                    <span>Laboratory:</span>
                    <span>TZS <?php echo number_format($lab_tests_total, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="receipt-total">
                    <div class="receipt-item">
                        <span>TOTAL AMOUNT:</span>
                        <span>TZS <?php echo number_format($total_amount, 2); ?></span>
                    </div>
                </div>
                
                <div class="receipt-item">
                    <span>Amount Paid:</span>
                    <span>TZS <?php echo number_format($amount_paid, 2); ?></span>
                </div>
                
                <?php if ($change_amount > 0): ?>
                <div class="receipt-item">
                    <span>Change Amount:</span>
                    <span>TZS <?php echo number_format($change_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="receipt-item">
                    <span>Payment Method:</span>
                    <span style="text-transform: uppercase; font-weight: bold;"><?php echo $payment_method; ?></span>
                </div>
            </div>
        </div>

        <!-- Barcode Area -->
        <div class="barcode">
            <div style="border: 1px solid #000; padding: 4px; display: inline-block; font-family: monospace;">
                <?php echo str_repeat('| ', 15); ?>
            </div>
            <div style="font-size: 8px; margin-top: 2px;">
                <?php echo $receipt_number; ?>
            </div>
        </div>

        <!-- Thank You Message -->
        <div class="thank-you">
            Thank you for choosing Almajyd Dispensary!<br>
            <strong>Get Well Soon!</strong>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Issued by: <?php echo htmlspecialchars($cashier_name); ?></div>
            <div>Date: <?php echo $current_date; ?></div>
            <div style="margin-top: 4px;">
                *** Computer generated receipt ***
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Redirect after printing
        window.onafterprint = function() {
            setTimeout(function() {
                window.location.href = 'dashboard.php';
            }, 1000);
        };

        // Manual print function
        function printReceipt() {
            window.print();
        }
    </script>
</body>
</html>