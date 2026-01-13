<?php
// debug_full.php - Comprehensive debug for rendering issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 4;

echo "<h1>Full Debug for Batch ID: $batch_id</h1>";

// 1. Check batch exists
echo "<h2>1. Batch Check</h2>";
$stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->execute(array($batch_id));
$batch = $stmt->fetch();

if (!$batch) {
    echo "<p style='color:red'>BATCH NOT FOUND!</p>";
    echo "<p>Existing batches:</p>";
    $all = $pdo->query("SELECT id, name, template_id FROM batches")->fetchAll();
    echo "<pre>" . print_r($all, true) . "</pre>";
    exit;
}

echo "<p style='color:green'>Batch found: " . $batch['name'] . "</p>";
echo "<p>Template ID: " . $batch['template_id'] . "</p>";
echo "<p>CSV Path: " . $batch['csv_path'] . "</p>";

// 2. Check template exists
echo "<h2>2. Template Check</h2>";
$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute(array($batch['template_id']));
$template = $stmt->fetch();

if (!$template) {
    echo "<p style='color:red'>TEMPLATE NOT FOUND! template_id=" . $batch['template_id'] . "</p>";
    exit;
}

echo "<p style='color:green'>Template found: " . $template['title'] . "</p>";
echo "<p>Image path: " . $template['image_path'] . "</p>";
echo "<p>Image exists: " . (file_exists($template['image_path']) ? '<span style="color:green">YES</span>' : '<span style="color:red">NO</span>') . "</p>";

// Check settings
$settings = json_decode($template['settings_json'], true);
echo "<h3>Template Settings (layers):</h3>";
echo "<pre>" . print_r($settings, true) . "</pre>";

// 3. Check batch_rows
echo "<h2>3. Batch Rows Check</h2>";
$stmt = $pdo->prepare("SELECT * FROM batch_rows WHERE batch_id = ? LIMIT 5");
$stmt->execute(array($batch_id));
$rows = $stmt->fetchAll();

echo "<p>Found " . count($rows) . " rows (showing first 5)</p>";

if (count($rows) == 0) {
    echo "<p style='color:red'>NO ROWS FOUND FOR THIS BATCH!</p>";
    exit;
}

foreach ($rows as $i => $row) {
    $data = json_decode($row['data_json'], true);
    echo "<h4>Row $i:</h4>";
    echo "<pre>" . print_r($data, true) . "</pre>";
}

// 4. Check font availability
echo "<h2>4. Font Check</h2>";
$font_dir = __DIR__ . '/fonts/';
if (is_dir($font_dir)) {
    $fonts = glob($font_dir . '*.ttf');
    echo "<p>Found " . count($fonts) . " fonts:</p>";
    foreach ($fonts as $f) {
        echo "- " . basename($f) . "<br>";
    }
} else {
    echo "<p style='color:red'>Font directory not found!</p>";
}

// 5. Try manual render
echo "<h2>5. Manual Render Test</h2>";
require_once 'includes/renderer.php';

// Get first row
$first_row = $rows[0];
$data = json_decode($first_row['data_json'], true);
$layers = isset($settings['layers']) ? $settings['layers'] : array();

echo "<p>Data keys: " . implode(', ', array_keys($data)) . "</p>";
echo "<p>Layer keys: ";
foreach ($layers as $l) {
    echo $l['key'] . " ";
}
echo "</p>";

// Check key mapping
echo "<h3>Key Mapping Check:</h3>";
foreach ($layers as $layer) {
    $key = $layer['key'];
    $value = isset($data[$key]) ? $data[$key] : '[NOT FOUND]';
    $color = isset($data[$key]) ? 'green' : 'red';
    echo "<p>Layer key '<b>$key</b>' => <span style='color:$color'>$value</span></p>";
}

// Try to render
$template_path = $template['image_path'];
$output_path = 'uploads/previews/debug_test.png';

echo "<h3>Rendering...</h3>";
echo "<p>Template path: $template_path</p>";
echo "<p>Exists: " . (file_exists($template_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($template_path)) {
    $result = render_certificate($template_path, $output_path, $layers, $data);
    echo "<p>Render result: " . ($result ? '<span style="color:green">SUCCESS</span>' : '<span style="color:red">FAILED</span>') . "</p>";
    
    if ($result && file_exists($output_path)) {
        echo "<h3>Rendered Output:</h3>";
        echo "<img src='$output_path?t=" . time() . "' style='max-width:100%; border:1px solid #ccc;'>";
    }
}
