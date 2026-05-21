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
                   lb.gharpari_bida_days, lb.parba_bida_days, lb.bhaeepari_bida_days
            FROM leave_requests lr
            INNER JOIN military_personnel_status mps ON lr.personnel_id = mps.id
            LEFT JOIN military_personnel_status io ON lr.initiating_officer = io.id
            LEFT JOIN military_personnel_status ao ON lr.accepting_officer = ao.id
            LEFT JOIN leave_balance lb ON lr.personnel_id = lb.personnel_id
            WHERE lr.id = ? AND lr.status = 'approved'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave) {
        die("Leave request not found or not approved.");
    }

    // Get unit
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

    // Get appointment (niyukti) from personnel table
    $niyukti = '';
    try {
        $stmt2 = $pdo->prepare("SELECT appointment FROM personnel WHERE personnel_number = ?");
        $stmt2->execute([$leave['personnel_number']]);
        $niy_data = $stmt2->fetch(PDO::FETCH_ASSOC);
        $niyukti = ($niy_data && isset($niy_data['appointment'])) ? $niy_data['appointment'] : 'प्र.उ.से.';
    } catch (PDOException $e) {
        $niyukti = 'प्र.उ.से.';
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

// Leave type
if ($leave['leave_type'] == 'gharpari_bida') {
    $leave_type_text  = 'घर विदा';
    $leave_type_short = 'घ.वि.';
} elseif ($leave['leave_type'] == 'parba_bida') {
    $leave_type_text  = 'पर्व विदा';
    $leave_type_short = 'प.वि.';
} elseif ($leave['leave_type'] == 'bhaeepari_bida') {
    $leave_type_text  = 'भाइपरी विदा';
    $leave_type_short = 'भै.वि.';
} else {
    $leave_type_text  = 'विदा';
    $leave_type_short = 'वि.';
}

$last_leave_date = $leave['created_at'] ? date('Y/m/d', strtotime($leave['created_at'])) : '';
$last_leave_end  = $leave['created_at'] ? date('Y/m/d', strtotime($leave['created_at'] . ' +7 days')) : '';
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विदा माग - <?php echo htmlspecialchars($leave['personnel_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* Font Definitions */
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

        /* Nepali text - use Kalimati */
        .nepali-text, 
        .title, 
        .section-label,
        .sig-left .title-label,
        .applicant-sig,
        .sig-right div,
        .top-header,
        .right-label,
        .balance-table td,
        .indent,
        .field-row .label,
        p {
            font-family: 'Kalimati', 'Times New Roman', serif;
        }

        /* English text and numbers - use Times New Roman */
        .english-text,
        .date-field,
        .right-value,
        .val,
        .field-row .val,
        .btn,
        .button-bar {
            font-family: 'Times New Roman', 'Kalimati', serif;
        }

        .page-wrap {
            max-width: 780px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
        }

        .pass {
            padding: 30px 35px 35px;
            position: relative;
        }

        /* TOP HEADER - Three column layout */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 13px;
            margin-bottom: 4px;
            gap: 20px;
        }

        .left-column {
            flex: 1;
            text-align: left;
            line-height: 1.9;
        }

        .center-column {
            flex: 1;
            text-align: center;
            line-height: 1.9;
        }

        .right-column {
            flex: 1;
            text-align: left;
            line-height: 1.9;
        }

        .right-label {
            display: inline-block;
            width: 65px;
            font-weight: normal;
        }

        .right-value {
            display: inline-block;
        }

        hr.divider {
            border: none;
            border-top: 1.5px solid #333;
            margin: 8px 0 14px;
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

        .date-field {
            display: inline-block;
            min-width: 90px;
        }

        .applicant-sig {
            text-align: right;
            margin-top: 25px;
            margin-bottom: 30px;
            font-size: 13px;
        }
        .applicant-sig .sig-line {
            display: inline-block;
            min-width: 180px;
            border-bottom: 1px dashed #777;
            margin-bottom: 5px;
        }

        /* BOTTOM SIGNATURE SECTION */
        .sig-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 40px;
            align-items: stretch;
        }

        .sig-left { 
            flex: 1.2; 
            font-size: 13px; 
            line-height: 2;
        }
        
        .sig-right { 
            flex: 0.8; 
            text-align: right;
            font-size: 13px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-end;
        }

        .sig-left .title-label {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 8px;
        }

        .sig-left .field-row {
            display: flex;
            gap: 8px;
            margin-bottom: 4px;
        }
        .sig-left .field-row .label { 
            white-space: nowrap; 
            min-width: 55px;
        }
        .sig-left .field-row .val {
            flex: 1;
            border-bottom: none;
            min-width: 100px;
        }

        .sig-right .dotted-line {
            border-bottom: 1px dotted #555;
            width: 200px;
            margin-bottom: 8px;
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

    <!-- TOP HEADER - Three column layout -->
    <div class="top-header">
        <div class="left-column">
            व्य.नं.:- <?php echo htmlspecialchars($leave['personnel_number']); ?><br>
            नियुक्तिः- <?php echo htmlspecialchars($niyukti); ?>
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

        <!-- Section 2: Leave balance -->
        <p class="section-label">२. संचित विदाको बिबरण</p>
        <table class="balance-table">
            <tr><td>(क)</td><td>घ.वि.:</span></td><td><?php echo $gharpari_balance; ?></td></tr>
            <tr><td>(ख)</td><td>भै.वि.:</span></span></td><td><?php echo $bhaeepari_balance; ?></span></td></tr>
            <tr><td>(ग)</span></td><td>प.वि.:</span></td><td><?php echo $parba_balance; ?></span></td></tr>
        </table>

        <!-- Section 3: Previous leave history -->
        <p class="section-label">३. विदा गएको बिबरण</p>
        <p class="indent">(क) पछिल्लो पटक घ.वि./भै.वि./प.वि. गएको दिनः- <span class="date-field"><?php echo $leave_days; ?></span></p>
        <p class="indent">(ख) पछिल्लो पटक विदा गएको मितिः- <span class="date-field"><?php echo $last_leave_date; ?></span> गतेदेखि <span class="date-field"><?php echo $last_leave_end; ?></span> गतेसम्म ।</p>

        <!-- Section 4: Contact address -->
        <p class="section-label">४. विदामा रहँदाको सम्पर्क ठेगाना</p>
        <p class="indent">(क) प्रदेश :- <?php echo htmlspecialchars($province); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (ख) जिल्ला :- <?php echo htmlspecialchars($district); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (ग) न.पा./गा.पा :- चा.न.पा.</p>
        <p class="indent">(घ) वडा नं. :- 9 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (ङ) गाउँ/टोल :- <?php echo htmlspecialchars($address); ?></p>

        <!-- Section 5 -->
        <p style="margin-top:10px;">५. समाविष्ट कागज (केही प्रमाण भएमा) :- </p>

    </div>

    <!-- APPLICANT SIGNATURE -->
    <div class="applicant-sig">
        <div class="sig-line"></div><br>
        आज्ञाकारी
    </div>

    <!-- BOTTOM: Initiating officer left | Accepting officer right -->
    <div class="sig-section">
        <div class="sig-left">
            <div class="title-label">सिफारिस गर्ने</div>
            <p>निवेदकलाई घ.वि./क्या.वि./प.वि. बाटो म्याद सहित,<br>विदा छाड्न सिफारिस गर्दछु ।</p>
            <br>
            <div class="field-row"><span class="label">दस्तखत :</span><span class="val"></span></div>
            <div class="field-row"><span class="label">नामथर :</span><span class="val"><?php echo htmlspecialchars($leave['initiating_officer_name'] ?? ''); ?></span></div>
            <div class="field-row"><span class="label">दर्जा :</span><span class="val"><?php echo htmlspecialchars($leave['initiating_officer_rank'] ?? ''); ?></span></div>
            <div class="field-row"><span class="label">नियुक्ति :</span><span class="val"></span></div>
        </div>

        <div class="sig-right">
            <div class="dotted-line"></div>
            <div>स्वीकृत गर्नेको द:ख.</div>
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