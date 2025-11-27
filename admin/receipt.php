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

// Fetch payment and receipt details
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
        (SELECT GROUP_CONCAT(CONCAT(pr.medicine_name, ' (', pr.dosage, ')') SEPARATOR ', ') 
         FROM prescriptions pr 
         WHERE pr.checking_form_id = p.checking_form_id AND pr.status = 'dispensed') as medications,
        (SELECT GROUP_CONCAT(CONCAT(lt.test_type, ': ', lt.results) SEPARATOR ' | ') 
         FROM laboratory_tests lt 
         WHERE lt.checking_form_id = p.checking_form_id AND lt.status = 'completed') as lab_tests
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

$total_amount = $payment['amount'] + ($payment['consultation_fee'] ?? 0);

// GENERATE RECEIPT NUMBER IF NOT EXISTS - FIXED WITH PROPER AMOUNT_PAID
if (empty($payment['receipt_number'])) {
    // Generate new receipt number
    $receipt_number = 'RCP' . date('Ymd') . str_pad($payment_id, 4, '0', STR_PAD_LEFT);
    
    // Get amount_paid from payments table to avoid null values
    $amount_paid = $payment['amount_paid'] ?? $total_amount;
    $change_amount = $amount_paid - $total_amount;
    
    // Ensure amount_paid is not null and is sufficient
    if ($amount_paid === null || $amount_paid < $total_amount) {
        $amount_paid = $total_amount;
        $change_amount = 0;
    }
    
    // Insert into receipts table if not exists
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
            
            // Update payment with receipt number
            $payment['receipt_number'] = $receipt_number;
            $payment['amount_paid'] = $amount_paid;
            $payment['change_amount'] = $change_amount;
            $payment['issued_at'] = date('Y-m-d H:i:s');
            $payment['cashier_name'] = $_SESSION['full_name'];
            
        } catch (PDOException $e) {
            // If insert fails, use temporary receipt number without saving to database
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
            padding: 20px;
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
                padding: 10px !important;
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
            padding: 15px;
            border: 1px solid #000;
            font-size: 12px;
            line-height: 1.3;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .receipt-header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .receipt-header p {
            font-size: 10px;
            margin: 2px 0;
        }

        .receipt-details {
            margin-bottom: 8px;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #ccc;
        }

        .receipt-section {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #000;
        }

        .receipt-section-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            background: #f0f0f0;
            padding: 3px;
        }

        .receipt-total {
            border-top: 2px solid #000;
            padding-top: 8px;
            margin-top: 8px;
            font-weight: bold;
            font-size: 13px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .barcode {
            text-align: center;
            margin: 10px 0;
            padding: 5px;
        }

        .thank-you {
            text-align: center;
            font-style: italic;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #000;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 8px;
            color: #666;
        }

        .actions {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
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
            font-size: 14px;
            font-weight: bold;
            color: #000;
            background: #f8f9fa;
            padding: 5px;
            border: 1px solid #000;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 11px;
        }
        
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 10px;
            border: 1px solid #a7f3d0;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 11px;
        }
    </style>
    <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <!-- Action buttons - hidden when printing -->
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">
            🖨️ Print Receipt
        </button>
        <a href="print_receipts.php" class="btn btn-back">
            📋 Back to Receipts
        </a>
        <a href="dashboard.php" class="btn btn-dashboard">
            🏠 Dashboard
        </a>
        <button class="btn btn-print" onclick="autoPrint()">
            🚀 Auto Print
        </button>
    </div>

    <!-- Receipt Content -->
    <div class="receipt-container" id="receiptContent">
        <!-- Status Messages -->
        <?php if (strpos($receipt_number, 'TEMP') === 0): ?>
        <div class="warning">
            ⚠️ TEMPORARY RECEIPT - Data not saved to database
        </div>
        <?php else: ?>
        <div class="success">
            ✅ OFFICIAL RECEIPT - Database Verified
        </div>
        <?php endif; ?>

        <!-- Clinic Header -->
        <div class="receipt-header">
            <h1>ALMAJYD DISPENSARY</h1>
            <p>Quality Healthcare Services</p>
            <p>Tel: +255 777 567 478 | Email: amrykassim@gmail.com</p>
            <p>OFFICIAL RECEIPT</p>
        </div>

        <!-- Receipt Number -->
        <div class="receipt-number">
            RECEIPT: <?php echo $receipt_number; ?>
        </div>

        <!-- Receipt Number and Date -->
        <div class="receipt-details">
            <div class="receipt-item">
                <span>Receipt No:</span>
                <span><strong><?php echo $receipt_number; ?></strong></span>
            </div>
            <div class="receipt-item">
                <span>Date & Time:</span>
                <span><?php echo $current_date; ?></span>
            </div>
            <div class="receipt-item">
                <span>Payment ID:</span>
                <span>#<?php echo $payment_id; ?></span>
            </div>
            <div class="receipt-item">
                <span>Issued By:</span>
                <span><?php echo htmlspecialchars($cashier_name); ?> (Admin)</span>
            </div>
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
                <span><?php echo $payment['age'] . ' yrs / ' . ucfirst($payment['gender']); ?></span>
            </div>
            <div class="receipt-item">
                <span>Phone:</span>
                <span><?php echo $payment['phone'] ?: 'N/A'; ?></span>
            </div>
            <div class="receipt-item">
                <span>Doctor:</span>
                <span>Dr. <?php echo htmlspecialchars($payment['doctor_name']); ?></span>
            </div>
        </div>

        <!-- Diagnosis and Treatment -->
        <?php if (!empty($payment['diagnosis'])): ?>
        <div class="receipt-section">
            <div class="receipt-section-title">MEDICAL INFORMATION</div>
            <div class="receipt-item" style="flex-direction: column; align-items: flex-start;">
                <div><strong>Diagnosis:</strong></div>
                <div style="margin-top: 3px;"><?php echo htmlspecialchars($payment['diagnosis']); ?></div>
            </div>
            
            <?php if (!empty($payment['medications'])): ?>
            <div class="receipt-item" style="flex-direction: column; align-items: flex-start; margin-top: 5px;">
                <div><strong>Medications:</strong></div>
                <div style="margin-top: 3px; font-size: 11px;"><?php echo htmlspecialchars($payment['medications']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($payment['lab_tests'])): ?>
            <div class="receipt-item" style="flex-direction: column; align-items: flex-start; margin-top: 5px;">
                <div><strong>Lab Tests:</strong></div>
                <div style="margin-top: 3px; font-size: 11px;"><?php echo htmlspecialchars($payment['lab_tests']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Payment Breakdown -->
        <div class="receipt-section">
            <div class="receipt-section-title">PAYMENT BREAKDOWN</div>
            
            <div class="receipt-item">
                <span>Consultation Fee:</span>
                <span>TSh <?php echo number_format($payment['consultation_fee'] ?? 0, 2); ?></span>
            </div>
            
            <?php if ($payment['medicine_amount'] > 0): ?>
            <div class="receipt-item">
                <span>Medicine Amount:</span>
                <span>TSh <?php echo number_format($payment['medicine_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($payment['lab_amount'] > 0): ?>
            <div class="receipt-item">
                <span>Lab Amount:</span>
                <span>TSh <?php echo number_format($payment['lab_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="receipt-item receipt-total">
                <span>TOTAL AMOUNT:</span>
                <span>TSh <?php echo number_format($total_amount, 2); ?></span>
            </div>
            
            <div class="receipt-item">
                <span>Amount Paid:</span>
                <span>TSh <?php echo number_format($amount_paid, 2); ?></span>
            </div>
            
            <?php if ($change_amount > 0): ?>
            <div class="receipt-item">
                <span>Change Amount:</span>
                <span>TSh <?php echo number_format($change_amount, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="receipt-item">
                <span>Payment Method:</span>
                <span style="text-transform: uppercase;"><?php echo $payment_method; ?></span>
            </div>
            
            <?php if (!empty($payment['transaction_id'])): ?>
            <div class="receipt-item">
                <span>Transaction ID:</span>
                <span><?php echo $payment['transaction_id']; ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Barcode Area -->
        <div class="barcode">
            <div style="border: 1px solid #000; padding: 5px; display: inline-block;">
                [BARCODE AREA]
            </div>
            <div style="font-size: 10px; margin-top: 3px;">
                <?php echo $receipt_number; ?>
            </div>
        </div>

        <!-- Thank You Message -->
        <div class="thank-you">
            Thank you for choosing Almajyd Dispensary!
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Issued by: <?php echo htmlspecialchars($cashier_name); ?> (Administrator)</div>
            <div>Date: <?php echo $current_date; ?></div>
            <div style="margin-top: 5px;">
                *** This is a computer generated receipt ***
            </div>
            <div>Printed on: <?php echo $current_date; ?></div>
        </div>
    </div>

    <script>
        // Auto-print function
        function autoPrint() {
            window.print();
        }

        // Auto-print option on page load
        window.onload = function() {
            // Uncomment the line below to auto-print when page loads
            // setTimeout(() => { window.print(); }, 1000);
        };

        // Keyboard shortcut for printing
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Print dialog closed event
        window.onafterprint = function() {
            console.log('Print dialog closed');
            // You can add any post-print actions here
        };
    </script>
</body>
</html>