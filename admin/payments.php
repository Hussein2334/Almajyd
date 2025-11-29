<!-- Payments Overview Section -->
<div class="table-card">
    <h3>
        <i class="fas fa-money-check"></i>
        Recent Payments
        <a href="payments.php" style="float: right; font-size: 0.9rem; text-decoration: none;">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </h3>
    
    <?php
    // Fetch recent payments for dashboard
    $recent_payments = $pdo->query("
        SELECT p.*, pt.full_name as patient_name, pt.card_no, u.full_name as cashier_name
        FROM payments p 
        JOIN patients pt ON p.patient_id = pt.id 
        LEFT JOIN users u ON p.created_by = u.id 
        WHERE p.status = 'completed' 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb;">Receipt No</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb;">Patient</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb;">Amount</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb;">Method</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_payments as $payment): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                    <td style="padding: 12px; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($payment['patient_name']); ?></td>
                    <td style="padding: 12px; border-bottom: 1px solid #f1f5f9;"><strong>TSh <?php echo number_format($payment['amount'], 2); ?></strong></td>
                    <td style="padding: 12px; border-bottom: 1px solid #f1f5f9;">
                        <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; background: #dbeafe; color: #1e40af;">
                            <?php echo ucfirst($payment['payment_method']); ?>
                        </span>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #f1f5f9;"><?php echo date('M j, H:i', strtotime($payment['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>