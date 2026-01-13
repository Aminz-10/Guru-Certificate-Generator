<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

require_login();

$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 2;

echo "<h1>Batch Debug for batch_id=$batch_id</h1>";

// Check batch
$stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->execute(array($batch_id));
$batch = $stmt->fetch();

echo "<h2>Batch Info:</h2>";
echo "<pre>";
print_r($batch);
echo "</pre>";

// Check batch_rows
$stmt = $pdo->prepare("SELECT * FROM batch_rows WHERE batch_id = ?");
$stmt->execute(array($batch_id));
$rows = $stmt->fetchAll();

echo "<h2>Batch Rows Count: " . count($rows) . "</h2>";
echo "<pre>";
print_r($rows);
echo "</pre>";

// Check template
if ($batch) {
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute(array($batch['template_id']));
    $template = $stmt->fetch();
    
    echo "<h2>Template Info:</h2>";
    echo "<pre>";
    print_r($template);
    echo "</pre>";
    
    echo "<h3>Template Settings:</h3>";
    echo "<pre>";
    print_r(json_decode($template['settings_json'], true));
    echo "</pre>";
}
