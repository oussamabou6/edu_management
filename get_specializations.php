<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$year_id = isset($_GET['year_id']) ? intval($_GET['year_id']) : 0;
$institution_id = $_SESSION['user_id'];

if ($year_id) {
    $stmt = $pdo->prepare("
        SELECT id, specialization_name 
        FROM specializations 
        WHERE year_id = ? AND institution_id = ?
        ORDER BY specialization_name
    ");
    $stmt->execute([$year_id, $institution_id]);
    $specializations = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($specializations);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid year_id']);
}
?>