<?php
/**
 * AJAX endpoint: saves the new drag-and-drop order of gallery items within
 * one album. Called by assets/js/admin.js. Expects JSON body:
 * {"order": ["12","9","14"], "csrf_token": "..."}
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorised.']);
    exit;
}

$albumId = (int) ($_GET['album_id'] ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$submittedToken = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired, please reload the page.']);
    exit;
}

$order = $input['order'] ?? [];
if (!$albumId || !is_array($order)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$stmt = db()->prepare('UPDATE media_items SET sort_order = :pos WHERE id = :id AND album_id = :album_id');
$position = 1;
foreach ($order as $itemId) {
    $stmt->execute(['pos' => $position, 'id' => (int) $itemId, 'album_id' => $albumId]);
    $position++;
}

echo json_encode(['ok' => true]);
