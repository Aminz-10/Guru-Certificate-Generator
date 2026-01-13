<?php
// includes/renderer.php
// Core Logic for rendering certificates

function render_certificate($template_path, $output_path, $layers, $data, $w = 0, $h = 0) {
    // Load Image
    $ext = $template_path ? strtolower(pathinfo($template_path, PATHINFO_EXTENSION)) : 'transparent';
    if ($template_path === 'white') $ext = 'white';
    $im = null;

    if ($template_path && $template_path !== 'white' && !file_exists($template_path)) {
        return false;
    }

    if ($ext === 'png') {
        $im = @imagecreatefrompng($template_path);
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $im = @imagecreatefromjpeg($template_path);
    } elseif ($ext === 'pdf') {
        // Since we can't render PDF to image without Imagick on server,
        // we create a high-res placeholder for the preview/designer.
        $pw = ($w > 0) ? $w : 1169;
        $ph = ($h > 0) ? $h : 826;
        $im = imagecreatetruecolor($pw, $ph);
        $bg = imagecolorallocate($im, 245, 248, 255);
        imagefill($im, 0, 0, $bg);
        $grid = imagecolorallocate($im, 230, 235, 250);
        for ($i=0; $i<$pw; $i+=50) imageline($im, $i, 0, $i, $ph, $grid);
        for ($i=0; $i<$ph; $i+=50) imageline($im, 0, $i, $pw, $i, $grid);
        
        $tc = imagecolorallocate($im, 100, 110, 160);
        imagestring($im, 5, 20, 20, 'PDF Template Mode (Vector Quality)', $tc);
    } elseif ($ext === 'white') {
        // Plain white background
        $pw = ($w > 0) ? $w : 1169;
        $ph = ($h > 0) ? $h : 826;
        $im = imagecreatetruecolor($pw, $ph);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);
    } elseif ($ext === 'transparent') {
        // Create a transparent canvas for hybrid overlay
        $pw = ($w > 0) ? $w : 1169;
        $ph = ($h > 0) ? $h : 826;
        $im = imagecreatetruecolor($pw, $ph);
        imagealphablending($im, false);
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);
        imagesavealpha($im, true);
        imagealphablending($im, true); // Re-enable for text rendering
    } else {
        return false;
    }

    if (!$im) return false;

    // Enable alpha blending for transparent backgrounds (though usually certificates are opaque)
    imagealphablending($im, true);
    imagesavealpha($im, true);

    foreach ($layers as $layer) {
        $key = $layer['key'];
        $text = isset($data[$key]) ? $data[$key] : ''; // Get text from CSV data or default
        
        // Font settings
        $fontDir = __DIR__ . '/../fonts/';
        $targetFont = isset($layer['font']) ? $layer['font'] : 'Poppins-Regular.ttf';
        
        // Try exact match
        $candidates = array(
            $targetFont,
            'Poppins-Regular.ttf',
            'Poppins.ttf',
            'Arial.ttf' // Last resort if system has it, or valid file
        );
        
        $fontPath = false;
        foreach ($candidates as $f) {
            $tryPath = realpath($fontDir . $f);
            if ($tryPath && file_exists($tryPath)) {
                $fontPath = $tryPath;
                break;
            }
        }
        
        if (!$fontPath) {
            // Scan dir for ANY ttf
            $files = glob($fontDir . '*.ttf');
            if ($files && count($files) > 0) {
                 $fontPath = realpath($files[0]);
            } else {
                 // No fonts found. Error or silent fail?
                 // imagettftext requires a font.
                 // Let's return false -> render failure
                 error_log("No fonts found in $fontDir");
                 return false;
            }
        }

        $size = $layer['font_size'];
        $colorHex = $layer['color'];
        list($r, $g, $b) = sscanf($colorHex, "#%02x%02x%02x");
        $color = imagecolorallocate($im, $r, $g, $b);
        $angle = 0; // No rotation support yet
        
        // Calculate Alignment
        $bbox = imagettfbbox($size, $angle, $fontPath, $text);
        // bbox: 0=LL, 1=LR, 2=UR, 3=UL, 4=UL, 5=UR, 6=LR, 7=LL (approx)
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7]; // approximate height

        $x = $layer['x'];
        $y = $layer['y'];

        // Adjust X based on alignment
        if ($layer['align'] === 'center') {
            $x = $x - ($textWidth / 2);
        } elseif ($layer['align'] === 'right') {
            $x = $x - $textWidth;
        }
        
        // Adjust Y? 
        // imagettftext X,Y is the bottom-left corner of the first character (baseline).
        // Our designer usually thinks in terms of "center of text box".
        // If we want visual vertical centering around the point Y:
        // shift down by half cap-height roughly.
        // Simple heuristic: add size/3 to Y to push baseline down so visual center matches Y.
        // or just accept Y is baseline. Let's stick to Y is baseline to keep it predictable,
        // OR, since my CSS designer centered it, I should attempt to replicate that.
        // CSS transform translate(0, -50%) means Y is the vertical center.
        // So I need to calculate baseline position such that vertical center is at Y.
        // Baseline = Y + (TextHeight / 2) - Descent? It's complex.
        // Simplest approximation: add size/3.
        $y = $y + ($size * 0.35); 

        imagettftext($im, $size, $angle, $x, $y, $color, $fontPath, $text);
    }

    // Save Output
    // Ensure dir exists
    $dir = dirname($output_path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $result = imagepng($im, $output_path);
    imagedestroy($im);
    
    return $result;
}
