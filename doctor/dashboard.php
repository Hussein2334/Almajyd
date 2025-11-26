<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Add Multiple Prescriptions
if (isset($_POST['add_prescription'])) {
    $checking_form_id = $_POST['checking_form_id'];
    $medicine_names = $_POST['medicine_name'];
    $dosages = $_POST['dosage'];
    $frequencies = $_POST['frequency'];
    $durations = $_POST['duration'];
    $instructions = $_POST['instructions'];
    
    try {
        $pdo->beginTransaction();
        
        // Loop through all medicines and insert each one
        for ($i = 0; $i < count($medicine_names); $i++) {
            if (!empty($medicine_names[$i])) {
                $stmt = $pdo->prepare("INSERT INTO prescriptions (checking_form_id, medicine_name, dosage, frequency, duration, instructions, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $checking_form_id, 
                    $medicine_names[$i], 
                    $dosages[$i] ?? '', 
                    $frequencies[$i] ?? '', 
                    $durations[$i] ?? '', 
                    $instructions[$i] ?? ''
                ]);
            }
        }
        
        $pdo->commit();
        $success_message = "Prescriptions added successfully!";
        
        // Clear the form by redirecting
        header("Location: ".$_SERVER['PHP_SELF']."#prescription-form");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error adding prescriptions: " . $e->getMessage();
    }
}

// Handle Add Multiple Laboratory Tests
if (isset($_POST['add_lab_test'])) {
    $checking_form_id = $_POST['checking_form_id'];
    $test_types = $_POST['test_type'];
    $test_descriptions = $_POST['test_description'];
    $conducted_by = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Loop through all test types and insert each one
        $tests_added = 0;
        for ($i = 0; $i < count($test_types); $i++) {
            if (!empty($test_types[$i])) {
                $stmt = $pdo->prepare("INSERT INTO laboratory_tests (checking_form_id, test_type, test_description, conducted_by, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $checking_form_id, 
                    $test_types[$i], 
                    $test_descriptions[$i] ?? '', 
                    $conducted_by
                ]);
                $tests_added++;
            }
        }
        
        $pdo->commit();
        $success_message = $tests_added . " laboratory test(s) requested successfully!";
        
        // Clear the form by redirecting
        header("Location: ".$_SERVER['PHP_SELF']."#lab-form");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error adding laboratory tests: " . $e->getMessage();
    }
}

// Handle Create Checking Form
if (isset($_POST['create_checking_form'])) {
    $patient_id = $_POST['patient_id'];
    $symptoms = $_POST['symptoms'];
    $diagnosis = $_POST['diagnosis'];
    $notes = $_POST['notes'];
    $doctor_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO checking_forms (patient_id, doctor_id, symptoms, diagnosis, notes, status) VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$patient_id, $doctor_id, $symptoms, $diagnosis, $notes]);
        
        $checking_form_id = $pdo->lastInsertId();
        $success_message = "Patient examination completed successfully! Checking Form ID: " . $checking_form_id;
        
        // Clear the form by redirecting
        header("Location: ".$_SERVER['PHP_SELF']."#examination-form");
        exit;
        
    } catch (PDOException $e) {
        $error_message = "Error creating checking form: " . $e->getMessage();
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

// Get statistics for dashboard
$total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'];

// Get today's patients count
$today_patients = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];

// Get total prescriptions by this doctor (through checking_forms)
$total_prescriptions_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions p 
                                         JOIN checking_forms cf ON p.checking_form_id = cf.id 
                                         WHERE cf.doctor_id = ?");
$total_prescriptions_stmt->execute([$_SESSION['user_id']]);
$total_prescriptions = $total_prescriptions_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total lab tests by this doctor
$total_tests_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM laboratory_tests WHERE conducted_by = ?");
$total_tests_stmt->execute([$_SESSION['user_id']]);
$total_tests = $total_tests_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get completed lab tests for this doctor
$completed_lab_tests = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests lt 
                                  JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                                  WHERE cf.doctor_id = " . $_SESSION['user_id'] . " 
                                  AND lt.status = 'completed'")->fetch(PDO::FETCH_ASSOC)['total'];

// Get all patients for dropdown
$patients = $pdo->query("SELECT * FROM patients ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get recent patients with medical info
$recent_patients = $pdo->query("SELECT p.*, 
                               (SELECT COUNT(*) FROM checking_forms WHERE patient_id = p.id) as checkup_count
                               FROM patients p 
                               ORDER BY p.created_at DESC 
                               LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get recent checking forms by this doctor
$recent_checkups = $pdo->query("SELECT cf.*, p.full_name as patient_name, p.card_no 
                              FROM checking_forms cf 
                              JOIN patients p ON cf.patient_id = p.id 
                              WHERE cf.doctor_id = " . $_SESSION['user_id'] . "
                              ORDER BY cf.created_at DESC 
                              LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get available checking forms for prescriptions and lab tests
$checking_forms = $pdo->query("SELECT cf.*, p.full_name as patient_name, p.card_no 
                             FROM checking_forms cf 
                             JOIN patients p ON cf.patient_id = p.id 
                             WHERE cf.doctor_id = " . $_SESSION['user_id'] . "
                             ORDER BY cf.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get laboratory test results for this doctor - LIMITED for initial display
$lab_results_limit = 6;
$lab_results = $pdo->query("SELECT lt.*, p.full_name as patient_name, p.card_no, 
                           u.full_name as lab_technician, cf.symptoms, cf.diagnosis
                    FROM laboratory_tests lt 
                    JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                    JOIN patients p ON cf.patient_id = p.id 
                    LEFT JOIN users u ON lt.conducted_by = u.id 
                    WHERE cf.doctor_id = " . $_SESSION['user_id'] . "
                    AND lt.status = 'completed'
                    ORDER BY lt.updated_at DESC 
                    LIMIT $lab_results_limit")->fetchAll(PDO::FETCH_ASSOC);

// Get total count for "See All"
$total_results_stmt = $pdo->query("SELECT COUNT(*) as total FROM laboratory_tests lt 
                                 JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                                 WHERE cf.doctor_id = " . $_SESSION['user_id'] . "
                                 AND lt.status = 'completed'");
$total_results = $total_results_stmt->fetch(PDO::FETCH_ASSOC)['total'];

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
    <title>Doctor Dashboard - Almajyd Dispensary</title>
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
            border: 3px solid #3b82f6;
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
            background: linear-gradient(135deg, #3b82f6, #2563eb);
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
            background: #3b82f6;
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
        .stat-card.prescriptions { border-left-color: #8b5cf6; }
        .stat-card.tests { border-left-color: #f59e0b; }
        .stat-card.results { border-left-color: #8b5cf6; }
        
        .stat-icon {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .stat-card.patients .stat-icon { color: #3b82f6; }
        .stat-card.today .stat-icon { color: #10b981; }
        .stat-card.prescriptions .stat-icon { color: #8b5cf6; }
        .stat-card.tests .stat-icon { color: #f59e0b; }
        .stat-card.results .stat-icon { color: #8b5cf6; }
        
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

        /* Clickable Process Steps */
        .steps-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .steps-title {
            text-align: center;
            margin-bottom: 25px;
            color: #1e293b;
            font-size: 1.4rem;
        }
        
        .steps {
            display: flex;
            align-items: center;
            margin: 30px 0;
            position: relative;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e2e8f0;
            border-radius: 3px;
            z-index: 1;
        }
        
        .step {
            width: 60px;
            height: 60px;
            background: white;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: #64748b;
            z-index: 2;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .step:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .step.active {
            border-color: #3b82f6;
            color: white;
            background: #3b82f6;
            box-shadow: 0 0 15px rgba(59,130,246,0.4);
        }
        
        .step-label {
            position: absolute;
            top: 100%;
            margin-top: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }
        
        .spacer { 
            flex-grow: 1; 
            min-width: 30px; 
        }

        /* Content area with ACTIONS */
        .content-area {
            margin-top: 25px;
            padding: 25px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px dashed #e2e8f0;
            min-height: 350px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .action-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .action-card h4 {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-card h4 i {
            color: #3b82f6;
        }
        
        .action-list {
            list-style: none;
            margin-bottom: 15px;
        }
        
        .action-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-list li:last-child {
            border-bottom: none;
        }
        
        .action-list li i {
            color: #3b82f6;
            font-size: 0.9em;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        .btn-info {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-info:hover {
            background: #7c3aed;
            transform: translateY(-2px);
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
            min-height: 100px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        /* Prescription Medicine Rows & Test Type Rows */
        .medicine-row {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .medicine-row:hover {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        
        .test-type-row {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #bae6fd;
            transition: all 0.3s ease;
        }
        
        .test-type-row:hover {
            border-color: #3b82f6;
            background: #e0f2fe;
        }
        
        .medicine-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .medicine-title {
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remove-medicine {
            background: #ef4444;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .remove-medicine:hover {
            background: #dc2626;
            transform: scale(1.1);
        }
        
        .add-medicine-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .add-medicine-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* Recent Activity Tables */
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        
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

        /* Laboratory Results Section - IMPROVED with Dropdown */
        .lab-results-section {
            margin-top: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .section-title {
            color: #1e293b;
            font-size: 1.4rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .results-count {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .see-all-btn {
            background: transparent;
            color: #3b82f6;
            border: 2px solid #3b82f6;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .see-all-btn:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }
        
        /* NEW: Dropdown Results Container */
        .results-dropdown-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-top: 20px;
        }
        
        .dropdown-header {
            background: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .dropdown-header:hover {
            background: #f1f5f9;
        }
        
        .dropdown-header.active {
            background: #3b82f6;
            color: white;
        }
        
        .dropdown-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .dropdown-icon {
            transition: transform 0.3s ease;
        }
        
        .dropdown-header.active .dropdown-icon {
            transform: rotate(180deg);
        }
        
        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        
        .dropdown-content.active {
            max-height: 1000px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .result-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #10b981;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .result-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #3b82f6);
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .result-type {
            font-weight: bold;
            color: #1e293b;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .result-type i {
            color: #3b82f6;
        }
        
        .result-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: #d1fae5;
            color: #065f46;
        }
        
        .result-info {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .info-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .result-description {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #475569;
            border-left: 3px solid #e2e8f0;
        }
        
        .result-findings {
            background: #ecfdf5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #10b981;
        }
        
        .findings-label {
            font-weight: bold;
            color: #065f46;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
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
            color: #8b5cf6;
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

        /* Modal for All Results */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px 25px;
            background: #3b82f6;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 25px;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }
        
        .all-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
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
            background: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: white !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
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
        .badge-primary { background: #dbeafe; color: #1e40af; }

        /* Table responsive container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 600px;
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
            
            .steps::before {
                display: none;
            }
            
            .steps {
                justify-content: space-around;
            }
            
            .spacer {
                display: none;
            }
            
            .step {
                width: 50px;
                height: 50px;
                font-size: 16px;
            }
            
            .step-label {
                font-size: 10px;
            }
            
            .content-area {
                padding: 20px 15px;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
            
            .all-results-grid {
                grid-template-columns: 1fr;
            }
            
            .table-card {
                padding: 15px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .section-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: center !important;
                float: none !important;
                margin-bottom: 10px !important;
            }
            
            .modal-content {
                width: 95%;
                height: 95vh;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .table-responsive {
                font-size: 0.8rem;
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
                <span id="currentTime"><?php echo $current_time; ?></span>
            </div>
            <div class="user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></div>
                    <small style="font-size: 0.75rem;">Medical Doctor</small>
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
            <div class="stat-card prescriptions">
                <div class="stat-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="stat-number"><?php echo $total_prescriptions; ?></div>
                <div class="stat-label">My Prescriptions</div>
            </div>
            <div class="stat-card tests">
                <div class="stat-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="stat-number"><?php echo $total_tests; ?></div>
                <div class="stat-label">Lab Tests</div>
            </div>
            <div class="stat-card results">
                <div class="stat-icon">
                    <i class="fas fa-file-medical-alt"></i>
                </div>
                <div class="stat-number"><?php echo $completed_lab_tests; ?></div>
                <div class="stat-label">Lab Results</div>
            </div>
        </div>

        <!-- Clickable Process Steps with ACTIONS -->
        <div class="steps-container">
            <h2 class="steps-title">Medical Doctor Control Panel</h2>
            
            <div class="steps">
                <div class="step active" onclick="showStep(1)">
                    1
                    <div class="step-label">Patients</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(2)">
                    2
                    <div class="step-label">Examination</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(3)">
                    3
                    <div class="step-label">Prescriptions</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(4)">
                    4
                    <div class="step-label">Lab Results</div>
                </div>
                <div class="spacer"></div>
                <div class="step" onclick="showStep(5)">
                    5
                    <div class="step-label">Settings</div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div class="content-area" id="content">
                <h2 style="color:#3b82f6; margin-bottom: 15px;">Welcome to Doctor Control Panel</h2>
                <p>Click on the numbers above to manage different medical sections.</p>
                
                <div class="action-grid">
                    <div class="action-card" onclick="showStep(1)">
                        <h4><i class="fas fa-user-injured"></i> Patient Management</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> View all patients</li>
                            <li><i class="fas fa-check"></i> Patient medical history</li>
                        </ul>
                    </div>
                    <div class="action-card" onclick="showStep(2)">
                        <h4><i class="fas fa-stethoscope"></i> Patient Examination</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> Add patient examination</li>
                            <li><i class="fas fa-check"></i> Request lab tests</li>
                        </ul>
                    </div>
                    <div class="action-card" onclick="showStep(4)">
                        <h4><i class="fas fa-file-medical-alt"></i> Laboratory Results</h4>
                        <ul class="action-list">
                            <li><i class="fas fa-check"></i> View test results</li>
                            <li><i class="fas fa-check"></i> Laboratory findings</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laboratory Results Section - IMPROVED with Dropdown -->
        <div class="lab-results-section">
            <div class="table-card">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-file-medical-alt"></i> Laboratory Test Results
                    </h3>
                    <div class="section-actions">
                        <span class="results-count">
                            <?php echo count($lab_results); ?> of <?php echo $total_results; ?>
                        </span>
                        <?php if ($total_results > $lab_results_limit): ?>
                        <button class="see-all-btn" onclick="showAllResults()">
                            <i class="fas fa-list"></i> See All Results
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($lab_results)): ?>
                    <div class="empty-state">
                        <i class="fas fa-vial"></i>
                        <h4>No Laboratory Results Available</h4>
                        <p>Laboratory test results will appear here once they are completed and ready for review.</p>
                        <div class="action-buttons" style="justify-content: center; margin-top: 20px;">
                            <button class="btn btn-info" onclick="scrollToLabForm()">
                                <i class="fas fa-vial"></i> Request Lab Test
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- NEW: Dropdown Results Container -->
                    <div class="results-dropdown-container">
                        <div class="dropdown-header active" onclick="toggleResultsDropdown()">
                            <div class="dropdown-title">
                                <i class="fas fa-microscope"></i>
                                Laboratory Test Results (<?php echo count($lab_results); ?>)
                            </div>
                            <div class="dropdown-icon">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="dropdown-content active">
                            <div class="results-grid">
                                <?php foreach ($lab_results as $result): ?>
                                <div class="result-card">
                                    <div class="result-header">
                                        <div class="result-type">
                                            <i class="fas fa-microscope"></i>
                                            <?php echo htmlspecialchars($result['test_type']); ?>
                                        </div>
                                        <div class="result-status">Completed</div>
                                    </div>
                                    
                                    <div class="result-info">
                                        <div class="info-row">
                                            <span class="info-label">Patient:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($result['patient_name']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Card No:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($result['card_no']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Lab Technician:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($result['lab_technician'] ?? 'Not specified'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Completed Date:</span>
                                            <span class="info-value"><?php echo date('M j, Y H:i', strtotime($result['updated_at'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($result['test_description'])): ?>
                                    <div class="result-description">
                                        <strong>Test Description:</strong><br>
                                        <?php echo htmlspecialchars($result['test_description']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($result['results'])): ?>
                                    <div class="result-findings">
                                        <div class="findings-label">
                                            <i class="fas fa-microscope"></i>
                                            Laboratory Findings:
                                        </div>
                                        <div style="color: #065f46; font-size: 0.9rem; line-height: 1.5;">
                                            <?php 
                                            $results = htmlspecialchars($result['results']);
                                            if (strlen($results) > 150) {
                                                echo substr($results, 0, 150) . '...';
                                            } else {
                                                echo $results;
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="action-buttons">
                                        <button class="btn btn-primary" onclick="printTestResult(<?php echo $result['id']; ?>)">
                                            <i class="fas fa-print"></i> Print Result
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($total_results > $lab_results_limit): ?>
                    <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <button class="see-all-btn" onclick="showAllResults()">
                            <i class="fas fa-list"></i> See All <?php echo $total_results; ?> Results
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Action Forms -->
        <div class="form-grid">
            <!-- Patient Examination Form -->
            <div class="form-card" id="examination-form">
                <h4><i class="fas fa-stethoscope"></i> Patient Examination</h4>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Select Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['card_no'] . ' - ' . $patient['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Symptoms</label>
                        <textarea name="symptoms" class="form-textarea" placeholder="Describe patient symptoms"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Diagnosis</label>
                        <textarea name="diagnosis" class="form-textarea" placeholder="Enter medical diagnosis"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Doctor Notes</label>
                        <textarea name="notes" class="form-textarea" placeholder="Additional medical notes"></textarea>
                    </div>
                    
                    <button type="submit" name="create_checking_form" class="btn btn-success">
                        <i class="fas fa-save"></i> Complete Examination
                    </button>
                </form>
            </div>

            <!-- Add Prescription Form - MULTIPLE MEDICINES -->
            <div class="form-card" id="prescription-form">
                <h4><i class="fas fa-prescription"></i> Add Prescription</h4>
                <form method="POST" action="" id="prescriptionForm">
                    <div class="form-group">
                        <label class="form-label">Select Checking Form *</label>
                        <select name="checking_form_id" class="form-select" required>
                            <option value="">Select Checking Form</option>
                            <?php foreach ($checking_forms as $form): ?>
                                <option value="<?php echo $form['id']; ?>">
                                    <?php echo htmlspecialchars('CF-' . $form['id'] . ' - ' . $form['patient_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Medicine Rows Container -->
                    <div id="medicineRows">
                        <!-- First Medicine Row -->
                        <div class="medicine-row" id="medicineRow_0">
                            <div class="medicine-header">
                                <div class="medicine-title">
                                    <i class="fas fa-pills"></i> Medicine #1
                                </div>
                                <button type="button" class="remove-medicine" onclick="removeMedicine(0)" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Medicine Name *</label>
                                <input type="text" name="medicine_name[]" class="form-input" placeholder="Enter medicine name" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Dosage</label>
                                <input type="text" name="dosage[]" class="form-input" placeholder="e.g., 500mg">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Frequency</label>
                                <input type="text" name="frequency[]" class="form-input" placeholder="e.g., Twice daily">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration[]" class="form-input" placeholder="e.g., 7 days">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Instructions</label>
                                <textarea name="instructions[]" class="form-textarea" placeholder="Additional instructions for patient"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add More Medicine Button -->
                    <button type="button" class="add-medicine-btn" onclick="addMedicine()">
                        <i class="fas fa-plus"></i> Add Another Medicine
                    </button>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" name="add_prescription" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save All Prescriptions
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Additional Forms Grid -->
        <div class="form-grid" style="margin-top: 20px;">
            <!-- Laboratory Test Request Form - MULTIPLE TESTS -->
            <div class="form-card" id="lab-form">
                <h4><i class="fas fa-vial"></i> Request Laboratory Tests</h4>
                <form method="POST" action="" id="labTestForm">
                    <div class="form-group">
                        <label class="form-label">Select Checking Form *</label>
                        <select name="checking_form_id" class="form-select" required>
                            <option value="">Select Checking Form</option>
                            <?php foreach ($checking_forms as $form): ?>
                                <option value="<?php echo $form['id']; ?>">
                                    <?php echo htmlspecialchars('CF-' . $form['id'] . ' - ' . $form['patient_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Test Types Container -->
                    <div id="testTypesContainer">
                        <!-- First Test Type Row -->
                        <div class="test-type-row" id="testTypeRow_0">
                            <div class="medicine-header">
                                <div class="medicine-title">
                                    <i class="fas fa-microscope"></i> Test Type #1
                                </div>
                                <button type="button" class="remove-medicine" onclick="removeTestType(0)" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Test Type *</label>
                                <select name="test_type[]" class="form-select" required>
                                    <option value="">Select Test Type</option>
                                    <option value="Blood Test">Blood Test</option>
                                    <option value="Urine Test">Urine Test</option>
                                    <option value="X-Ray">X-Ray</option>
                                    <option value="Ultrasound">Ultrasound</option>
                                    <option value="ECG">ECG</option>
                                    <option value="Blood Pressure">Blood Pressure</option>
                                    <option value="Blood Sugar">Blood Sugar</option>
                                    <option value="Cholesterol">Cholesterol</option>
                                    <option value="Malaria Test">Malaria Test</option>
                                    <option value="Typhoid Test">Typhoid Test</option>
                                    <option value="HIV Test">HIV Test</option>
                                    <option value="Hepatitis Test">Hepatitis Test</option>
                                    <option value="Pregnancy Test">Pregnancy Test</option>
                                    <option value="Stool Test">Stool Test</option>
                                    <option value="Sputum Test">Sputum Test</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Test Description</label>
                                <textarea name="test_description[]" class="form-textarea" placeholder="Describe the test requirements, specific parameters, or any special instructions"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add More Test Button -->
                    <button type="button" class="add-medicine-btn" onclick="addTestType()">
                        <i class="fas fa-plus"></i> Add Another Test Type
                    </button>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" name="add_lab_test" class="btn btn-info">
                            <i class="fas fa-paper-plane"></i> Request All Tests
                        </button>
                    </div>
                </form>
            </div>

            <!-- Profile Settings Form -->
            <div class="form-card" id="profile-form">
                <h4><i class="fas fa-user-cog"></i> Profile Settings</h4>
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
                    
                    <button type="submit" name="update_profile" class="btn btn-warning">
                        <i class="fas fa-sync"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Activity Tables -->
        <div class="tables-grid">
            <div class="table-card">
                <h3><i class="fas fa-user-injured"></i> Recent Patients</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="patientsTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Card No</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Checkups</th>
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
                                    <span class="badge badge-primary"><?php echo $patient['checkup_count']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-card">
                <h3><i class="fas fa-file-medical"></i> Recent Checkups</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="checkupsTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Card No</th>
                                <th>Diagnosis</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_checkups as $checkup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($checkup['patient_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($checkup['card_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($checkup['diagnosis'] ?? 'No diagnosis', 0, 50)) . (strlen($checkup['diagnosis'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge badge-success"><?php echo ucfirst($checkup['status']); ?></span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($checkup['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal for All Results -->
    <div id="allResultsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-medical-alt"></i> All Laboratory Results</h3>
                <button class="close-modal" onclick="closeAllResults()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="allResultsContainer">
                    <!-- Results will be loaded here via AJAX -->
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #3b82f6;"></i>
                        <p>Loading results...</p>
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
        // Initialize DataTables for both tables
        $(document).ready(function() {
            $('#patientsTable').DataTable({
                responsive: true,
                paging: false,
                searching: true,
                info: false,
                ordering: true,
                language: {
                    search: "Search patients:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });

            $('#checkupsTable').DataTable({
                responsive: true,
                paging: false,
                searching: true,
                info: false,
                ordering: true,
                language: {
                    search: "Search checkups:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        });

        // Medicine counter for dynamic form
        let medicineCounter = 1;

        // Function to add new medicine row
        function addMedicine() {
            const medicineRows = document.getElementById('medicineRows');
            const newRow = document.createElement('div');
            newRow.className = 'medicine-row';
            newRow.id = 'medicineRow_' + medicineCounter;
            
            newRow.innerHTML = `
                <div class="medicine-header">
                    <div class="medicine-title">
                        <i class="fas fa-pills"></i> Medicine #${medicineCounter + 1}
                    </div>
                    <button type="button" class="remove-medicine" onclick="removeMedicine(${medicineCounter})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Medicine Name *</label>
                    <input type="text" name="medicine_name[]" class="form-input" placeholder="Enter medicine name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dosage</label>
                    <input type="text" name="dosage[]" class="form-input" placeholder="e.g., 500mg">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Frequency</label>
                    <input type="text" name="frequency[]" class="form-input" placeholder="e.g., Twice daily">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duration</label>
                    <input type="text" name="duration[]" class="form-input" placeholder="e.g., 7 days">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions[]" class="form-textarea" placeholder="Additional instructions for patient"></textarea>
                </div>
            `;
            
            medicineRows.appendChild(newRow);
            medicineCounter++;
            
            // Scroll to the new medicine row
            newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Function to remove medicine row
        function removeMedicine(index) {
            const row = document.getElementById('medicineRow_' + index);
            if (row) {
                row.remove();
                // Re-number remaining medicines
                updateMedicineNumbers();
            }
        }

        // Function to update medicine numbers after removal
        function updateMedicineNumbers() {
            const medicineRows = document.querySelectorAll('.medicine-row');
            medicineCounter = 0;
            
            medicineRows.forEach((row, index) => {
                const title = row.querySelector('.medicine-title');
                title.innerHTML = `<i class="fas fa-pills"></i> Medicine #${index + 1}`;
                row.id = 'medicineRow_' + index;
                
                // Update remove button onclick
                const removeBtn = row.querySelector('.remove-medicine');
                removeBtn.onclick = function() { removeMedicine(index); };
                
                // Show remove button for all except first
                if (index === 0) {
                    removeBtn.style.display = 'none';
                } else {
                    removeBtn.style.display = 'flex';
                }
                
                medicineCounter = index + 1;
            });
        }

        // Test Type counter for dynamic form
        let testTypeCounter = 1;

        // Function to add new test type row
        function addTestType() {
            const testTypesContainer = document.getElementById('testTypesContainer');
            const newRow = document.createElement('div');
            newRow.className = 'test-type-row';
            newRow.id = 'testTypeRow_' + testTypeCounter;
            
            newRow.innerHTML = `
                <div class="medicine-header">
                    <div class="medicine-title">
                        <i class="fas fa-microscope"></i> Test Type #${testTypeCounter + 1}
                    </div>
                    <button type="button" class="remove-medicine" onclick="removeTestType(${testTypeCounter})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Test Type *</label>
                    <select name="test_type[]" class="form-select" required>
                        <option value="">Select Test Type</option>
                        <option value="Blood Test">Blood Test</option>
                        <option value="Urine Test">Urine Test</option>
                        <option value="X-Ray">X-Ray</option>
                        <option value="Ultrasound">Ultrasound</option>
                        <option value="ECG">ECG</option>
                        <option value="Blood Pressure">Blood Pressure</option>
                        <option value="Blood Sugar">Blood Sugar</option>
                        <option value="Cholesterol">Cholesterol</option>
                        <option value="Malaria Test">Malaria Test</option>
                        <option value="Typhoid Test">Typhoid Test</option>
                        <option value="HIV Test">HIV Test</option>
                        <option value="Hepatitis Test">Hepatitis Test</option>
                        <option value="Pregnancy Test">Pregnancy Test</option>
                        <option value="Stool Test">Stool Test</option>
                        <option value="Sputum Test">Sputum Test</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Test Description</label>
                    <textarea name="test_description[]" class="form-textarea" placeholder="Describe the test requirements, specific parameters, or any special instructions"></textarea>
                </div>
            `;
            
            testTypesContainer.appendChild(newRow);
            testTypeCounter++;
            
            // Scroll to the new test type row
            newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Function to remove test type row
        function removeTestType(index) {
            const row = document.getElementById('testTypeRow_' + index);
            if (row) {
                row.remove();
                // Re-number remaining test types
                updateTestTypeNumbers();
            }
        }

        // Function to update test type numbers after removal
        function updateTestTypeNumbers() {
            const testTypeRows = document.querySelectorAll('#testTypesContainer .test-type-row');
            testTypeCounter = 0;
            
            testTypeRows.forEach((row, index) => {
                const title = row.querySelector('.medicine-title');
                title.innerHTML = `<i class="fas fa-microscope"></i> Test Type #${index + 1}`;
                row.id = 'testTypeRow_' + index;
                
                // Update remove button onclick
                const removeBtn = row.querySelector('.remove-medicine');
                removeBtn.onclick = function() { removeTestType(index); };
                
                // Show remove button for all except first
                if (index === 0) {
                    removeBtn.style.display = 'none';
                } else {
                    removeBtn.style.display = 'flex';
                }
                
                testTypeCounter = index + 1;
            });
        }

        // Function to validate lab test form before submission
        function validateLabTestForm() {
            const testTypes = document.getElementsByName('test_type[]');
            let hasValidTest = false;
            
            for (let i = 0; i < testTypes.length; i++) {
                if (testTypes[i].value !== '') {
                    hasValidTest = true;
                    break;
                }
            }
            
            if (!hasValidTest) {
                alert('Please add at least one test type!');
                return false;
            }
            
            return true;
        }

        // Add event listener to lab test form
        document.getElementById('labTestForm').addEventListener('submit', function(e) {
            if (!validateLabTestForm()) {
                e.preventDefault();
            }
        });

        // NEW: Function to toggle results dropdown
        function toggleResultsDropdown() {
            const header = document.querySelector('.dropdown-header');
            const content = document.querySelector('.dropdown-content');
            const icon = document.querySelector('.dropdown-icon');
            
            header.classList.toggle('active');
            content.classList.toggle('active');
        }

        // Function to show step content with ACTIONS
        function showStep(num) {
            // Remove active class from all steps
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            // Add active to clicked step
            event.target.classList.add('active');

            // Change content based on step
            const content = document.getElementById('content');
            
            const stepsContent = {
                1: `
                    <h2 style="color:#3b82f6; margin-bottom: 15px;"><i class="fas fa-user-injured"></i> Patient Management</h2>
                    <p>View and manage all patient records and medical history.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="window.location.href='view_patients.php'">
                            <i class="fas fa-list"></i> View All Patients
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='patient_history.php'">
                            <i class="fas fa-history"></i> Medical History
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='view_patients.php'">
                            <h4><i class="fas fa-list"></i> All Patients</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Browse patient database</li>
                                <li><i class="fas fa-check"></i> View patient details</li>
                                <li><i class="fas fa-check"></i> Search and filter patients</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.location.href='view_patients.php'">
                                    <i class="fas fa-list"></i> View All Patients
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='patient_history.php'">
                            <h4><i class="fas fa-history"></i> Medical History</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Treatment records</li>
                                <li><i class="fas fa-check"></i> Previous diagnoses</li>
                                <li><i class="fas fa-check"></i> Chronic conditions</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="window.location.href='patient_history.php'">
                                    <i class="fas fa-history"></i> View History
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                2: `
                    <h2 style="color:#10b981; margin-bottom: 15px;"><i class="fas fa-stethoscope"></i> Patient Examination</h2>
                    <p>Conduct patient examinations and medical assessments.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="scrollToExaminationForm()">
                            <i class="fas fa-plus"></i> Add Examination
                        </button>
                        <button class="btn btn-info" onclick="scrollToLabForm()">
                            <i class="fas fa-vial"></i> Request Lab Tests
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="scrollToExaminationForm()">
                            <h4><i class="fas fa-file-medical-alt"></i> Add Examination</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Create new checking form</li>
                                <li><i class="fas fa-check"></i> Record symptoms</li>
                                <li><i class="fas fa-check"></i> Enter diagnosis</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="scrollToExaminationForm()">
                                    <i class="fas fa-plus"></i> Add Examination
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card" onclick="scrollToLabForm()">
                            <h4><i class="fas fa-vial"></i> Lab Requests</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Request multiple lab tests</li>
                                <li><i class="fas fa-check"></i> Various test types</li>
                                <li><i class="fas fa-check"></i> Test descriptions</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-info" onclick="scrollToLabForm()">
                                    <i class="fas fa-paper-plane"></i> Request Tests
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                3: `
                    <h2 style="color:#8b5cf6; margin-bottom: 15px;"><i class="fas fa-prescription"></i> Prescription Management</h2>
                    <p>Manage patient prescriptions and medication records.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="scrollToPrescriptionForm()">
                            <i class="fas fa-plus"></i> Add Prescription
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="scrollToPrescriptionForm()">
                            <h4><i class="fas fa-prescription"></i> Add Prescription</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Prescribe multiple medications</li>
                                <li><i class="fas fa-check"></i> Set dosage and frequency</li>
                                <li><i class="fas fa-check"></i> Add instructions for each medicine</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="scrollToPrescriptionForm()">
                                    <i class="fas fa-plus"></i> Add Prescription
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                4: `
                    <h2 style="color:#8b5cf6; margin-bottom: 15px;"><i class="fas fa-file-medical-alt"></i> Laboratory Results</h2>
                    <p>View and manage laboratory test results and findings.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="scrollToLabResults()">
                            <i class="fas fa-eye"></i> View Lab Results
                        </button>
                        <button class="btn btn-success" onclick="refreshLabResults()">
                            <i class="fas fa-sync"></i> Refresh Results
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="scrollToLabResults()">
                            <h4><i class="fas fa-microscope"></i> Test Results</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> View completed tests</li>
                                <li><i class="fas fa-check"></i> Laboratory findings</li>
                                <li><i class="fas fa-check"></i> Print results</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="scrollToLabResults()">
                                    <i class="fas fa-eye"></i> View Results
                                </button>
                            </div>
                        </div>
                        
                        <div class="action-card">
                            <h4><i class="fas fa-chart-line"></i> Results Analysis</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Test trends</li>
                                <li><i class="fas fa-check"></i> Patient history</li>
                                <li><i class="fas fa-check"></i> Medical decisions</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-info" onclick="analyzeResults()">
                                    <i class="fas fa-chart-bar"></i> Analyze
                                </button>
                            </div>
                        </div>
                    </div>
                `,
                5: `
                    <h2 style="color:#f59e0b; margin-bottom: 15px;"><i class="fas fa-user-cog"></i> Personal Settings</h2>
                    <p>Update your personal information and account settings.</p>
                    
                    <div class="action-buttons" style="margin-bottom: 20px;">
                        <button class="btn btn-warning" onclick="scrollToProfileForm()">
                            <i class="fas fa-edit"></i> Profile Settings
                        </button>
                    </div>
                    
                    <div class="action-grid">
                        <div class="action-card" onclick="scrollToProfileForm()">
                            <h4><i class="fas fa-user-edit"></i> Profile Settings</h4>
                            <ul class="action-list">
                                <li><i class="fas fa-check"></i> Update personal details</li>
                                <li><i class="fas fa-check"></i> Change contact information</li>
                                <li><i class="fas fa-check"></i> Update password</li>
                            </ul>
                            <div class="action-buttons">
                                <button class="btn btn-warning" onclick="scrollToProfileForm()">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </button>
                            </div>
                        </div>
                    </div>
                `
            };
            
            content.innerHTML = stepsContent[num] || stepsContent[1];
        }

        // Helper functions
        function scrollToExaminationForm() {
            document.getElementById('examination-form').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function scrollToPrescriptionForm() {
            document.getElementById('prescription-form').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function scrollToLabForm() {
            document.getElementById('lab-form').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function scrollToLabResults() {
            document.querySelector('.lab-results-section').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function scrollToProfileForm() {
            document.getElementById('profile-form').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        // Function to show all results in modal
        function showAllResults() {
            const modal = document.getElementById('allResultsModal');
            const container = document.getElementById('allResultsContainer');
            
            // Show modal
            modal.style.display = 'block';
            
            // Load all results via AJAX
            fetch('get_all_lab_results.php')
                .then(response => response.text())
                .then(data => {
                    container.innerHTML = data;
                })
                .catch(error => {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #ef4444;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2em;"></i>
                            <p>Error loading results. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Function to close modal
        function closeAllResults() {
            document.getElementById('allResultsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('allResultsModal');
            if (event.target === modal) {
                closeAllResults();
            }
        }

        // Function to view full result
        function viewFullResult(testId) {
            // You can implement a detailed view modal here
            alert('Viewing full details for test ID: ' + testId + '\nThis would show complete results in a detailed view.');
        }

        // Function to print test result
        function printTestResult(testId) {
            window.open('print_lab_result.php?id=' + testId, '_blank');
        }

        function refreshLabResults() {
            location.reload();
        }

        function analyzeResults() {
            alert('Laboratory results analysis feature will be implemented soon.');
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