<?php
require_once '../config.php';

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get patient ID from URL or session
$patient_id = $_GET['patient_id'] ?? $_SESSION['patient_id'] ?? 0;

if (!$patient_id) {
    die('Patient ID not specified');
}

// Get patient details
$patient_stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$patient_stmt->execute([$patient_id]);
$patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient not found');
}

// Get medical history (checking_forms) for this patient
$history_stmt = $pdo->prepare("SELECT cf.*, u.full_name as doctor_name 
                              FROM checking_forms cf 
                              LEFT JOIN users u ON cf.doctor_id = u.id 
                              WHERE cf.patient_id = ? 
                              ORDER BY cf.created_at DESC");
$history_stmt->execute([$patient_id]);
$medical_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescriptions history
$prescriptions_stmt = $pdo->prepare("SELECT p.*, cf.created_at as checkup_date, u.full_name as doctor_name
                                    FROM prescriptions p 
                                    JOIN checking_forms cf ON p.checking_form_id = cf.id 
                                    LEFT JOIN users u ON cf.doctor_id = u.id 
                                    WHERE cf.patient_id = ? 
                                    ORDER BY cf.created_at DESC");
$prescriptions_stmt->execute([$patient_id]);
$prescriptions_history = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lab tests history
$lab_tests_stmt = $pdo->prepare("SELECT lt.*, cf.created_at as checkup_date, u.full_name as doctor_name,
                                u2.full_name as conducted_by_name
                                FROM laboratory_tests lt 
                                JOIN checking_forms cf ON lt.checking_form_id = cf.id 
                                LEFT JOIN users u ON cf.doctor_id = u.id 
                                LEFT JOIN users u2 ON lt.conducted_by = u2.id 
                                WHERE cf.patient_id = ? 
                                ORDER BY cf.created_at DESC");
$lab_tests_stmt->execute([$patient_id]);
$lab_tests_history = $lab_tests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - Almajyd Dispensary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .patient-details {
            color: #666;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #2c5aa0;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3d6d;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tab {
            padding: 15px 30px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            color: #666;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }
        
        .tab.active {
            background: #2c5aa0;
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: #e9ecef;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid #2c5aa0;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-date {
            font-size: 14px;
            color: #666;
            font-weight: bold;
        }
        
        .card-doctor {
            font-size: 14px;
            color: #2c5aa0;
            font-weight: bold;
        }
        
        .card-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .card-content {
            margin-bottom: 15px;
        }
        
        .content-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .content-text {
            color: #333;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .table th {
            background: #2c5aa0;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        /* Search and Filter */
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                font-size: 12pt;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .header, .search-filter, .tabs, .card-actions {
                display: none;
            }
            
            .tab-content {
                display: block !important;
                box-shadow: none;
                padding: 0;
                margin-bottom: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            
            .table {
                font-size: 10pt;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .card-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
     <link rel="icon" href="../images/logo.jpg">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header no-print">
            <div class="patient-info">
                <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                <div class="patient-details">
                    Card No: <?php echo htmlspecialchars($patient['card_no']); ?> | 
                    Age: <?php echo $patient['age'] ? $patient['age'] . ' years' : 'N/A'; ?> | 
                    Gender: <?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?> | 
                    Weight: <?php echo $patient['weight'] ? $patient['weight'] . ' kg' : 'N/A'; ?>
                </div>
            </div>
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Dashboard
                </a>
                <!-- Create New Checkup - Goes to Dashboard with patient pre-selected -->
                <a href="dashboard.php?create_checkup=1&patient_id=<?php echo $patient_id; ?>" class="btn btn-success">
                    ＋ New Checkup
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    🖨️ Print History
                </button>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter no-print">
            <div class="form-group">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="form-group">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="form-group">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="searchInput" placeholder="Search in symptoms, diagnosis...">
            </div>
            <div class="form-group">
                <button class="btn btn-primary" onclick="filterHistory()">
                    🔍 Filter
                </button>
                <button class="btn btn-secondary" onclick="clearFilters()">
                    🗑️ Clear
                </button>
            </div>
        </div>

        <!-- Print Header (Only shows when printing) -->
        <div class="print-header" style="display: none;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px double #333; padding-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                    <?php if (file_exists('../images/logo.jpg')): ?>
                        <img src="../images/logo.jpg" alt="Almajyd Dispensary Logo" style="width: 80px; height: 80px; border: 2px solid #2c5aa0; border-radius: 10px; margin-right: 20px;">
                    <?php else: ?>
                        <div style="width: 80px; height: 80px; border: 2px solid #2c5aa0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; margin-right: 20px; font-size: 12px; text-align: center; padding: 5px;">
                            ALMAJYD<br>DISPENSARY
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 style="font-size: 24px; color: #2c5aa0; margin: 0;">ALMAJYD DISPENSARY</h1>
                        <p style="color: #666; margin: 5px 0;">TOMONDO - ZANZIBAR</p>
                        <p style="color: #666; margin: 0;">MEDICAL HISTORY REPORT</p>
                    </div>
                </div>
                <div style="text-align: left; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <h3 style="color: #2c5aa0; margin-bottom: 10px;">Patient Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><strong>Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></div>
                        <div><strong>Card No:</strong> <?php echo htmlspecialchars($patient['card_no']); ?></div>
                        <div><strong>Age:</strong> <?php echo $patient['age'] ? $patient['age'] . ' years' : 'N/A'; ?></div>
                        <div><strong>Gender:</strong> <?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'N/A'; ?></div>
                        <div><strong>Weight:</strong> <?php echo $patient['weight'] ? $patient['weight'] . ' kg' : 'N/A'; ?></div>
                        <div><strong>Printed:</strong> <?php echo date('F j, Y \a\t h:i A'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs no-print">
            <button class="tab active" onclick="openTab('checkups')">Medical Checkups</button>
            <button class="tab" onclick="openTab('prescriptions')">Prescriptions</button>
            <button class="tab" onclick="openTab('lab-tests')">Lab Tests</button>
        </div>

        <!-- Checkups Tab -->
        <div id="checkups" class="tab-content active">
            <h3 style="margin-bottom: 20px; color: #2c5aa0;">Medical Checkup History</h3>
            
            <?php if (!empty($medical_history)): ?>
                <?php foreach ($medical_history as $checkup): ?>
                    <div class="card checkup-card" data-date="<?php echo date('Y-m-d', strtotime($checkup['created_at'])); ?>" 
                         data-content="<?php echo htmlspecialchars(strtolower($checkup['symptoms'] . ' ' . $checkup['diagnosis'])); ?>">
                        <div class="card-header">
                            <div class="card-date">
                                📅 <?php echo date('F j, Y \a\t h:i A', strtotime($checkup['created_at'])); ?>
                            </div>
                            <div class="card-doctor">
                                👨‍⚕️ Dr. <?php echo htmlspecialchars($checkup['doctor_name'] ?: 'Unknown'); ?>
                            </div>
                            <div class="card-status status-<?php echo $checkup['status']; ?>">
                                <?php echo strtoupper($checkup['status']); ?>
                            </div>
                        </div>
                        
                        <div class="card-content">
                            <?php if (!empty($checkup['symptoms'])): ?>
                                <div class="content-label">Symptoms:</div>
                                <div class="content-text"><?php echo htmlspecialchars($checkup['symptoms']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($checkup['diagnosis'])): ?>
                                <div class="content-label">Diagnosis:</div>
                                <div class="content-text"><?php echo htmlspecialchars($checkup['diagnosis']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($checkup['notes'])): ?>
                                <div class="content-label">Clinical Notes:</div>
                                <div class="content-text"><?php echo htmlspecialchars($checkup['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-actions no-print">
                            <a href="print_checkup.php?id=<?php echo $checkup['id']; ?>" target="_blank" class="btn btn-primary btn-sm">
                                📄 View Full Report
                            </a>
                            <a href="edit_checkup.php?id=<?php echo $checkup['id']; ?>" class="btn btn-secondary btn-sm">
                                ✏️ Edit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div>📋</div>
                    <h3>No Medical Checkups Found</h3>
                    <p>This patient hasn't had any medical checkups yet.</p>
                    <a href="dashboard.php?create_checkup=1&patient_id=<?php echo $patient_id; ?>" class="btn btn-success no-print" style="margin-top: 15px;">
                        ＋ Create First Checkup
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Prescriptions Tab -->
        <div id="prescriptions" class="tab-content">
            <h3 style="margin-bottom: 20px; color: #2c5aa0;">Prescription History</h3>
            
            <?php if (!empty($prescriptions_history)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Doctor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions_history as $prescription): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($prescription['checkup_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($prescription['medicine_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($prescription['dosage'] ?: 'As prescribed'); ?></td>
                                <td><?php echo htmlspecialchars($prescription['frequency'] ?: 'As directed'); ?></td>
                                <td><?php echo htmlspecialchars($prescription['duration'] ?: 'Until finished'); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($prescription['doctor_name'] ?: 'Unknown'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div>💊</div>
                    <h3>No Prescriptions Found</h3>
                    <p>No prescription history available for this patient.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Lab Tests Tab -->
        <div id="lab-tests" class="tab-content">
            <h3 style="margin-bottom: 20px; color: #2c5aa0;">Laboratory Test History</h3>
            
            <?php if (!empty($lab_tests_history)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Test Type</th>
                            <th>Description</th>
                            <th>Results</th>
                            <th>Status</th>
                            <th>Conducted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests_history as $test): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($test['checkup_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($test['test_type']); ?></strong></td>
                                <td><?php echo htmlspecialchars($test['test_description'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($test['results'] ?: 'Pending'); ?></td>
                                <td>
                                    <span class="card-status status-<?php echo $test['status']; ?>">
                                        <?php echo ucfirst($test['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($test['conducted_by_name'] ?: 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div>🔬</div>
                    <h3>No Lab Tests Found</h3>
                    <p>No laboratory test history available for this patient.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tab functionality
        function openTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            const tabs = document.getElementsByClassName('tab');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Filter functionality
        function filterHistory() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            
            const cards = document.getElementsByClassName('checkup-card');
            
            for (let card of cards) {
                const cardDate = card.getAttribute('data-date');
                const cardContent = card.getAttribute('data-content');
                
                let showCard = true;
                
                // Date filter
                if (dateFrom && cardDate < dateFrom) {
                    showCard = false;
                }
                if (dateTo && cardDate > dateTo) {
                    showCard = false;
                }
                
                // Search filter
                if (searchText && !cardContent.includes(searchText)) {
                    showCard = false;
                }
                
                card.style.display = showCard ? 'block' : 'none';
            }
        }
        
        function clearFilters() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('searchInput').value = '';
            
            const cards = document.getElementsByClassName('checkup-card');
            for (let card of cards) {
                card.style.display = 'block';
            }
        }
        
        // Set default date range to last 30 days
        window.onload = function() {
            const dateTo = new Date();
            const dateFrom = new Date();
            dateFrom.setDate(dateFrom.getDate() - 30);
            
            document.getElementById('dateTo').valueAsDate = dateTo;
            document.getElementById('dateFrom').valueAsDate = dateFrom;
        };

        // Print functionality with custom header
        window.onload = function() {
            const dateTo = new Date();
            const dateFrom = new Date();
            dateFrom.setDate(dateFrom.getDate() - 30);
            
            document.getElementById('dateTo').valueAsDate = dateTo;
            document.getElementById('dateFrom').valueAsDate = dateFrom;
            
            // Show print header when printing
            window.addEventListener('beforeprint', function() {
                document.querySelector('.print-header').style.display = 'block';
                document.querySelectorAll('.no-print').forEach(el => {
                    el.style.display = 'none';
                });
            });
            
            window.addEventListener('afterprint', function() {
                document.querySelector('.print-header').style.display = 'none';
                document.querySelectorAll('.no-print').forEach(el => {
                    el.style.display = '';
                });
            });
        };
    </script>
</body>
</html>