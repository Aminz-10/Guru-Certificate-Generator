<?php
// download.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

require_login();

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batch_id) redirect('dashboard.php');

// Fetch Batch to check ownership and path
$stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ? AND user_id = ?");
$stmt->execute(array($batch_id, current_user_id()));
$batch = $stmt->fetch();

if (!$batch || empty($batch['zip_path']) || !file_exists($batch['zip_path'])) {
    set_flash('error', 'File not found or access denied.');
    redirect('dashboard.php');
}

// Serve file
$file = $batch['zip_path'];
$filename = 'certificates_' . $batch['name'] . '.zip'; // Friendly name

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
