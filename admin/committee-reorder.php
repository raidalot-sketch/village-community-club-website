<?php
/** AJAX endpoint: saves drag-and-drop order of committee members. */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorised.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$submittedToken = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired, please reload the page.']);
    exit;
}

$order = $input['order'] ?? [];
if (!is_array($order)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$stmt = db()->prepare('UPDATE committee_members SET sort_order = :pos WHERE id = :id');
$position = 1;
foreach ($order as $memberId) {
    $stmt->execute(['pos' => $position, 'id' => (int) $memberId]);
    $position++;
}

echo json_encode(['ok' => true]);
