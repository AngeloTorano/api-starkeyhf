<?php
include '../connection.php';
header('Content-Type: application/json');

$patientID = $_POST['PatientID'] ?? null;
$surname = $_POST['Surname'] ?? null;
$firstName = $_POST['FirstName'] ?? null;
$school = $_POST['School'] ?? null;
$city = $_POST['City'] ?? null;

$userId = $_POST['UserID'] ?? null;
$role = $_POST['Role'] ?? null;

$where = [];
$params = [];
$types = '';

// Filter conditions
if ($patientID) {
    $where[] = 'p.id = ?';
    $params[] = $patientID;
    $types .= 'i';
}
if ($surname) {
    $where[] = 'p.surname LIKE ?';
    $params[] = "%$surname%";
    $types .= 's';
}
if ($firstName) {
    $where[] = 'p.first_name LIKE ?';
    $params[] = "%$firstName%";
    $types .= 's';
}
if ($school) {
    $where[] = 'p.school_name LIKE ?';
    $params[] = "%$school%";
    $types .= 's';
}
if ($city) {
    $where[] = 'c.CityName LIKE ?';
    $params[] = "%$city%";
    $types .= 's';
}

// 🔒 Get CoordinatorID from users table first (based on logged in user ID)
$coordinatorId = null;
if ($role === 'City Coordinator' && $userId) {
    $userStmt = $connectNow->prepare("SELECT CoordinatorID FROM users WHERE UserID = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($userRow = $userResult->fetch_assoc()) {
        $coordinatorId = $userRow['CoordinatorID'];
    }
    $userStmt->close();

    if ($coordinatorId) {
        $where[] = 'c.CoordinatorID = ?';
        $params[] = $coordinatorId;
        $types .= 'i';
    } else {
        echo json_encode([
            "success" => false,
            "message" => "CoordinatorID not found for user"
        ]);
        exit;
    }
}

// Main query
$sql = "SELECT 
    p.id AS `SHF Patient ID`,
    p.shf_id,
    CONCAT(p.first_name, ' ', p.surname) AS `Name`,
    TIMESTAMPDIFF(YEAR, p.birthdate, CURDATE()) AS `Age`,
    p.birthdate AS `Birthdate`,
    p.gender AS `Gender`,
    p.mobile_number AS `Mobile`,
    c.CityName AS `City`,
    p.school_name AS `School`,
    p.education_level AS `Education`,
    p.employment_status AS `Employment`
FROM patients p
LEFT JOIN cities c ON p.city_id = c.CityID";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$stmt = $connectNow->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    echo json_encode([
        "success" => true,
        "patients" => $patients
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No patients found"
    ]);
}

$stmt->close();
$connectNow->close();
?>
