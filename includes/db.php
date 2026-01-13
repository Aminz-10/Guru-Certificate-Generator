<?php
// includes/db.php
require_once __DIR__ . '/polyfills.php';
// Database Connection

$host = 'localhost';
$dbname = 'certigen';
$username = 'root'; // Default for many local setups, user should change
$password = '';     // Default for many local setups, user should change

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage() . 
        "<br>Please ensure you have created the database 'certigen' and imported schema.sql");
}
