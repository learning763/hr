<?php
include('../includes/config.php');
include('../includes/functions.php');

$group = $_POST['blood_group'] ?? '';

$stmt = $pdo->prepare("
    SELECT personnel_number, full_name_ne, rank, unit, phone
    FROM personnel
    WHERE blood_group = :blood_group
    AND current_status = 'Active'
");

$stmt->bindParam(':blood_group', $group);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h5>Blood Group: <b>$group</b></h5>";

if (count($rows) > 0) {

    echo "<table class='table table-bordered table-striped' style='text-align:center;'>";
    echo "<tr>
            <th>सि.नं.</th>
            <th>नामथर</th>
            <th>दर्जा</th>
            <th>युनिट</th>
            <th>फोन नं.</th>
          </tr>";

    $i = 1;

    foreach ($rows as $row) {
        echo "<tr>
                <td style='text-align:center;'>" . engTouni($i) . "</td>
                <td style='text-align:center;'>{$row['full_name_ne']}</td>
                <td style='text-align:center;'>{$row['rank']}</td>
                <td style='text-align:center;'>{$row['unit']}</td>
                <td style='text-align:center;'>{$row['phone']}</td>
              </tr>";
        $i++;
    }

    echo "</table>";

} else {
    echo "<div class='alert alert-warning'>No personnel found for this blood group.</div>";
}
?>