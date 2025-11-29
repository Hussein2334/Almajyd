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

// Handle AJAX requests
if (isset($_POST['ajax_request'])) {
    header('Content-Type: application/json');
    
    // Simulate processing time
    sleep(1);
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Add New Prescription
        if ($_POST['action'] == 'add_prescription') {
            $checking_form_id = $_POST['checking_form_id'];
            $medicine_name = $_POST['medicine_name'];
            $dosage = $_POST['dosage'];
            $frequency = $_POST['frequency'];
            $duration = $_POST['duration'];
            $instructions = $_POST['instructions'];
            $medicine_price = $_POST['medicine_price'];
            
            $stmt = $pdo->prepare("INSERT INTO prescriptions (checking_form_id, medicine_name, dosage, frequency, duration, instructions, medicine_price, status, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, 'prescribed', 'yes')");
            $stmt->execute([$checking_form_id, $medicine_name, $dosage, $frequency, $duration, $instructions, $medicine_price]);
            
            $response['success'] = true;
            $response['message'] = "New prescription added successfully!";
        }
        
        // Update Medicine
        elseif ($_POST['action'] == 'update_medicine') {
            $prescription_id = $_POST['prescription_id'];
            $medicine_price = $_POST['medicine_price'];
            $is_available = $_POST['is_available'];
            $alternative_medicine = $_POST['alternative_medicine'];
            
            $stmt = $pdo->prepare("UPDATE prescriptions SET medicine_price = ?, is_available = ?, alternative_medicine = ? WHERE id = ?");
            $stmt->execute([$medicine_price, $is_available, $alternative_medicine, $prescription_id]);
            
            $response['success'] = true;
            $response['message'] = "Medicine information updated successfully!";
        }
        
        // Update Lab Price
        elseif ($_POST['action'] == 'update_lab_price') {
            $lab_test_id = $_POST['lab_test_id'];
            $lab_price = $_POST['lab_price'];
            
            $stmt = $pdo->prepare("UPDATE laboratory_tests SET lab_price = ? WHERE id = ?");
            $stmt->execute([$lab_price, $lab_test_id]);
            
            $response['success'] = true;
            $response['message'] = "Laboratory test price updated successfully!";
        }
        
        // Delete Prescription
        elseif ($_POST['action'] == 'delete_prescription') {
            $prescription_id = $_POST['prescription_id'];
            
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
            $stmt->execute([$prescription_id]);
            
            $response['success'] = true;
            $response['message'] = "Prescription deleted successfully!";
        }
        
    } catch (PDOException $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Handle Update Profile (non-AJAX)
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

// Get statistics for dashboard
$total_prescriptions = $pdo->query("SELECT COUNT(*) as total FROM prescriptions")->fetch(PDO::FETCH_ASSOC)['total'];
$today_prescriptions = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];
$prescriptions_no_price = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE medicine_price = 0 OR medicine_price IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];
$lab_tests_no_price = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests WHERE lab_price = 0 OR lab_price IS NULL")->fetch(PDO::FETCH_ASSOC)['total'];
$unavailable_medicines = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE is_available = 'no'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get all prescriptions with patient and doctor info
$prescriptions_stmt = $pdo->prepare("SELECT p.*, 
                                   pat.full_name as patient_name, 
                                   pat.card_no,
                                   pat.phone as patient_phone,
                                   doc.full_name as doctor_name,
                                   cf.symptoms,
                                   cf.diagnosis
                                   FROM prescriptions p 
                                   JOIN checking_forms cf ON p.checking_form_id = cf.id 
                                   JOIN patients pat ON cf.patient_id = pat.id 
                                   JOIN users doc ON cf.doctor_id = doc.id 
                                   ORDER BY p.created_at DESC");
$prescriptions_stmt->execute();
$all_prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get laboratory test results
$lab_results_stmt = $pdo->prepare("SELECT lt.*, 
                                 p.full_name as patient_name, 
                                 p.card_no,
                                 cf.symptoms, 
                                 cf.diagnosis,
                                 doc.full_name as doctor_name
                                 FROM laboratory_tests lt 
                                 JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                                 JOIN patients p ON cf.patient_id = p.id 
                                 JOIN users doc ON cf.doctor_id = doc.id 
                                 WHERE lt.status = 'completed'
                                 ORDER BY lt.updated_at DESC");
$lab_results_stmt->execute();
$lab_results = $lab_results_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get checking forms for adding prescriptions
$checking_forms = $pdo->query("SELECT cf.*, p.full_name as patient_name, p.card_no 
                              FROM checking_forms cf 
                              JOIN patients p ON cf.patient_id = p.id 
                              ORDER BY cf.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get all patients for patient records
$patients_stmt = $pdo->prepare("SELECT p.*, 
                               COUNT(DISTINCT pr.id) as prescription_count,
                               COUNT(DISTINCT lt.id) as lab_test_count,
                               MAX(cf.created_at) as last_visit
                               FROM patients p 
                               LEFT JOIN checking_forms cf ON p.id = cf.patient_id 
                               LEFT JOIN prescriptions pr ON cf.id = pr.checking_form_id 
                               LEFT JOIN laboratory_tests lt ON cf.id = lt.checking_form_id 
                               GROUP BY p.id 
                               ORDER BY p.created_at DESC");
$patients_stmt->execute();
$all_patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get current date and time
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - Almajyd Dispensary</title>
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
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
            gap: 20px;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #10b981;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .loading-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            display: none;
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

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 160px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-card.prescriptions { border-left-color: #10b981; }
        .stat-card.today { border-left-color: #3b82f6; }
        .stat-card.no-price { border-left-color: #f59e0b; }
        .stat-card.unavailable { border-left-color: #ef4444; }
        .stat-card.lab-tests { border-left-color: #8b5cf6; }
        
        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 12px;
        }
        
        .stat-card.prescriptions .stat-icon { color: #10b981; }
        .stat-card.today .stat-icon { color: #3b82f6; }
        .stat-card.no-price .stat-icon { color: #f59e0b; }
        .stat-card.unavailable .stat-icon { color: #ef4444; }
        .stat-card.lab-tests .stat-icon { color: #8b5cf6; }
        
        .stat-number {
            font-size: 2.4em;
            font-weight: bold;
            margin: 8px 0;
            color: #1e293b;
            line-height: 1;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9em;
            font-weight: 500;
            line-height: 1.3;
        }

        /* Content Tabs */
        .tabs-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 25px;
            background: none;
            border: none;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab.active {
            color: #10b981;
            border-bottom-color: #10b981;
        }
        
        .tab:hover {
            color: #10b981;
            background: #f0fdf4;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        /* Patient Records Styles */
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .patient-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #10b981;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .patient-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1.1rem;
        }
        
        .patient-card-no {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .patient-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #10b981;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
        }
        
        .patient-profile {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #10b981;
        }
        
        .section-title {
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .print-section {
            background: #f0fdf4;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #10b981;
            margin-top: 30px;
            text-align: center;
        }
        
        .print-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .print-ready {
            background: #d1fae5;
            color: #065f46;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .print-not-ready {
            background: #fef3c7;
            color: #92400e;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-print-patient {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-print-patient:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .visit-timeline {
            margin-top: 30px;
        }
        
        .timeline-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .timeline-date {
            font-weight: 600;
            color: #1e293b;
        }
        
        .timeline-doctor {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .timeline-diagnosis {
            color: #374151;
            margin-bottom: 10px;
        }
        
        .timeline-stats {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
        }

        /* [STYLES ZINGINE ZA AWALI ZINABAKI ZILE ZILE...] */
        .add-prescription-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 4px solid #10b981;
        }
        
        .prescription-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .prescription-title {
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
        }
        
        .prescription-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .prescription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 600;
        }
        
        .medicine-form {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
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
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        .btn {
            padding: 10px 20px;
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
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
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
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #dbeafe; color: #1e40af; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #9ca3af;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .patients-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-info {
                grid-template-columns: 1fr;
            }
            
            .print-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/logo.jpg">
</head>
<body>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Processing your request...</div>
    </div>

    <div class="topbar">
        <div class="logo-section">
            <div class="logo">
                <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo">
            </div>
            <div class="clinic-info">
                <h1>ALMAJYD DISPENSARY</h1>
                <p>PHARMACY DEPARTMENT - TOMONDO ZANZIBAR</p>
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
                    <small style="font-size: 0.75rem;">Pharmacy Department</small>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="main">

        <!-- Messages -->
        <div class="alert alert-success" id="successAlert">
            <i class="fas fa-check-circle"></i> <span id="successMessage"></span>
        </div>
        
        <div class="alert alert-error" id="errorAlert">
            <i class="fas fa-exclamation-circle"></i> <span id="errorMessage"></span>
        </div>

        <!-- Quick Statistics -->
        <div class="stats-grid">
            <div class="stat-card prescriptions">
                <div class="stat-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="stat-number"><?php echo $total_prescriptions; ?></div>
                <div class="stat-label">Total Prescriptions</div>
            </div>
            
            <div class="stat-card today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $today_prescriptions; ?></div>
                <div class="stat-label">Today's Prescriptions</div>
            </div>
            
            <div class="stat-card no-price">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo $prescriptions_no_price; ?></div>
                <div class="stat-label">Prescriptions Missing Prices</div>
            </div>
            
            <div class="stat-card unavailable">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $unavailable_medicines; ?></div>
                <div class="stat-label">Unavailable Medicines</div>
            </div>
            
            <div class="stat-card lab-tests">
                <div class="stat-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="stat-number"><?php echo $lab_tests_no_price; ?></div>
                <div class="stat-label">Lab Tests Missing Prices</div>
            </div>
        </div>

        <!-- Content Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('prescriptions')">
                    <i class="fas fa-prescription"></i> Prescriptions
                </button>
                <button class="tab" onclick="showTab('lab-results')">
                    <i class="fas fa-file-medical-alt"></i> Laboratory Results
                </button>
                <button class="tab" onclick="showTab('patient-records')">
                    <i class="fas fa-users"></i> Patient Records
                </button>
                <button class="tab" onclick="showTab('profile')">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </button>
            </div>

            <!-- Prescriptions Tab -->
            <div id="prescriptions" class="tab-content active">
                <h2 style="color:#10b981; margin-bottom: 20px;">
                    <i class="fas fa-prescription"></i> All Prescriptions
                </h2>
                
                <!-- Add New Prescription Form -->
                <div class="add-prescription-form">
                    <h3><i class="fas fa-plus-circle"></i> Add New Prescription</h3>
                    <form id="addPrescriptionForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Select Checking Form *</label>
                                <select name="checking_form_id" class="form-select" required>
                                    <option value="">Select Checking Form</option>
                                    <?php foreach ($checking_forms as $form): ?>
                                        <option value="<?php echo $form['id']; ?>">
                                            <?php echo htmlspecialchars('CF-' . $form['id'] . ' - ' . $form['patient_name'] . ' (' . $form['card_no'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Medicine Name *</label>
                                <input type="text" name="medicine_name" class="form-input" placeholder="Enter medicine name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Dosage</label>
                                <input type="text" name="dosage" class="form-input" placeholder="e.g., 500mg">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Frequency</label>
                                <input type="text" name="frequency" class="form-input" placeholder="e.g., Twice daily">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" class="form-input" placeholder="e.g., 7 days">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Medicine Price (TZS)</label>
                                <input type="number" name="medicine_price" class="form-input" step="0.01" min="0" placeholder="Enter price">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Instructions</label>
                            <textarea name="instructions" class="form-textarea" placeholder="Additional instructions for patient"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="addPrescriptionBtn">
                            <i class="fas fa-save"></i> Add Prescription
                        </button>
                    </form>
                </div>
                
                <?php if (empty($all_prescriptions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription"></i>
                        <h4>No Prescriptions Found</h4>
                        <p>There are no prescriptions in the system yet.</p>
                    </div>
                <?php else: ?>
                    <div class="prescriptions-list" id="prescriptionsList">
                        <?php foreach ($all_prescriptions as $prescription): ?>
                        <div class="prescription-item <?php echo $prescription['is_available'] == 'no' ? 'unavailable' : ''; ?>" id="prescription-<?php echo $prescription['id']; ?>">
                            <div class="prescription-header">
                                <div class="prescription-title">
                                    <i class="fas fa-pills"></i> 
                                    <?php echo htmlspecialchars($prescription['medicine_name']); ?>
                                    <?php if ($prescription['is_available'] == 'no'): ?>
                                        <span class="badge badge-danger">Unavailable</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Available</span>
                                    <?php endif; ?>
                                </div>
                                <div class="prescription-actions">
                                    <?php if ($prescription['medicine_price'] > 0): ?>
                                        <span class="badge badge-primary">TZS <?php echo number_format($prescription['medicine_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Price Not Set</span>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-danger btn-sm" onclick="deletePrescription(<?php echo $prescription['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            
                            <div class="prescription-details">
                                <div class="detail-item">
                                    <span class="detail-label">Patient</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Card No</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($prescription['card_no']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Doctor</span>
                                    <span class="detail-value">Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Dosage</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($prescription['dosage']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Frequency</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($prescription['frequency'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($prescription['duration'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($prescription['instructions'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Instructions</span>
                                <span class="detail-value"><?php echo htmlspecialchars($prescription['instructions']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Medicine Price and Availability Form -->
                            <div class="medicine-form">
                                <h4 style="margin-bottom: 15px; color: #374151;">
                                    <i class="fas fa-edit"></i> Update Medicine Information
                                </h4>
                                <form class="update-medicine-form" data-prescription-id="<?php echo $prescription['id']; ?>">
                                    <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Availability *</label>
                                            <select name="is_available" class="form-select" required>
                                                <option value="yes" <?php echo $prescription['is_available'] == 'yes' ? 'selected' : ''; ?>>Available</option>
                                                <option value="no" <?php echo $prescription['is_available'] == 'no' ? 'selected' : ''; ?>>Not Available</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Medicine Price (TZS) *</label>
                                            <input type="number" name="medicine_price" class="form-input" 
                                                   value="<?php echo $prescription['medicine_price']; ?>" 
                                                   step="0.01" min="0" placeholder="Enter price" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Alternative Medicine (if not available)</label>
                                        <textarea name="alternative_medicine" class="form-textarea" 
                                                  placeholder="Enter alternative medicine name and details"><?php echo htmlspecialchars($prescription['alternative_medicine'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary update-medicine-btn">
                                        <i class="fas fa-save"></i> Update Medicine Info
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Laboratory Results Tab -->
            <div id="lab-results" class="tab-content">
                <h2 style="color:#8b5cf6; margin-bottom: 20px;">
                    <i class="fas fa-file-medical-alt"></i> Laboratory Test Results
                </h2>
                
                <?php if (empty($lab_results)): ?>
                    <div class="empty-state">
                        <i class="fas fa-vial"></i>
                        <h4>No Laboratory Results Available</h4>
                        <p>Laboratory test results will appear here once they are completed.</p>
                    </div>
                <?php else: ?>
                    <div class="lab-results-container">
                        <?php foreach ($lab_results as $result): ?>
                        <div class="result-card">
                            <div class="result-header">
                                <div class="result-title">
                                    <i class="fas fa-microscope"></i>
                                    <?php echo htmlspecialchars($result['test_type']); ?>
                                </div>
                                <div class="result-status">Completed</div>
                            </div>
                            
                            <div class="result-details">
                                <div class="detail-item">
                                    <span class="detail-label">Patient</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($result['patient_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Card No</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($result['card_no']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Doctor</span>
                                    <span class="detail-value">Dr. <?php echo htmlspecialchars($result['doctor_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Completed Date</span>
                                    <span class="detail-value"><?php echo date('M j, Y H:i', strtotime($result['updated_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($result['test_description'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Test Description</span>
                                <span class="detail-value"><?php echo htmlspecialchars($result['test_description']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['results'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Laboratory Findings</span>
                                <span class="detail-value" style="background: #ecfdf5; padding: 12px; border-radius: 8px; border-left: 4px solid #10b981;">
                                    <?php echo htmlspecialchars($result['results']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Lab Test Price Form -->
                            <div class="lab-price-form">
                                <h4 style="margin-bottom: 15px; color: #374151;">
                                    <i class="fas fa-money-bill-wave"></i> Set Laboratory Test Price
                                </h4>
                                <form class="update-lab-price-form" data-lab-test-id="<?php echo $result['id']; ?>">
                                    <input type="hidden" name="lab_test_id" value="<?php echo $result['id']; ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Test Price (TZS)</label>
                                            <input type="number" name="lab_price" class="form-input" 
                                                   value="<?php echo $result['lab_price']; ?>" 
                                                   step="0.01" min="0" placeholder="Enter test price">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary update-lab-price-btn">
                                        <i class="fas fa-save"></i> Update Test Price
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Patient Records Tab -->
            <div id="patient-records" class="tab-content">
                <h2 style="color:#10b981; margin-bottom: 20px;">
                    <i class="fas fa-users"></i> Patient Records
                </h2>
                
                <div id="patientsListView">
                    <?php if (empty($all_patients)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h4>No Patients Found</h4>
                            <p>There are no patients in the system yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="patients-grid">
                            <?php foreach ($all_patients as $patient): ?>
                            <div class="patient-card" onclick="loadPatientDetails(<?php echo $patient['id']; ?>)">
                                <div class="patient-header">
                                    <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                    <div class="patient-card-no"><?php echo htmlspecialchars($patient['card_no']); ?></div>
                                </div>
                                
                                <div class="patient-stats">
                                    <div class="stat">
                                        <div class="stat-number"><?php echo $patient['prescription_count']; ?></div>
                                        <div class="stat-label">Prescriptions</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-number"><?php echo $patient['lab_test_count']; ?></div>
                                        <div class="stat-label">Lab Tests</div>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Last Visit</span>
                                    <span class="detail-value">
                                        <?php 
                                        if ($patient['last_visit']) {
                                            echo date('M j, Y', strtotime($patient['last_visit']));
                                        } else {
                                            echo 'No visits yet';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div style="margin-top: 15px; text-align: center;">
                                    <button class="btn btn-primary" style="width: 100%;">
                                        <i class="fas fa-eye"></i> View Full Record
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div id="patientDetailsView" style="display: none;">
                    <!-- Patient details will be loaded here via AJAX -->
                </div>
            </div>

            <!-- Profile Settings Tab -->
            <div id="profile" class="tab-content">
                <h2 style="color:#f59e0b; margin-bottom: 20px;">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </h2>
                
                <div class="profile-form">
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
            </div>
        </div>

    </div>

    <script>
        // Global loading state
        let isLoading = false;
        let currentPatientId = null;

        // Function to show loading overlay
        function showLoading(message = 'Processing your request...') {
            isLoading = true;
            document.getElementById('loadingText').textContent = message;
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.body.classList.add('page-loading');
        }

        // Function to hide loading overlay
        function hideLoading() {
            isLoading = false;
            document.getElementById('loadingOverlay').style.display = 'none';
            document.body.classList.remove('page-loading');
        }

        // Function to show tabs
        function showTab(tabName) {
            if (isLoading) return;
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Reset patient details view if switching away from patient records
            if (tabName !== 'patient-records') {
                document.getElementById('patientsListView').style.display = 'block';
                document.getElementById('patientDetailsView').style.display = 'none';
                currentPatientId = null;
            }
        }

        // Function to show message
        function showMessage(message, type = 'success') {
            if (type === 'success') {
                document.getElementById('successMessage').textContent = message;
                document.getElementById('successAlert').style.display = 'block';
                document.getElementById('errorAlert').style.display = 'none';
            } else {
                document.getElementById('errorMessage').textContent = message;
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('successAlert').style.display = 'none';
            }
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                document.getElementById('successAlert').style.display = 'none';
                document.getElementById('errorAlert').style.display = 'none';
            }, 5000);
        }

        // Function to handle AJAX requests
        async function makeAjaxRequest(formData) {
            showLoading('Processing your request...');
            
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                return result;
            } catch (error) {
                return { success: false, message: 'Network error: ' + error.message };
            } finally {
                hideLoading();
            }
        }

        // Load patient details via AJAX
        async function loadPatientDetails(patientId) {
            if (isLoading) return;
            
            showLoading('Loading patient details...');
            currentPatientId = patientId;
            
            try {
                const response = await fetch(`get_patient_details.php?patient_id=${patientId}`);
                const html = await response.text();
                
                document.getElementById('patientDetailsView').innerHTML = html;
                document.getElementById('patientsListView').style.display = 'none';
                document.getElementById('patientDetailsView').style.display = 'block';
                
            } catch (error) {
                showMessage('Error loading patient details: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }

        // Go back to patients list
        function backToPatientsList() {
            document.getElementById('patientsListView').style.display = 'block';
            document.getElementById('patientDetailsView').style.display = 'none';
            currentPatientId = null;
        }

        // Print patient record - redirect to print page
        function printPatientRecord(patientId) {
            window.open(`print_patient_prescriptions.php?patient_id=${patientId}`, '_blank');
        }

        // Add Prescription Form
        document.getElementById('addPrescriptionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (isLoading) return;
            
            const submitBtn = document.getElementById('addPrescriptionBtn');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            formData.append('ajax_request', 'true');
            formData.append('action', 'add_prescription');
            
            const result = await makeAjaxRequest(formData);
            
            // Restore button state
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (result.success) {
                showMessage(result.message, 'success');
                this.reset();
            } else {
                showMessage(result.message, 'error');
            }
        });

        // Update Medicine Forms
        document.querySelectorAll('.update-medicine-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (isLoading) return;
                
                const submitBtn = this.querySelector('.update-medicine-btn');
                const originalText = submitBtn.innerHTML;
                const prescriptionId = this.dataset.prescriptionId;
                
                // Show loading state
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                
                const formData = new FormData(this);
                formData.append('ajax_request', 'true');
                formData.append('action', 'update_medicine');
                
                const result = await makeAjaxRequest(formData);
                
                // Restore button state
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                if (result.success) {
                    showMessage(result.message, 'success');
                } else {
                    showMessage(result.message, 'error');
                }
            });
        });

        // Update Lab Price Forms
        document.querySelectorAll('.update-lab-price-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (isLoading) return;
                
                const submitBtn = this.querySelector('.update-lab-price-btn');
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                
                const formData = new FormData(this);
                formData.append('ajax_request', 'true');
                formData.append('action', 'update_lab_price');
                
                const result = await makeAjaxRequest(formData);
                
                // Restore button state
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                if (result.success) {
                    showMessage(result.message, 'success');
                } else {
                    showMessage(result.message, 'error');
                }
            });
        });

        // Delete Prescription
        async function deletePrescription(prescriptionId) {
            if (isLoading) return;
            
            if (!confirm('Are you sure you want to delete this prescription? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax_request', 'true');
            formData.append('action', 'delete_prescription');
            formData.append('prescription_id', prescriptionId);
            
            const result = await makeAjaxRequest(formData);
            
            if (result.success) {
                showMessage(result.message, 'success');
                // Remove the prescription item from the DOM
                document.getElementById(`prescription-${prescriptionId}`).remove();
            } else {
                showMessage(result.message, 'error');
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

        // Initial time update
        updateTime();
        setInterval(updateTime, 60000);
    </script>
</body>
</html>