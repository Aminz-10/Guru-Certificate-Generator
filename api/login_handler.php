<?php
// api/login_handler.php
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

if (!$email || !$password) {
    echo json_encode(array('success' => false, 'error' => 'Email and password are required.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(array($email));
$user = $stmt->fetch();

if ($user && $password === $user['password']) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name']; // Ensure name is in session
    $_SESSION['user_email'] = $user['email'];
    
    echo json_encode(array(
        'success' => true, 
        'message' => 'Welcome back, ' . $user['name'] . '! ✨',
        'redirect' => 'dashboard.php'
    ));
} else {
    echo json_encode(array('success' => false, 'error' => 'Invalid email or password.'));
}
?>
