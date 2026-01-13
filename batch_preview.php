<?php
// batch_preview.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

require_login();

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batch_id) redirect('dashboard.php');

$stmt = $pdo->prepare("
    SELECT b.*, t.image_path, t.width as t_width, t.height as t_height 
    FROM batches b 
    JOIN templates t ON b.template_id = t.id 
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute(array($batch_id, current_user_id()));
$batch = $stmt->fetch();

if (!$batch) redirect('dashboard.php');

// Count rows
$stmt = $pdo->prepare("SELECT COUNT(*) FROM batch_rows WHERE batch_id = ?");
$stmt->execute(array($batch_id));
$total_rows = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Batch - CertiGen</title>
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
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        body {
            background-color: #f1f5f9;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            height: 70px;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            max-width: 1000px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2.5rem;
        }

        .gallery-card {
            background: white;
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .gallery-viewer {
            position: relative;
            background: #f8fafc;
            border-radius: 24px;
            padding: 3rem;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            border: 2px dashed #e2e8f0;
        }

        .preview-wrapper {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
            perspective: 1000px;
        }

        .preview-content {
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-radius: 8px;
            transition: transform 0.3s ease;
            max-width: 100%;
            max-height: 600px;
        }

        .preview-content:hover {
            transform: translateY(-5px);
        }

        .recipient-badge {
            background: #6366f1;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 15px rgba(99, 102, 241, 0.2);
            display: inline-block;
        }

        .gallery-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
        }

        .btn-modern {
            padding: 0.85rem 1.75rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 10px 15px rgba(99, 102, 241, 0.2);
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px rgba(99, 102, 241, 0.3);
            text-decoration: none !important;
        }

        .btn-secondary-modern {
            background: #ffffff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary-modern:hover {
            background: #f8fafc;
            color: #1e293b;
            border-color: #cbd5e1;
            text-decoration: none !important;
        }

        .btn-circle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            padding: 0;
        }

        .counter-badge {
            background: #f1f5f9;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            color: #475569;
            font-size: 1.1rem;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            z-index: 10000;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }

        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 32px;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .modal-overlay.active .modal-content { transform: scale(1); }

        .download-option {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem;
            background: #f8fafc;
            border-radius: 20px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 1rem;
            text-align: left;
        }

        .download-option:hover {
            background: #f1f5f9;
            border-color: #6366f1;
            transform: translateX(8px);
        }

        .download-icon {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        /* Processing Modal */
        .processing-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10001;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .processing-card {
            background: white;
            padding: 3rem;
            border-radius: 32px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 90%;
            transform: translateY(20px);
            transition: transform 0.3s;
        }

        .processing-overlay.active {
            display: flex;
            opacity: 1;
        }

        .processing-overlay.active .processing-card {
            transform: translateY(0);
        }

        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div style="display:flex; align-items:center; gap:1.5rem;">
        <a href="dashboard.php" class="btn-modern btn-secondary-modern btn-circle">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <div style="display:flex; flex-direction:column;">
            <span style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">Verification Mode</span>
            <span style="font-size:1.1rem; font-weight:700; color:#0f172a;"><?php echo h($batch['name']); ?></span>
        </div>
    </div>
</nav>

<!-- Download Modal -->
<div class="modal-overlay" id="downloadModal">
    <div class="modal-content">
        <h3 style="margin:0 0 0.5rem 0; font-size:1.5rem; font-weight:700;">Generate Batch</h3>
        <p style="color:#64748b; margin-bottom:2rem;">Choose your preferred delivery format:</p>
        
        <div class="download-option" onclick="downloadAll('all_one')">
            <div class="download-icon">📚</div>
            <div style="display:flex; flex-direction:column;">
                <span style="font-weight:700; color:#1e293b;">Combined PDF</span>
                <span style="font-size:0.8rem; color:#64748b;">All certificates in one file</span>
            </div>
        </div>
        
        <div class="download-option" onclick="downloadAll('all_zip')">
            <div class="download-icon">📦</div>
            <div style="display:flex; flex-direction:column;">
                <span style="font-weight:700; color:#1e293b;">Individual DOWNLOAD</span>
                <span style="font-size:0.8rem; color:#64748b;">Each certificate as a file</span>
            </div>
        </div>

        <button onclick="closeModal()" class="btn-modern btn-secondary-modern" style="width:100%; margin-top:1rem;">Cancel</button>
    </div>
</div>


</div>


<!-- Processing Modal -->
<div id="processingModal" class="processing-overlay">
    <div class="processing-card">
        <div class="loader-spinner"></div>
        <h2 style="margin-bottom:0.5rem; font-weight:700; color:#1e293b;">Generating Batch... ✨</h2>
        <p style="color:#64748b; line-height:1.6;">Please wait while we prepare your high-quality certificates. This might take a few moments.</p>
        <div style="margin-top:2rem; font-size:0.8rem; color:#94a3b8;">
            Don't close this window
        </div>
    </div>
</div>


<div class="container">
    <div class="header-section">
        <div>
            <h1 style="font-size: 2.25rem; font-weight: 800; margin: 0; color: #0f172a; letter-spacing: -0.025em;">
                Ready to Generate? ✨
            </h1>
            <p style="color: #64748b; font-size: 1.1rem; margin: 0.5rem 0 0 0;">
                Review your certificates before the final batch processing.
            </p>
        </div>
        <div style="display:flex; gap:1rem;">
            <button class="btn-modern btn-primary-modern" onclick="openModal()" style="padding: 1rem 2.5rem; font-size: 1.1rem;">
                📥 Download File
            </button>
        </div>
    </div>

    <div class="gallery-card">
        <div id="recipientBadge" class="recipient-badge">Loading...</div>
        
        <div class="gallery-viewer">
            <div id="previewWrapper" class="preview-wrapper">
                <?php 
                    $image_path = isset($batch['image_path']) ? $batch['image_path'] : '';
                    if (strtolower(pathinfo($image_path, PATHINFO_EXTENSION)) === 'pdf'): 
                ?>
                    <canvas id="bgCanvas" class="preview-content" style="background:white;"></canvas>
                    <img id="previewImage" class="preview-content" src="" alt="Certificate" style="position:absolute; top:0; left:50%; transform:translateX(-50%); opacity:0; pointer-events:none;">
                <?php else: ?>
                    <img id="previewImage" class="preview-content" src="" alt="Certificate">
                <?php endif; ?>
            </div>
            <p id="loadingText" style="display:none; color:#64748b; font-weight:600; margin-top:1.5rem;">✨ Polishing your preview...</p>
        </div>
        
        <div class="gallery-controls">
            <button id="prevBtn" class="btn-modern btn-secondary-modern btn-circle" onclick="prevCertificate()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="counter-badge">
                <span id="currentIndex">1</span> <span style="color:#cbd5e1; margin:0 0.5rem;">/</span> <span id="totalCount"><?php echo $total_rows; ?></span>
            </div>
            <button id="nextBtn" class="btn-modern btn-secondary-modern btn-circle" onclick="nextCertificate()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </div>
    </div>
</div>

<script>
const BATCH_ID = <?php echo $batch_id; ?>;
const TOTAL_ROWS = <?php echo $total_rows; ?>;
const TEMPLATE_PATH = '<?php echo h($batch['image_path']); ?>';
const bgCanvas = document.getElementById('bgCanvas');
const IS_PDF = !!bgCanvas;

// PDF State
let pdfDoc = null, pdfW = 0, pdfH = 0;

// Fetch full recipient data from PHP
const recipientData = <?php
    $stmt = $pdo->prepare("SELECT data_json FROM batch_rows WHERE batch_id = ? ORDER BY id");
    $stmt->execute(array($batch_id));
    $rows = $stmt->fetchAll();
    $dataArr = array();
    foreach($rows as $r) {
        $dataArr[] = json_decode($r['data_json'], true);
    }
    echo json_encode($dataArr);
?>;

let currentIndex = 0;

async function renderPdfBackground() {
    if (!IS_PDF || pdfDoc) return;
    try {
        const loadingTask = pdfjsLib.getDocument(TEMPLATE_PATH);
        const pdf = await loadingTask.promise;
        pdfDoc = pdf;
        const page = await pdf.getPage(1);
        const viewport = page.getViewport({ scale: 1.5 });
        const context = bgCanvas.getContext('2d');
        bgCanvas.height = viewport.height;
        bgCanvas.width = viewport.width;
        pdfW = Math.round(viewport.width / 1.5);
        pdfH = Math.round(viewport.height / 1.5);
        await page.render({canvasContext: context, viewport: viewport}).promise;
    } catch (err) { console.error('PDF.js Error:', err); }
}

async function updateGallery() {
    const img = document.getElementById('previewImage');
    const loading = document.getElementById('loadingText');
    const badgeEl = document.getElementById('recipientBadge');
    const currentEl = document.getElementById('currentIndex');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    loading.style.display = 'block';
    img.style.opacity = '0';
    
    const data = recipientData[currentIndex];
    let displayText = '';
    if (data) {
        const name = data.name || data.recipient_name || 'N/A';
        const ic = data.ic || data.ic_number || data.ic_no || '';
        displayText = `✨ ${name} ${ic ? '('+ic+')' : ''}`;
    } else {
        displayText = `Recipient ${currentIndex + 1}`;
    }
    badgeEl.textContent = displayText;
    currentEl.textContent = currentIndex + 1;
    
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex >= TOTAL_ROWS - 1;
    
    if (IS_PDF) await renderPdfBackground();

    const timestamp = Date.now();
    img.onload = () => {
        loading.style.display = 'none';
        img.style.opacity = '1';
        if (IS_PDF) {
            const rect = bgCanvas.getBoundingClientRect();
            img.style.width = rect.width + 'px';
            img.style.height = rect.height + 'px';
        }
    };
    
    let baseUrl = `api/render.php?batch_id=${BATCH_ID}&index=${currentIndex}&t=${timestamp}`;
    if (IS_PDF) {
        baseUrl += `&layers_only=1&real_w=${pdfW}&real_h=${pdfH}`;
    }
    img.src = baseUrl;
}

function prevCertificate() { if (currentIndex > 0) { currentIndex--; updateGallery(); } }
function nextCertificate() { if (currentIndex < TOTAL_ROWS - 1) { currentIndex++; updateGallery(); } }
function openModal() { document.getElementById('downloadModal').classList.add('active'); }
function closeModal() { document.getElementById('downloadModal').classList.remove('active'); }

function downloadAll(mode) {
    closeModal();
    const procModal = document.getElementById('processingModal');
    procModal.classList.add('active');
    
    const token = Date.now();
    window.location.href = `api/download.php?batch_id=${BATCH_ID}&mode=${mode}&format=pdf&download_token=${token}`;
    
    const checkCookie = setInterval(() => {
        if (document.cookie.indexOf('download_status_' + token) !== -1) {
            procModal.classList.remove('active');
            clearInterval(checkCookie);
            document.cookie = 'download_status_' + token + '=; Max-Age=-99999999; path=/;';
        }
    }, 500);

    // Max timeout fallback
    setTimeout(() => {
        procModal.classList.remove('active');
        clearInterval(checkCookie);
    }, 60000);
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') prevCertificate();
    if (e.key === 'ArrowRight') nextCertificate();
    if (e.key === 'Escape') closeModal();
});

document.getElementById('downloadModal').onclick = (e) => {
    if (e.target.id === 'downloadModal') closeModal();
};

updateGallery();
</script>
<script src="assets/app.js"></script>
</body>
</html>


