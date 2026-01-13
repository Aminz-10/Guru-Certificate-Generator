<?php
// template_designer.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

require_login();

$template_id = filter_input(INPUT_GET, 'template_id', FILTER_VALIDATE_INT);
if (!$template_id) redirect('dashboard.php');

$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ? AND user_id = ?");
$stmt->execute(array($template_id, current_user_id()));
$template = $stmt->fetch();

if (!$template) redirect('dashboard.php');

$settings = json_decode($template['settings_json'], true) ?: array('layers' => array());
$initial_layers = json_encode($settings['layers']);
$available_fonts = get_available_fonts();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Designer - CertiGen</title>
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
            background-color: #f1f5f9;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
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
            z-index: 100;
        }

        .designer-layout { 
            display: flex; 
            flex: 1; 
            overflow: hidden;
            background: #e2e8f0;
        }

        .designer-sidebar { 
            width: 320px; 
            padding: 2rem 1.5rem; 
            background: #ffffff; 
            overflow-y: auto; 
            border-right: 1px solid rgba(0,0,0,0.05);
            box-shadow: 10px 0 30px rgba(0,0,0,0.02);
            z-index: 10;
        }

        .designer-stage { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: flex-start; 
            padding: 5rem 3rem 3rem 3rem; 
            overflow: auto; 
            position: relative;
            background-image: 
                radial-gradient(#cbd5e1 1px, transparent 1px),
                radial-gradient(#cbd5e1 1px, #e2e8f0 1px);
            background-size: 20px 20px;
        }

        /* Dynamic Fonts */
        <?php foreach($available_fonts as $font): 
            $fontName = pathinfo($font, PATHINFO_FILENAME);
        ?>
        @font-face {
            font-family: '<?php echo $fontName; ?>';
            src: url('fonts/<?php echo $font; ?>') format('truetype');
        }
        <?php endforeach; ?>

        /* Property Cards */
        .prop-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f1f5f9;
        }

        .prop-card-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
            font-family: inherit;
            box-sizing: border-box;
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-modern {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);
        }

        .btn-primary-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px rgba(99, 102, 241, 0.3);
            text-decoration: none !important;
        }

        .btn-secondary-modern {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary-modern:hover {
            background: #e2e8f0;
            color: #1e293b;
            text-decoration: none !important;
        }

        .btn-danger-modern {
            background: #fef2f2;
            color: #ef4444;
        }

        /* Canvas / Stage */
        .canvas-wrapper {
            background: white;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            border-radius: 4px;
            position: relative;
        }

        .text-layer {
            position: absolute;
            cursor: move;
            white-space: nowrap;
            user-select: none;
            padding: 8px;
            border: 2px solid transparent;
            border-radius: 8px;
            transition: border-color 0.2s;
        }

        .text-layer:hover {
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(99, 102, 241, 0.05);
        }

        .text-layer.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.1);
            z-index: 100;
        }

        /* Cute Toast */
        #toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #0f172a;
            color: white;
            padding: 1rem 2rem;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            transition: transform 0.4s cubic-bezier(0.17, 0.84, 0.44, 1);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }
        #toast.show { transform: translateX(-50%) translateY(0); }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            z-index: 10000;
        }
        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 32px;
            width: 420px;
            max-width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        /* Template Info Card */
        .info-card {
            background: #f0f4ff;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid #e0e7ff;
        }
        .info-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #6366f1;
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .edge-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .edge-item {
            background: rgba(255, 255, 255, 0.6);
            padding: 0.75rem;
            border-radius: 16px;
        }
        .edge-label {
            font-size: 0.7rem;
            color: #818cf8;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .edge-val {
            font-size: 1rem;
            font-weight: 800;
            color: #1e293b;
        }
        .edge-unit {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-left: 2px;
        }
    </style>
</head>
<body>

<div id="toast">
    <span style="font-size:1.25rem;">✨</span>
    <span id="toastMsg" style="font-weight:600;">Template saved successfully!</span>
</div>

<nav class="navbar">
    <div style="display:flex; align-items:center; gap:1.5rem;">
        <a href="dashboard.php" class="btn-modern btn-secondary-modern" style="border-radius:50%; width:40px; height:40px; padding:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <div style="display:flex; flex-direction:column;">
            <span style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">Designer Workspace</span>
            <span style="font-size:1.1rem; font-weight:700; color:#0f172a;"><?php echo h($template['title']); ?></span>
        </div>
    </div>
    <div style="display:flex; gap:1rem;">
        <button id="saveBtn" class="btn-modern btn-primary-modern" style="padding:0.75rem 2rem; font-size:1rem;">
            Save Changes ✨
        </button>
    </div>
</nav>

<div class="designer-layout">
    <div class="designer-sidebar">
        <!-- Template Info -->
        <div class="info-card">
            <div class="info-badge">Active</div>
            <div style="font-size: 0.75rem; font-weight: 800; color: #6366f1; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Template Info</div>
            <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                Dimensions: <span id="infoDim">...</span>
            </div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #64748b;">
                Detected: <span id="infoFormat">...</span>
            </div>

            <div class="edge-grid">
                <div class="edge-item">
                    <div class="edge-label">Left edge</div>
                    <div class="edge-val"><span id="valLeft">0</span><span class="edge-unit">cm</span></div>
                </div>
                <div class="edge-item">
                    <div class="edge-label">Right edge</div>
                    <div class="edge-val"><span id="valRight">0</span><span class="edge-unit">cm</span></div>
                </div>
                <div class="edge-item">
                    <div class="edge-label">Top edge</div>
                    <div class="edge-val"><span id="valTop">0</span><span class="edge-unit">cm</span></div>
                </div>
                <div class="edge-item">
                    <div class="edge-label">Bottom edge</div>
                    <div class="edge-val"><span id="valBottom">0</span><span class="edge-unit">cm</span></div>
                </div>
            </div>
        </div>

        <!-- Selection -->
        <div class="prop-card" style="background:white; border:2px solid #6366f1; box-shadow:0 4px 0 #6366f1;">
            <label class="form-label" style="color:#6366f1;">Active Layer</label>
            <select id="layerSelect" class="form-control" style="font-weight:700; color:#1e293b; border-color:transparent; background:#f5f3ff;"></select>
        </div>

        <!-- Typography -->
        <div class="prop-card">
            <div class="prop-card-title"><span>🔡</span> Typography</div>
            
            <div class="form-group">
                <label class="form-label">Font Family</label>
                <select id="inputFont" class="form-control"></select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Size (px)</label>
                    <input type="number" id="inputSizeNum" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" id="inputColor" class="form-control" style="height:45px; padding:4px;">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Alignment</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0.5rem;">
                    <button class="btn-modern btn-secondary-modern align-btn" data-align="left" style="padding:0.5rem;">L</button>
                    <button class="btn-modern btn-secondary-modern align-btn" data-align="center" style="padding:0.5rem;">C</button>
                    <button class="btn-modern btn-secondary-modern align-btn" data-align="right" style="padding:0.5rem;">R</button>
                    <select id="inputAlign" style="display:none;"><option value="left">left</option><option value="center">center</option><option value="right">right</option></select>
                </div>
            </div>
        </div>

        <!-- Position -->
        <div class="prop-card">
            <div class="prop-card-title"><span>📍</span> Position</div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">X Pos</label>
                    <input type="number" id="inputX" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Y Pos</label>
                    <input type="number" id="inputY" class="form-control">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                <button id="centerHBtn" class="btn-modern btn-secondary-modern">Center H</button>
                <button id="centerVBtn" class="btn-modern btn-secondary-modern">Center V</button>
            </div>
        </div>

        <!-- Layer Management -->
        <div class="prop-card">
            <div class="prop-card-title"><span>⚙️</span> Management</div>
            <div class="form-group">
                <label class="form-label">Display Label</label>
                <input type="text" id="inputLabel" class="form-control" placeholder="Recipient Name">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                <button id="addLayerBtn" class="btn-modern btn-primary-modern" style="background:#10b981;">+ Add</button>
                <button id="removeLayerBtn" class="btn-modern btn-danger-modern">Delete</button>
            </div>
        </div>
    </div>

    <!-- Custom Modern Modal -->
    <div id="customModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin:0 0 0.5rem 0; font-size:1.5rem; font-weight:700;">New Layer</h3>
            <p id="modalDesc" style="color:#64748b; margin-bottom:2rem;">Enter placeholder key (e.g. name, date):</p>
            <div class="modal-body">
                <input type="text" id="modalInput" class="form-control" style="border-radius:16px; padding:1rem; margin-bottom:1.5rem; text-align:center; font-size:1.1rem; font-weight:600;">
                <input type="text" id="modalInput2" class="form-control" style="display:none;">
            </div>
            <div class="modal-footer" style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:1rem;">
                <button id="modalCancel" class="btn-modern btn-secondary-modern" style="padding:1rem;">Cancel</button>
                <button id="modalConfirm" class="btn-modern btn-primary-modern" style="padding:1rem;">Confirm</button>
            </div>
        </div>
    </div>

    <div class="designer-stage">
        <div id="stage" class="canvas-wrapper">
            <?php if (strtolower(pathinfo($template['image_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                <canvas id="bgCanvas" style="width:100%; height:auto; display:block; z-index:1; pointer-events:none;"></canvas>
                <img id="bgImage" src="api/render.php?batch_id=0&index=0&template_id=<?php echo $template_id; ?>" style="opacity:0; width:100%; height:auto; display:block; pointer-events:none; position:absolute; top:0; left:0;">
            <?php else: ?>
                <img id="bgImage" src="<?php echo h($template['image_path']); ?>" style="display:block; max-width:100%; height:auto;">
            <?php endif; ?>
            <!-- Layers injected here -->
        </div>
    </div>
</div>

<script>
const TEMPLATE_ID = <?php echo $template_id; ?>;
let ORIGINAL_WIDTH = <?php echo $template['width']; ?>;
let ORIGINAL_HEIGHT = <?php echo $template['height']; ?>;
const RETURN_TO = <?php echo json_encode(isset($_GET['return_to']) ? $_GET['return_to'] : ''); ?>;
let LAYERS = <?php echo $initial_layers; ?>;
const AVAILABLE_FONTS = <?php echo json_encode($available_fonts); ?>;


// Normalize layers if empty
if (LAYERS.length === 0) {
    LAYERS = [
        {
            key: 'name', label: 'Recipient Name', x: ORIGINAL_WIDTH/2, y: ORIGINAL_HEIGHT/2 - 40, 
            font_size: 60, color: '#000000', align: 'center', font: 'Poppins-Bold.ttf'
        },
        {
            key: 'ic', label: 'IC Number', x: ORIGINAL_WIDTH/2, y: ORIGINAL_HEIGHT/2 + 40, 
            font_size: 40, color: '#000000', align: 'center', font: 'Poppins-Regular.ttf'
        }
    ];
}

const stage = document.getElementById('stage');
const bgImage = document.getElementById('bgImage');
const bgCanvas = document.getElementById('bgCanvas');
const IS_PDF = <?php echo (strtolower(pathinfo($template['image_path'], PATHINFO_EXTENSION)) === 'pdf') ? 'true' : 'false'; ?>;
const TEMPLATE_URL = '<?php echo h($template['image_path']); ?>';

// PDF Rendering Logic
async function renderPdfBackground() {
    if (!IS_PDF) return;
    try {
        const loadingTask = pdfjsLib.getDocument(TEMPLATE_URL);
        const pdf = await loadingTask.promise;
        const page = await pdf.getPage(1);
        
        const viewport = page.getViewport({ scale: 1.5 }); // High-res render
        const canvas = document.getElementById('bgCanvas');
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
            canvasContext: context,
            viewport: viewport
        };
        await page.render(renderContext).promise;

        // Sync TRUE dimensions
        const trueWidth = Math.round(viewport.width / 1.5);
        const trueHeight = Math.round(viewport.height / 1.5);
        
        if (Math.abs(trueWidth - ORIGINAL_WIDTH) > 5 || Math.abs(trueHeight - ORIGINAL_HEIGHT) > 5) {
            LAYERS.forEach(l => {
                l.x = Math.round((l.x / ORIGINAL_WIDTH) * trueWidth);
                l.y = Math.round((l.y / ORIGINAL_HEIGHT) * trueHeight);
            });
            ORIGINAL_WIDTH = trueWidth;
            ORIGINAL_HEIGHT = trueHeight;
        }
        
        updateScale();
    } catch (err) {
        console.error('PDF.js Error:', err);
    }
}

// UI Inputs
const inputs = {
    x: document.getElementById('inputX'),
    y: document.getElementById('inputY'),
    sizeNum: document.getElementById('inputSizeNum'),
    color: document.getElementById('inputColor'),
    align: document.getElementById('inputAlign'),
    font: document.getElementById('inputFont'),
    label: document.getElementById('inputLabel')
};
let activeLayerIndex = 0;
let scale = 1;

// Initialize
function init() {
    // Populate Font Dropdown
    const fontSelect = inputs.font;
    fontSelect.innerHTML = ''; 
    AVAILABLE_FONTS.forEach(font => {
        const option = document.createElement('option');
        option.value = font;
        option.textContent = font.replace(/\.ttf$/i, '').replace(/-/g, ' ');
        fontSelect.appendChild(option);
    });

    renderLayers();
    updateLayerSelect();
    updateControls();
    
    window.addEventListener('resize', updateScale);
    
    if (IS_PDF) {
        renderPdfBackground();
    } else {
        bgImage.onload = updateScale;
        if (bgImage.complete) updateScale();
    }

    // Align buttons (Left, Center, Right)
    document.querySelectorAll('.align-btn').forEach(btn => {
        btn.onclick = () => {
            const align = btn.dataset.align;
            inputs.align.value = align;
            updateLayer('align', align);
        };
    });
}

function updateLayerSelect() {
    const layerSelect = document.getElementById('layerSelect');
    layerSelect.innerHTML = '';
    LAYERS.forEach((l, i) => {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = l.label || l.key;
        if (i === activeLayerIndex) opt.selected = true;
        layerSelect.appendChild(opt);
    });
}

function updateScale() {
    const target = (IS_PDF && bgCanvas && bgCanvas.width > 0) ? bgCanvas : bgImage;
    const rect = target.getBoundingClientRect();
    if (rect.width > 0) {
        scale = rect.width / ORIGINAL_WIDTH;
    }
    renderLayers();
}

function renderLayers() {
    document.querySelectorAll('.text-layer').forEach(el => el.remove());

    LAYERS.forEach((layer, index) => {
        const el = document.createElement('div');
        el.className = 'text-layer';
        el.textContent = layer.label || 'Text';
        el.dataset.index = index;
        
        const displayFontSize = layer.font_size * scale;
        el.style.fontSize = displayFontSize + 'px';
        el.style.color = layer.color;
        
        const fontFamily = layer.font.replace(/\.ttf$/i, '');
        el.style.fontFamily = `"${fontFamily}", "Poppins", sans-serif`;
        
        el.style.left = (layer.x * scale) + 'px';
        el.style.top = (layer.y * scale) + 'px';
        
        if (layer.align === 'center') {
            el.style.transform = 'translate(-50%, -50%)';
        } else if (layer.align === 'right') {
            el.style.transform = 'translate(-100%, -50%)';
        } else {
            el.style.transform = 'translate(0, -50%)';
        }

        if (index === activeLayerIndex) el.classList.add('selected');
        el.addEventListener('mousedown', startDrag);
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            activeLayerIndex = index;
            updateLayerSelect();
            updateControls();
            renderLayers();
        });
        stage.appendChild(el);
    });
}

function updateControls() {
    const layer = LAYERS[activeLayerIndex];
    if (!layer) return;

    inputs.x.value = Math.round(layer.x);
    inputs.y.value = Math.round(layer.y);
    inputs.sizeNum.value = layer.font_size;
    inputs.color.value = layer.color;
    inputs.align.value = layer.align;
    inputs.font.value = layer.font;
    inputs.label.value = layer.label;

    // Active button state
    document.querySelectorAll('.align-btn').forEach(btn => {
        btn.classList.toggle('btn-primary-modern', btn.dataset.align === layer.align);
        btn.classList.toggle('btn-secondary-modern', btn.dataset.align !== layer.align);
    });

    // Update Template Info Card
    document.getElementById('infoDim').textContent = `${ORIGINAL_WIDTH} x ${ORIGINAL_HEIGHT}`;
    
    // Detect Format (A4 landscape is ~842x595, A4 portrait is ~595x842)
    let format = "Custom";
    const aspect = ORIGINAL_WIDTH / ORIGINAL_HEIGHT;
    if (Math.abs(ORIGINAL_WIDTH-842) < 20 && Math.abs(ORIGINAL_HEIGHT-595) < 20) format = "A4 Landscape (approx)";
    else if (Math.abs(ORIGINAL_WIDTH-595) < 20 && Math.abs(ORIGINAL_HEIGHT-842) < 20) format = "A4 Portrait (approx)";
    else if (Math.abs(ORIGINAL_WIDTH-595) < 20 && Math.abs(ORIGINAL_HEIGHT-420) < 20) format = "A5 Landscape (approx)";
    else if (Math.abs(ORIGINAL_WIDTH-420) < 20 && Math.abs(ORIGINAL_HEIGHT-595) < 20) format = "A5 Portrait (approx)";
    document.getElementById('infoFormat').textContent = format;

    // CM conversion (1pt = 25.4/72 mm = 0.3527 mm)
    const ptToCm = 0.0352778;
    document.getElementById('valLeft').textContent = (layer.x * ptToCm).toFixed(2);
    document.getElementById('valRight').textContent = ((ORIGINAL_WIDTH - layer.x) * ptToCm).toFixed(2);
    document.getElementById('valTop').textContent = (layer.y * ptToCm).toFixed(2);
    document.getElementById('valBottom').textContent = ((ORIGINAL_HEIGHT - layer.y) * ptToCm).toFixed(2);
}

// Input Event Listeners
inputs.x.oninput = (e) => updateLayer('x', parseFloat(e.target.value));
inputs.y.oninput = (e) => updateLayer('y', parseFloat(e.target.value));
inputs.sizeNum.oninput = (e) => updateLayer('font_size', parseInt(e.target.value));
inputs.color.oninput = (e) => updateLayer('color', e.target.value);
inputs.font.onchange = (e) => updateLayer('font', e.target.value);
inputs.label.oninput = (e) => {
    updateLayer('label', e.target.value);
    updateLayerSelect();
};

function updateLayer(key, value) {
    if (activeLayerIndex === -1) return;
    LAYERS[activeLayerIndex][key] = value;
    renderLayers();
    if (key === 'align') updateControls();
}

// Custom Modal Handler
function showModal(options) {
    const modal = document.getElementById('customModal');
    const title = document.getElementById('modalTitle');
    const desc = document.getElementById('modalDesc');
    const input = document.getElementById('modalInput');
    const confirmBtn = document.getElementById('modalConfirm');
    const cancelBtn = document.getElementById('modalCancel');

    title.textContent = options.title || 'Alert';
    desc.textContent = options.desc || '';
    input.value = options.defaultValue || '';
    input.placeholder = options.placeholder || '';
    input.style.display = options.showInput ? 'block' : 'none';

    modal.style.display = 'flex';
    input.focus();

    return new Promise((resolve) => {
        const handleConfirm = () => {
            cleanup();
            resolve(input.value || true);
        };
        const handleCancel = () => {
            cleanup();
            resolve(null);
        };
        const cleanup = () => {
            modal.style.display = 'none';
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
        };
        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        input.onkeydown = (e) => { if(e.key === 'Enter') handleConfirm(); };
    });
}

// Layer Add/Remove
document.getElementById('addLayerBtn').onclick = async () => {
    const label = await showModal({
        title: '✨ Add New Layer',
        desc: 'What would you like to call this field?',
        showInput: true,
        defaultValue: 'New Field'
    });
    if (!label || typeof label !== 'string') return;
    const baseKey = label.toLowerCase().trim().replace(/[^a-z0-9]/g, '_') || 'field';
    let newKey = baseKey;
    let counter = 1;
    while (LAYERS.some(l => l.key === newKey)) newKey = baseKey + '_' + counter++;
    
    LAYERS.push({
        key: newKey, label: label, x: ORIGINAL_WIDTH / 2, y: ORIGINAL_HEIGHT / 2,
        font_size: 40, color: '#000000', align: 'center', font: 'Poppins-Regular.ttf'
    });
    activeLayerIndex = LAYERS.length - 1;
    updateLayerSelect();
    updateControls();
    renderLayers();
};

document.getElementById('removeLayerBtn').onclick = async () => {
    if (LAYERS.length <= 1) { alert('You must have at least one layer.'); return; }
    const res = await showModal({
        title: '🗑️ Delete Layer?',
        desc: 'Are you sure you want to remove this layer?',
        showInput: false
    });
    if (res) {
        LAYERS.splice(activeLayerIndex, 1);
        activeLayerIndex = Math.max(0, activeLayerIndex - 1);
        updateLayerSelect();
        updateControls();
        renderLayers();
    }
};

document.getElementById('layerSelect').onchange = (e) => {
    activeLayerIndex = parseInt(e.target.value);
    updateControls();
    renderLayers();
};

// Quick Alignment Buttons
document.getElementById('centerHBtn').onclick = () => {
    updateLayer('x', ORIGINAL_WIDTH / 2);
    updateLayer('align', 'center');
};
document.getElementById('centerVBtn').onclick = () => {
    updateLayer('y', ORIGINAL_HEIGHT / 2);
};

// Drag functionality
let isDragging = false, dragLayer = null, startX, startY, initialLayerX, initialLayerY;

function startDrag(e) {
    if (e.target.classList.contains('text-layer')) {
        isDragging = true;
        dragLayer = parseInt(e.target.dataset.index);
        activeLayerIndex = dragLayer;
        updateLayerSelect();
        updateControls();
        renderLayers();
        startX = e.clientX;
        startY = e.clientY;
        initialLayerX = LAYERS[dragLayer].x;
        initialLayerY = LAYERS[dragLayer].y;
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
    }
}

function onDrag(e) {
    if (!isDragging) return;
    const dx = (e.clientX - startX) / scale;
    const dy = (e.clientY - startY) / scale;
    LAYERS[dragLayer].x = initialLayerX + dx;
    LAYERS[dragLayer].y = initialLayerY + dy;
    updateControls();
    renderLayers();
}

function stopDrag() {
    isDragging = false;
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
}

// Keyboard Navigation
document.addEventListener('keydown', (e) => {
    // If typing in an input, skip keyboard nav
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;

    const layer = LAYERS[activeLayerIndex];
    if (!layer) return;

    const moveStep = e.shiftKey ? 10 : 1;
    let changed = false;

    if (e.key === 'ArrowLeft') { layer.x -= moveStep; changed = true; }
    else if (e.key === 'ArrowRight') { layer.x += moveStep; changed = true; }
    else if (e.key === 'ArrowUp') { layer.y -= moveStep; changed = true; }
    else if (e.key === 'ArrowDown') { layer.y += moveStep; changed = true; }

    if (changed) {
        e.preventDefault(); // Prevent page scroll
        updateControls();
        renderLayers();
    }
});

// Save
document.getElementById('saveBtn').onclick = () => {
    const btn = document.getElementById('saveBtn');
    const oldText = btn.innerHTML;
    btn.innerHTML = 'Saving...';
    btn.disabled = true;

    fetch('api/save_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            template_id: TEMPLATE_ID,
            settings: { layers: LAYERS },
            width: ORIGINAL_WIDTH,
            height: ORIGINAL_HEIGHT
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('toast').classList.add('show');
            setTimeout(() => { 
                if (RETURN_TO) window.location.href = RETURN_TO;
                else window.location.href = 'batch_new.php?template_id=' + TEMPLATE_ID;
            }, 1000);
        } else {
            alert('Error: ' + data.error);
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Failed to save');
        btn.innerHTML = oldText;
        btn.disabled = false;
    });
};

init();
</script>
<script src="assets/app.js"></script>
</body>
</html>

