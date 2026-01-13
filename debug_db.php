<?php
// debug_db.php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM templates");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($templates);
echo "</pre>";
