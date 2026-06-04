<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$leave_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($leave_id <= 0) {
    die("Invalid leave request ID");
}

// Get current user's role and personnel number
$current_user_role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;
$current_personnel_number = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';

// Database connection with proper collation
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch leave request details with all related data from personnel table
try {
    $sql = "SELECT lr.*, 
                   p.personnel_number,
                   p.full_name_en as personnel_name, 
                   p.rank,
                   p.unit,
                   p.appointment,
                   p.signature as personnel_signature,
                   p.contact,
                   p.address,
                   p.province,
                   p.district,
                   p.municipality,
                   p.ward_number,
                   p.village_tole,
                   io.full_name_en as initiating_officer_name, 
                   io.rank as initiating_officer_rank,
                   io.signature as initiating_officer_signature,
                   ao.full_name_en as accepting_officer_name,
                   ao.rank as accepting_officer_rank,
                   ao.signature as accepting_officer_signature,
                   vo.full_name_en as verifying_officer_name,
                   vo.rank as verifying_officer_rank,
                   vo.signature as verifying_officer_signature,
                   receiver.full_name_en as receiver_name,
                   receiver.rank as receiver_rank,
                   lb.gharpari_bida_days, 
                   lb.parba_bida_days, 
                   lb.bhaeepari_bida_days
            FROM leave_requests lr
            INNER JOIN personnel p ON lr.personnel_id COLLATE utf8mb4_unicode_ci = p.personnel_number
            LEFT JOIN personnel io ON lr.initiating_officer COLLATE utf8mb4_unicode_ci = io.personnel_number
            LEFT JOIN personnel ao ON lr.accepting_officer COLLATE utf8mb4_unicode_ci = ao.personnel_number
            LEFT JOIN personnel vo ON lr.verifying_officer COLLATE utf8mb4_unicode_ci = vo.personnel_number
            LEFT JOIN personnel receiver ON lr.receiver_id COLLATE utf8mb4_unicode_ci = receiver.personnel_number
            LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
            WHERE lr.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave) {
        die("Leave request not found.");
    }

    // Check if user is authorized to view this leave
    $is_authorized = false;
    if ($current_user_role >= 1) { // Admin or Super Admin
        $is_authorized = true;
    } elseif ($current_personnel_number == $leave['personnel_number']) { // The personnel who submitted
        $is_authorized = true;
    } elseif ($current_personnel_number == $leave['initiating_officer']) { // Initiating officer
        $is_authorized = true;
    } elseif ($current_personnel_number == $leave['accepting_officer']) { // Accepting officer
        $is_authorized = true;
    } elseif ($current_personnel_number == $leave['verifying_officer']) { // Verifying officer (Receiving)
        $is_authorized = true;
    }
    
    if (!$is_authorized) {
        die("You are not authorized to view this leave request.");
    }

    // Get unit and appointment details (already in leave array from JOIN)
    $unit = $leave['unit'] ?? 'श्री साइबर सुरक्षा निर्देशनालय';
    $appointment = $leave['appointment'] ?? '';

} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Calculate leave days
$start_date = new DateTime($leave['start_date']);
$end_date   = new DateTime($leave['end_date']);
$leave_days = $start_date->diff($end_date)->days + 1;

function formatNepaliDate($date) {
    return date('Y/m/d', strtotime($date));
}

function numberToNepaliWords($number) {
    $words = ['', 'एक', 'दुई', 'तीन', 'चार', 'पाँच', 'छ', 'सात', 'आठ', 'नौ', 'दस',
              'एघार', 'बाह्र', 'तेह्र', 'चौध', 'पन्ध्र', 'सोह्र', 'सत्र', 'अठार', 'उन्नाइस', 'बीस',
              'एक्काइस', 'बाइस', 'तेइस', 'चौबिस', 'पच्चिस', 'छब्बिस', 'सत्ताइस', 'अट्ठाइस', 'उनन्तिस', 'तीस'];
    return ($number <= 30) ? $words[$number] : $number;
}

$current_date = date('Y/m/d');

// Get location details with proper fallbacks
$province      = $leave['province']      ?? 'वागमती प्रदेश';
$district      = $leave['district']      ?? 'काठमाडौं';
$municipality  = $leave['municipality']  ?? 'चा.न.पा.';
$ward_number   = $leave['ward_number']   ?? '९';
$village_tole  = $leave['village_tole']  ?? $leave['address'] ?? '';
$address       = !empty($village_tole) ? $village_tole : ($leave['address'] ?? 'ताथली');

$gharpari_balance  = $leave['gharpari_bida_days']  ?? 0;
$parba_balance     = $leave['parba_bida_days']     ?? 0;
$bhaeepari_balance = $leave['bhaeepari_bida_days'] ?? 0;

// Leave type mapping
$leave_type_map = [
    'gharpari_bida' => ['text' => 'घर विदा', 'short' => 'घ.वि.'],
    'parba_bida' => ['text' => 'पर्व विदा', 'short' => 'प.वि.'],
    'bhaeepari_bida' => ['text' => 'भाइपरी विदा', 'short' => 'भै.वि.'],
    'annual' => ['text' => 'वार्षिक विदा', 'short' => 'वा.वि.'],
    'sick' => ['text' => 'बिरामी विदा', 'short' => 'बि.वि.'],
    'casual' => ['text' => 'साधारण विदा', 'short' => 'सा.वि.'],
    'emergency' => ['text' => 'आपत्कालीन विदा', 'short' => 'आ.वि.']
];

$leave_type_text = $leave_type_map[$leave['leave_type']]['text'] ?? 'विदा';

$last_leave_date = $leave['created_at'] ? date('Y/m/d', strtotime($leave['created_at'])) : '';
$last_leave_end  = $leave['created_at'] ? date('Y/m/d', strtotime($leave['created_at'] . ' +7 days')) : '';

// Function to get correct image path
function getSignaturePath($signature_path) {
    if (empty($signature_path)) {
        return null;
    }
    
    $path = ltrim($signature_path, '/');
    
    $possible_paths = [
        $path,
        '../' . $path,
        '../../' . $path,
        $_SERVER['DOCUMENT_ROOT'] . '/' . $path,
    ];
    
    foreach ($possible_paths as $test_path) {
        if (file_exists($test_path)) {
            return $test_path;
        }
    }
    
    return null;
}

// Function to display signature image
function displaySignatureImage($signature_path, $person_name) {
    if (empty($signature_path)) {
        return '';
    }
    
    $image_path = getSignaturePath($signature_path);
    
    if ($image_path && file_exists($image_path)) {
        $web_path = '/' . ltrim($signature_path, '/');
        return '<img src="' . htmlspecialchars($web_path) . '" style="height: 40px; width: auto; max-width: 120px;" alt="हस्ताक्षर - ' . htmlspecialchars($person_name) . '">';
    }
    
    return '';
}

// Determine which signatures to show based on approval status
$show_verifying_signature = ($leave['verifying_officer_approved'] == 1);
$show_initiating_signature = ($leave['initiating_officer_approved'] == 1);
$show_accepting_signature = ($leave['accepting_officer_approved'] == 1);
$is_fully_approved = ($leave['status'] === 'approved');

// Get receiver info - priority: receiver_id > verifying_officer (if approved)
$receiver_display_name = '';
$receiver_display_rank = '';
if (!empty($leave['receiver_name'])) {
    $receiver_display_name = $leave['receiver_name'];
    $receiver_display_rank = $leave['receiver_rank'];
} elseif ($show_verifying_signature && !empty($leave['verifying_officer_name'])) {
    $receiver_display_name = $leave['verifying_officer_name'];
    $receiver_display_rank = $leave['verifying_officer_rank'];
}

// Only show status indicator if NOT approved
$show_status_indicator = ($leave['status'] !== 'approved');
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विदा माग - <?php echo htmlspecialchars($leave['personnel_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @font-face {
            font-family: 'Kalimati';
            src: url('fonts/kalimati.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: 'Times New Roman', 'Kalimati', 'Nirmala UI', serif;
            background: #d0d0d0;
            padding: 20px;
            font-size: 12.5px;
            line-height: 1.5;
            color: #111;
        }

        .page-wrap {
            max-width: 780px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            position: relative;
        }

        .pass {
            padding: 20px 25px 25px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 12px;
            margin-bottom: 8px;
            gap: 15px;
        }

        .left-column, .center-column, .right-column {
            flex: 1;
        }
        
        .left-column { text-align: left; line-height: 1.6; }
        .center-column { text-align: center; line-height: 1.6; }
        .right-column { text-align: left; line-height: 1.6; }

        .right-label {
            display: inline-block;
            width: 60px;
            font-weight: normal;
        }

        .right-value {
            display: inline-block;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-decoration: underline;
            margin: 12px 0 12px;
            letter-spacing: 1px;
        }

        .body-text p { margin-bottom: 5px; }

        .section-label {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .indent { padding-left: 25px; }

        .balance-table {
            border-collapse: collapse;
            margin: 4px 0 4px 25px;
            font-size: 12.5px;
        }
        
        .balance-table td {
            padding: 1px 6px;
        }
        
        .balance-table td:first-child { 
            padding-left: 0;
            padding-right: 8px;
        }

        .address-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 4px 0 4px 25px;
        }
        
        .address-item {
            white-space: nowrap;
        }

        .signatures-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 20px;
            margin-bottom: 30px;
            gap: 0;
        }
        
        .receiver-signature-area {
            flex: 0 0 auto;
            text-align: left;
            padding-left: 0;
        }
        
        .receiver-signature-wrapper {
            text-align: left;
            display: inline-block;
        }
        
        .receiver-officer-name {
            text-align: left;
            margin-top: 3px;
        }
        
        .applicant-signature-area {
            flex: 0 0 auto;
            text-align: right;
            padding-right: 0;
        }
        
        .applicant-signature-wrapper {
            text-align: right;
            display: inline-block;
        }
        
        .signature-container {
            display: inline-block;
            text-align: center;
        }
        
        .sig-line {
            border-bottom: 1px dotted #333;
            min-width: 160px;
            margin-top: 3px;
        }
        
        .applicant-officer-name {
            text-align: right;
            margin-top: 3px;
        }

        .bottom-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            margin-bottom: 0;
            align-items: flex-start;
            gap: 30px;
        }

        .left-signature {
            flex: 1;
            text-align: left;
        }

        .right-signature {
            flex: 1;
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .signature-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 6px;
        }

        .signature-field {
            margin: 8px 0;
        }

        .signature-field .field-label {
            font-weight: normal;
            min-width: 60px;
            display: inline-block;
        }

        .signature-field .field-value {
            display: inline-block;
            vertical-align: middle;
        }
        
        .signature-field .signature-container {
            display: inline-block;
        }

        .right-signature .signature-wrapper {
            text-align: center;
            display: inline-block;
        }
        
        .right-signature .accepting-officer-name {
            text-align: center;
            margin-top: 3px;
        }
        
        .status-indicator {
            background: #fef3c7;
            color: #92400e;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .status-verified {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .status-initiated {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .page-wrap { box-shadow: none; }
            .pass { padding: 12px 18px 18px; }
            .status-indicator { display: none; }
        }

        @media (max-width: 600px) {
            .address-grid {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            .signatures-wrapper {
                flex-direction: column;
                gap: 20px;
            }
            .receiver-signature-area {
                text-align: left;
            }
            .applicant-signature-area {
                text-align: right;
            }
            .bottom-signatures {
                flex-direction: column;
            }
            .right-signature {
                align-items: flex-start;
                margin-top: 20px;
            }
        }

        .button-bar {
            text-align: center;
            padding: 10px;
            background: #f0f0f0;
            border-top: 1px solid #ccc;
        }
        
        .btn {
            padding: 6px 18px;
            margin: 0 6px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }
        
        .btn-print { background: #2c5f4e; color: #fff; }
        .btn-back  { background: #6c757d; color: #fff; }
        
        .text-muted {
            color: #999;
        }
        
        .text-warning {
            color: #ff6600;
        }
        
        .italic {
            font-style: italic;
        }
        
        .small-text {
            font-size: 11px;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .text-info {
            color: #17a2b8;
        }
    </style>
</head>
<body>

<div class="page-wrap">
<div class="pass">
    <div class="content-wrapper">
        <!-- Status Indicator - Only show if NOT approved -->
        <?php if ($show_status_indicator): ?>
        <div class="status-indicator 
            <?php echo ($leave['status'] === 'verified') ? 'status-verified' : ''; ?>
            <?php echo ($leave['status'] === 'initiating_approved') ? 'status-initiated' : ''; ?>">
            <?php if ($leave['status'] === 'initiating_approved'): ?>
                ⚠️ यो विदा प्रारम्भिक स्वीकृत भएको छ, अन्तिम स्वीकृतिको पर्खाइमा | Status: Awaiting Final Approval
            <?php elseif ($leave['status'] === 'verified'): ?>
                📋 यो विदा प्राप्त गर्ने अधिकृतले हस्ताक्षर गरिसकेको छ, Initiating Officer को पर्खाइमा | Status: Verified (Awaiting Initiating Officer)
            <?php elseif ($leave['status'] === 'pending'): ?>
                ⏳ यो विदा प्राप्त गर्ने अधिकृतको पर्खाइमा | Status: Pending (Awaiting Receiving Officer)
            <?php else: ?>
                ⚠️ यो विदा अझै स्वीकृत भएको छैन | Status: <?php echo strtoupper($leave['status']); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- TOP HEADER -->
        <div class="top-header">
            <div class="left-column">
                व्य.नं.:- <?php echo htmlspecialchars($leave['personnel_number']); ?><br>
                <?php if (!empty($appointment)): ?>
                    नियुक्तिः- <?php echo htmlspecialchars($appointment); ?>
                <?php else: ?>
                    नियुक्तिः- ----------
                <?php endif; ?>
            </div>
            
            <div class="center-column">
                दर्जाः- <?php echo htmlspecialchars($leave['rank']); ?>
            </div>
            
            <div class="right-column">
                <div><span class="right-label">नामथरः-</span> <span class="right-value"><?php echo htmlspecialchars($leave['personnel_name']); ?></span></div>
                <div><span class="right-label">युनिटः-</span> <span class="right-value"><?php echo htmlspecialchars($unit); ?></span></div>
                <div><span class="right-label"></span> <span class="right-value">जंगी अड्डा</span></div>
                <div><span class="right-label">मितिः-</span> <span class="right-value"><?php echo $current_date; ?> गते ।</span></div>
            </div>
        </div>

        <div style="margin-bottom:3px;">
            श्रीमान निर्देशकज्यू,<br>
            श्री साइबर सुरक्षा निर्देशनालय,<br>
            जंगी अड्डा ।
        </div>

        <div class="title">विदा माग</div>

        <div class="body-text">
            <p>महोदय,</p>

            <p>१. मेरो घरायसी काम परेको हुँदा मेरो संचित विदाबाट कट्टा हुने गरि मिति <?php echo formatNepaliDate($leave['start_date']); ?> गतेदेखि <?php echo formatNepaliDate($leave['end_date']); ?> गतेसम्म दिन-<?php echo $leave_days; ?> (<?php echo numberToNepaliWords($leave_days); ?>) <?php echo htmlspecialchars($leave_type_text); ?> पाउन अनुरोध गर्दछु ।</p>

            <p class="section-label">२. संचित विदाको बिबरण</p>
            <table class="balance-table">
                <tr>
                    <td>(क)</td>
                    <td>घ.वि.: <?php echo $gharpari_balance; ?></td>
                </tr>
                <tr>
                    <td>(ख)</td>
                    <td>भै.वि.: <?php echo $bhaeepari_balance; ?></td>
                </tr>
                <tr>
                    <td>(ग)</td>
                    <td>प.वि.: <?php echo $parba_balance; ?></td>
                </tr>
            </table>

            <p class="section-label">३. विदा गएको बिबरण</p>
            <p class="indent">(क) पछिल्लो पटक घ.वि./भै.वि./प.वि. गएको दिनः- <?php echo $leave_days; ?></p>
            <p class="indent">(ख) पछिल्लो पटक विदा गएको मितिः- <?php echo $last_leave_date; ?> गतेदेखि <?php echo $last_leave_end; ?> गतेसम्म ।</p>

            <p class="section-label">४. विदामा रहँदाको सम्पर्क ठेगाना</p>
            
            <div class="address-grid">
                <div class="address-item">(क) प्रदेश :- <?php echo htmlspecialchars($province); ?></div>
                <div class="address-item">(ख) जिल्ला :- <?php echo htmlspecialchars($district); ?></div>
                <div class="address-item">(ग) न.पा./गा.पा :- <?php echo htmlspecialchars($municipality); ?></div>
                <div class="address-item">(घ) वडा नं. :- <?php echo htmlspecialchars($ward_number); ?></div>
                <div class="address-item">(ङ) गाउँ/टोल :- <?php echo htmlspecialchars($address); ?></div>
                <div class="address-item"></div>
            </div>

            <p style="margin-top:6px;">५. समाविष्ट कागज (केही प्रमाण भएमा) :- </p>
        </div>

        <!-- SIGNATURES SECTION: RECEIVER (LEFT CORNER) AND APPLICANT (RIGHT CORNER) -->
        <div class="signatures-wrapper">
            <!-- LEFT CORNER: RECEIVER SIGNATURE (प्राप्त गर्ने व्यक्ति) -->
            <div class="receiver-signature-area">
                <div class="receiver-signature-wrapper">
                    <div class="signature-title">प्राप्त गर्ने व्यक्ति (Receiver)</div>
                    <div class="signature-container">
                        <?php 
                        if ($show_verifying_signature && !empty($leave['verifying_officer_signature'])) {
                            echo displaySignatureImage($leave['verifying_officer_signature'], $leave['verifying_officer_name']);
                        } elseif ($show_verifying_signature && empty($leave['verifying_officer_signature'])) {
                            echo '<div class="text-warning italic small-text">(हस्ताक्षर थपिएको छैन)</div>';
                        } else {
                            echo '<div class="text-muted italic small-text">(प्राप्त गर्ने अधिकृतको पर्खाइमा)</div>';
                        }
                        ?>
                        <div class="sig-line"></div>
                    </div>
                </div>
                <div class="receiver-officer-name">
                    <?php if ($show_verifying_signature && !empty($leave['verifying_officer_name'])): ?>
                        (<?php echo htmlspecialchars($leave['verifying_officer_rank'] ?? '') . ' ' . htmlspecialchars($leave['verifying_officer_name'] ?? ''); ?>)
                    <?php elseif ($show_verifying_signature && empty($leave['verifying_officer_name'])): ?>
                        <span class="text-warning small-text">(प्राप्त गर्नेको नाम थपिएको छैन)</span>
                    <?php else: ?>
                        <span class="text-muted small-text">(प्राप्त गर्ने अधिकृतको पर्खाइमा)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT CORNER: APPLICANT SIGNATURE (आज्ञाकारी) -->
            <div class="applicant-signature-area">
                <div class="applicant-signature-wrapper">
                    <div class="signature-title">आज्ञाकारी (Applicant)</div>
                    <div class="signature-container">
                        <?php 
                        $personnel_sig = displaySignatureImage($leave['personnel_signature'], $leave['personnel_name']);
                        if ($personnel_sig) {
                            echo $personnel_sig;
                        }
                        ?>
                        <div class="sig-line"></div>
                    </div>
                </div>
                <div class="applicant-officer-name">
                    (<?php echo htmlspecialchars($leave['rank']) . ' ' . htmlspecialchars($leave['personnel_name']); ?>)
                </div>
            </div>
        </div>
    </div>

    <!-- BOTTOM SIGNATURES SECTION (Initiating and Accepting Officers) -->
    <div class="bottom-signatures">
        <div class="left-signature">
            <div class="signature-title">सिफारिस गर्ने</div>
            <p>निवेदकलाई घ.वि./क्या.वि./प.वि. बाटो म्याद सहित,<br>विदा छाड्न सिफारिस गर्दछु ।</p>
            <div class="signature-field">
                <span class="field-label">दस्तखत :</span>
                <span class="field-value">
                    <div class="signature-container">
                        <?php 
                        if ($show_initiating_signature && !empty($leave['initiating_officer_signature'])) {
                            echo displaySignatureImage($leave['initiating_officer_signature'], $leave['initiating_officer_name']);
                        } elseif ($show_initiating_signature) {
                            echo '<span class="text-warning italic small-text">(हस्ताक्षर थपिएको छैन)</span>';
                        } else {
                            echo '<span class="text-muted italic small-text">(प्रारम्भिक स्वीकृत पश्चात् देखिनेछ)</span>';
                        }
                        ?>
                        <div class="sig-line"></div>
                    </div>
                </span>
            </div>
            <div class="signature-field">
                <span class="field-label">नामथर :</span>
                <span class="field-value"><?php echo htmlspecialchars($leave['initiating_officer_name'] ?? ''); ?></span>
            </div>
            <div class="signature-field">
                <span class="field-label">दर्जा :</span>
                <span class="field-value"><?php echo htmlspecialchars($leave['initiating_officer_rank'] ?? ''); ?></span>
            </div>
            <div class="signature-field">
                <span class="field-label">नियुक्ति :</span>
                <span class="field-value">प्र.उ.से.</span>
            </div>
        </div>

        <!-- RIGHT BOTTOM: स्वीकृत गर्ने (Accepting Officer) -->
        <div class="right-signature">
            <div class="signature-wrapper">
                <div class="signature-title">स्वीकृत गर्ने</div>
                <div class="signature-container">
                    <?php 
                    if ($show_accepting_signature) {
                        if (!empty($leave['accepting_officer_signature'])) {
                            echo displaySignatureImage($leave['accepting_officer_signature'], $leave['accepting_officer_name']);
                        } else {
                            echo '<div class="text-warning italic small-text">(हस्ताक्षर थपिएको छैन)</div>';
                        }
                    } else {
                        echo '<div class="text-muted italic small-text">(अन्तिम स्वीकृत पश्चात् मात्र देखिनेछ)</div>';
                    }
                    ?>
                    <div class="sig-line"></div>
                </div>
            </div>
            <div class="accepting-officer-name">
                <?php if ($show_accepting_signature && !empty($leave['accepting_officer_name'])): ?>
                    (<?php echo htmlspecialchars($leave['accepting_officer_rank'] ?? '') . ' ' . htmlspecialchars($leave['accepting_officer_name'] ?? ''); ?>)
                <?php elseif ($show_accepting_signature && empty($leave['accepting_officer_name'])): ?>
                    <span class="text-warning small-text">(स्वीकृत गर्नेको नाम थपिएको छैन)</span>
                <?php else: ?>
                    <span class="text-muted small-text">(अन्तिम स्वीकृत पश्चात् मात्र)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<div class="button-bar no-print">
    <button class="btn btn-print" onclick="window.print()">🖨 प्रिन्ट गर्नुहोस्</button>
    <button class="btn btn-back" onclick="window.location.href='leave.php'">← फिर्ता जानुहोस्</button>
</div>
</div>

<script>
    setTimeout(function() {
        if (confirm('के तपाईं यो विदा माग प्रिन्ट गर्न चाहनुहुन्छ?')) {
            window.print();
        }
    }, 500);
</script>
</body>
</html>