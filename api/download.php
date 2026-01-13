<?php
// api/download.php
// Handles downloading certificates as PNG or PDF without external libraries
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/renderer.php';
require_once __DIR__ . '/../includes/fpdf.php';
require_once __DIR__ . '/../includes/fpdi.php';

// Set limits for high-resolution batch processing
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutes max for large batches

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$index = filter_input(INPUT_GET, 'index', FILTER_VALIDATE_INT);
$format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'png';
$mode = filter_input(INPUT_GET, 'mode', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'single'; // single, all_one, all_zip

if (!$batch_id) {
    http_response_code(400);
    die('Missing batch_id');
}

// Download Tracking Cookie Helper
function set_download_cookie() {
    if (isset($_GET['download_token'])) {
        $token = $_GET['download_token'];
        // Use positional arguments for maximum compatibility with older PHP versions
        setcookie("download_status_" . $token, "success", time() + 300, "/");
    }
}

// Verify batch exists - Prioritize Template settings and dimensions
$stmt = $pdo->prepare("
    SELECT b.*, t.image_path, t.settings_json as template_settings, t.width as t_width, t.height as t_height 
    FROM batches b 
    JOIN templates t ON b.template_id = t.id 
    WHERE b.id = ?
");
$stmt->execute(array($batch_id));
$batch = $stmt->fetch();

if (!$batch) {
    http_response_code(404);
    die('Batch not found');
}

$settings_json = isset($batch['template_settings']) ? $batch['template_settings'] : $batch['settings_json'];
$settings = json_decode($settings_json, true);
$layers = isset($settings['layers']) ? $settings['layers'] : array();

$t_width = isset($batch['t_width']) ? $batch['t_width'] : (isset($batch['width']) ? $batch['width'] : 1169);
$t_height = isset($batch['t_height']) ? $batch['t_height'] : (isset($batch['height']) ? $batch['height'] : 826);

$use_background = (int)$batch['use_background'];
$templatePath = ($use_background === 1) ? __DIR__ . '/../' . $batch['image_path'] : 'white';

if ($use_background === 1 && !file_exists($templatePath)) {
    http_response_code(404);
    die('Template image not found');
}

// Get rows
$stmt = $pdo->prepare("SELECT data_json FROM batch_rows WHERE batch_id = ? ORDER BY id");
$stmt->execute(array($batch_id));
$rows = $stmt->fetchAll();

// Mark batch as done when downloading (combines generate + download)
if ($mode === 'all_one' || $mode === 'all_zip') {
    $stmt = $pdo->prepare("UPDATE batches SET status = 'done' WHERE id = ?");
    $stmt->execute(array($batch_id));
}

function renderCertificateImage($templatePath, $layers, $data, $w = 1169, $h = 826, $scale = 3.0, $cachedBg = null) {
    $img = null;
    $ext = ($templatePath === 'white') ? 'white' : strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
    
    // Calculate scaled dimensions
    $sw = (int)($w * $scale);
    $sh = (int)($h * $scale);

    if ($cachedBg) {
        $img = imagecreatetruecolor(imagesx($cachedBg), imagesy($cachedBg));
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagecopy($img, $cachedBg, 0, 0, 0, 0, imagesx($cachedBg), imagesy($cachedBg));
        imagealphablending($img, true);
    } elseif ($ext === 'white') {
        $img = imagecreatetruecolor($sw, $sh);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
    } else {
        // Load original template
        $src = null;
        if ($ext === 'png') {
            $src = imagecreatefrompng($templatePath);
        } else {
            $src = imagecreatefromjpeg($templatePath);
        }
        if (!$src) return null;

        // Create high-res canvas
        $img = imagecreatetruecolor($sw, $sh);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        
        // Resize template into high-res canvas
        imagecopyresampled($img, $src, 0, 0, 0, 0, $sw, $sh, imagesx($src), imagesy($src));
        imagedestroy($src);
        imagealphablending($img, true);
    }
    
    if (!$img) return null;
    
    foreach ($layers as $layer) {
        $key = $layer['key'];
        $text = isset($data[$key]) ? $data[$key] : '';
        if (empty($text)) continue;
        
        $fontPath = __DIR__ . '/../fonts/' . $layer['font'];
        if (!file_exists($fontPath)) {
            $fontPath = __DIR__ . '/../fonts/Poppins-Regular.ttf';
        }
        
        $hexColor = ltrim($layer['color'], '#');
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
        $color = imagecolorallocate($img, $r, $g, $b);
        
        // Scale parameters
        $fontSize = $layer['font_size'] * $scale;
        $x = $layer['x'] * $scale;
        $y = $layer['y'] * $scale;
        $align = $layer['align'];
        
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = abs($bbox[2] - $bbox[0]);
        
        if ($align === 'center') {
            $x = $x - ($textWidth / 2);
        } elseif ($align === 'right') {
            $x = $x - $textWidth;
        }
        
        imagettftext($img, $fontSize, 0, (int)$x, (int)$y, $color, $fontPath, $text);
    }
    
    return $img;
}

function renderCertificatePdf($templatePath, $layers, $data, $pdf = null, $storedW = 1169, $storedH = 826) {
    if (!$pdf) {
        $pdf = new FPDI();
    }
    
    $useBg = ($templatePath !== 'white');
    $size = array('w' => $storedW * (72/96), 'h' => $storedH * (72/96)); // Default scale px to pt
    $templateId = null;

    if ($useBg) {
        $pageCount = $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($templateId);
    }
    
    // Actual PDF dimensions in points. Convert to logical "browser" pixels for comparison.
    $realW = round($size['w'] * (96 / 72));
    $realH = round($size['h'] * (96 / 72));
    
    // Scale layers if stored dimensions don't match real PDF dimensions
    if ($storedW > 0 && $storedH > 0 && (abs($storedW - $realW) > 5 || abs($storedH - $realH) > 5)) {
        foreach ($layers as &$l) {
            $l['x'] = round(($l['x'] / $storedW) * $realW);
            $l['y'] = round(($l['y'] / $storedH) * $realH);
            $l['font_size'] = round(($l['font_size'] / $storedW) * $realW);
        }
    }
    
    
    // Add page with dimensions
    $pdf->AddPage($size['w'] > $size['h'] ? 'L' : 'P', array($size['w'], $size['h']));
    if ($templateId) {
        $pdf->useTemplate($templateId);
    }
    
    foreach ($layers as $layer) {
        $key = $layer['key'];
        $text = isset($data[$key]) ? $data[$key] : '';
        if (empty($text)) continue;
        
        $fontName = 'Arial'; // Standard font fallback
        // Note: FPDF/FPDI requires font definition files for custom TTF. 
        // For simplicity, we use core fonts or try to handle custom ones if available.
        $pdf->SetTextColor(
            hexdec(substr($layer['color'], 1, 2)),
            hexdec(substr($layer['color'], 3, 2)),
            hexdec(substr($layer['color'], 5, 2))
        );
        
        $fontSize = $layer['font_size'] * 0.75; // Convert px to pt roughly
        $pdf->SetFont($fontName, 'B', $fontSize);
        
        $x = $layer['x'] * (72 / 96); // Convert px to points
        $y = $layer['y'] * (72 / 96);
        
        $textWidth = $pdf->GetStringWidth($text);
        if ($layer['align'] === 'center') {
            $x = $x - ($textWidth / 2);
        } elseif ($layer['align'] === 'right') {
            $x = $x - $textWidth;
        }
        
        $pdf->Text($x, $y, $text);
    }
    
    return $pdf;
}

// Create a simple PDF with embedded image (no library)
function createPdfFromImage($imgData, $width, $height) {
    $imgLen = strlen($imgData);
    
    // Scale to A4 (595 x 842 points)
    $pageWidth = 595;
    $pageHeight = 842;
    
    // Calculate scale to fit
    $scale = min($pageWidth / $width, $pageHeight / $height);
    $imgW = round($width * $scale, 2);
    $imgH = round($height * $scale, 2);
    $imgX = round(($pageWidth - $imgW) / 2, 2);
    $imgY = round(($pageHeight - $imgH) / 2, 2);
    
    // Build PDF with tracked offsets
    $objects = array();
    $pdf = "%PDF-1.4\n";
    
    // Object 1: Catalog
    $objects[1] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    
    // Object 2: Pages
    $objects[2] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    
    // Object 3: Page
    $objects[3] = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Contents 4 0 R /Resources << /XObject << /Im0 5 0 R >> >> >>\nendobj\n";
    
    // Object 4: Content stream
    $content = "q\n$imgW 0 0 $imgH $imgX $imgY cm\n/Im0 Do\nQ\n";
    $contentLen = strlen($content);
    $objects[4] = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length $contentLen >>\nstream\n" . $content . "endstream\nendobj\n";
    
    // Object 5: Image XObject
    $objects[5] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width $width /Height $height /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length $imgLen >>\nstream\n";
    $pdf .= $imgData;
    $pdf .= "\nendstream\nendobj\n";
    
    // xref table
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $objects[$i]);
    }
    
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
    
    return $pdf;
}

$isPdfTemplate = (strtolower(pathinfo($templatePath, PATHINFO_EXTENSION)) === 'pdf');

if ($mode === 'single') {
    if ($index === null || $index < 0 || $index >= count($rows)) {
        http_response_code(400); die('Invalid index');
    }
    
    $data = json_decode($rows[$index]['data_json'], true);
    $name = isset($data['name']) ? preg_replace('/[^a-zA-Z0-9]/', '_', $data['name']) : 'certificate_' . ($index + 1);

    if ($isPdfTemplate && $use_background === 1) {
        $pdf = renderCertificatePdf($templatePath, $layers, $data, null, $t_width, $t_height);
        set_download_cookie();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
        echo $pdf->Output('S');
    } else {
        $img = renderCertificateImage($templatePath, $layers, $data, $t_width, $t_height);
        if (!$img) { http_response_code(500); die('Failed to render'); }
        
        set_download_cookie();
        if ($format === 'png') {
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="' . $name . '.png"');
            imagepng($img);
        } else {
            ob_start();
            imagejpeg($img, null, 80);
            $jpegData = ob_get_clean();
            $pdfContent = createPdfFromImage($jpegData, imagesx($img), imagesy($img));
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
            echo $pdfContent;
        }
        imagedestroy($img);
    }
    
} elseif ($mode === 'all_one') {
    if ($isPdfTemplate && $use_background === 1) {
        $pdf = new FPDI();
        foreach ($rows as $row) {
            $data = json_decode($row['data_json'], true);
            renderCertificatePdf($templatePath, $layers, $data, $pdf, $t_width, $t_height);
        }
        set_download_cookie();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="all_certificates.pdf"');
        echo $pdf->Output('S');
    } else {
        // Build multi-page PDF from images
        $pageCount = count($rows);
        
        // --- OPTIMIZATION: Cache resized background ---
        $cachedBg = null;
        if ($templatePath !== 'white') {
            $src = (strtolower(pathinfo($templatePath, PATHINFO_EXTENSION)) === 'png') ? @imagecreatefrompng($templatePath) : @imagecreatefromjpeg($templatePath);
            if ($src) {
                $sw = (int)($t_width * 3.0);
                $sh = (int)($t_height * 3.0);
                $cachedBg = imagecreatetruecolor($sw, $sh);
                imagealphablending($cachedBg, false);
                imagesavealpha($cachedBg, true);
                imagecopyresampled($cachedBg, $src, 0, 0, 0, 0, $sw, $sh, imagesx($src), imagesy($src));
                imagedestroy($src);
            }
        }
        // --------------------------------------------

        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pageKids = '';
        $objs = array();
        $currObj = 3;
        for ($i = 0; $i < $pageCount; $i++) {
            $pageKids .= $currObj . ' 0 R ';
            $currObj += 3;
        }
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [$pageKids] /Count $pageCount >>\nendobj\n";
        
        $currObj = 3;
        foreach ($rows as $row) {
            $data = json_decode($row['data_json'], true);
            $img = renderCertificateImage($templatePath, $layers, $data, $t_width, $t_height, 3.0, $cachedBg);
            if ($img) {
                ob_start(); imagejpeg($img, null, 75); $jpegData = ob_get_clean();
                $w = imagesx($img); $h = imagesy($img);
                $scale = min(595/$w, 842/$h);
                $imgW = $w * $scale; $imgH = $h * $scale;
                $imgX = (595 - $imgW) / 2; $imgY = (842 - $imgH) / 2;

                $pdf .= "$currObj 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents " . ($currObj + 1) . " 0 R /Resources << /XObject << /Im0 " . ($currObj + 2) . " 0 R >> >> >>\nendobj\n";
                $content = "q\n$imgW 0 0 $imgH $imgX $imgY cm\n/Im0 Do\nQ\n";
                $pdf .= ($currObj + 1) . " 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
                $pdf .= ($currObj + 2) . " 0 obj\n<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegData) . " >>\nstream\n" . $jpegData . "\nendstream\nendobj\n";
                $currObj += 3;
                imagedestroy($img);
            }
        }
        if ($cachedBg) imagedestroy($cachedBg);
        
        set_download_cookie();
        $pdf .= "xref\n0 " . ($currObj) . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $currObj; $i++) { $pdf .= sprintf("%010d 00000 n \n", 10 + $i * 100); } // dummy xref
        $pdf .= "trailer\n<< /Size $currObj /Root 1 0 R >>\nstartxref\n" . (strlen($pdf)) . "\n%%EOF";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="all_certificates.pdf"');
        echo $pdf;
    }
    } elseif ($mode === 'all_zip') {
    $zipPath = sys_get_temp_dir() . '/certs_' . $batch_id . '_' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) { http_response_code(500); die('Cannot create ZIP'); }
    
    // --- OPTIMIZATION: Cache resized background ---
    $cachedBg = null;
    if ($templatePath !== 'white') {
        $src = (strtolower(pathinfo($templatePath, PATHINFO_EXTENSION)) === 'png') ? @imagecreatefrompng($templatePath) : @imagecreatefromjpeg($templatePath);
        if ($src) {
            $sw = (int)($t_width * 3.0);
            $sh = (int)($t_height * 3.0);
            $cachedBg = imagecreatetruecolor($sw, $sh);
            imagealphablending($cachedBg, false);
            imagesavealpha($cachedBg, true);
            imagecopyresampled($cachedBg, $src, 0, 0, 0, 0, $sw, $sh, imagesx($src), imagesy($src));
            imagedestroy($src);
        }
    }
    // --------------------------------------------

    foreach ($rows as $i => $row) {
        $data = json_decode($row['data_json'], true);
        $name = isset($data['name']) ? preg_replace('/[^a-zA-Z0-9]/', '_', $data['name']) : 'certificate_' . ($i + 1);
        
        if ($isPdfTemplate && $use_background === 1) {
            $pdf = renderCertificatePdf($templatePath, $layers, $data, null, $t_width, $t_height);
            $zip->addFromString($name . '.pdf', $pdf->Output('S'));
        } else {
            $img = renderCertificateImage($templatePath, $layers, $data, $t_width, $t_height, 3.0, $cachedBg);
            if ($img) {
                if ($format === 'png') {
                    ob_start(); imagepng($img); $imgData = ob_get_clean();
                    $zip->addFromString($name . '.png', $imgData);
                } else {
                    ob_start(); imagejpeg($img, null, 80); $jpegData = ob_get_clean();
                    $pdfContent = createPdfFromImage($jpegData, imagesx($img), imagesy($img));
                    $zip->addFromString($name . '.pdf', $pdfContent);
                }
                imagedestroy($img);
            }
        }
    }
    if ($cachedBg) imagedestroy($cachedBg);
    set_download_cookie();
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="certificates.zip"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath); unlink($zipPath);
}
?>
