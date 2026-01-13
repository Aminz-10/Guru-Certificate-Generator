<?php
// template_new.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $allowed = array('jpg', 'jpeg', 'png', 'pdf');
        if (!in_array($ext, $allowed)) {
            set_flash('error', 'Only JPG, PNG, or PDF files are allowed.');
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            set_flash('error', 'File size must be under 10MB.');
        } else {
            $target_dir = 'uploads/templates/';
            $success = false;
            $width = 0;
            $height = 0;
            
            if ($ext === 'pdf') {
                // Generate correct PDF filename first
                $filename = generate_filename('pdf');
                $target_path = $target_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $width = 842; 
                    $height = 595;
                    $success = true;
                } else {
                    set_flash('error', 'Failed to save PDF.');
                }
            } else {
                // Regular image upload
                $filename = generate_filename('png');
                $target_path = $target_dir . $filename;
                list($width, $height) = getimagesize($file['tmp_name']);
                
                // Convert to PNG for consistency
                $src = null;
                if ($ext === 'png') {
                    $src = imagecreatefrompng($file['tmp_name']);
                } else {
                    $src = imagecreatefromjpeg($file['tmp_name']);
                }
                
                if ($src) {
                    imagesavealpha($src, true);
                    imagepng($src, $target_path);
                    imagedestroy($src);
                    $success = true;
                } else {
                    set_flash('error', 'Failed to process image.');
                }
            }
            
            if ($success && $width > 0 && $height > 0) {
                $stmt = $pdo->prepare("INSERT INTO templates (user_id, title, image_path, width, height, settings_json) VALUES (?, ?, ?, ?, ?, ?)");
                $default_settings = json_encode(array(
                    'layers' => array(
                        array(
                            'key' => 'name',
                            'label' => 'Recipient Name',
                            'x' => floor($width / 2),
                            'y' => floor($height / 2),
                            'font_size' => 48,
                            'color' => '#000000',
                            'font' => 'Poppins-Bold.ttf',
                            'align' => 'center'
                        )
                    )
                ));
                
                $stmt->execute(array(current_user_id(), $title, $target_path, $width, $height, $default_settings));
                $new_id = $pdo->lastInsertId();
                
                set_flash('success', 'Template uploaded! Now design it.');
                redirect("template_designer.php?template_id=$new_id");
            }
        }
    } else {
        set_flash('error', 'Please select a valid file.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Template - CertiGen</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <style>
        :root {
            --surface: #ffffff;
            --bg-muted: #f8fafc;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --secondary-gradient: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }

        body {
            background-color: var(--bg-muted);
            color: #1e293b;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .upload-container {
            max-width: 650px;
            margin: 3rem auto;
        }

        .upload-card {
            background: white;
            border-radius: 28px;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.02);
            animation: cardIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .upload-header h2 {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }

        .upload-header p {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.95rem;
            color: #475569;
            margin-bottom: 0.75rem;
            margin-left: 4px;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.2s;
            box-sizing: border-box;
            font-family: inherit;
            background: #f8fafc;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .drop-zone {
            border: 2px dashed #e2e8f0;
            border-radius: 24px;
            padding: 3.5rem 2rem;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .drop-zone:hover, .drop-zone.dragover {
            border-color: #6366f1;
            background: #f5f3ff;
            transform: scale(1.01);
        }

        .drop-zone .icon-container {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .drop-zone:hover .icon-container {
            transform: rotate(-5deg) scale(1.1);
        }

        .drop-zone h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .drop-zone p {
            font-size: 0.95rem;
            color: #64748b;
        }

        .file-info {
            display: none;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            text-align: left;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .file-info.active {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-icon {
            width: 50px;
            height: 50px;
            background: var(--success-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .file-details h4 {
            margin: 0 0 2px 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .file-details p {
            margin: 0;
            font-size: 0.85rem;
            color: #059669;
            font-weight: 500;
        }

        .btn-modern {
            padding: 1.1rem 2rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            width: 100%;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn-primary-modern:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
            text-decoration: none !important;
        }

        .btn-primary-modern:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .format-badges {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 1.25rem;
        }

        .format-badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 2rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #6366f1;
        }

        /* Loading Modal */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text {
            margin-top: 1.5rem;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .loading-subtext {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="navbar-brand">
            <img src="assets/logo.png" alt="CertiGen" style="height:32px; vertical-align:middle; margin-right:8px;">
            <span style="font-weight:700; font-size:1.25rem; color:var(--primary);">CertiGen</span>
        </div>
        <ul class="navbar-nav" style="display:flex; gap:1.5rem; list-style:none;">
            <li><a href="dashboard.php" style="font-weight:600; color:var(--primary); text-decoration:none;">Dashboard</a></li>
            <li><a href="logout.php" style="font-weight:600; color:#ef4444; text-decoration:none;">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <div class="upload-container">
        <div class="upload-card">
            <div class="upload-header">
                <h2>✨ Create Template</h2>
                <p>Upload your certificate artwork to begin</p>
            </div>
            
            <?php $flash = get_flash(); if($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:2rem; border-radius:16px;">
                    <?php echo h($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group">
                    <label>Template Title</label>
                    <input type="text" name="title" placeholder="e.g. Employee of the Month" required>
                </div>
                
                <div class="drop-zone" id="dropZone">
                    <div class="icon-container">📂</div>
                    <h3>Drag & Drop artwork</h3>
                    <p>or click to browse your files</p>
                    <div class="format-badges">
                        <span class="format-badge">JPG</span>
                        <span class="format-badge">PNG</span>
                        <span class="format-badge">PDF</span>
                    </div>
                </div>
                <input type="file" name="image" id="fileInput" accept="image/png, image/jpeg, application/pdf" style="display:none;" required>
                
                <div class="file-info" id="fileInfo">
                    <div class="file-icon">✓</div>
                    <div class="file-details">
                        <h4 id="fileName">filename.png</h4>
                        <p id="fileSize">Ready to process</p>
                    </div>
                </div>
                
                <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn" disabled>
                    Upload & Start Designing 🚀
                </button>
            </form>
            
            <a href="dashboard.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Workspace
            </a>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="loading-overlay">
    <div class="loading-spinner"></div>
    <div class="loading-text">Uploading Your Template...</div>
    <div class="loading-subtext">Please wait, this may take a moment for larger files.</div>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const submitBtn = document.getElementById('submitBtn');
const uploadForm = document.getElementById('uploadForm');
const loadingModal = document.getElementById('loadingModal');

// Show loading modal on form submit
uploadForm.addEventListener('submit', function(e) {
    // Don't prevent default - we want normal form submission
    loadingModal.classList.add('active');
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Processing...';
});

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        handleFileSelect(e.dataTransfer.files[0]);
    }
});

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
        handleFileSelect(fileInput.files[0]);
    }
});

async function handleFileSelect(file) {
    if (file.type === 'application/pdf') {
        fileName.textContent = file.name;
        fileSize.textContent = "⚙️ Processing high-res PDF...";
        fileInfo.classList.add('active');
        dropZone.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.innerText = "⏳ Processing PDF...";

        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument(arrayBuffer).promise;
            const page = await pdf.getPage(1);
            
            const scale = 3.0; 
            const viewport = page.getViewport({ scale: scale });
            
            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            const context = canvas.getContext('2d');
            
            await page.render({
                canvasContext: context,
                viewport: viewport
            }).promise;
            
            canvas.toBlob((blob) => {
                const newFileName = file.name.replace(/\.pdf$/i, '.png');
                const newFile = new File([blob], newFileName, { type: 'image/png' });
                
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(newFile);
                fileInput.files = dataTransfer.files;
                
                showFileInfo(newFile, true);
            }, 'image/png');
            
        } catch (error) {
            console.error(error);
            alert("Error processing PDF: " + error.message);
            location.reload();
        }
    } else {
        if (fileInput.files[0] !== file) {
             const dataTransfer = new DataTransfer();
             dataTransfer.items.add(file);
             fileInput.files = dataTransfer.files;
        }
        showFileInfo(file, false);
    }
}

function showFileInfo(file, isConverted = false) {
    const sizeKB = (file.size / 1024).toFixed(1);
    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
    const sizeText = file.size > 1024 * 1024 ? sizeMB + ' MB' : sizeKB + ' KB';
    
    fileName.textContent = file.name;
    fileSize.textContent = sizeText + (isConverted ? ' • Converted & Optimized' : ' • Ready to upload');
    fileInfo.classList.add('active');
    dropZone.style.display = 'none';
    submitBtn.innerText = "Upload & Start Designing 🚀";
    submitBtn.disabled = false;
}
</script>
<script src="assets/app.js"></script>
</body>
</html>


