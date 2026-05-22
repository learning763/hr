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

// Fetch leave request details with all related data
try {
    $sql = "SELECT lr.*, 
                   mps.personnel_name, mps.rank, mps.personnel_number,
                   io.personnel_name as initiating_officer_name, 
                   io.rank as initiating_officer_rank,
                   ao.personnel_name as accepting_officer_name,
                   ao.rank as accepting_officer_rank,
                   p_io.signature as initiating_officer_signature,
                   p_ao.signature as accepting_officer_signature,
                   p_personnel.signature as personnel_signature,
                   lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days
            FROM leave_requests lr
            INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
            INNER JOIN personnel p_personnel ON mps.personnel_number = p_personnel.personnel_number
            LEFT JOIN military_personnel_status io ON lr.initiating_officer = io.id
            LEFT JOIN personnel p_io ON io.personnel_number = p_io.personnel_number
            LEFT JOIN military_personnel_status ao ON lr.accepting_officer = ao.id
            LEFT JOIN personnel p_ao ON ao.personnel_number = p_ao.personnel_number
            LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
            WHERE lr.id = ? AND lr.status = 'approved'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave) {
        die("Leave request not found or not approved.");
    }

    // Get unit from personnel table
    $unit = '';
    try {
        $stmt2 = $pdo->prepare("SELECT unit FROM personnel WHERE personnel_number = ?");
        $stmt2->execute([$leave['personnel_number']]);
        $unit_data = $stmt2->fetch(PDO::FETCH_ASSOC);
        $unit = ($unit_data && isset($unit_data['unit'])) ? $unit_data['unit'] : 'श्री साइबर सुरक्षा निर्देशनालय';
    } catch (PDOException $e) {
        $unit = 'श्री साइबर सुरक्षा निर्देशनालय';
    }

    // Get contact info
    $personnel_info = [];
    try {
        $stmt2 = $pdo->prepare("SELECT contact, address, province, district FROM personnel WHERE personnel_number = ?");
        $stmt2->execute([$leave['personnel_number']]);
        $personnel_info = $stmt2->fetch(PDO::FETCH_ASSOC);
        if (!$personnel_info) $personnel_info = [];
    } catch (PDOException $e) {
        $personnel_info = [];
    }

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

$province  = $personnel_info['province'] ?? 'वागमती';
$district  = $personnel_info['district'] ?? 'भक्तपुर';
$address   = $personnel_info['address']  ?? 'ताथली';
$contact   = $personnel_info['contact']  ?? '९८४१३७८३७४';

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
$leave_type_short = $leave_type_map[$leave['leave_type']]['short'] ?? 'वि.';

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

// Function to display signature image without extra border
function displaySignatureImage($signature_path, $person_name) {
    if (empty($signature_path)) {
        return '';
    }
    
    $image_path = getSignaturePath($signature_path);
    
    if ($image_path && file_exists($image_path)) {
        $web_path = '/' . ltrim($signature_path, '/');
        return '<img src="' . htmlspecialchars($web_path) . '" style="height: 50px; width: auto; max-width: 150px;" alt="हस्ताक्षर - ' . htmlspecialchars($person_name) . '">';
    }
    
    return '';
}
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
            padding: 30px;
            font-size: 13.5px;
            line-height: 1.85;
            color: #111;
        }

        .page-wrap {
            max-width: 780px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            position: relative;
            min-height: 100vh;
        }

        .pass {
            padding: 30px 35px 35px;
            position: relative;
            min-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        /* TOP HEADER */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 13px;
            margin-bottom: 4px;
            gap: 20px;
        }

        .left-column, .center-column, .right-column {
            flex: 1;
        }
        
        .left-column { text-align: left; line-height: 1.9; }
        .center-column { text-align: center; line-height: 1.9; }
        .right-column { text-align: left; line-height: 1.9; }

        .right-label {
            display: inline-block;
            width: 65px;
            font-weight: normal;
        }

        .right-value {
            display: inline-block;
        }

        .title {
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            text-decoration: underline;
            margin: 20px 0 16px;
            letter-spacing: 1px;
        }

        .body-text p { margin-bottom: 8px; }

        .section-label {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 12px;
            margin-bottom: 6px;
        }

        .indent { padding-left: 28px; }

        .balance-table {
            border-collapse: collapse;
            margin: 6px 0 6px 28px;
            font-size: 13px;
        }
        
        .balance-table td {
            padding: 3px 12px;
        }
        
        .balance-table td:first-child { padding-left: 0; }

        /* APPLICANT SIGNATURE - RIGHT ALIGNED with slight right offset */
        .applicant-signature-area {
            text-align: right;
            margin-top: 30px;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding-right: 15px;
        }
        
        .applicant-signature-wrapper {
            text-align: center;
            display: inline-block;
        }
        
        .signature-container {
            display: inline-block;
            text-align: center;
        }
        
        .sig-line {
            border-bottom: 1px dotted #333;
            min-width: 180px;
            margin-top: 5px;
        }
        
        .applicant-officer-name {
            text-align: center;
            margin-top: 5px;
        }

        /* Bottom signature section - Left and Right layout */
        .bottom-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            margin-bottom: 0;
            align-items: flex-end;
            gap: 50px;
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
            padding-right: 15px;
        }

        .signature-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
        }

        .signature-field {
            margin: 15px 0;
        }

        .signature-field .field-label {
            font-weight: normal;
            min-width: 65px;
            display: inline-block;
        }

        .signature-field .field-value {
            display: inline-block;
            vertical-align: middle;
        }
        
        .signature-field .signature-container {
            display: inline-block;
        }

        .content-wrapper {
            flex: 1;
        }
        
        .signature-wrapper {
            text-align: center;
            display: inline-block;
        }
        
        .signature-wrapper .signature-container {
            display: inline-block;
        }

        /* Right side accepting officer signature block - right aligned */
        .right-signature .signature-wrapper {
            text-align: center;
            display: inline-block;
        }
        
        .right-signature .accepting-officer-name {
            text-align: center;
            margin-top: 5px;
        }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .page-wrap { box-shadow: none; }
            .pass { padding: 15px 20px 20px; }
        }

        .button-bar {
            text-align: center;
            padding: 14px;
            background: #f0f0f0;
            border-top: 1px solid #ccc;
        }
        
        .btn {
            padding: 9px 22px;
            margin: 0 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        
        .btn-print { background: #2c5f4e; color: #fff; }
        .btn-back  { background: #6c757d; color: #fff; }
    </style>
</head>
<body>

<div class="page-wrap">
<div class="pass">
    <div class="content-wrapper">
        <!-- TOP HEADER -->
        <div class="top-header">
            <div class="left-column">
                व्य.नं.:- <?php echo htmlspecialchars($leave['personnel_number']); ?><br>
                नियुक्तिः- प्र.उ.से.
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

        <div style="margin-bottom:4px;">
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
                    <td style="padding-left:0;">(क)</td>
                    <td>घ.वि.:</td>
                    <td><?php echo $gharpari_balance; ?></td>
                </tr>
                <tr>
                    <td style="padding-left:0;">(ख)</td>
                    <td>भै.वि.:</td>
                    <td><?php echo $bhaeepari_balance; ?></td>
                </tr>
                <tr>
                    <td style="padding-left:0;">(ग)</td>
                    <td>प.वि.:</td>
                    <td><?php echo $parba_balance; ?></td>
                </tr>
            </table>

            <p class="section-label">३. विदा गएको बिबरण</p>
            <p class="indent">(क) पछिल्लो पटक घ.वि./भै.वि./प.वि. गएको दिनः- <?php echo $leave_days; ?></p>
            <p class="indent">(ख) पछिल्लो पटक विदा गएको मितिः- <?php echo $last_leave_date; ?> गतेदेखि <?php echo $last_leave_end; ?> गतेसम्म ।</p>

            <p class="section-label">४. विदामा रहँदाको सम्पर्क ठेगाना</p>
            <p class="indent">(क) प्रदेश :- <?php echo htmlspecialchars($province); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (ख) जिल्ला :- <?php echo htmlspecialchars($district); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (ग) न.पा./गा.पा :- चा.न.पा.</p>
            <p class="indent">(घ) वडा नं. :- 9 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (ङ) गाउँ/टोल :- <?php echo htmlspecialchars($address); ?></p>

            <p style="margin-top:10px;">५. समाविष्ट कागज (केही प्रमाण भएमा) :- </p>
        </div>

        <!-- APPLICANT SIGNATURE - RIGHT ALIGNED with SAME STYLING as accepting officer -->
        <div class="applicant-signature-area">
            <div class="applicant-signature-wrapper">
                <div class="signature-container">
                    <?php echo displaySignatureImage($leave['personnel_signature'], $leave['personnel_name']); ?>
                    <div class="sig-line"></div>
                </div>
            </div>
            <div class="applicant-officer-name">
                आज्ञाकारी<br>
                (<?php echo htmlspecialchars($leave['rank']) . ' ' . htmlspecialchars($leave['personnel_name']); ?>)
            </div>
        </div>
    </div>

    <!-- BOTTOM SIGNATURES SECTION - Left: Initiating Officer, Right: Accepting Officer -->
    <div class="bottom-signatures">
        <!-- Left side - Initiating Officer (सिफारिस गर्ने) - LEFT ALIGNED -->
        <div class="left-signature">
            <div class="signature-title">सिफारिस गर्ने</div>
            <p>निवेदकलाई घ.वि./क्या.वि./प.वि. बाटो म्याद सहित,<br>विदा छाड्न सिफारिस गर्दछु ।</p>
            <br>
            <div class="signature-field">
                <span class="field-label">दस्तखत :</span>
                <span class="field-value">
                    <div class="signature-container">
                        <?php echo displaySignatureImage($leave['initiating_officer_signature'], $leave['initiating_officer_name']); ?>
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

        <!-- Right side - Accepting Officer (स्वीकृत गर्नेको दःख.) - RIGHT ALIGNED -->
        <div class="right-signature">
            <div class="signature-wrapper">
                <div class="signature-container">
                    <?php 
                    // Display signature image if exists
                    if (!empty($leave['accepting_officer_signature'])) {
                        echo displaySignatureImage($leave['accepting_officer_signature'], $leave['accepting_officer_name']);
                    }
                    ?>
                    <div class="sig-line"></div>
                </div>
            </div>
            <div class="accepting-officer-name">
                स्वीकृत गर्नेको द:ख.<br>
                <?php if (!empty($leave['accepting_officer_name'])): ?>
                    (<?php echo htmlspecialchars($leave['accepting_officer_rank'] ?? '') . ' ' . htmlspecialchars($leave['accepting_officer_name'] ?? ''); ?>)
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