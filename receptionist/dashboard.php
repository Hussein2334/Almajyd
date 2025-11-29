<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Receptionist role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'receptionist') {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Add Patient
if (isset($_POST['add_patient'])) {
    $card_no = $_POST['card_no'];
    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $weight = $_POST['weight'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $patient_type = $_POST['patient_type'];
    $consultation_fee = $_POST['consultation_fee'];
    
    try {
        // Check if card number already exists
        $check_stmt = $pdo->prepare("SELECT id FROM patients WHERE card_no = ?");
        $check_stmt->execute([$card_no]);
        
        if ($check_stmt->fetch()) {
            $error_message = "Patient with this card number already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO patients (card_no, full_name, age, gender, weight, phone, address, patient_type, consultation_fee, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$card_no, $full_name, $age, $gender, $weight, $phone, $address, $patient_type, $consultation_fee, $_SESSION['user_id']]);
            
            $success_message = "Patient added successfully!" . ($consultation_fee > 0 ? " Consultation fee: TSh " . number_format($consultation_fee, 2) : "");
        }
    } catch (PDOException $e) {
        $error_message = "Error adding patient: " . $e->getMessage();
    }
}

// Handle Update Patient
if (isset($_POST['update_patient'])) {
    $patient_id = $_POST['patient_id'];
    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $weight = $_POST['weight'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $patient_type = $_POST['patient_type'];
    $consultation_fee = $_POST['consultation_fee'];
    
    try {
        $stmt = $pdo->prepare("UPDATE patients SET full_name = ?, age = ?, gender = ?, weight = ?, phone = ?, address = ?, patient_type = ?, consultation_fee = ? WHERE id = ?");
        $stmt->execute([$full_name, $age, $gender, $weight, $phone, $address, $patient_type, $consultation_fee, $patient_id]);
        
        $success_message = "Patient information updated successfully!" . ($consultation_fee > 0 ? " Consultation fee updated to: TSh " . number_format($consultation_fee, 2) : "");
    } catch (PDOException $e) {
        $error_message = "Error updating patient: " . $e->getMessage();
    }
}

// Handle Delete Patient
if (isset($_POST['delete_patient'])) {
    $patient_id = $_POST['patient_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // 1. First check if patient exists
        $check_patient_stmt = $pdo->prepare("SELECT id, card_no, full_name FROM patients WHERE id = ?");
        $check_patient_stmt->execute([$patient_id]);
        $patient = $check_patient_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            throw new Exception("Patient not found!");
        }
        
        // 2. Delete related records
        $delete_checking_forms_stmt = $pdo->prepare("DELETE FROM checking_forms WHERE patient_id = ?");
        $delete_checking_forms_stmt->execute([$patient_id]);
        
        $delete_payments_stmt = $pdo->prepare("DELETE FROM payments WHERE patient_id = ?");
        $delete_payments_stmt->execute([$patient_id]);
        
        // 3. Finally delete the patient
        $delete_patient_stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
        $delete_patient_stmt->execute([$patient_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Patient '{$patient['full_name']}' (Card: {$patient['card_no']}) deleted successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error_message = "Error deleting patient: " . $e->getMessage();
    }
}

// Handle Consultation Fee Settings
if (isset($_POST['update_fee_settings'])) {
    $standard_fee = $_POST['standard_fee'];
    $child_fee = $_POST['child_fee'];
    $senior_fee = $_POST['senior_fee'];
    $emergency_fee = $_POST['emergency_fee'];
    $follow_up_fee = $_POST['follow_up_fee'];
    
    try {
        $settings = [
            'consultation_fee_standard' => $standard_fee,
            'consultation_fee_child' => $child_fee,
            'consultation_fee_senior' => $senior_fee,
            'consultation_fee_emergency' => $emergency_fee,
            'consultation_fee_follow_up' => $follow_up_fee
        ];
        
        foreach ($settings as $key => $value) {
            $check_stmt = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
            $check_stmt->execute([$key]);
            
            if ($check_stmt->fetch()) {
                $update_stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $update_stmt->execute([$value, $key]);
            } else {
                $insert_stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
                $insert_stmt->execute([$key, $value]);
            }
        }
        
        $success_message = "Consultation fee settings updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating fee settings: " . $e->getMessage();
    }
}

// Handle Update Profile
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
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

// Get all patients for display
$patients_stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
$patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient count for today
$today = date('Y-m-d');
$today_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = ?");
$today_count_stmt->execute([$today]);
$today_count = $today_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total patient count
$total_count_stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
$total_count = $total_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total consultation fees pending
$pending_fees_stmt = $pdo->query("SELECT COALESCE(SUM(consultation_fee), 0) as total FROM patients WHERE consultation_fee > 0");
$pending_fees = $pending_fees_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get consultation fee settings
$fee_settings = [];
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'consultation_fee_%'");
$settings_data = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($settings_data as $setting) {
    $fee_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Set default fees if not exists
$default_fees = [
    'consultation_fee_standard' => 10000,
    'consultation_fee_child' => 5000,
    'consultation_fee_senior' => 7000,
    'consultation_fee_emergency' => 15000,
    'consultation_fee_follow_up' => 8000
];

foreach ($default_fees as $key => $default_value) {
    if (!isset($fee_settings[$key])) {
        $fee_settings[$key] = $default_value;
    }
}

// Get current user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Almajyd Dispensary</title>
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
        
        /* Topbar */
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            color: #1e293b;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Stats Grid */
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
        
        .stat-card.total { border-left-color: #3b82f6; }
        .stat-card.today { border-left-color: #10b981; }
        .stat-card.fees { border-left-color: #f59e0b; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.total .stat-icon { color: #3b82f6; }
        .stat-card.today .stat-icon { color: #10b981; }
        .stat-card.fees .stat-icon { color: #f59e0b; }
        
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

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Forms Grid */
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .form-card h3 {
            margin-bottom: 20px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }

        /* Form Styles */
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
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
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
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
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
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            font-size: 0.8rem;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-card h3 {
            margin-bottom: 20px;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }

        /* Simple Table Styling */
        .simple-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .simple-table th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.85rem;
        }
        
        .simple-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        
        .simple-table tr:hover {
            background: #f8fafc;
        }
        
        .simple-table tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-purple { background: #f3e8ff; color: #7c3aed; }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 800px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        /* Clickable Process Steps with ACTIONS */
        .process-steps {
            display: flex;
            justify-content: center;
            margin: 30px 0;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .process-step {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            border: 2px solid transparent;
            min-width: 180px;
            position: relative;
        }
        
        .process-step:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .process-step.active {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .step-number {
            position: absolute;
            top: -12px;
            left: -12px;
            width: 30px;
            height: 30px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        
        .step-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #10b981;
        }
        
        .step-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .step-desc {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.4;
        }

        /* Consultation Fee Settings */
        .fee-settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .fee-setting-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f59e0b;
        }
        
        .fee-type {
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .fee-amount {
            font-size: 1.1rem;
            font-weight: bold;
            color: #059669;
        }

        /* Delete Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .modal h3 {
            margin-bottom: 15px;
            color: #1e293b;
        }
        
        .modal p {
            margin-bottom: 20px;
            color: #64748b;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .forms-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 15px;
            }
            
            .table-card {
                padding: 15px;
            }
            
            .process-steps {
                flex-direction: column;
                align-items: center;
            }
            
            .process-step {
                width: 100%;
                max-width: 300px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .fee-settings-grid {
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
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link rel="icon" href="../images/logo.jpg">
</head>
<body>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>TOMONDO - ZANZIBAR</p>
            </div>
        </div>
        
        <div class="user-area">
            <div class="time-display">
                <i class="fas fa-clock"></i>
                <span id="currentTime"><?php echo date('h:i A'); ?></span>
            </div>
            <div class="user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></div>
                    <small style="font-size: 0.75rem;">Receptionist</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Receptionist Dashboard
                </h1>
                <p style="color: #64748b; margin-top: 5px;">Manage patient registration and consultation fees</p>
            </div>
            <div class="page-actions">
                <button onclick="refreshPage()" class="btn btn-warning">
                    <i class="fas fa-sync"></i>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Clickable Process Steps with ACTIONS -->
        <div class="process-steps">
            <div class="process-step active" onclick="showUserManagement()">
                <div class="step-number">1</div>
                <div class="step-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="step-title">Patient Management</div>
                <div class="step-desc">Add & Update Patients with Consultation Fees</div>
            </div>
            <div class="process-step" onclick="showFeeSettings()">
                <div class="step-number">2</div>
                <div class="step-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="step-title">Fee Settings</div>
                <div class="step-desc">Manage Consultation Fee Rates</div>
            </div>
            <div class="process-step" onclick="showPersonalSettings()">
                <div class="step-number">3</div>
                <div class="step-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="step-title">Personal Settings</div>
                <div class="step-desc">Update Your Personal Information</div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_count; ?></div>
                <div class="stat-label">Today's Registrations</div>
            </div>
            <div class="stat-card fees">
                <div class="stat-icon">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-number">TSh <?php echo number_format($pending_fees, 0); ?></div>
                <div class="stat-label">Pending Consultation Fees</div>
            </div>
        </div>

        <!-- User Management Section (Step 1) -->
        <div id="userManagementSection">
            <!-- Forms Grid -->
            <div class="forms-grid">
                <!-- Add Patient Form -->
                <div class="form-card">
                    <h3><i class="fas fa-user-plus"></i> Add New Patient</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Card Number *</label>
                            <input type="text" name="card_no" class="form-input" placeholder="e.g., PT2024-0001" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-input" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-input" placeholder="Enter age" min="0" max="120">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-input" placeholder="Enter weight in kg" step="0.1" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" placeholder="Enter phone number">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-input" placeholder="Enter address" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Patient Type *</label>
                            <select name="patient_type" class="form-select" id="patientType" onchange="updateConsultationFee()" required>
                                <option value="">Select Patient Type</option>
                                <option value="standard">Standard</option>
                                <option value="child">Child (Under 12)</option>
                                <option value="senior">Senior (Over 60)</option>
                                <option value="emergency">Emergency</option>
                                <option value="follow_up">Follow-up</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (TSh) *</label>
                            <input type="number" name="consultation_fee" class="form-input" id="consultationFee" 
                                   placeholder="Enter consultation fee" min="0" step="100" required>
                            <small style="color: #64748b; font-size: 0.8rem;">Based on patient type. You can adjust if needed.</small>
                        </div>
                        
                        <button type="submit" name="add_patient" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Patient & Set Fee
                        </button>
                    </form>
                </div>

                <!-- Update Patient Form -->
                <div class="form-card">
                    <h3><i class="fas fa-user-edit"></i> Update Patient Information</h3>
                    <form method="POST" action="" id="updateForm">
                        <div class="form-group">
                            <label class="form-label">Select Patient *</label>
                            <select name="patient_id" class="form-select" id="patientSelect" required onchange="loadPatientData()">
                                <option value="">Select a patient to update</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['card_no']) . ' - ' . htmlspecialchars($patient['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-input" id="updateFullName" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-input" id="updateAge" min="0" max="120">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" id="updateGender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-input" id="updateWeight" step="0.1" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" id="updatePhone">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-input" id="updateAddress" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Patient Type *</label>
                            <select name="patient_type" class="form-select" id="updatePatientType" required onchange="updateConsultationFeeForUpdate()">
                                <option value="">Select Patient Type</option>
                                <option value="standard">Standard</option>
                                <option value="child">Child (Under 12)</option>
                                <option value="senior">Senior (Over 60)</option>
                                <option value="emergency">Emergency</option>
                                <option value="follow_up">Follow-up</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (TSh) *</label>
                            <input type="number" name="consultation_fee" class="form-input" id="updateConsultationFee" 
                                   placeholder="Enter consultation fee" min="0" step="100" required>
                            <small style="color: #64748b; font-size: 0.8rem;">Based on patient type. You can adjust if needed.</small>
                        </div>
                        
                        <button type="submit" name="update_patient" class="btn btn-primary">
                            <i class="fas fa-sync"></i> Update Patient & Fee
                        </button>
                    </form>
                </div>
            </div>

            <!-- Patients Table -->
            <div class="table-card">
                <h3>
                    <i class="fas fa-list"></i>
                    All Patients
                    <small style="font-size: 0.9rem; color: #64748b; font-weight: normal;">
                        (Total: <?php echo $total_count; ?> patients)
                    </small>
                </h3>

                <div class="table-responsive">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Card No</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Type</th>
                                <th>Consultation Fee</th>
                                <th>Phone</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 20px; color: #64748b;">
                                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                    No patients found. Add your first patient above.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($patient['card_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                    <td><?php echo $patient['age'] ?: 'N/A'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $patient['gender'] == 'male' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo ($patient['patient_type'] ?? 'standard') == 'standard' ? 'badge-success' : 
                                                 (($patient['patient_type'] ?? 'standard') == 'child' ? 'badge-info' : 
                                                 (($patient['patient_type'] ?? 'standard') == 'senior' ? 'badge-warning' : 
                                                 (($patient['patient_type'] ?? 'standard') == 'emergency' ? 'badge-danger' : 'badge-purple'))); 
                                        ?>">
                                            <?php echo isset($patient['patient_type']) ? ucfirst(str_replace('_', ' ', $patient['patient_type'])) : 'Standard'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: #059669;">TSh <?php echo number_format($patient['consultation_fee'] ?? 0, 2); ?></strong>
                                    </td>
                                    <td><?php echo $patient['phone'] ?: 'N/A'; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-edit" onclick="editPatient(<?php echo $patient['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button type="button" class="btn-delete" onclick="confirmDelete(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['full_name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Fee Settings Section (Step 2) -->
        <div id="feeSettingsSection" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-money-bill-wave"></i> Consultation Fee Settings</h3>
                
                <div class="fee-settings-grid">
                    <div class="fee-setting-card">
                        <div class="fee-type">Standard Consultation</div>
                        <div class="fee-amount">TSh <?php echo number_format($fee_settings['consultation_fee_standard'], 2); ?></div>
                        <small>Regular patient consultation</small>
                    </div>
                    
                    <div class="fee-setting-card">
                        <div class="fee-type">Child Consultation (Under 12)</div>
                        <div class="fee-amount">TSh <?php echo number_format($fee_settings['consultation_fee_child'], 2); ?></div>
                        <small>Patients under 12 years</small>
                    </div>
                    
                    <div class="fee-setting-card">
                        <div class="fee-type">Senior Consultation (Over 60)</div>
                        <div class="fee-amount">TSh <?php echo number_format($fee_settings['consultation_fee_senior'], 2); ?></div>
                        <small>Patients over 60 years</small>
                    </div>
                    
                    <div class="fee-setting-card">
                        <div class="fee-type">Emergency Consultation</div>
                        <div class="fee-amount">TSh <?php echo number_format($fee_settings['consultation_fee_emergency'], 2); ?></div>
                        <small>Emergency cases</small>
                    </div>
                    
                    <div class="fee-setting-card">
                        <div class="fee-type">Follow-up Consultation</div>
                        <div class="fee-amount">TSh <?php echo number_format($fee_settings['consultation_fee_follow_up'], 2); ?></div>
                        <small>Follow-up visits</small>
                    </div>
                </div>

                <form method="POST" action="">
                    <h4 style="margin: 25px 0 15px 0; color: #374151; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                        <i class="fas fa-cog"></i> Update Fee Rates
                    </h4>
                    
                    <div class="forms-grid">
                        <div class="form-group">
                            <label class="form-label">Standard Fee (TSh)</label>
                            <input type="number" name="standard_fee" class="form-input" value="<?php echo $fee_settings['consultation_fee_standard']; ?>" min="0" step="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Child Fee (TSh)</label>
                            <input type="number" name="child_fee" class="form-input" value="<?php echo $fee_settings['consultation_fee_child']; ?>" min="0" step="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Senior Fee (TSh)</label>
                            <input type="number" name="senior_fee" class="form-input" value="<?php echo $fee_settings['consultation_fee_senior']; ?>" min="0" step="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Emergency Fee (TSh)</label>
                            <input type="number" name="emergency_fee" class="form-input" value="<?php echo $fee_settings['consultation_fee_emergency']; ?>" min="0" step="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Follow-up Fee (TSh)</label>
                            <input type="number" name="follow_up_fee" class="form-input" value="<?php echo $fee_settings['consultation_fee_follow_up']; ?>" min="0" step="100" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_fee_settings" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Fee Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Personal Settings Section (Step 3) -->
        <div id="personalSettingsSection" style="display: none;">
            <div class="forms-grid">
                <div class="form-card">
                    <h3><i class="fas fa-user-edit"></i> Update Personal Information</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" placeholder="Enter email address">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" placeholder="Enter phone number">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" placeholder="Enter current password to change password">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Enter new password">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-sync"></i> Update Profile
                        </button>
                    </form>
                </div>
                
                <div class="form-card">
                    <h3><i class="fas fa-chart-bar"></i> Activity Summary</h3>
                    <div style="text-align: center; padding: 20px;">
                        <div class="stat-card today" style="margin-bottom: 15px;">
                            <div class="stat-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-number"><?php echo $today_count; ?></div>
                            <div class="stat-label">Patients Registered Today</div>
                        </div>
                        <div class="stat-card total" style="margin-bottom: 15px;">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $total_count; ?></div>
                            <div class="stat-label">Total Patients Registered</div>
                        </div>
                        <div class="stat-card fees">
                            <div class="stat-icon">
                                <i class="fas fa-money-bill"></i>
                            </div>
                            <div class="stat-number">TSh <?php echo number_format($pending_fees, 0); ?></div>
                            <div class="stat-label">Pending Consultation Fees</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Delete</h3>
            <p>Are you sure you want to delete patient: <strong id="deletePatientName"></strong>?</p>
            <p style="font-size: 0.8rem; color: #ef4444;">This action cannot be undone!</p>
            <div class="modal-actions">
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="patient_id" id="deletePatientId">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="delete_patient" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Consultation fee rates
        const feeRates = {
            'standard': <?php echo $fee_settings['consultation_fee_standard']; ?>,
            'child': <?php echo $fee_settings['consultation_fee_child']; ?>,
            'senior': <?php echo $fee_settings['consultation_fee_senior']; ?>,
            'emergency': <?php echo $fee_settings['consultation_fee_emergency']; ?>,
            'follow_up': <?php echo $fee_settings['consultation_fee_follow_up']; ?>
        };

        // Update consultation fee based on patient type (for Add form)
        function updateConsultationFee() {
            const patientType = document.getElementById('patientType').value;
            const consultationFeeInput = document.getElementById('consultationFee');
            
            if (patientType && feeRates[patientType]) {
                consultationFeeInput.value = feeRates[patientType];
            } else {
                consultationFeeInput.value = '';
            }
        }

        // Update consultation fee based on patient type (for Update form)
        function updateConsultationFeeForUpdate() {
            const patientType = document.getElementById('updatePatientType').value;
            const consultationFeeInput = document.getElementById('updateConsultationFee');
            
            if (patientType && feeRates[patientType]) {
                consultationFeeInput.value = feeRates[patientType];
            } else {
                consultationFeeInput.value = '';
            }
        }

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

        updateTime();
        setInterval(updateTime, 60000);

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);

        // Refresh page
        function refreshPage() {
            location.reload();
        }

        // Load patient data for update form
        function loadPatientData() {
            const patientSelect = document.getElementById('patientSelect');
            const patientId = patientSelect.value;
            
            if (!patientId) {
                // Clear form if no patient selected
                document.getElementById('updateFullName').value = '';
                document.getElementById('updateAge').value = '';
                document.getElementById('updateGender').value = '';
                document.getElementById('updateWeight').value = '';
                document.getElementById('updatePhone').value = '';
                document.getElementById('updateAddress').value = '';
                document.getElementById('updatePatientType').value = '';
                document.getElementById('updateConsultationFee').value = '';
                return;
            }
            
            // In a real application, you would fetch this data via AJAX
            // For simplicity, we'll use the existing PHP data
            const patients = <?php echo json_encode($patients); ?>;
            const patient = patients.find(p => p.id == patientId);
            
            if (patient) {
                document.getElementById('updateFullName').value = patient.full_name || '';
                document.getElementById('updateAge').value = patient.age || '';
                document.getElementById('updateGender').value = patient.gender || '';
                document.getElementById('updateWeight').value = patient.weight || '';
                document.getElementById('updatePhone').value = patient.phone || '';
                document.getElementById('updateAddress').value = patient.address || '';
                document.getElementById('updatePatientType').value = patient.patient_type || 'standard';
                document.getElementById('updateConsultationFee').value = patient.consultation_fee || feeRates.standard;
            }
        }

        // Edit patient function
        function editPatient(patientId) {
            const patientSelect = document.getElementById('patientSelect');
            patientSelect.value = patientId;
            loadPatientData();
            
            // Scroll to update form
            document.getElementById('updateForm').scrollIntoView({ 
                behavior: 'smooth' 
            });
            
            // Highlight the form
            const formCard = document.querySelector('.form-card:nth-child(2)');
            formCard.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.3)';
            setTimeout(() => {
                formCard.style.boxShadow = '0 4px 20px rgba(0,0,0,0.08)';
            }, 2000);
        }

        // Delete confirmation modal
        function confirmDelete(patientId, patientName) {
            document.getElementById('deletePatientId').value = patientId;
            document.getElementById('deletePatientName').textContent = patientName;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Process Steps Navigation
        function showUserManagement() {
            document.getElementById('userManagementSection').style.display = 'block';
            document.getElementById('feeSettingsSection').style.display = 'none';
            document.getElementById('personalSettingsSection').style.display = 'none';
            
            // Update active step
            document.querySelectorAll('.process-step').forEach(step => {
                step.classList.remove('active');
            });
            document.querySelectorAll('.process-step')[0].classList.add('active');
        }

        function showFeeSettings() {
            document.getElementById('userManagementSection').style.display = 'none';
            document.getElementById('feeSettingsSection').style.display = 'block';
            document.getElementById('personalSettingsSection').style.display = 'none';
            
            // Update active step
            document.querySelectorAll('.process-step').forEach(step => {
                step.classList.remove('active');
            });
            document.querySelectorAll('.process-step')[1].classList.add('active');
        }

        function showPersonalSettings() {
            document.getElementById('userManagementSection').style.display = 'none';
            document.getElementById('feeSettingsSection').style.display = 'none';
            document.getElementById('personalSettingsSection').style.display = 'block';
            
            // Update active step
            document.querySelectorAll('.process-step').forEach(step => {
                step.classList.remove('active');
            });
            document.querySelectorAll('.process-step')[2].classList.add('active');
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const cardNo = this.querySelector('input[name="card_no"]');
                    if (cardNo && !cardNo.value.trim()) {
                        e.preventDefault();
                        alert('Please enter a card number!');
                        cardNo.focus();
                        return false;
                    }
                    
                    // Password confirmation validation
                    const newPassword = this.querySelector('input[name="new_password"]');
                    const confirmPassword = this.querySelector('input[name="confirm_password"]');
                    if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        newPassword.focus();
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>