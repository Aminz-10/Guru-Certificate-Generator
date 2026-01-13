<?php
// try_download.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = 'https://github.com/google/fonts/raw/main/ofl/opensans/OpenSans-Regular.ttf';
$file = __DIR__ . '/fonts/OpenSans-Test.ttf';

if (!is_dir(__DIR__ . '/fonts')) mkdir(__DIR__ . '/fonts');

echo "<h2>Testing Download</h2>";

// Method 1: copy()
echo "<h3>Method 1: copy()</h3>";
if (copy($url, $file)) {
    echo "Filesize: " . filesize($file) . " bytes<br>";
    echo "SUCCESS via copy()";
    exit;
} else {
    echo "copy() failed.<br>";
    echo "Last error: ";
    print_r(error_get_last());
}

// Method 2: file_get_contents with stream context
echo "<h3>Method 2: file_get_contents()</h3>";
$ctx = stream_context_create(array(
    'http' => array('timeout' => 10),
    'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)
));
$data = @file_get_contents($url, false, $ctx);
if ($data) {
    file_put_contents($file, $data);
    echo "SUCCESS via file_get_contents()";
    exit;
} else {
    echo "file_get_contents() failed.<br>";
    echo "Last error: ";
    print_r(error_get_last());
}

// Method 3: fopen
echo "<h3>Method 3: fopen()</h3>";
$fp_remote = fopen($url, 'rb', false, $ctx);
if ($fp_remote) {
    $fp_local = fopen($file, 'wb');
    while ($chunk = fread($fp_remote, 8192)) {
        fwrite($fp_local, $chunk);
    }
    fclose($fp_remote);
    fclose($fp_local);
    echo "SUCCESS via fopen()";
} else {
    echo "fopen() failed.<br>";
}
