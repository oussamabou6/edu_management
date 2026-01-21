<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$specialization_id = isset($_GET['specialization_id']) ? intval($_GET['specialization_id']) : 0;
$institution_id = $_SESSION['user_id'];

if ($specialization_id) {
    $stmt = $pdo->prepare("
        SELECT id, subject_name, coefficient 
        FROM subjects 
        WHERE specialization_id = ? AND institution_id = ?
        ORDER BY subject_name
    ");
    $stmt->execute([$specialization_id, $institution_id]);
    $subjects = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($subjects);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid specialization_id']);
}
?>