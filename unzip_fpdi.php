<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$file = __DIR__ . '/includes/fpdi.zip';
echo "Checking file: $file<br>";

if (!file_exists($file)) {
    die("File does not exist.");
}

echo "File exists. Size: " . filesize($file) . "<br>";

$zip = new ZipArchive;
$res = $zip->open($file);

if ($res === TRUE) {
    echo "Zip opened successfully.<br>";
    // Print files in zip
    for($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        echo "File: " . $stat['name'] . "<br>";
    }
    
    // Extract
    $zip->extractTo(__DIR__ . '/includes/');
    $zip->close();
    echo 'Extraction successful!';
} else {
    echo "Extraction failed. Error Code: " . $res . "<br>";
    echo "Message: " . getZipErrMsg($res);
}

function getZipErrMsg($errno) {
    // PHP < 5.4 array syntax
    $zipFileFunctions = array(
        'ER_OK'          => 'No error',
        'ER_MULTIDISK'   => 'Multi-disk zip archives not supported',
        'ER_RENAME'      => 'Renaming temporary file failed',
        'ER_CLOSE'       => 'Closing zip archive failed',
        'ER_SEEK'        => 'Seek error',
        'ER_READ'        => 'Read error',
        'ER_WRITE'       => 'Write error',
        'ER_CRC'         => 'CRC error',
        'ER_ZIPCLOSED'   => 'Containing zip archive was closed',
        'ER_NOENT'       => 'No such file',
        'ER_EXISTS'      => 'File already exists',
        'ER_OPEN'        => 'Can\'t open file',
        'ER_TMPOPEN'     => 'Failure to create temporary file',
        'ER_ZLIB'        => 'Zlib error',
        'ER_MEMORY'      => 'Memory allocation failure',
        'ER_CHANGED'     => 'Entry has been changed',
        'ER_COMPNOTSUPP' => 'Compression method not supported',
        'ER_EOF'         => 'Premature EOF',
        'ER_INVAL'       => 'Invalid argument',
        'ER_NOZIP'       => 'Not a zip archive',
        'ER_INTERNAL'    => 'Internal error',
        'ER_INCONS'      => 'Zip archive inconsistent',
        'ER_REMOVE'      => 'Can\'t remove file',
        'ER_DELETED'     => 'Entry has been deleted'
    );
    $errmsg = 'Unknown';
    foreach ($zipFileFunctions as $constName => $errorMessage) {
        if (defined("ZipArchive::$constName") && constant("ZipArchive::$constName") === $errno) {
            return "$constName ($errorMessage)";
        }
    }
    return 'Unknown Error';
}
?>
