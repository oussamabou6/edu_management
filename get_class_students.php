<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'teacher') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$year_id = isset($_GET['year']) ? intval($_GET['year']) : 0;
$spec_id = isset($_GET['spec']) ? intval($_GET['spec']) : 0;
$institution_id = $_SESSION['institution_id'];

if ($year_id && $spec_id) {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name 
        FROM students 
        WHERE year_id = ? AND specialization_id = ? AND institution_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$year_id, $spec_id, $institution_id]);
    $students = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($students);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
}
?>