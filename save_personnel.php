<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $editId = $_POST['editId'] ?? '';
    $serviceNo = $_POST['serviceNo'] ?? '';
    $fullName = $_POST['fullName'] ?? '';
    $fullNameNe = $_POST['fullNameNe'] ?? '';
    $rank = $_POST['rank'] ?? '';
    $branch = $_POST['branch'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bloodGroup = $_POST['bloodGroup'] ?? '';
    $recruitmentDate = $_POST['recruitmentDate'] ?? '';
    $commissionDate = $_POST['commissionDate'] ?? '';
    $status = $_POST['status'] ?? '';
    $email = $_POST['email'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $religion = $_POST['religion'] ?? '';
    $militaryStatus = $_POST['militaryStatus'] ?? '';
    $education = $_POST['education'] ?? '';
    $militaryTrainings = $_POST['militaryTrainings'] ?? '';
    $training = $_POST['training'] ?? '';
    $training1 = $_POST['training1'] ?? '';
    $training2 = $_POST['training2'] ?? '';
    $training3 = $_POST['training3'] ?? '';
    $training4 = $_POST['training4'] ?? '';
    $training5 = $_POST['training5'] ?? '';
    $foreignTraining = $_POST['foreignTraining'] ?? '';
    $fatherName = $_POST['fatherName'] ?? '';
    $motherName = $_POST['motherName'] ?? '';
    $spouseName = $_POST['spouseName'] ?? '';
    $grandfatherName = $_POST['grandfatherName'] ?? '';
    $childrenNames = $_POST['childrenNames'] ?? '';
    $familyNotes = $_POST['familyNotes'] ?? '';
    $trainingAddress = $_POST['trainingAddress'] ?? '';
    $training1Address = $_POST['training1Address'] ?? '';
    $training2Address = $_POST['training2Address'] ?? '';
    
    if ($editId) {
        // Update existing personnel
        $sql = "UPDATE personnel SET 
                    personnel_number = :serviceNo,
                    full_name_en = :fullName,
                    full_name_ne = :fullNameNe,
                    dob = :dob,
                    gender = :gender,
                    blood_group = :bloodGroup,
                    rank = :rank,
                    unit = :branch,
                    recruitment_date = :recruitmentDate,
                    commission_date = :commissionDate,
                    current_status = :status,
                    email = :email,
                    contact = :contact,
                    phone = :phone,
                    address = :address,
                    religion = :religion,
                    military_status = :militaryStatus,
                    higher_education = :education,
                    military_trainings = :militaryTrainings,
                    training = :training,
                    training1 = :training1,
                    training2 = :training2,
                    training3 = :training3,
                    training4 = :training4,
                    training5 = :training5,
                    foreign_training = :foreignTraining,
                    father_name = :fatherName,
                    mother_name = :motherName,
                    spouse_name = :spouseName,
                    grandfather_name = :grandfatherName,
                    children_names = :childrenNames,
                    family_notes = :familyNotes,
                    training_address = :trainingAddress,
                    training1_address = :training1Address,
                    training2_address = :training2Address,
                    updated_at = NOW()
                WHERE personnel_number = :editId";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':serviceNo' => $serviceNo,
            ':fullName' => $fullName,
            ':fullNameNe' => $fullNameNe,
            ':dob' => !empty($dob) ? $dob : null,
            ':gender' => $gender,
            ':bloodGroup' => $bloodGroup,
            ':rank' => $rank,
            ':branch' => $branch,
            ':recruitmentDate' => !empty($recruitmentDate) ? $recruitmentDate : null,
            ':commissionDate' => !empty($commissionDate) ? $commissionDate : null,
            ':status' => $status,
            ':email' => $email,
            ':contact' => $contact,
            ':phone' => $phone,
            ':address' => $address,
            ':religion' => $religion,
            ':militaryStatus' => $militaryStatus,
            ':education' => $education,
            ':militaryTrainings' => $militaryTrainings,
            ':training' => $training,
            ':training1' => $training1,
            ':training2' => $training2,
            ':training3' => $training3,
            ':training4' => $training4,
            ':training5' => $training5,
            ':foreignTraining' => $foreignTraining,
            ':fatherName' => $fatherName,
            ':motherName' => $motherName,
            ':spouseName' => $spouseName,
            ':grandfatherName' => $grandfatherName,
            ':childrenNames' => $childrenNames,
            ':familyNotes' => $familyNotes,
            ':trainingAddress' => $trainingAddress,
            ':training1Address' => $training1Address,
            ':training2Address' => $training2Address,
            ':editId' => $editId
        ]);
        
        $response['success'] = true;
        $response['message'] = 'Personnel updated successfully';
    } else {
        // Check if personnel number already exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_number = ?");
        $check->execute([$serviceNo]);
        if ($check->fetchColumn() > 0) {
            $response['message'] = 'Personnel number already exists';
            echo json_encode($response);
            exit;
        }
        
        // Check if email already exists
        if (!empty($email)) {
            $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM personnel WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetchColumn() > 0) {
                $response['message'] = 'Email already exists';
                echo json_encode($response);
                exit;
            }
        }
        
        // Insert new personnel
        $sql = "INSERT INTO personnel (
                    personnel_number, 
                    full_name_en, 
                    full_name_ne,
                    dob,
                    gender,
                    blood_group,
                    rank, 
                    unit, 
                    recruitment_date,
                    commission_date,
                    current_status, 
                    email, 
                    contact,
                    phone,
                    address,
                    religion,
                    military_status,
                    higher_education,
                    military_trainings,
                    training,
                    training1,
                    training2,
                    training3,
                    training4,
                    training5,
                    foreign_training,
                    father_name,
                    mother_name,
                    spouse_name,
                    grandfather_name,
                    children_names,
                    family_notes,
                    training_address,
                    training1_address,
                    training2_address
                ) VALUES (
                    :serviceNo, 
                    :fullName, 
                    :fullNameNe,
                    :dob,
                    :gender,
                    :bloodGroup,
                    :rank, 
                    :branch, 
                    :recruitmentDate,
                    :commissionDate,
                    :status, 
                    :email, 
                    :contact,
                    :phone,
                    :address,
                    :religion,
                    :militaryStatus,
                    :education,
                    :militaryTrainings,
                    :training,
                    :training1,
                    :training2,
                    :training3,
                    :training4,
                    :training5,
                    :foreignTraining,
                    :fatherName,
                    :motherName,
                    :spouseName,
                    :grandfatherName,
                    :childrenNames,
                    :familyNotes,
                    :trainingAddress,
                    :training1Address,
                    :training2Address
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':serviceNo' => $serviceNo,
            ':fullName' => $fullName,
            ':fullNameNe' => $fullNameNe,
            ':dob' => !empty($dob) ? $dob : null,
            ':gender' => $gender,
            ':bloodGroup' => $bloodGroup,
            ':rank' => $rank,
            ':branch' => $branch,
            ':recruitmentDate' => !empty($recruitmentDate) ? $recruitmentDate : null,
            ':commissionDate' => !empty($commissionDate) ? $commissionDate : null,
            ':status' => $status,
            ':email' => $email,
            ':contact' => $contact,
            ':phone' => $phone,
            ':address' => $address,
            ':religion' => $religion,
            ':militaryStatus' => $militaryStatus,
            ':education' => $education,
            ':militaryTrainings' => $militaryTrainings,
            ':training' => $training,
            ':training1' => $training1,
            ':training2' => $training2,
            ':training3' => $training3,
            ':training4' => $training4,
            ':training5' => $training5,
            ':foreignTraining' => $foreignTraining,
            ':fatherName' => $fatherName,
            ':motherName' => $motherName,
            ':spouseName' => $spouseName,
            ':grandfatherName' => $grandfatherName,
            ':childrenNames' => $childrenNames,
            ':familyNotes' => $familyNotes,
            ':trainingAddress' => $trainingAddress,
            ':training1Address' => $training1Address,
            ':training2Address' => $training2Address
        ]);
        
        $response['success'] = true;
        $response['message'] = 'Personnel added successfully';
    }
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>