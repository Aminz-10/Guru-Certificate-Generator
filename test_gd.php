<?php
// test_gd.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>GD Diagnostic</h1>";

if (!extension_loaded('gd')) {
    die("GD Library NOT LOADED.");
} else {
    echo "GD Library Loaded.<br>";
}

$gd_info = gd_info();
echo "FreeType Support: " . ($gd_info['FreeType Support'] ? 'Yes' : 'No') . "<br>";

$font = realpath(__DIR__ . '/fonts/Roboto.ttf');
echo "Checking font: $font<br>";
if (file_exists($font)) {
    echo "Font file currently exists.<br>";
} else {
    echo "Font file NOT found.<br>";
    // Lists fonts dir
    echo "Files in fonts/:<br>";
    print_r(scandir(__DIR__ . '/fonts'));
}

// Try Image creation
echo "<h3>Testing Image Creation...</h3>";
$im = imagecreate(300, 100);
$bg = imagecolorallocate($im, 200, 200, 200);
$black = imagecolorallocate($im, 0, 0, 0);

if (function_exists('imagettftext')) {
    echo "imagettftext function exists.<br>";
    try {
        $bbox = imagettftext($im, 20, 0, 10, 50, $black, $font, "Hello GD");
        echo "imagettftext call success.<br>";
    } catch (Throwable $e) {
        echo "imagettftext Exception: " . $e->getMessage() . "<br>";
    } catch (Exception $e) {
         echo "imagettftext Exception: " . $e->getMessage() . "<br>";
    }
} else {
    echo "imagettftext function MISSING.<br>";
}

echo "Done.";
