<?php
// check_image.php
$path = 'uploads/templates/75cfcee92ca3872a62ec2bf5a17a8cd0.jpg';

echo "Path: $path<br>";
echo "Exists: " . (file_exists($path) ? 'Yes' : 'No') . "<br>";
echo "Readable: " . (is_readable($path) ? 'Yes' : 'No') . "<br>";
echo "Size: " . filesize($path) . "<br>";
$info = getimagesize($path);
echo "Image Info: " . print_r($info, true) . "<br>";
echo "Absolute Path: " . realpath($path) . "<br>";
