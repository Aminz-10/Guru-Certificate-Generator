<?php
// api/register_handler.php
if (session_id() == '') {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'error' => 'Method Not Allowed'));
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = isset($_POST['password']) ? $_POST['password'] : '';
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);

if (!$email || !$password || !$name) {
    echo json_encode(array('success' => false, 'error' => 'Full Name, email and password are required.'));
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(array('success' => false, 'error' => 'Password must be at least 6 characters.'));
    exit;
}

// Auto-migrate: check if 'name' column exists, if not add it
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'name'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) AFTER id");
    }
} catch (Exception $e) {
    // Ignore migration errors if any (e.g. permission issues), 
    // but usually this works in XAMPP
}

// Check if email exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute(array($email));
if ($stmt->fetch()) {
    echo json_encode(array('success' => false, 'error' => 'Email already used.'));
    exit;
}

// Create user
$stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
if ($stmt->execute(array($name, $email, $password))) {
    echo json_encode(array(
        'success' => true, 
        'message' => 'Account created successfully! ✨',
        'redirect' => 'login.php'
    ));
} else {
    echo json_encode(array('success' => false, 'error' => 'Error creating account.'));
}
?>
