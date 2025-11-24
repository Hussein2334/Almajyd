<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'cashier') {
    header('Location: ../login.php');
    exit;
}

// Get payment ID from URL
$payment_id = $_GET['id'] ?? 0;

if (!$payment_id) {
    die("Invalid payment ID");
}

// Get payment details with patient and prescription information
$stmt = $pdo->prepare("
    SELECT 
        pmt.*,
        p.full_name as patient_name,
        p.card_no,
        p.age,
        p.gender,
        p.phone as patient_phone,
        p.address as patient_address,
        pr.medicine_name,
        pr.dosage,
        pr.frequency,
        pr.duration,
        pr.instructions,
        u.full_name as doctor_name,
        c.full_name as cashier_name,
        cf.diagnosis,
        cf.symptoms
    FROM payments pmt
    JOIN patients p ON pmt.patient_id = p.id
    JOIN checking_forms cf ON pmt.checking_form_id = cf.id
    JOIN prescriptions pr ON cf.id = pr.checking_form_id
    JOIN users u ON cf.doctor_id = u.id
    JOIN users c ON pmt.processed_by = c.id
    WHERE pmt.id = ?
");

$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Payment record not found");
}

// Get current date and time
$current_date = date('F j, Y');
$current_time = date('h:i A');
$invoice_number = 'INV-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Almajyd Dispensary</title>
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
            line-height: 1.4;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }

        /* Header Section */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #10b981;
        }

        .clinic-info {
            flex: 1;
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            border: 3px solid #10b981;
            margin-bottom: 10px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .clinic-info h1 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .clinic-info p {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 2px;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 10px;
        }

        .invoice-number {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .invoice-date {
            font-size: 14px;
            color: #64748b;
        }

        /* Patient and Payment Info */
        .info-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }

        .info-card h3 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 16px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-label {
            color: #64748b;
            font-weight: 500;
        }

        .info-value {
            color: #1e293b;
            font-weight: 600;
        }

        /* Prescription Details */
        .prescription-section {
            margin-bottom: 30px;
        }

        .section-title {
            background: #10b981;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }

        .prescription-details {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .medicine-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 10px;
            text-align: center;
        }

        .prescription-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .prescription-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        /* Payment Summary */
        .payment-summary {
            background: #ecfdf5;
            border: 2px solid #d1fae5;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #d1fae5;
        }

        .payment-row.total {
            border-bottom: none;
            border-top: 2px solid #10b981;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: bold;
        }

        .amount {
            font-size: 20px;
            font-weight: bold;
            color: #10b981;
            text-align: right;
        }

        /* Footer Section */
        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .signature-box {
            text-align: center;
            padding: 20px;
        }

        .signature-line {
            border-top: 1px solid #64748b;
            margin: 40px 0 10px 0;
        }

        .signature-name {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .signature-role {
            font-size: 12px;
            color: #64748b;
        }

        .thank-you {
            text-align: center;
            background: #d1fae5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .thank-you h3 {
            color: #065f46;
            margin-bottom: 5px;
        }

        .footer-notes {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }

        /* Print Styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .invoice-container {
                max-width: 100%;
                padding: 15px;
                box-shadow: none;
            }

            .no-print {
                display: none;
            }

            .invoice-header {
                margin-bottom: 20px;
            }

            .info-sections {
                gap: 20px;
                margin-bottom: 20px;
            }

            .prescription-section {
                margin-bottom: 20px;
            }

            .payment-summary {
                margin-bottom: 20px;
            }

            .invoice-footer {
                margin-top: 30px;
            }

            .thank-you {
                page-break-inside: avoid;
            }
        }

        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .print-button:hover {
            background: #059669;
            transform: translateY(-2px);
        }
    </style>
     <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <!-- Print Button -->
    <button class="print-button no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Invoice
    </button>

    <div class="invoice-container">
        <!-- Header Section -->
        <div class="invoice-header">
            <div class="clinic-info">
                <div class="logo">
                    <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo" onerror="this.style.display='none'">
                </div>
                <h1>ALMAJYD DISPENSARY</h1>
                <p>Quality Healthcare Services</p>
                <p>Tomondo - Zanzibar</p>
                <p>Phone: +255 777 567 478 | +255 719 053 764 | Email: amrykassim@gmail.com</p>
            </div>
            <div class="invoice-meta">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">Invoice: <?php echo $invoice_number; ?></div>
                <div class="invoice-date">Date: <?php echo $current_date; ?></div>
                <div class="invoice-date">Time: <?php echo $current_time; ?></div>
            </div>
        </div>

        <!-- Patient and Payment Information -->
        <div class="info-sections">
            <div class="info-card">
                <h3>Patient Information</h3>
                <div class="info-row">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['patient_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Card No:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['card_no']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age/Gender:</span>
                    <span class="info-value"><?php echo $payment['age'] . ' yrs / ' . ucfirst($payment['gender']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['patient_phone'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>Payment Information</h3>
                <div class="info-row">
                    <span class="info-label">Payment ID:</span>
                    <span class="info-value">PMT-<?php echo str_pad($payment_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?php echo ucfirst($payment['payment_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: <?php echo $payment['status'] == 'paid' ? '#10b981' : '#ef4444'; ?>;">
                        <?php echo ucfirst($payment['status']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Processed by:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['cashier_name']); ?></span>
                </div>
            </div>
        </div>

        <!-- Prescription Details -->
        <div class="prescription-section">
            <div class="section-title">PRESCRIPTION DETAILS</div>
            <div class="prescription-details">
                <div class="medicine-name"><?php echo htmlspecialchars($payment['medicine_name']); ?></div>
                
                <div class="prescription-info">
                    <div>
                        <div class="prescription-row">
                            <span class="info-label">Dosage:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['dosage'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="prescription-row">
                            <span class="info-label">Frequency:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['frequency'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="prescription-row">
                            <span class="info-label">Duration:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['duration'] ?? 'Not specified'); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="prescription-row">
                            <span class="info-label">Prescribed by:</span>
                            <span class="info-value">Dr. <?php echo htmlspecialchars($payment['doctor_name']); ?></span>
                        </div>
                        <?php if (!empty($payment['diagnosis'])): ?>
                        <div class="prescription-row">
                            <span class="info-label">Diagnosis:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['diagnosis']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($payment['instructions'])): ?>
                <div class="prescription-row">
                    <span class="info-label">Special Instructions:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['instructions']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="payment-summary">
            <div class="section-title">PAYMENT SUMMARY</div>
            <div class="payment-row">
                <span style="font-size: 16px; font-weight: 600;">Medicine Cost:</span>
                <span class="amount">TZS <?php echo number_format($payment['amount'], 2); ?></span>
            </div>
            <div class="payment-row">
                <span>Tax (0%):</span>
                <span>TZS 0.00</span>
            </div>
            <div class="payment-row">
                <span>Discount:</span>
                <span>TZS 0.00</span>
            </div>
            <div class="payment-row total">
                <span>TOTAL AMOUNT:</span>
                <span class="amount">TZS <?php echo number_format($payment['amount'], 2); ?></span>
            </div>
        </div>

        <!-- Footer Section -->
        <div class="invoice-footer">
            <div class="thank-you">
                <h3>Thank You for Choosing Almajyd Dispensary!</h3>
                <p>We appreciate your trust in our healthcare services</p>
            </div>

            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-name">Dr. <?php echo htmlspecialchars($payment['doctor_name']); ?></div>
                    <div class="signature-role">Medical Doctor</div>
                    <div class="signature-line"></div>
                    <div class="signature-role">Signature & Stamp</div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-name"><?php echo htmlspecialchars($payment['cashier_name']); ?></div>
                    <div class="signature-role">Cashier</div>
                    <div class="signature-line"></div>
                    <div class="signature-role">Signature & Stamp</div>
                </div>
            </div>

            <div class="footer-notes">
                <p><strong>Important Notes:</strong></p>
                <p>• This invoice is computer generated and requires no physical signature</p>
                <p>• Please keep this invoice for your records</p>
                <p>• For any queries, contact us at +255 777 567 478</p>
                <p>• Office hours: Monday - Saturday, 8:00 AM - 6:00 PM</p>
            </div>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script>
        // Auto-print option (optional)
        window.onload = function() {
            // Uncomment the line below if you want to auto-print when page loads
            // window.print();
        };

        // Add keyboard shortcut for printing (Ctrl + P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>