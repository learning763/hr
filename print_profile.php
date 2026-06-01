<?php
session_start();
include('includes/config.php');

// Check if personnel_number is provided
if (!isset($_GET['personnel_number']) || empty($_GET['personnel_number'])) {
    die("No personnel selected. Please go back and select a personnel.");
}

$personnel_number = $_GET['personnel_number'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch personnel details
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_number = ?");
$stmt->execute([$personnel_number]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel) {
    die("Personnel not found.");
}

function calculateYearsOfService($join_date) {
    if (!$join_date || $join_date == '0000-00-00') return 'N/A';
    $join = new DateTime($join_date);
    $today = new DateTime();
    $diff = $join->diff($today);
    return $diff->y . ' years ' . $diff->m . ' months';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Profile - <?php echo htmlspecialchars($personnel['full_name_en']); ?> | Nepali Army</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Arial, sans-serif;
            background: white;
            padding: 20px;
        }
        
        /* A4 Print Styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none;
            }
            .print-wrapper {
                padding: 15px;
            }
        }
        
        /* Screen Styles */
        @media screen {
            body {
                background: #e2e8f0;
                padding: 30px;
            }
            .print-wrapper {
                max-width: 1100px;
                margin: 0 auto;
                background: white;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border-radius: 12px;
            }
            .print-button {
                text-align: center;
                padding: 20px;
                background: white;
                margin-bottom: 20px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .print-btn {
                background: #1a5a4a;
                color: white;
                border: none;
                padding: 12px 30px;
                font-size: 16px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                margin: 0 10px;
                transition: all 0.3s;
            }
            .print-btn:hover {
                background: #0f3d32;
                transform: translateY(-2px);
            }
            .close-btn {
                background: #dc2626;
                color: white;
                border: none;
                padding: 12px 30px;
                font-size: 16px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                margin: 0 10px;
                transition: all 0.3s;
            }
            .close-btn:hover {
                background: #b91c1c;
                transform: translateY(-2px);
            }
        }
        
        /* Print Layout - A4 Optimized */
        .print-wrapper {
            padding: 20px;
            font-size: 11px;
            line-height: 1.4;
        }
        
        /* Header Section */
        .print-header {
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 3px solid #1a5a4a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .print-logo {
            font-size: 55px;
            color: #1a5a4a;
        }
        
        .print-title h1 {
            font-size: 24px;
            color: #1a5a4a;
            margin: 0;
            letter-spacing: 2px;
        }
        
        .print-title p {
            margin: 5px 0;
            font-size: 12px;
            color: #475569;
        }
        
        .print-title span {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Photo & Basic Info */
        .print-photo-section {
            display: flex;
            gap: 25px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            break-inside: avoid;
        }
        
        .print-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1a5a4a;
            background: white;
        }
        
        .print-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #94a3b8;
            border: 3px solid #1a5a4a;
        }
        
        .print-basic-info h2 {
            margin: 0 0 10px 0;
            font-size: 20px;
            color: #1e293b;
        }
        
        .print-basic-info p {
            margin: 5px 0;
            font-size: 11px;
            color: #475569;
        }
        
        /* Section Styles */
        .print-section {
            break-inside: avoid;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .print-section h3 {
            color: #1a5a4a;
            border-bottom: 2px solid #1a5a4a;
            padding-bottom: 5px;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Table Styles */
        .print-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .print-table th,
        .print-table td {
            text-align: left;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 10.5px;
            border-bottom: 0.5px solid #e2e8f0;
        }
        
        .print-table th {
            width: 30%;
            font-weight: 600;
            color: #475569;
            background: #f8fafc;
        }
        
        .print-table td {
            width: 70%;
            color: #334155;
        }
        
        /* Training Table */
        .print-training-table th,
        .print-training-table td {
            padding: 5px 6px;
            font-size: 10px;
        }
        
        .print-training-table thead th {
            background: #f1f5f9;
            font-weight: 600;
        }
        
        /* Footer */
        .print-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        
        /* Status Badge */
        .status-print {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-leave {
            background: #fed7aa;
            color: #9a3412;
        }
        
        .status-retired {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Blood Badge */
        .blood-print {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .print-photo-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="print-button no-print">
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print / Save as PDF
    </button>
    <button class="close-btn" onclick="window.close()">
        <i class="fas fa-times"></i> Close Window
    </button>
</div>

<div class="print-wrapper">
    <!-- Header -->
    <div class="print-header">
        <div class="print-logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="print-title">
            <h1>NEPALI ARMY</h1>
            <p>DIRECTORATE OF CYBER SECURITY</p>
            <span>COMPLETE PERSONNEL PROFILE - OFFICIAL DOCUMENT</span>
        </div>
    </div>
    
    <!-- Photo & Basic Information -->
    <div class="print-photo-section">
        <?php if (!empty($personnel['profile_picture_path']) && file_exists($personnel['profile_picture_path'])): ?>
            <img src="<?php echo $personnel['profile_picture_path']; ?>" class="print-photo" alt="Profile Photo">
        <?php else: ?>
            <div class="print-photo-placeholder">
                <i class="fas fa-user"></i>
            </div>
        <?php endif; ?>
        <div class="print-basic-info">
            <h2><?php echo htmlspecialchars($personnel['full_name_en']); ?></h2>
            <p><strong>Personnel Number:</strong> <?php echo htmlspecialchars($personnel['personnel_number']); ?></p>
            <p><strong>Rank:</strong> <?php echo htmlspecialchars($personnel['rank']); ?></p>
            <p><strong>Unit:</strong> <?php echo htmlspecialchars($personnel['unit'] ?? 'Corps of Engineers'); ?></p>
            <p><strong>Current Status:</strong> <span class="status-print status-<?php echo strtolower($personnel['current_status'] ?? 'active'); ?>"><?php echo htmlspecialchars($personnel['current_status'] ?? 'Active'); ?></span></p>
            <p><strong>Years of Service:</strong> <?php echo calculateYearsOfService($personnel['recruitment_date'] ?? null); ?></p>
        </div>
    </div>
    
    <!-- PERSONAL INFORMATION -->
    <div class="print-section">
        <h3><i class="fas fa-user-circle"></i> PERSONAL INFORMATION</h3>
        <table class="print-table">
            <tr>
                <th>Full Name (English):</th>
                <td><?php echo htmlspecialchars($personnel['full_name_en'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Full Name (Nepali):</th>
                <td><?php echo htmlspecialchars($personnel['full_name_ne'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Date of Birth:</th>
                <td><?php echo $personnel['dob'] && $personnel['dob'] != '0000-00-00' ? date('F j, Y', strtotime($personnel['dob'])) : 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Gender:</th>
                <td><?php echo htmlspecialchars($personnel['gender'] ?? 'Not specified'); ?></td>
            </tr>
            <tr>
                <th>Blood Group:</th>
                <td><span class="blood-print"><?php echo htmlspecialchars($personnel['blood_group'] ?? 'N/A'); ?></span></td>
            </tr>
            <tr>
                <th>Religion:</th>
                <td><?php echo htmlspecialchars($personnel['religion'] ?? 'Hindu'); ?></td>
            </tr>
            <tr>
                <th>Email:</th>
                <td><?php echo htmlspecialchars($personnel['email'] ?? 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Mobile Number:</th>
                <td><?php echo htmlspecialchars($personnel['contact'] ?? 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Phone (Landline):</th>
                <td><?php echo htmlspecialchars($personnel['phone'] ?? 'Not provided'); ?></td>
            </tr>
            <tr>
                <th>Address:</th>
                <td><?php echo htmlspecialchars($personnel['address'] ?? 'Tripurapur, Kathmandu'); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- OFFICIAL INFORMATION -->
    <div class="print-section">
        <h3><i class="fas fa-briefcase"></i> OFFICIAL INFORMATION</h3>
        <table class="print-table">
            <tr>
                <th>Military Status:</th>
                <td><?php echo htmlspecialchars($personnel['military_status'] ?? 'Single'); ?></td>
            </tr>
            <tr>
                <th>Recruitment / Enrollment Date:</th>
                <td><?php echo $personnel['recruitment_date'] && $personnel['recruitment_date'] != '0000-00-00' ? date('F j, Y', strtotime($personnel['recruitment_date'])) : 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Commission Date:</th>
                <td><?php echo $personnel['commission_date'] && $personnel['commission_date'] != '0000-00-00' ? date('F j, Y', strtotime($personnel['commission_date'])) : 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Years of Service:</th>
                <td><?php echo calculateYearsOfService($personnel['recruitment_date'] ?? null); ?></td>
            </tr>
            <tr>
                <th>Current Status:</th>
                <td><span class="status-print status-<?php echo strtolower($personnel['current_status'] ?? 'active'); ?>"><?php echo htmlspecialchars($personnel['current_status'] ?? 'Active'); ?></span></td>
            </tr>
        </table>
    </div>
    
    <!-- FAMILY INFORMATION -->
    <div class="print-section">
        <h3><i class="fas fa-users"></i> FAMILY INFORMATION</h3>
        <table class="print-table">
            <tr>
                <th>Father's Name:</th>
                <td><?php echo htmlspecialchars($personnel['father_name'] ?? 'Not specified'); ?></td>
            </tr>
            <tr>
                <th>Mother's Name:</th>
                <td><?php echo htmlspecialchars($personnel['mother_name'] ?? 'Not specified'); ?></td>
            </tr>
            <tr>
                <th>Spouse's Name:</th>
                <td><?php echo htmlspecialchars($personnel['spouse_name'] ?? 'Not specified'); ?></td>
            </tr>
            <tr>
                <th>Grandfather's Name:</th>
                <td><?php echo htmlspecialchars($personnel['grandfather_name'] ?? 'Not specified'); ?></td>
            </tr>
            <tr>
                <th>Children:</th>
                <td><?php echo htmlspecialchars($personnel['children_names'] ?? 'None'); ?></td>
            </tr>
            <tr>
                <th>Family Notes:</th>
                <td><?php echo nl2br(htmlspecialchars($personnel['family_notes'] ?? 'No additional information')); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- EDUCATION & QUALIFICATIONS -->
    <div class="print-section">
        <h3><i class="fas fa-graduation-cap"></i> EDUCATION & QUALIFICATIONS</h3>
        <table class="print-table">
            <tr>
                <th>Academic Qualifications:</th>
                <td><?php echo nl2br(htmlspecialchars($personnel['higher_education'] ?? 'N/A')); ?></td>
            </tr>
            <tr>
                <th>Military Trainings:</th>
                <td><?php echo nl2br(htmlspecialchars($personnel['military_trainings'] ?? 'N/A')); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- PROFESSIONAL TRAININGS - ALL 6 -->
    <div class="print-section">
        <h3><i class="fas fa-chalkboard-teacher"></i> PROFESSIONAL TRAININGS</h3>
        <table class="print-table print-training-table">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="width: 10%;">S.No</th>
                    <th style="width: 55%;">Training Name</th>
                    <th style="width: 35%;">Location</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?php echo htmlspecialchars($personnel['training'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($personnel['training_address'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>2</td>
                    <td><?php echo htmlspecialchars($personnel['training1'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($personnel['training1_address'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>3</td>
                    <td><?php echo htmlspecialchars($personnel['training2'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($personnel['training2_address'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>4</td>
                    <td><?php echo htmlspecialchars($personnel['training3'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($personnel['training3'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>5</td>
                    <td><?php echo htmlspecialchars($personnel['training4'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($personnel['training4'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>6</td>
                    <td><?php echo htmlspecialchars($personnel['training5'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($personnel['training5'] ?? '-'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- FOREIGN TRAINING -->
    <div class="print-section">
        <h3><i class="fas fa-globe"></i> FOREIGN TRAINING</h3>
        <table class="print-table">
            <tr>
                <th>Foreign Training Details:</th>
                <td><?php echo nl2br(htmlspecialchars($personnel['foreign_training'] ?? 'Not specified')); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Footer -->
    <div class="print-footer">
        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?> | Nepali Army - Directorate of Cyber Security | This is a system generated official document</p>
        <p>Signature: _________________________ | Stamp: _________________________</p>
    </div>
</div>

</body>
</html>