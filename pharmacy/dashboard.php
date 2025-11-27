<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Pharmacy role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Update Profile
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        // Update basic profile info
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
        
        // Update password if provided
        if (!empty($current_password) && !empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match!";
            } else {
                // Verify current password
                $user_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $user_stmt->execute([$_SESSION['user_id']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pwd_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $pwd_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    $success_message = "Profile and password updated successfully!";
                } else {
                    $error_message = "Current password is incorrect!";
                }
            }
        } else {
            $success_message = "Profile updated successfully!";
        }
        
        // Update session
        $_SESSION['full_name'] = $full_name;
        
    } catch (PDOException $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Handle Dispense All Prescriptions AND Lab Tests with Prices for a Patient
if (isset($_POST['dispense_all_prescriptions'])) {
    $patient_id = $_POST['patient_id'];
    $prescriptions_data = $_POST['prescriptions'] ?? [];
    $lab_tests_data = $_POST['lab_tests'] ?? [];
    $total_medicine_amount = 0;
    $total_lab_amount = 0;
    
    try {
        $pdo->beginTransaction();
        
        // Get checking_form_id for the patient
        $checking_stmt = $pdo->prepare("SELECT id FROM checking_forms WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $checking_stmt->execute([$patient_id]);
        $checking_form = $checking_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checking_form) {
            $checking_form_id = $checking_form['id'];
            
            // Process each prescription
            foreach ($prescriptions_data as $prescription_id => $data) {
                $medicine_available = $data['available'] ?? 'no';
                $price = floatval($data['price'] ?? 0);
                $alternative_medicine = $data['alternative'] ?? null;
                
                if ($medicine_available === 'yes') {
                    // Update prescription status to dispensed with price
                    $stmt = $pdo->prepare("UPDATE prescriptions SET status = 'dispensed', medicine_price = ?, alternative_medicine = NULL, is_available = 'yes' WHERE id = ?");
                    $stmt->execute([$price, $prescription_id]);
                    
                    // Add to total medicine amount
                    $total_medicine_amount += $price;
                } else {
                    // Mark as not available with alternative medicine
                    $stmt = $pdo->prepare("UPDATE prescriptions SET status = 'not_available', alternative_medicine = ?, is_available = 'no' WHERE id = ?");
                    $stmt->execute([$alternative_medicine, $prescription_id]);
                }
            }
            
            // Process each lab test
            foreach ($lab_tests_data as $lab_test_id => $data) {
                $lab_test_price = floatval($data['price'] ?? 0);
                
                if ($lab_test_price > 0) {
                    // Update lab test with price
                    $stmt = $pdo->prepare("UPDATE laboratory_tests SET lab_price = ? WHERE id = ?");
                    $stmt->execute([$lab_test_price, $lab_test_id]);
                    
                    // Add lab test price to total lab amount
                    $total_lab_amount += $lab_test_price;
                }
            }
            
            // Calculate total amount (including consultation fee)
            $patient_stmt = $pdo->prepare("SELECT consultation_fee FROM patients WHERE id = ?");
            $patient_stmt->execute([$patient_id]);
            $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
            $consultation_fee = $patient['consultation_fee'] ?? 0;
            
            $total_amount = $total_medicine_amount + $total_lab_amount + $consultation_fee;
            
            // Create or update payment record with separate medicine and lab amounts
            if ($total_amount > 0) {
                // Check if payment record already exists
                $check_payment_stmt = $pdo->prepare("
                    SELECT id FROM payments 
                    WHERE patient_id = ? AND checking_form_id = ? AND payment_type = 'medicine_and_lab'
                ");
                $check_payment_stmt->execute([$patient_id, $checking_form_id]);
                $existing_payment = $check_payment_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_payment) {
                    // Update existing payment
                    $payment_stmt = $pdo->prepare("
                        UPDATE payments 
                        SET amount = ?, medicine_amount = ?, lab_amount = ?, consultation_fee = ?, processed_by = ?, updated_at = NOW()
                        WHERE patient_id = ? AND checking_form_id = ? AND payment_type = 'medicine_and_lab'
                    ");
                    $payment_stmt->execute([
                        $total_amount, 
                        $total_medicine_amount, 
                        $total_lab_amount,
                        $consultation_fee,
                        $_SESSION['user_id'],
                        $patient_id, 
                        $checking_form_id
                    ]);
                } else {
                    // Create new payment record
                    $payment_stmt = $pdo->prepare("
                        INSERT INTO payments (patient_id, checking_form_id, amount, medicine_amount, lab_amount, consultation_fee, payment_type, status, processed_by) 
                        VALUES (?, ?, ?, ?, ?, ?, 'medicine_and_lab', 'pending', ?)
                    ");
                    $payment_stmt->execute([
                        $patient_id, 
                        $checking_form_id, 
                        $total_amount, 
                        $total_medicine_amount, 
                        $total_lab_amount,
                        $consultation_fee,
                        $_SESSION['user_id']
                    ]);
                }
            }
            
            $success_message = "Prescriptions and lab tests processed successfully!<br>";
            $success_message .= "Consultation Fee: TSh " . number_format($consultation_fee, 2) . "<br>";
            $success_message .= "Medicine Amount: TSh " . number_format($total_medicine_amount, 2) . "<br>";
            $success_message .= "Lab Amount: TSh " . number_format($total_lab_amount, 2) . "<br>";
            $success_message .= "Total Amount: TSh " . number_format($total_amount, 2);
            
        } else {
            $error_message = "No checking form found for this patient!";
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error processing prescriptions and lab tests: " . $e->getMessage();
    }
}

// Handle Edit Total Price (for completed prescriptions and lab tests)
if (isset($_POST['edit_total_price'])) {
    $patient_id = $_POST['patient_id'];
    $new_medicine_amount = $_POST['new_medicine_amount'];
    $new_lab_amount = $_POST['new_lab_amount'];
    $reason = $_POST['reason'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get checking_form_id for the patient
        $checking_stmt = $pdo->prepare("SELECT id FROM checking_forms WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $checking_stmt->execute([$patient_id]);
        $checking_form = $checking_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checking_form) {
            $checking_form_id = $checking_form['id'];
            
            // Get consultation fee
            $patient_stmt = $pdo->prepare("SELECT consultation_fee FROM patients WHERE id = ?");
            $patient_stmt->execute([$patient_id]);
            $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
            $consultation_fee = $patient['consultation_fee'] ?? 0;
            
            // Calculate new total amount
            $new_total_amount = $new_medicine_amount + $new_lab_amount + $consultation_fee;
            
            // Check if payment record exists
            $check_payment_stmt = $pdo->prepare("
                SELECT id FROM payments 
                WHERE patient_id = ? AND checking_form_id = ? AND payment_type = 'medicine_and_lab'
            ");
            $check_payment_stmt->execute([$patient_id, $checking_form_id]);
            $existing_payment = $check_payment_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_payment) {
                // Update existing payment amount
                $payment_stmt = $pdo->prepare("
                    UPDATE payments 
                    SET amount = ?, medicine_amount = ?, lab_amount = ?, notes = ?, processed_by = ?, updated_at = NOW()
                    WHERE patient_id = ? AND checking_form_id = ? AND payment_type = 'medicine_and_lab'
                ");
                $payment_stmt->execute([
                    $new_total_amount, 
                    $new_medicine_amount, 
                    $new_lab_amount,
                    $reason,
                    $_SESSION['user_id'],
                    $patient_id, 
                    $checking_form_id
                ]);
            } else {
                // Create new payment record
                $payment_stmt = $pdo->prepare("
                    INSERT INTO payments (patient_id, checking_form_id, amount, medicine_amount, lab_amount, consultation_fee, payment_type, status, processed_by, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, 'medicine_and_lab', 'pending', ?, ?)
                ");
                $payment_stmt->execute([
                    $patient_id, 
                    $checking_form_id, 
                    $new_total_amount, 
                    $new_medicine_amount, 
                    $new_lab_amount,
                    $consultation_fee,
                    $_SESSION['user_id'],
                    $reason
                ]);
            }
            
            $success_message = "Price updated successfully!<br>";
            $success_message .= "Consultation Fee: TSh " . number_format($consultation_fee, 2) . "<br>";
            $success_message .= "Medicine Amount: TSh " . number_format($new_medicine_amount, 2) . "<br>";
            $success_message .= "Lab Amount: TSh " . number_format($new_lab_amount, 2) . "<br>";
            $success_message .= "Total Amount: TSh " . number_format($new_total_amount, 2);
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating total price: " . $e->getMessage();
    }
}

// Handle Delete Patient Card from Dispensed Section
if (isset($_POST['delete_dispensed_card'])) {
    $patient_id = $_POST['patient_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get checking_form_id for the patient
        $checking_stmt = $pdo->prepare("SELECT id FROM checking_forms WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $checking_stmt->execute([$patient_id]);
        $checking_form = $checking_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checking_form) {
            $checking_form_id = $checking_form['id'];
            
            // Change prescription status back to pending (so it appears in pending section again)
            $update_stmt = $pdo->prepare("UPDATE prescriptions SET status = 'pending', medicine_price = 0, alternative_medicine = NULL, is_available = 'yes' WHERE checking_form_id = ? AND status = 'dispensed'");
            $update_stmt->execute([$checking_form_id]);
            
            // Reset lab test prices
            $lab_stmt = $pdo->prepare("UPDATE laboratory_tests SET lab_price = 0 WHERE checking_form_id = ?");
            $lab_stmt->execute([$checking_form_id]);
            
            // Delete the payment record for medicine_and_lab
            $delete_stmt = $pdo->prepare("DELETE FROM payments WHERE patient_id = ? AND checking_form_id = ? AND payment_type = 'medicine_and_lab'");
            $delete_stmt->execute([$patient_id, $checking_form_id]);
            
            $success_message = "Patient card removed from dispensed section successfully!";
        } else {
            $error_message = "No checking form found for this patient!";
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error deleting patient card: " . $e->getMessage();
    }
}

// Get statistics for dashboard
$total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'];
$today_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];
$total_prescriptions = $pdo->query("SELECT COUNT(*) as total FROM prescriptions")->fetch(PDO::FETCH_ASSOC)['total'];
$pending_prescriptions = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];
$completed_prescriptions = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'dispensed'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get total revenue statistics
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];
$pending_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];
$today_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(created_at) = CURDATE() AND status = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'];

// FIXED: Get pending prescriptions with patient data - SIMPLE AND WORKING QUERY
$pending_patients = $pdo->query("
    SELECT 
        p.id as patient_id,
        p.full_name as patient_name, 
        p.card_no, 
        p.age, 
        p.gender,
        p.consultation_fee,
        u.full_name as doctor_name,
        cf.diagnosis,
        cf.symptoms,
        cf.id as checking_form_id
    FROM patients p 
    JOIN checking_forms cf ON p.id = cf.patient_id 
    JOIN prescriptions pr ON cf.id = pr.checking_form_id 
    JOIN users u ON cf.doctor_id = u.id 
    WHERE pr.status = 'pending'
    GROUP BY p.id, p.full_name, p.card_no, p.age, p.gender, p.consultation_fee, 
             u.full_name, cf.diagnosis, cf.symptoms, cf.id
    ORDER BY cf.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Now get detailed prescriptions and lab tests for each patient
foreach ($pending_patients as &$patient) {
    $checking_form_id = $patient['checking_form_id'];
    
    // Get prescriptions count and data
    $prescriptions_stmt = $pdo->prepare("
        SELECT COUNT(*) as prescription_count 
        FROM prescriptions 
        WHERE checking_form_id = ? AND status = 'pending'
    ");
    $prescriptions_stmt->execute([$checking_form_id]);
    $patient['prescription_count'] = $prescriptions_stmt->fetch(PDO::FETCH_ASSOC)['prescription_count'];
    
    // Get prescriptions details
    $prescriptions_data_stmt = $pdo->prepare("
        SELECT * FROM prescriptions 
        WHERE checking_form_id = ? AND status = 'pending'
    ");
    $prescriptions_data_stmt->execute([$checking_form_id]);
    $patient['prescriptions'] = $prescriptions_data_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lab tests
    $lab_tests_stmt = $pdo->prepare("
        SELECT * FROM laboratory_tests 
        WHERE checking_form_id = ? AND status = 'completed'
    ");
    $lab_tests_stmt->execute([$checking_form_id]);
    $patient['lab_tests'] = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// FIXED: Get completed prescriptions with patient data
$completed_patients = $pdo->query("
    SELECT 
        p.id as patient_id,
        p.full_name as patient_name, 
        p.card_no, 
        p.age, 
        p.gender,
        p.consultation_fee,
        u.full_name as doctor_name,
        cf.diagnosis,
        cf.symptoms,
        cf.id as checking_form_id
    FROM patients p 
    JOIN checking_forms cf ON p.id = cf.patient_id 
    JOIN prescriptions pr ON cf.id = pr.checking_form_id 
    JOIN users u ON cf.doctor_id = u.id 
    WHERE pr.status = 'dispensed'
    GROUP BY p.id, p.full_name, p.card_no, p.age, p.gender, p.consultation_fee, 
             u.full_name, cf.diagnosis, cf.symptoms, cf.id
    ORDER BY cf.created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Now get detailed data for completed patients
foreach ($completed_patients as &$patient) {
    $checking_form_id = $patient['checking_form_id'];
    
    // Get prescriptions count and data
    $prescriptions_stmt = $pdo->prepare("
        SELECT COUNT(*) as prescription_count 
        FROM prescriptions 
        WHERE checking_form_id = ? AND status = 'dispensed'
    ");
    $prescriptions_stmt->execute([$checking_form_id]);
    $patient['prescription_count'] = $prescriptions_stmt->fetch(PDO::FETCH_ASSOC)['prescription_count'];
    
    // Get prescriptions details
    $prescriptions_data_stmt = $pdo->prepare("
        SELECT * FROM prescriptions 
        WHERE checking_form_id = ? AND status = 'dispensed'
    ");
    $prescriptions_data_stmt->execute([$checking_form_id]);
    $patient['prescriptions'] = $prescriptions_data_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lab tests
    $lab_tests_stmt = $pdo->prepare("
        SELECT * FROM laboratory_tests 
        WHERE checking_form_id = ? AND status = 'completed'
    ");
    $lab_tests_stmt->execute([$checking_form_id]);
    $patient['lab_tests'] = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get payment details with separate medicine and lab amounts
$payment_details = [];
$payment_stmt = $pdo->query("
    SELECT p.*, pt.full_name as patient_name, pt.card_no, cf.id as checking_form_id
    FROM payments p 
    JOIN patients pt ON p.patient_id = pt.id
    LEFT JOIN checking_forms cf ON p.checking_form_id = cf.id
    ORDER BY p.created_at DESC
");
$all_payments = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_payments as $payment) {
    $payment_details[$payment['patient_id']] = $payment;
}

// Get patients for dropdown
$patients = $pdo->query("SELECT id, full_name, card_no FROM patients ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get recent patients with prescriptions
$recent_patients = $pdo->query("SELECT DISTINCT p.*, 
    (SELECT COUNT(*) FROM prescriptions pr 
     JOIN checking_forms cf ON pr.checking_form_id = cf.id 
     WHERE cf.patient_id = p.id) as prescription_count
FROM patients p 
ORDER BY p.created_at DESC 
LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// FIXED: Get laboratory results grouped by patient
$lab_results_by_patient = $pdo->query("
    SELECT 
        p.id as patient_id,
        p.full_name as patient_name, 
        p.card_no, 
        p.age, 
        p.gender,
        COUNT(lt.id) as test_count
    FROM patients p 
    JOIN checking_forms cf ON p.id = cf.patient_id 
    JOIN laboratory_tests lt ON cf.id = lt.checking_form_id 
    WHERE lt.status = 'completed'
    GROUP BY p.id, p.full_name, p.card_no, p.age, p.gender
    ORDER BY MAX(lt.updated_at) DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Now get lab test details for each patient
foreach ($lab_results_by_patient as &$patient) {
    $lab_details_stmt = $pdo->prepare("
        SELECT lt.test_type, lt.results 
        FROM laboratory_tests lt 
        JOIN checking_forms cf ON lt.checking_form_id = cf.id 
        WHERE cf.patient_id = ? AND lt.status = 'completed'
    ");
    $lab_details_stmt->execute([$patient['patient_id']]);
    $patient['lab_details'] = $lab_details_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get current user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');

// Safe variable access for form values
$current_full_name = htmlspecialchars($current_user['full_name'] ?? '');
$current_email = htmlspecialchars($current_user['email'] ?? '');
$current_phone = htmlspecialchars($current_user['phone'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - Almajyd Dispensary</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
            color: #334155;
        }
        
        /* Topbar - WITH LOGO */
        .topbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 3px solid #10b981;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .clinic-info h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .clinic-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        .user-area {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .time-display {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f1f5f9;
            padding: 8px 15px;
            border-radius: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Main Content */
        .main { 
            padding: 25px; 
            max-width: 1400px; 
            margin: 0 auto; 
        }

        /* Messages - IMPROVED ALERT SYSTEM */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 10px;
            font-weight: 500;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-left: 4px solid;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-card.patients { border-left-color: #3b82f6; }
        .stat-card.today { border-left-color: #10b981; }
        .stat-card.total-rx { border-left-color: #8b5cf6; }
        .stat-card.pending-rx { border-left-color: #f59e0b; }
        .stat-card.completed-rx { border-left-color: #10b981; }
        .stat-card.revenue { border-left-color: #10b981; }
        .stat-card.pending-revenue { border-left-color: #f59e0b; }
        .stat-card.today-revenue { border-left-color: #3b82f6; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.patients .stat-icon { color: #3b82f6; }
        .stat-card.today .stat-icon { color: #10b981; }
        .stat-card.total-rx .stat-icon { color: #8b5cf6; }
        .stat-card.pending-rx .stat-icon { color: #f59e0b; }
        .stat-card.completed-rx .stat-icon { color: #10b981; }
        .stat-card.revenue .stat-icon { color: #10b981; }
        .stat-card.pending-revenue .stat-icon { color: #f59e0b; }
        .stat-card.today-revenue .stat-icon { color: #3b82f6; }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin: 8px 0;
            color: #1e293b;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.85em;
            font-weight: 500;
        }

        /* Tabs Navigation */
        .tabs-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: #f8fafc;
            border: none;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab:hover {
            background: #e2e8f0;
            color: #475569;
        }
        
        .tab.active {
            background: #10b981;
            color: white;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 20px;
        }
        
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .form-card h4 {
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: #3b82f6;
            color: white;
        }
        
        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(700px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .card.prescription { border-left-color: #f59e0b; }
        .card.dispensed { border-left-color: #10b981; }
        .card.lab-result { border-left-color: #8b5cf6; }
        .card.patient { border-left-color: #3b82f6; }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-weight: bold;
            color: #1e293b;
            font-size: 1.2rem;
        }
        
        .card-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-dispensed { background: #dbeafe; color: #1e40af; }
        
        .card-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .info-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .card-description {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.5;
        }
        
        .medications-list {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .medication-item {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }
        
        .medication-item:last-child {
            border-bottom: none;
        }
        
        .medication-details {
            flex: 1;
        }
        
        .medication-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .medication-specs {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.4;
        }
        
        .medication-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 200px;
        }
        
        .card-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        /* Delete Button */
        .delete-card-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .delete-card-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        /* Lab Results Section */
        .lab-results-section {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .lab-results-section h5 {
            color: #1e40af;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .lab-test-item {
            padding: 15px;
            background: white;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #3b82f6;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }
        
        .lab-test-item:last-child {
            margin-bottom: 0;
        }
        
        .test-details {
            flex: 1;
        }
        
        .test-type {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .test-results {
            color: #475569;
            font-size: 0.9rem;
        }
        
        .test-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 200px;
        }

        /* Dispense All Form */
        .dispense-all-form {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #10b981;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .price-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-input span {
            font-weight: 600;
            color: #059669;
            font-size: 0.9rem;
        }
        
        .availability-toggle {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .toggle-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .toggle-option input[type="radio"] {
            margin: 0;
        }
        
        .toggle-option label {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .total-amount {
            background: #d1fae5;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: #065f46;
            border: 2px solid #10b981;
        }

        /* Alternative Medicine Input */
        .alternative-medicine {
            background: #fff3cd;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
            border-left: 3px solid #ffc107;
        }
        
        .alternative-medicine label {
            font-weight: 600;
            color: #856404;
            font-size: 0.85rem;
            margin-bottom: 5px;
            display: block;
        }

        /* Edit Price Form */
        .edit-price-form {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 3px solid #ffc107;
        }
        
        .price-display {
            font-weight: bold;
            color: #059669;
            font-size: 1.1rem;
        }
        
        .edit-price-btn {
            background: none;
            border: none;
            color: #3b82f6;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 8px;
        }
        
        .edit-price-btn:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* Tables */
        .table-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-card h3 {
            margin-bottom: 15px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper {
            font-size: 0.85rem;
        }
        
        .dataTables_filter input {
            border: 2px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            font-size: 0.85rem !important;
            margin-bottom: 15px !important;
        }
        
        .dataTables_length select {
            border: 2px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            font-size: 0.85rem !important;
            margin-bottom: 15px !important;
        }
        
        .dataTables_info {
            color: #6b7280 !important;
            font-size: 0.8rem !important;
            padding: 10px 0 !important;
        }
        
        .dataTables_paginate .paginate_button {
            border: 1px solid #e5e7eb !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            margin: 0 2px !important;
            font-size: 0.8rem !important;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 800px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #10b981;
            opacity: 0.7;
        }
        
        .empty-state h4 {
            color: #475569;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Lab Results Card */
        .lab-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .lab-patient-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #8b5cf6;
        }
        
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .patient-name {
            font-weight: bold;
            color: #1e293b;
            font-size: 1.1rem;
        }
        
        .test-count {
            background: #8b5cf6;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .tests-summary {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .test-item {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .test-item:last-child {
            border-bottom: none;
        }

        /* Payments Management */
        .payments-management {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .payment-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .payment-amounts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .amount-display {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 3px solid #10b981;
        }
        
        .amount-label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 5px;
        }
        
        .amount-value {
            font-weight: bold;
            color: #059669;
            font-size: 1.1rem;
        }
        
        .payment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Consultation Fee Display */
        .consultation-fee-display {
            background: #fffbeb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #f59e0b;
            text-align: center;
        }
        
        .consultation-fee-label {
            font-size: 0.9rem;
            color: #92400e;
            margin-bottom: 5px;
        }
        
        .consultation-fee-value {
            font-weight: bold;
            color: #d97706;
            font-size: 1.3rem;
        }

        /* MOBILE RESPONSIVE DESIGN */
        @media (max-width: 768px) {
            .topbar {
                padding: 12px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .logo-section {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            
            .user-area {
                width: 100%;
                justify-content: space-between;
            }
            
            .main {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.8em;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-radius: 8px;
                margin-bottom: 5px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .card-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .medication-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .medication-actions {
                width: 100%;
            }
            
            .lab-test-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .test-actions {
                width: 100%;
            }
            
            .dispense-all-form {
                padding: 15px;
            }
            
            .delete-card-btn {
                position: relative;
                top: auto;
                right: auto;
                margin-bottom: 15px;
            }
            
            .payment-form-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-amounts {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .user-area {
                flex-direction: column;
                gap: 10px;
            }
            
            .time-display, .user, .logout-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/logo.jpg">
</head>
<body>

    <!-- Improved Alert System -->
    <?php if ($success_message): ?>
    <div class="alert-container">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <span><?php echo $success_message; ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert-container">
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> 
            <span><?php echo $error_message; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>PHARMACY DEPARTMENT</p>
            </div>
        </div>
        
        <div class="user-area">
            <div class="time-display">
                <i class="fas fa-clock"></i>
                <span id="currentTime"><?php echo $current_time; ?></span>
            </div>
            <div class="user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></div>
                    <small style="font-size: 0.75rem;">Pharmacist</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Quick Statistics -->
        <div class="stats-grid">
            <div class="stat-card patients">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_patients; ?></div>
                <div class="stat-label">Today's Patients</div>
            </div>
            <div class="stat-card total-rx">
                <div class="stat-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="stat-number"><?php echo $total_prescriptions; ?></div>
                <div class="stat-label">Total Prescriptions</div>
            </div>
            <div class="stat-card pending-rx">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_prescriptions; ?></div>
                <div class="stat-label">Pending Prescriptions</div>
            </div>
            <div class="stat-card completed-rx">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $completed_prescriptions; ?></div>
                <div class="stat-label">Dispensed Prescriptions</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($total_revenue, 0); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card pending-revenue">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($pending_revenue, 0); ?></div>
                <div class="stat-label">Pending Revenue</div>
            </div>
            <div class="stat-card today-revenue">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($today_revenue, 0); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('prescriptions')">
                    <i class="fas fa-prescription"></i>
                    Pending Prescriptions (<?php echo count($pending_patients); ?>)
                </button>
                <button class="tab" onclick="showTab('dispensed')">
                    <i class="fas fa-check-circle"></i>
                    Dispensed Prescriptions (<?php echo count($completed_patients); ?>)
                </button>
                <button class="tab" onclick="showTab('payments')">
                    <i class="fas fa-money-bill-wave"></i>
                    Payments Management (<?php echo count($all_payments); ?>)
                </button>
                <button class="tab" onclick="showTab('lab-results')">
                    <i class="fas fa-file-medical-alt"></i>
                    Laboratory Results (<?php echo count($lab_results_by_patient); ?>)
                </button>
                <button class="tab" onclick="showTab('patients')">
                    <i class="fas fa-user-injured"></i>
                    Patients
                </button>
                <button class="tab" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </button>
            </div>

            <!-- Pending Prescriptions Tab -->
            <div id="prescriptions-tab" class="tab-content active">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-prescription"></i> Pending Prescriptions - Doctor's Instructions
                </h3>
                
                <?php if (empty($pending_patients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>No Pending Prescriptions</h4>
                        <p>All prescriptions have been dispensed successfully.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($pending_patients as $patient): ?>
                        <div class="card prescription">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($patient['patient_name']); ?></div>
                                <div class="card-status status-pending">
                                    <?php echo $patient['prescription_count']; ?> Meds
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($patient['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Age/Gender:</span>
                                    <span class="info-value"><?php echo $patient['age'] . ' yrs / ' . ucfirst($patient['gender']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Prescribed by:</span>
                                    <span class="info-value">Dr. <?php echo htmlspecialchars($patient['doctor_name']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Consultation Fee Display -->
                            <div class="consultation-fee-display">
                                <div class="consultation-fee-label">Consultation Fee</div>
                                <div class="consultation-fee-value">TSh <?php echo number_format($patient['consultation_fee'], 2); ?></div>
                            </div>
                            
                            <?php if (!empty($patient['diagnosis'])): ?>
                            <div class="card-description">
                                <strong><i class="fas fa-stethoscope"></i> Doctor's Diagnosis:</strong><br>
                                <?php echo htmlspecialchars($patient['diagnosis']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($patient['symptoms'])): ?>
                            <div class="card-description">
                                <strong><i class="fas fa-notes-medical"></i> Reported Symptoms:</strong><br>
                                <?php echo htmlspecialchars($patient['symptoms']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Laboratory Results Section WITH PRICING -->
                            <?php if (!empty($patient['lab_tests'])): ?>
                            <div class="lab-results-section">
                                <h5><i class="fas fa-vial"></i> Laboratory Test Results - Set Prices</h5>
                                <?php foreach ($patient['lab_tests'] as $lab_test): ?>
                                <div class="lab-test-item">
                                    <div class="test-details">
                                        <div class="test-type"><?php echo htmlspecialchars($lab_test['test_type']); ?></div>
                                        <div class="test-results"><?php echo htmlspecialchars($lab_test['results']); ?></div>
                                    </div>
                                    <div class="test-actions">
                                        <div class="price-input">
                                            <span>Price (TSh):</span>
                                            <input type="number" name="lab_tests[<?php echo $lab_test['id']; ?>][price]" class="form-input lab-price-input-field" min="0" step="0.01" value="0" onchange="updateTotalAmount(<?php echo $patient['patient_id']; ?>)" style="width: 100px; padding: 8px;">
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Dispense All Form for Patient (INCLUDING LAB TESTS) -->
                            <div class="dispense-all-form">
                                <h4 style="margin-bottom: 15px; color: #1e293b;">
                                    <i class="fas fa-capsules"></i> Dispense All Medications & Lab Tests
                                </h4>
                                
                                <form method="POST" action="" id="dispenseForm_<?php echo $patient['patient_id']; ?>">
                                    <input type="hidden" name="patient_id" value="<?php echo $patient['patient_id']; ?>">
                                    
                                    <div class="medications-list">
                                        <strong style="display: block; margin-bottom: 15px; color: #1e293b; font-size: 1rem;">Prescribed Medications:</strong>
                                        <?php foreach ($patient['prescriptions'] as $prescription): ?>
                                        <div class="medication-item">
                                            <div class="medication-details">
                                                <div class="medication-name"><?php echo htmlspecialchars($prescription['medicine_name']); ?></div>
                                                <div class="medication-specs">
                                                    <strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?> | 
                                                    <strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?> | 
                                                    <strong>Duration:</strong> <?php echo htmlspecialchars($prescription['duration']); ?>
                                                    <?php if (!empty($prescription['instructions'])): ?>
                                                    | <strong>Instructions:</strong> <?php echo htmlspecialchars($prescription['instructions']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="medication-actions">
                                                <div class="availability-toggle">
                                                    <div class="toggle-option">
                                                        <input type="radio" id="available_yes_<?php echo $prescription['id']; ?>" name="prescriptions[<?php echo $prescription['id']; ?>][available]" value="yes" checked onchange="toggleAlternativeMedicine(<?php echo $prescription['id']; ?>)">
                                                        <label for="available_yes_<?php echo $prescription['id']; ?>">Available</label>
                                                    </div>
                                                    <div class="toggle-option">
                                                        <input type="radio" id="available_no_<?php echo $prescription['id']; ?>" name="prescriptions[<?php echo $prescription['id']; ?>][available]" value="no" onchange="toggleAlternativeMedicine(<?php echo $prescription['id']; ?>)">
                                                        <label for="available_no_<?php echo $prescription['id']; ?>">Not Available</label>
                                                    </div>
                                                </div>
                                                
                                                <!-- Alternative Medicine Input (Shows when not available) -->
                                                <div class="alternative-medicine" id="alternative_<?php echo $prescription['id']; ?>" style="display: none;">
                                                    <label>Alternative Medicine:</label>
                                                    <input type="text" name="prescriptions[<?php echo $prescription['id']; ?>][alternative]" class="form-input" placeholder="Enter alternative medicine name">
                                                </div>
                                                
                                                <div class="price-input">
                                                    <span>Price (TSh):</span>
                                                    <input type="number" name="prescriptions[<?php echo $prescription['id']; ?>][price]" class="form-input price-input-field" min="0" step="0.01" value="0" onchange="updateTotalAmount(<?php echo $patient['patient_id']; ?>)" style="width: 100px; padding: 8px;">
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="total-amount" id="totalAmount_<?php echo $patient['patient_id']; ?>">
                                        Total Amount: TSh 0.00
                                    </div>
                                    
                                    <div class="card-actions">
                                        <button type="submit" name="dispense_all_prescriptions" class="btn btn-success">
                                            <i class="fas fa-check-circle"></i> Process All (Meds & Lab Tests)
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="printAllPrescriptions(<?php echo $patient['patient_id']; ?>)">
                                            <i class="fas fa-print"></i> Print All
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dispensed Prescriptions Tab -->
            <div id="dispensed-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> Dispensed Prescriptions & Lab Tests - Edit Total Price
                </h3>
                
                <?php if (empty($completed_patients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription-bottle"></i>
                        <h4>No Dispensed Prescriptions</h4>
                        <p>No prescriptions have been dispensed yet.</p>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($completed_patients as $patient): 
                            $payment = $payment_details[$patient['patient_id']] ?? null;
                            $total_amount = $payment['amount'] ?? 0;
                            $medicine_amount = $payment['medicine_amount'] ?? 0;
                            $lab_amount = $payment['lab_amount'] ?? 0;
                            $consultation_fee = $patient['consultation_fee'] ?? 0;
                        ?>
                        <div class="card dispensed">
                            <!-- Delete Button -->
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="patient_id" value="<?php echo $patient['patient_id']; ?>">
                                <button type="submit" name="delete_dispensed_card" class="delete-card-btn" title="Remove from dispensed section" onclick="return confirm('Are you sure you want to remove this patient from dispensed section? This will move prescriptions back to pending.')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                            
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($patient['patient_name']); ?></div>
                                <div class="card-status status-dispensed">
                                    <?php echo $patient['prescription_count']; ?> Meds
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($patient['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Age/Gender:</span>
                                    <span class="info-value"><?php echo $patient['age'] . ' yrs / ' . ucfirst($patient['gender']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Prescribed by:</span>
                                    <span class="info-value">Dr. <?php echo htmlspecialchars($patient['doctor_name']); ?></span>
                                </div>
                                
                                <!-- Consultation Fee -->
                                <div class="info-row">
                                    <span class="info-label">Consultation Fee:</span>
                                    <span class="info-value price-display">TSh <?php echo number_format($consultation_fee, 2); ?></span>
                                </div>
                                
                                <?php if ($total_amount > 0): ?>
                                <div class="info-row">
                                    <span class="info-label">Medicine Amount:</span>
                                    <span class="info-value price-display">TSh <?php echo number_format($medicine_amount, 2); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Lab Amount:</span>
                                    <span class="info-value price-display">TSh <?php echo number_format($lab_amount, 2); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Total Amount:</span>
                                    <span class="info-value price-display">
                                        TSh <?php echo number_format($total_amount, 2); ?>
                                        <button class="edit-price-btn" onclick="toggleEditPriceForm(<?php echo $patient['patient_id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($patient['diagnosis'])): ?>
                            <div class="card-description">
                                <strong><i class="fas fa-stethoscope"></i> Doctor's Diagnosis:</strong><br>
                                <?php echo htmlspecialchars($patient['diagnosis']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($patient['symptoms'])): ?>
                            <div class="card-description">
                                <strong><i class="fas fa-notes-medical"></i> Reported Symptoms:</strong><br>
                                <?php echo htmlspecialchars($patient['symptoms']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Laboratory Results Section -->
                            <?php if (!empty($patient['lab_tests'])): ?>
                            <div class="lab-results-section">
                                <h5><i class="fas fa-vial"></i> Laboratory Test Results</h5>
                                <?php foreach ($patient['lab_tests'] as $lab_test): ?>
                                <div class="lab-test-item">
                                    <div class="test-details">
                                        <div class="test-type"><?php echo htmlspecialchars($lab_test['test_type']); ?></div>
                                        <div class="test-results"><?php echo htmlspecialchars($lab_test['results']); ?></div>
                                    </div>
                                    <div class="test-actions">
                                        <span style="color: #3b82f6; font-weight: bold; font-size: 0.9rem;">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </span>
                                        <?php if ($lab_test['lab_price'] > 0): ?>
                                        <div style="color: #059669; font-weight: bold;">
                                            Price: TSh <?php echo number_format($lab_test['lab_price'], 2); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="medications-list">
                                <strong style="display: block; margin-bottom: 15px; color: #1e293b; font-size: 1rem;">Dispensed Medications:</strong>
                                <?php foreach ($patient['prescriptions'] as $prescription): ?>
                                <div class="medication-item">
                                    <div class="medication-details">
                                        <?php if (!empty($prescription['alternative_medicine'])): ?>
                                        <div class="medication-name" style="color: #f59e0b;">
                                            <i class="fas fa-exchange-alt"></i> Alternative: <?php echo htmlspecialchars($prescription['alternative_medicine']); ?>
                                            <br><small style="color: #64748b;">Original: <?php echo htmlspecialchars($prescription['medicine_name']); ?></small>
                                        </div>
                                        <?php else: ?>
                                        <div class="medication-name"><?php echo htmlspecialchars($prescription['medicine_name']); ?></div>
                                        <?php endif; ?>
                                        <div class="medication-specs">
                                            <strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?> | 
                                            <strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?> | 
                                            <strong>Duration:</strong> <?php echo htmlspecialchars($prescription['duration']); ?>
                                            <?php if (!empty($prescription['instructions'])): ?>
                                            | <strong>Instructions:</strong> <?php echo htmlspecialchars($prescription['instructions']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="medication-actions">
                                        <span style="color: #059669; font-weight: bold; font-size: 0.9rem;">
                                            <i class="fas fa-check-circle"></i> Dispensed
                                        </span>
                                        <?php if ($prescription['medicine_price'] > 0): ?>
                                        <div style="color: #059669; font-weight: bold;">
                                            Price: TSh <?php echo number_format($prescription['medicine_price'], 2); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <!-- Edit Total Price Form (Hidden by default) -->
                                <div id="edit_price_form_<?php echo $patient['patient_id']; ?>" class="edit-price-form" style="display: none;">
                                    <form method="POST" action="">
                                        <input type="hidden" name="patient_id" value="<?php echo $patient['patient_id']; ?>">
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label class="form-label">Medicine Amount (TSh)</label>
                                                <input type="number" name="new_medicine_amount" class="form-input" min="0" step="0.01" value="<?php echo $medicine_amount; ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Lab Amount (TSh)</label>
                                                <input type="number" name="new_lab_amount" class="form-input" min="0" step="0.01" value="<?php echo $lab_amount; ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Reason for Edit</label>
                                            <input type="text" name="reason" class="form-input" placeholder="Reason for price change">
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <button type="submit" name="edit_total_price" class="btn btn-warning">
                                                <i class="fas fa-save"></i> Update Total Price
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="toggleEditPriceForm(<?php echo $patient['patient_id']; ?>)">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card-actions">
                                <button class="btn btn-primary" onclick="printAllPrescriptions(<?php echo $patient['patient_id']; ?>)">
                                    <i class="fas fa-print"></i> Print All
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payments Management Tab -->
            <div id="payments-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-money-bill-wave"></i> Payments Management
                </h3>
                
                <!-- Payments List -->
                <div class="table-card">
                    <h3><i class="fas fa-list"></i> All Payments Records</h3>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="paymentsTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Medicine Amount</th>
                                    <th>Lab Amount</th>
                                    <th>Consultation Fee</th>
                                    <th>Total Amount</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Processed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_payments as $payment): ?>
                                <tr>
                                    <td><strong>#<?php echo $payment['id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['patient_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($payment['card_no']); ?></small>
                                    </td>
                                    <td>TSh <?php echo number_format($payment['medicine_amount'], 2); ?></td>
                                    <td>TSh <?php echo number_format($payment['lab_amount'], 2); ?></td>
                                    <td>TSh <?php echo number_format($payment['consultation_fee'], 2); ?></td>
                                    <td><strong>TSh <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $payment['status'] == 'paid' ? 'badge-success' : ($payment['status'] == 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <small>User #<?php echo $payment['processed_by']; ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Laboratory Results Tab -->
            <div id="lab-results-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-file-medical-alt"></i> Laboratory Results by Patient
                </h3>
                
                <?php if (empty($lab_results_by_patient)): ?>
                    <div class="empty-state">
                        <i class="fas fa-vial"></i>
                        <h4>No Laboratory Results</h4>
                        <p>Laboratory results will appear here once they are completed.</p>
                    </div>
                <?php else: ?>
                    <div class="lab-results-grid">
                        <?php foreach ($lab_results_by_patient as $patient): ?>
                        <div class="lab-patient-card">
                            <div class="patient-header">
                                <div class="patient-name"><?php echo htmlspecialchars($patient['patient_name']); ?></div>
                                <div class="test-count"><?php echo $patient['test_count']; ?> Tests</div>
                            </div>
                            
                            <div class="card-info">
                                <div class="info-row">
                                    <span class="info-label">Card No:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($patient['card_no']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Age/Gender:</span>
                                    <span class="info-value"><?php echo $patient['age'] . ' yrs / ' . ucfirst($patient['gender']); ?></span>
                                </div>
                            </div>
                            
                            <div class="tests-summary">
                                <strong>Laboratory Tests Summary:</strong>
                                <?php foreach ($patient['lab_details'] as $test): ?>
                                    <div class="test-item">
                                        <strong><?php echo htmlspecialchars($test['test_type']); ?>:</strong> 
                                        <?php echo htmlspecialchars($test['results']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Patients Tab -->
            <div id="patients-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-user-injured"></i> Recent Patients
                </h3>
                
                <?php if (empty($recent_patients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Patients Found</h4>
                        <p>No patients are registered in the system.</p>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="patientsTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Card No</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Consultation Fee</th>
                                        <th>Prescriptions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_patients as $patient): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($patient['card_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                        <td><?php echo $patient['age'] ?: 'N/A'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $patient['gender'] == 'male' ? 'badge-info' : 'badge-warning'; ?>">
                                                <?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $patient['phone'] ?: 'N/A'; ?></td>
                                        <td>
                                            <strong>TSh <?php echo number_format($patient['consultation_fee'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $patient['prescription_count']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Settings Tab -->
            <div id="profile-tab" class="tab-content">
                <h3 style="color: #1e293b; margin-bottom: 20px;">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </h3>
                
                <div class="form-grid">
                    <div class="form-card">
                        <h4><i class="fas fa-user-edit"></i> Personal Information</h4>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-input" value="<?php echo $current_full_name; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" value="<?php echo $current_email; ?>" placeholder="Enter email address">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" value="<?php echo $current_phone; ?>" placeholder="Enter phone number">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <div class="form-card">
                        <h4><i class="fas fa-key"></i> Change Password</h4>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-input" placeholder="Enter current password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-input" placeholder="Enter new password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#patientsTable').DataTable({
                responsive: true,
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                pageLength: 10,
                language: {
                    search: "Search patients:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });

            $('#paymentsTable').DataTable({
                responsive: true,
                paging: true,
                searching: true,
                info: true,
                ordering: true,
                pageLength: 10,
                language: {
                    search: "Search payments:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                order: [[0, 'desc']] // Sort by ID descending
            });
        });

        // Function to show tabs
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Function to toggle alternative medicine input
        function toggleAlternativeMedicine(prescriptionId) {
            const availableRadio = document.querySelector('input[name="prescriptions[' + prescriptionId + '][available]"][value="no"]');
            const alternativeDiv = document.getElementById('alternative_' + prescriptionId);
            
            if (availableRadio.checked) {
                alternativeDiv.style.display = 'block';
            } else {
                alternativeDiv.style.display = 'none';
            }
        }

        // Function to update total amount for a patient (INCLUDING LAB TESTS AND CONSULTATION FEE)
        function updateTotalAmount(patientId) {
            const form = document.getElementById('dispenseForm_' + patientId);
            let medicineTotal = 0;
            let labTotal = 0;
            
            // Add medicine prices
            const priceInputs = form.querySelectorAll('.price-input-field');
            priceInputs.forEach(input => {
                const prescriptionId = input.name.match(/\[(\d+)\]/)[1];
                const availableRadio = form.querySelector('input[name="prescriptions[' + prescriptionId + '][available]"][value="yes"]');
                
                if (availableRadio.checked) {
                    medicineTotal += parseFloat(input.value) || 0;
                }
            });
            
            // Add lab test prices
            const labPriceInputs = form.querySelectorAll('.lab-price-input-field');
            labPriceInputs.forEach(input => {
                labTotal += parseFloat(input.value) || 0;
            });
            
            // Get consultation fee from the display element
            const consultationFeeElement = form.closest('.card').querySelector('.consultation-fee-value');
            let consultationFee = 0;
            if (consultationFeeElement) {
                consultationFee = parseFloat(consultationFeeElement.textContent.replace('TSh', '').replace(',', '')) || 0;
            }
            
            const totalAmount = medicineTotal + labTotal + consultationFee;
            const totalAmountElement = document.getElementById('totalAmount_' + patientId);
            totalAmountElement.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: center;">
                    <div>
                        <small>Consultation Fee</small><br>
                        <strong>TSh ${consultationFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                    </div>
                    <div>
                        <small>Medicine Total</small><br>
                        <strong>TSh ${medicineTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                    </div>
                    <div>
                        <small>Lab Total</small><br>
                        <strong>TSh ${labTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                    </div>
                </div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 2px solid #10b981;">
                    <strong>Grand Total: TSh ${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                </div>
            `;
        }

        // Function to toggle edit price form
        function toggleEditPriceForm(patientId) {
            const form = document.getElementById('edit_price_form_' + patientId);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        // Function to print all prescriptions for a patient
        function printAllPrescriptions(patientId) {
            window.open('print_patient_prescriptions.php?patient_id=' + patientId, '_blank');
        }

        // Auto-remove alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Update time every minute
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Initial time update
        updateTime();
        setInterval(updateTime, 60000);

        // Initialize total amounts on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($pending_patients as $patient): ?>
            updateTotalAmount(<?php echo $patient['patient_id']; ?>);
            <?php endforeach; ?>
        });
    </script>
</body>
</html>