<?php
// api/save_template.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// require_login(); // Don't redirect HTML
if (!is_logged_in()) {
    json_response(array('error' => 'Authentication required'), 401);
}
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_response(array('error' => 'Invalid JSON'), 400);
}

$template_id = isset($input['template_id']) ? $input['template_id'] : null;
$settings = isset($input['settings']) ? $input['settings'] : null;

if (!$template_id || !$settings) {
    json_response(array('error' => 'Missing fields'), 400);
}

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM templates WHERE id = ? AND user_id = ?");
$stmt->execute(array($template_id, current_user_id()));
if (!$stmt->fetch()) {
    json_response(array('error' => 'Unauthorized'), 403);
}

// Update settings and dimensions
$width = isset($input['width']) ? (int)$input['width'] : null;
$height = isset($input['height']) ? (int)$input['height'] : null;

if ($width && $height) {
    $stmt = $pdo->prepare("UPDATE templates SET settings_json = ?, width = ?, height = ? WHERE id = ?");
    $success = $stmt->execute(array(json_encode($settings), $width, $height, $template_id));
} else {
    $stmt = $pdo->prepare("UPDATE templates SET settings_json = ? WHERE id = ?");
    $success = $stmt->execute(array(json_encode($settings), $template_id));
}

if ($success) {
    json_response(array('success' => true));
} else {
    json_response(array('error' => 'Database error'), 500);
}
