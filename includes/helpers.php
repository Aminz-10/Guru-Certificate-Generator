<?php
// includes/helpers.php
// General Helper Functions

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message
 * Types: success, error, info
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = array('type' => $type, 'message' => $message);
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate a secure random filename
 */
function generate_filename($extension) {
    return bin2hex(random_bytes(16)) . '.' . $extension;
}

/**
 * Validate image upload
 */
function validate_image($file) {
    $allowed_types = array('image/jpeg', 'image/png');
    if (!in_array($file['type'], $allowed_types)) {
        return "Only JPG and PNG files are allowed.";
    }
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return "File size must be less than 5MB.";
    }
    return null; // No error
}

/**
 * JSON Response helper
 */
function json_response($data, $code = 200) {
    if (function_exists('http_response_code')) {
        http_response_code($code);
    } else {
        $text = 'OK';
        switch ($code) {
            case 200: $text = 'OK'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 500: $text = 'Internal Server Error'; break;
        }
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
        header($protocol . ' ' . $code . ' ' . $text);
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get available fonts
 */
function get_available_fonts() {
    $font_dir = __DIR__ . '/../fonts/';
    $fonts = array();
    if (is_dir($font_dir)) {
        if ($handle = opendir($font_dir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) == 'ttf') {
                    $fonts[] = $entry;
                }
            }
            closedir($handle);
        }
    }
    // Sort cleanly
    sort($fonts);
    // Ensure Poppins exists or fallback is handled in UI
    if (empty($fonts)) {
        $fonts[] = 'Poppins-Regular.ttf';
    }
    return $fonts;
}
