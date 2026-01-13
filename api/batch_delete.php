<?php
// api/batch_delete.php
if (session_id() == '') {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

error_reporting(0); // Suppress any notices from breaking JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'error' => 'Method Not Allowed'));
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Login required'));
    exit;
}

$user_id = $_SESSION['user_id'];
$batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);

if (!$batch_id) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Missing batch ID'));
    exit;
}

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM batches WHERE id = ? AND user_id = ?");
$stmt->execute(array($batch_id, $user_id));
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized or batch not found'));
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Delete batch rows
    $stmt = $pdo->prepare("DELETE FROM batch_rows WHERE batch_id = ?");
    $stmt->execute(array($batch_id));

    // 2. Delete the batch record
    $stmt = $pdo->prepare("DELETE FROM batches WHERE id = ?");
    $stmt->execute(array($batch_id));

    $pdo->commit();
    echo json_encode(array('success' => true));
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
}
?>
