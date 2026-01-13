<?php
// test_render.php - Direct test of rendering
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/renderer.php';

// Skip auth for testing
// require_login();

$batch_id = 2;
$row_index = 0;

echo "<h1>Render Test</h1>";

// Fetch Batch & Template
$stmt = $pdo->prepare("SELECT b.*, t.image_path, t.settings_json, t.width, t.height
                       FROM batches b 
                       JOIN templates t ON b.template_id = t.id 
                       WHERE b.id = ?");
$stmt->execute(array($batch_id));
$batch = $stmt->fetch();

if (!$batch) {
    die("Batch not found");
}

echo "<h2>Batch Info:</h2>";
echo "image_path: " . $batch['image_path'] . "<br>";
echo "template width: " . $batch['width'] . "<br>";
echo "template height: " . $batch['height'] . "<br>";

// Check if template image exists
$template_path = $batch['image_path'];
echo "<h2>Template Path Check:</h2>";
echo "Path: $template_path<br>";
echo "File exists: " . (file_exists($template_path) ? 'YES' : 'NO') . "<br>";

// Try with different path variations
$alt_path = './' . $template_path;
echo "Alt path (./$template_path): " . (file_exists($alt_path) ? 'YES' : 'NO') . "<br>";

$realpath = realpath($template_path);
echo "Realpath: " . ($realpath ?: 'FALSE') . "<br>";

// Fetch Row Data
$stmt = $pdo->prepare("SELECT data_json FROM batch_rows WHERE batch_id = ? LIMIT 1 OFFSET ?");
$stmt->execute(array($batch_id, (int)$row_index));
$row = $stmt->fetch();

if (!$row) {
    die("Row not found");
}

echo "<h2>Row Data:</h2>";
$data = json_decode($row['data_json'], true);
print_r($data);

echo "<h2>Template Settings:</h2>";
$settings = json_decode($batch['settings_json'], true);
$layers = isset($settings['layers']) ? $settings['layers'] : array();
echo "Layers count: " . count($layers) . "<br>";
print_r($layers);

// Check fonts directory
echo "<h2>Fonts Check:</h2>";
$font_dir = __DIR__ . '/fonts/';
echo "Font dir: $font_dir<br>";
echo "Font dir exists: " . (is_dir($font_dir) ? 'YES' : 'NO') . "<br>";
$fonts = glob($font_dir . '*.ttf');
echo "Fonts found: " . count($fonts) . "<br>";
foreach ($fonts as $f) {
    echo "- " . basename($f) . "<br>";
}

// Test render
echo "<h2>Attempting Render...</h2>";
$temp_preview_dir = 'uploads/previews/';
if (!is_dir($temp_preview_dir)) mkdir($temp_preview_dir, 0777, true);

$temp_file = $temp_preview_dir . 'test_render.png';

// Use correct path for template
$template_full_path = $template_path;
if (!file_exists($template_full_path)) {
    $template_full_path = './' . $template_path;
}

echo "Using template path: $template_full_path<br>";
echo "Template exists: " . (file_exists($template_full_path) ? 'YES' : 'NO') . "<br>";

if (file_exists($template_full_path)) {
    $result = render_certificate($template_full_path, $temp_file, $layers, $data);
    echo "Render result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    if ($result && file_exists($temp_file)) {
        echo "<h2>Rendered Image:</h2>";
        echo "<img src='$temp_file?t=" . time() . "' style='max-width:100%;'>";
    }
} else {
    echo "Cannot render - template file not found!";
}
