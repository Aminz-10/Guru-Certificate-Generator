<?php
// api/render.php
// Renders a single certificate for preview
// GET parameters: batch_id, row_index

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/renderer.php';

// Note: We skip auth check here because session cookies may not be sent with image requests

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$row_index = filter_input(INPUT_GET, 'index', FILTER_VALIDATE_INT);
$template_id = filter_input(INPUT_GET, 'template_id', FILTER_VALIDATE_INT);
$layers_only = isset($_GET['layers_only']) && $_GET['layers_only'] == '1';
$real_w = filter_input(INPUT_GET, 'real_w', FILTER_VALIDATE_INT);
$real_h = filter_input(INPUT_GET, 'real_h', FILTER_VALIDATE_INT);

$batch = null;
$template = null;
$row = null;

if ($batch_id) {
    // Fetch Batch & Template - Prioritize Template Settings for layout sync
    $stmt = $pdo->prepare("SELECT b.*, t.image_path, t.settings_json as template_settings, t.width as t_width, t.height as t_height
                           FROM batches b 
                           JOIN templates t ON b.template_id = t.id 
                           WHERE b.id = ?");

    $stmt->execute(array($batch_id));
    $batch = $stmt->fetch();
    
    if ($batch && $row_index !== null) {
        $offset = (int)$row_index;
        $stmt = $pdo->prepare("SELECT data_json FROM batch_rows WHERE batch_id = ? LIMIT 1 OFFSET $offset");
        $stmt->execute(array($batch_id));
        $row = $stmt->fetch();
    }
} elseif ($template_id) {
    // Designer Mode - direct template preview
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute(array($template_id));
    $template = $stmt->fetch();
}

if (!$batch && !$template) {
    header('Content-Type: image/png');
    $im = imagecreate(300, 30);
    imagecolorallocate($im, 255, 200, 200);
    $tc = imagecolorallocate($im, 128, 0, 0);
    imagestring($im, 5, 5, 5, 'Error: Template not found', $tc);
    imagepng($im);
    imagedestroy($im);
    exit;
}

$data = $row ? json_decode($row['data_json'], true) : array();
$settings_json = $batch ? (isset($batch['template_settings']) ? $batch['template_settings'] : $batch['settings_json']) : $template['settings_json'];
$settings = json_decode($settings_json, true);
$layers = isset($settings['layers']) ? $settings['layers'] : array();

$t_width = $batch ? (isset($batch['t_width']) ? $batch['t_width'] : $batch['width']) : (isset($template['width']) ? $template['width'] : 0);
$t_height = $batch ? (isset($batch['t_height']) ? $batch['t_height'] : $batch['height']) : (isset($template['height']) ? $template['height'] : 0);

// Live Scaling: If client provides real dimensions, adjust layers to match real space
if ($real_w > 0 && $real_h > 0 && ($real_w != $t_width || $real_h != $t_height)) {
    foreach ($layers as &$l) {
        $l['x'] = round(($l['x'] / $t_width) * $real_w);
        $l['y'] = round(($l['y'] / $t_height) * $real_h);
        $l['font_size'] = round(($l['font_size'] / $t_width) * $real_w); // Scale font too
    }
    $t_width = $real_w;
    $t_height = $real_h;
}

// Use __DIR__ for reliable path resolution from api/ directory
$base_dir = dirname(__DIR__); // Go up from api/ to project root
$template_path = $base_dir . '/' . ($batch ? $batch['image_path'] : $template['image_path']);

// Verify template exists
if (!file_exists($template_path)) {
    header('Content-Type: image/png');
    $im = imagecreate(500, 50);
    imagecolorallocate($im, 255, 200, 200);
    $tc = imagecolorallocate($im, 128, 0, 0);
    imagestring($im, 5, 5, 5, 'Template not found:', $tc);
    imagestring($im, 4, 5, 25, substr($template_path, -50), $tc);
    imagepng($im);
    imagedestroy($im);
    exit;
}

// Set up preview directory with absolute path
$temp_preview_dir = $base_dir . '/uploads/previews/';
if (!is_dir($temp_preview_dir)) {
    @mkdir($temp_preview_dir, 0777, true);
}

$temp_file = $temp_preview_dir . 'preview_' . $batch_id . '_' . $row_index . ($layers_only ? '_layers' : '') . '.png';

$use_bg = $batch ? (int)$batch['use_background'] : 1;

// Attempt render
if (render_certificate(($layers_only || !$use_bg) ? ($use_bg ? '' : 'white') : $template_path, $temp_file, $layers, $data, $t_width, $t_height)) {
    header('Content-Type: image/png');
    readfile($temp_file);
} else {
    header('Content-Type: image/png');
    $im = imagecreate(400, 50);
    imagecolorallocate($im, 255, 200, 200);
    $tc = imagecolorallocate($im, 128, 0, 0);
    imagestring($im, 5, 5, 5, 'Render Failed', $tc);
    imagestring($im, 4, 5, 25, 'Check fonts directory', $tc);
    imagepng($im);
    imagedestroy($im);
}
