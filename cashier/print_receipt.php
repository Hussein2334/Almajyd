<?php
require_once '../config.php';

// Angalia kama session haijaanzishwa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Angalia uhakika - Wajibu wa Cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['payment_id'])) {
    die('Hitaji la ID ya malipo');
}

$payment_id = $_GET['payment_id'];

try {
    // Pata data ya resiti kwa maelezo yote
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            p.full_name as patient_name,
            p.card_no,
            p.age,
            p.gender,
            p.phone,
            p.address,
            p.consultation_fee,
            pt.amount as payment_amount,
            pt.payment_method,
            u.full_name as cashier_name,
            doc.full_name as doctor_name,
            cf.diagnosis,
            cf.symptoms,
            cf.created_at as visit_date,
            (SELECT GROUP_CONCAT(CONCAT(pr.medicine_name, ' (', pr.dosage, ') - TZS ', pr.medicine_price) SEPARATOR '; ') 
             FROM prescriptions pr 
             WHERE pr.checking_form_id = pt.checking_form_id) as medications,
            (SELECT GROUP_CONCAT(CONCAT(lt.test_type, ' - TZS ', lt.lab_price) SEPARATOR '; ') 
             FROM laboratory_tests lt 
             WHERE lt.checking_form_id = pt.checking_form_id) as lab_tests,
            (SELECT SUM(medicine_price) FROM prescriptions pr WHERE pr.checking_form_id = pt.checking_form_id) as total_medicine_cost,
            (SELECT SUM(lab_price) FROM laboratory_tests lt WHERE lt.checking_form_id = pt.checking_form_id) as total_lab_cost
        FROM receipts r
        JOIN payments pt ON r.payment_id = pt.id
        JOIN patients p ON pt.patient_id = p.id
        LEFT JOIN users u ON r.issued_by = u.id
        LEFT JOIN checking_forms cf ON pt.checking_form_id = cf.id
        LEFT JOIN users doc ON cf.doctor_id = doc.id
        WHERE r.payment_id = ?
    ");
    $stmt->execute([$payment_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receipt) {
        die('Resiti haipatikani');
    }
    
} catch (PDOException $e) {
    die('Hitilafu ya hifadhidata: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resiti <?php echo $receipt['receipt_number']; ?> - Almajyd Dispensary</title>
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
            background: white;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
        }
        
        .receipt-container {
            width: 100%;
            max-width: 75mm;
            margin: 0 auto;
            background: white;
            padding: 10px;
            border: 1px solid #000;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        
        .receipt-header h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .receipt-header p {
            font-size: 10px;
            margin: 2px 0;
        }
        
        .receipt-logo {
            text-align: center;
            margin-bottom: 5px;
        }
        
        .receipt-logo img {
            height: 40px;
            width: auto;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
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
            padding: 3px;
            font-size: 11px;
        }
        
        .total-section {
            background: #e8f4ff;
            border: 1px solid #2c5aa0;
            padding: 8px;
            margin: 10px 0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-weight: bold;
        }
        
        .grand-total {
            font-size: 13px;
            color: #2c5aa0;
            border-top: 2px solid #2c5aa0;
            padding-top: 6px;
        }
        
        .items-list {
            margin: 5px 0;
        }
        
        .item-row {
            margin-bottom: 2px;
            padding: 1px 0;
            font-size: 10px;
        }
        
        .barcode {
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            border: 1px solid #000;
        }
        
        .thank-you {
            text-align: center;
            font-style: italic;
            margin: 10px 0;
            padding: 8px;
            background: #f0f0f0;
        }
        
        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 8px;
            color: #666;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .receipt-container {
                border: none;
                box-shadow: none;
                width: 75mm;
                max-width: 75mm;
                padding: 8px;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                margin: 0;
                size: 75mm auto;
            }
        }
        
        .print-actions {
            text-align: center;
            margin: 15px auto;
            max-width: 75mm;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            text-decoration: none;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            margin: 3px;
        }
        
        .btn-primary {
            background: #2c5aa0;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        /* Compact styles for small receipt */
        .compact-text {
            font-size: 10px;
        }
        
        .compact-section {
            margin-bottom: 6px;
            padding-bottom: 4px;
        }
    </style>
</head>
<body onload="window.print()">
    <!-- Vitendo vya kuchapisha -->
    <div class="print-actions no-print">
        <button class="btn btn-primary" onclick="window.print()">
            🖨️ Chapisha Resiti
        </button>
        <a href="cashier_receipts.php" class="btn btn-secondary">
            ↩️ Rudi
        </a>
        <p style="margin-top: 8px; color: #666; font-size: 10px;">
            Resiti itachapisha kiotomatiki. Usiwe na wasiwasi - itakaa ukurasa mmoja tu!
        </p>
    </div>

    <div class="receipt-container">
        <!-- Kichwa cha Dispensary -->
        <div class="receipt-logo">
            <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo" onerror="this.style.display='none'">
        </div>
        
        <div class="receipt-header">
            <h1>ALMAJYD DISPENSARY</h1>
            <p>Huduma Bora za Afya</p>
            <p>Simu: +255 777 567 478</p>
            <p>Tomondo - Zanzibar</p>
            <p><strong>RESITI YA MALIPO</strong></p>
        </div>

        <!-- Maelezo ya Resiti -->
        <div class="receipt-section compact-section">
            <div class="receipt-section-title">TAARIFA ZA RESITI</div>
            <div class="info-row">
                <span>Nambari:</span>
                <span><strong><?php echo htmlspecialchars($receipt['receipt_number']); ?></strong></span>
            </div>
            <div class="info-row">
                <span>Tarehe:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($receipt['issued_at'])); ?></span>
            </div>
            <div class="info-row">
                <span>Malipo:</span>
                <span style="text-transform: uppercase;"><strong><?php echo $receipt['payment_method']; ?></strong></span>
            </div>
        </div>

        <!-- Taarifa za Mgonjwa -->
        <div class="receipt-section compact-section">
            <div class="receipt-section-title">TAARIFA ZA MGONJWA</div>
            <div class="info-row">
                <span>Jina:</span>
                <span><?php echo htmlspecialchars($receipt['patient_name']); ?></span>
            </div>
            <div class="info-row">
                <span>Kadi:</span>
                <span><?php echo htmlspecialchars($receipt['card_no']); ?></span>
            </div>
            <div class="info-row">
                <span>Umri/Jinsia:</span>
                <span><?php echo $receipt['age'] . 'y / ' . ucfirst($receipt['gender']); ?></span>
            </div>
        </div>

        <!-- Vipimo vya Laboratori (Ikiwapo) -->
        <?php if (!empty($receipt['lab_tests'])): ?>
        <div class="receipt-section compact-section">
            <div class="receipt-section-title">VIPIMO VYA LABORATORI</div>
            <div class="items-list compact-text">
                <?php 
                $lab_tests = explode('; ', $receipt['lab_tests']);
                $count = 0;
                foreach ($lab_tests as $test): 
                    if (!empty(trim($test)) && $count < 3): // Limit to 3 items
                ?>
                <div class="item-row">• <?php echo htmlspecialchars($test); ?></div>
                <?php 
                    $count++;
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dawa (Ikiwapo) -->
        <?php if (!empty($receipt['medications'])): ?>
        <div class="receipt-section compact-section">
            <div class="receipt-section-title">DAWA</div>
            <div class="items-list compact-text">
                <?php 
                $medications = explode('; ', $receipt['medications']);
                $count = 0;
                foreach ($medications as $med): 
                    if (!empty(trim($med)) && $count < 3): // Limit to 3 items
                ?>
                <div class="item-row">• <?php echo htmlspecialchars($med); ?></div>
                <?php 
                    $count++;
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Muhtasari wa Malipo -->
        <div class="total-section">
            <div class="receipt-section-title">MUHTASARI WA MALIPO</div>
            
            <?php if ($receipt['consultation_fee'] > 0): ?>
            <div class="total-row compact-text">
                <span>Uchunguzi:</span>
                <span>TZS <?php echo number_format($receipt['consultation_fee'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($receipt['total_medicine_cost'] > 0): ?>
            <div class="total-row compact-text">
                <span>Dawa:</span>
                <span>TZS <?php echo number_format($receipt['total_medicine_cost'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($receipt['total_lab_cost'] > 0): ?>
            <div class="total-row compact-text">
                <span>Vipimo:</span>
                <span>TZS <?php echo number_format($receipt['total_lab_cost'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row grand-total">
                <span>JUMLA:</span>
                <span>TZS <?php echo number_format($receipt['total_amount'], 2); ?></span>
            </div>
            
            <div class="total-row">
                <span>Imelipwa:</span>
                <span>TZS <?php echo number_format($receipt['amount_paid'], 2); ?></span>
            </div>
            
            <?php if ($receipt['change_amount'] > 0): ?>
            <div class="total-row">
                <span>Rudufu:</span>
                <span>TZS <?php echo number_format($receipt['change_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mstari wa Mwisho -->
        <div class="barcode">
            <div style="font-family: monospace; letter-spacing: 1px;">
                <?php echo str_repeat('| ', 15); ?>
            </div>
            <div style="font-size: 9px; margin-top: 2px;">
                <?php echo $receipt['receipt_number']; ?>
            </div>
        </div>

        <!-- Ushuhuda -->
        <div class="thank-you compact-text">
            <strong>Asante kwa kuchagua Almajyd Dispensary!</strong><br>
            <em>Tunakuombea Afya nje na  Kwa Heri!</em>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Imetolewa na: <?php echo htmlspecialchars($receipt['cashier_name']); ?></div>
            <div>Tarehe: <?php echo date('d/m/Y H:i', strtotime($receipt['issued_at'])); ?></div>
            <div style="margin-top: 5px;">
                *** Resiti ya kompyuta ***
            </div>
        </div>
    </div>

    <script>
        // Kuchapisha kiotomatiki
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Kurudi baada ya kuchapisha
        window.onafterprint = function() {
            setTimeout(function() {
                window.location.href = 'cashier_receipts.php';
            }, 1000);
        };

        // Chaguo la kuchapisha kwa mkono
        function printReceipt() {
            window.print();
        }
    </script>
</body>
</html>