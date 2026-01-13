<?php
// api/render_preview.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/renderer.php';

// No require_login() to allow image loading in img tags, 
// but we should check template ownership eventually if needed.

$template_id = filter_input(INPUT_GET, 'template_id', FILTER_VALIDATE_INT);
$data_json = isset($_GET['data']) ? $_GET['data'] : '{}';
$data = json_decode($data_json, true) ?: array();
$real_w = filter_input(INPUT_GET, 'real_w', FILTER_VALIDATE_INT);
$real_h = filter_input(INPUT_GET, 'real_h', FILTER_VALIDATE_INT);

if (!$template_id) {
    die("Missing template_id");
}

// Fetch Template
$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute(array($template_id));
$template = $stmt->fetch();

if (!$template) {
    die("Template not found");
}

$settings = json_decode($template['settings_json'], true);
$layers = isset($settings['layers']) ? $settings['layers'] : array();

$template_path = __DIR__ . '/../' . $template['image_path'];
$layers_only = isset($_GET['layers_only']) && $_GET['layers_only'] == '1';
$use_bg = !isset($_GET['use_bg']) || $_GET['use_bg'] == '1';

if (!$use_bg) {
    $template_path = 'white';
}

$temp_output = __DIR__ . '/../uploads/temp_preview_' . session_id() . ($layers_only ? '_layers' : '') . '.png';

$t_w = isset($template['width']) ? $template['width'] : 0;
$t_h = isset($template['height']) ? $template['height'] : 0;

// Live Scaling: If client provides real dimensions, adjust layers to match real space
if ($real_w > 0 && $real_h > 0 && ($real_w != $t_w || $real_h != $t_h)) {
    foreach ($layers as &$l) {
        $l['x'] = round(($l['x'] / $t_w) * $real_w);
        $l['y'] = round(($l['y'] / $t_h) * $real_h);
        $l['font_size'] = round(($l['font_size'] / $t_w) * $real_w);
    }
    $t_w = $real_w;
    $t_h = $real_h;
}

if (render_certificate(($layers_only || !$use_bg) ? ($use_bg ? '' : 'white') : $template_path, $temp_output, $layers, $data, $t_w, $t_h)) {
    header('Content-Type: image/png');
    // Set no-cache to ensure real-time updates work
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($temp_output);
    // Optional: unlink($temp_output); 
} else {
    // Return error image
    header('Content-Type: image/png');
    $im = imagecreate(300, 30);
    imagecolorallocate($im, 255, 200, 200);
    $tc = imagecolorallocate($im, 128, 0, 0);
    imagestring($im, 5, 5, 5, 'Render Failed', $tc);
    imagepng($im);
    imagedestroy($im);
}
