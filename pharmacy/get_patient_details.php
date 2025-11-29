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
    
    // Calculate totals and check pricing status
    $total_cost = 0;
    $priced_items = 0;
    $total_items = count($prescriptions) + count($lab_tests);
    
    // Add consultation fee to total
    $consultation_fee = $patient['consultation_fee'] ?? 0;
    $total_cost += $consultation_fee;
    
    foreach ($prescriptions as $prescription) {
        if ($prescription['medicine_price'] > 0) {
            $total_cost += $prescription['medicine_price'];
            $priced_items++;
        }
    }
    
    foreach ($lab_tests as $test) {
        if ($test['lab_price'] > 0) {
            $total_cost += $test['lab_price'];
            $priced_items++;
        }
    }
    
    $all_priced = ($priced_items === $total_items && $total_items > 0);
    $some_priced = ($priced_items > 0 && $priced_items < $total_items);
    $none_priced = ($priced_items === 0 && $total_items > 0);
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}
?>

<div class="patient-profile">
    <div class="profile-header">
        <div>
            <h2 style="color:#10b981; margin-bottom: 5px;">
                <i class="fas fa-user-injured"></i> <?php echo htmlspecialchars($patient['full_name']); ?>
            </h2>
            <p style="color:#64748b;">Card No: <?php echo htmlspecialchars($patient['card_no']); ?></p>
        </div>
        <div class="patient-card-no">
            PATIENT RECORD
        </div>
    </div>
    
    <div class="profile-info">
        <div class="info-card">
            <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Card Number</span>
                <span class="detail-value"><?php echo htmlspecialchars($patient['card_no']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Consultation Fee</span>
                <span class="detail-value" style="color: #059669; font-weight: bold;">
                    TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?>
                </span>
            </div>
        </div>
        
        <div class="info-card">
            <h4><i class="fas fa-chart-line"></i> Medical Statistics</h4>
            <div class="detail-item">
                <span class="detail-label">Total Prescriptions</span>
                <span class="detail-value"><?php echo count($prescriptions); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Laboratory Tests</span>
                <span class="detail-value"><?php echo count($lab_tests); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Consultation Fee</span>
                <span class="detail-value">TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Total Cost</span>
                <span class="detail-value" style="color: #dc2626; font-weight: bold;">
                    TZS <?php echo number_format($total_cost, 2); ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Pricing Status</span>
                <span class="detail-value">
                    <?php if ($all_priced): ?>
                        <span class="badge badge-success">All Items Priced</span>
                    <?php elseif ($some_priced): ?>
                        <span class="badge badge-warning"><?php echo $priced_items; ?>/<?php echo $total_items; ?> Priced</span>
                    <?php elseif ($none_priced): ?>
                        <span class="badge badge-danger">No Prices Set</span>
                    <?php else: ?>
                        <span class="badge badge-info">No Items</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Print Section -->
    <div class="print-section">
        <h3 style="color:#10b981; margin-bottom: 15px;">
            <i class="fas fa-print"></i> Print Options
        </h3>
        
        <div class="print-info">
            <div>
                <strong>Patient:</strong> <?php echo htmlspecialchars($patient['full_name']); ?>
            </div>
            <div>
                <strong>Card No:</strong> <?php echo htmlspecialchars($patient['card_no']); ?>
            </div>
            <div>
                <strong>Total Items:</strong> <?php echo $total_items; ?> (<?php echo $priced_items; ?> priced)
            </div>
            <div>
                <strong>Consultation Fee:</strong> TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?>
            </div>
            <div>
                <strong>Current Total:</strong> TZS <?php echo number_format($total_cost, 2); ?>
            </div>
        </div>
        
        <!-- Print Options -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <!-- Option 1: Print All Items -->
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981;">
                <h4 style="color: #10b981; margin-bottom: 10px;">
                    <i class="fas fa-print"></i> Print All Items
                </h4>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 15px;">
                    Print all prescriptions and lab tests including consultation fee and items without prices.
                </p>
                <a href="print_patient_prescriptions.php?patient_id=<?php echo $patient_id; ?>&type=all" 
                   class="btn btn-primary" 
                   style="width: 100%; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; gap: 8px;"
                   target="_blank">
                    <i class="fas fa-print"></i> Print All
                </a>
            </div>
            
            <!-- Option 2: Print Priced Items Only -->
            <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border-left: 4px solid #059669;">
                <h4 style="color: #059669; margin-bottom: 10px;">
                    <i class="fas fa-file-invoice-dollar"></i> Print Priced Items
                </h4>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 15px;">
                    Print only items that have prices set including consultation fee. Total: TZS <?php echo number_format($total_cost, 2); ?>
                </p>
                <?php if ($priced_items > 0 || $consultation_fee > 0): ?>
                    <a href="print_patient_prescriptions.php?patient_id=<?php echo $patient_id; ?>&type=priced" 
                       class="btn btn-success" 
                       style="width: 100%; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; gap: 8px;"
                       target="_blank">
                        <i class="fas fa-receipt"></i> Print Priced (<?php echo $priced_items + ($consultation_fee > 0 ? 1 : 0); ?>)
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" style="width: 100%;" disabled>
                        <i class="fas fa-ban"></i> No Priced Items
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Option 3: Print Summary -->
            <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; border-left: 4px solid #3b82f6;">
                <h4 style="color: #3b82f6; margin-bottom: 10px;">
                    <i class="fas fa-file-alt"></i> Print Summary
                </h4>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 15px;">
                    Print a simple summary with totals, consultation fee and item counts.
                </p>
                <a href="print_patient_prescriptions.php?patient_id=<?php echo $patient_id; ?>&type=summary" 
                   class="btn btn-info" 
                   style="width: 100%; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; gap: 8px;"
                   target="_blank">
                    <i class="fas fa-file-contract"></i> Print Summary
                </a>
            </div>
        </div>
        
        <!-- Status Information -->
        <div style="background: #fffbeb; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <i class="fas fa-info-circle" style="color: #f59e0b; font-size: 1.2rem;"></i>
                <h4 style="color: #92400e; margin: 0;">Printing Information</h4>
            </div>
            <div style="color: #92400e; font-size: 0.9rem;">
                <p style="margin: 5px 0;">
                    • <strong>All Items:</strong> Includes everything + consultation fee (TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?>)
                </p>
                <p style="margin: 5px 0;">
                    • <strong>Priced Items:</strong> Only items with prices set + consultation fee (<?php echo $priced_items + ($consultation_fee > 0 ? 1 : 0); ?> available)
                </p>
                <p style="margin: 5px 0;">
                    • <strong>Summary:</strong> Brief overview with totals, consultation fee and counts
                </p>
                <p style="margin: 5px 0; font-weight: bold;">
                    • <strong>Consultation Fee:</strong> TZS <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?> (included in all prints)
                </p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
            <button class="btn btn-secondary" onclick="backToPatientsList()">
                <i class="fas fa-arrow-left"></i> Back to Patients List
            </button>
            
            <button class="btn btn-primary" onclick="showTab('prescriptions')">
                <i class="fas fa-edit"></i> Update Prices
            </button>
            
            <?php if ($some_priced || $none_priced): ?>
            <button class="btn btn-warning" onclick="showTab('prescriptions')">
                <i class="fas fa-money-bill-wave"></i> Set Missing Prices (<?php echo $total_items - $priced_items; ?>)
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.btn-success {
    background: #059669;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.btn-success:hover {
    background: #047857;
    transform: translateY(-2px);
}

.btn-info {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.btn-info:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

.btn-warning {
    background: #f59e0b;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.btn-warning:hover {
    background: #d97706;
    transform: translateY(-2px);
}

.btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
}

.btn:disabled:hover {
    background: #9ca3af;
    transform: none;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}
</style>