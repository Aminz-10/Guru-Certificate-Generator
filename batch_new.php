<?php
// batch_new.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

require_login();

$template_id = filter_input(INPUT_GET, 'template_id', FILTER_VALIDATE_INT);
if (!$template_id) redirect('dashboard.php');

// Fetch Template
$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ? AND user_id = ?");
$stmt->execute(array($template_id, current_user_id()));
$template = $stmt->fetch();
if (!$template) redirect('dashboard.php');

$settings = json_decode($template['settings_json'], true);
$layers = isset($settings['layers']) ? $settings['layers'] : array();
$layer_keys = array_column($layers, 'key'); // e.g. ['name', 'course']

// (Redundant block removed)

// HANDLING CSV UPLOAD AND MAPPING IN ONE FLOW IS TRICKY WITHOUT JS.
// Let's change approach: 
// 1. Page shows Upload Form.
// 2. User uploads.
// 3. Script parses CSV headers immediately (stored in temp session or re-read) and shows "Confirm Mapping" step.
// 4. User confirms -> Create Batch.

// Resume from session if requested
if (isset($_GET['resume']) && isset($_SESSION['batch_resume'][$template_id])) {
    $resume_data = $_SESSION['batch_resume'][$template_id];
    $batch_name_input = $resume_data['batch_name'];
    $csv_path = $resume_data['csv_path'];
    if (file_exists($csv_path)) {
        $step = 2;
        if (($handle = fopen($csv_path, "r")) !== FALSE) {
            $csv_headers = fgetcsv($handle);
            $first_row_data = fgetcsv($handle);
            fclose($handle);
        }
    }
} else {
    $step = isset($_POST['step']) ? $_POST['step'] : 1;
}

if ($step == 2 && isset($_POST['csv_path'])) {
    // Finalize Batch
    $batch_name = $_POST['batch_name'];
    $csv_path = $_POST['csv_path'];
    $mapping = $_POST['mapping']; // array [layer_key => csv_header_index]
    
    // Debug: Check if file exists
    if (!file_exists($csv_path)) {
        set_flash('error', 'CSV file not found: ' . $csv_path);
        $step = 1;
    } else {
        // Process CSV
        $data_rows = array();
        if (($handle = fopen($csv_path, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            $row_count = 0;
            while (($row = fgetcsv($handle)) !== FALSE) {
                $row_data = array();
                foreach ($mapping as $key => $col_index) {
                    if ($col_index !== '') {
                        $row_data[$key] = isset($row[$col_index]) ? $row[$col_index] : '';
                    }
                }
                $data_rows[] = $row_data;
                $row_count++;
            }
            fclose($handle);
        } else {
            set_flash('error', 'Failed to open CSV file');
            $step = 1;
        }
    
        if (count($data_rows) > 0) {
            // Create Batch
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO batches (user_id, template_id, name, csv_path, status, use_background) VALUES (?, ?, ?, ?, 'ready', ?)");
                $use_background = isset($_POST['use_background']) ? (int)$_POST['use_background'] : 1;
                $stmt->execute(array(current_user_id(), $template_id, $batch_name, $csv_path, $use_background));
                $batch_id = $pdo->lastInsertId();
                
                $stmt_row = $pdo->prepare("INSERT INTO batch_rows (batch_id, data_json) VALUES (?, ?)");
                foreach ($data_rows as $row_data) {
                    $stmt_row->execute(array($batch_id, json_encode($row_data)));
                }
                $pdo->commit();
                set_flash('success', 'Batch created! Ready to preview.');
                redirect("batch_preview.php?batch_id=$batch_id");
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Error creating batch: ' . $e->getMessage());
                $step = 1;
            }
        } else {
            set_flash('error', 'CSV file was empty or invalid.');
            $step = 1;
        }
    }
} elseif ($step == 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle File Upload to Intermediary
     if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'csv') {
            set_flash('error', 'Only CSV files allowed.');
        } else {
            $csv_filename = generate_filename('csv');
            $csv_path = 'uploads/csv/' . $csv_filename;
            if (move_uploaded_file($file['tmp_name'], $csv_path)) {
                $step = 2; // Move to mapping
                // Read headers and first row for mapping & preview
                $csv_headers = array();
                $first_row_data = array();
                if (($handle = fopen($csv_path, "r")) !== FALSE) {
                    $csv_headers = fgetcsv($handle);
                    $first_row_data = fgetcsv($handle); // First data row
                    fclose($handle);
                }
                $batch_name_input = $_POST['name'];
                
                // Store in session for resume
                $_SESSION['batch_resume'][$template_id] = array(
                    'batch_name' => $batch_name_input,
                    'csv_path' => $csv_path
                );
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Batch - CertiGen</title>
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

        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
            gap: 2rem;
            position: relative;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.95rem;
            position: relative;
            z-index: 2;
        }

        .step.active {
            color: #4f46e5;
        }

        .step.completed .number {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        .step.active .number {
            background: #fff;
            color: #4f46e5;
            border: 2px solid #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .number {
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .card {
            background: white;
            border-radius: 28px;
            padding: 2.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0,0,0,0.02);
            margin-bottom: 2rem;
            animation: cardIn 0.5s ease;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-title-group h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.5rem 0;
        }

        .card-title-group p {
            color: #64748b;
            margin: 0;
        }

        .drop-zone {
            border: 2px dashed #e2e8f0;
            border-radius: 20px;
            padding: 3rem;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .drop-zone:hover {
            border-color: #6366f1;
            background: #f5f3ff;
            transform: scale(1.005);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #475569;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1.25rem;
            border: 2px solid #f1f5f9;
            border-radius: 14px;
            transition: all 0.2s;
            font-family: inherit;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .mapping-list {
            background: #f8fafc;
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid #f1f5f9;
        }

        .mapping-item {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.03);
        }

        .mapping-item:last-child {
            border-bottom: none;
        }

        .mapping-label {
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mapping-tag {
            font-size: 0.75rem;
            background: #e0e7ff;
            color: #4338ca;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .preview-pane {
            background: #1e293b;
            border-radius: 24px;
            overflow: hidden;
            position: sticky;
            top: 5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .preview-header {
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #cbd5e1;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .preview-stage {
            min-height: 450px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 1rem;
        }

        .btn-modern {
            padding: 1rem 1.5rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.95rem;
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
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
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

        .preview-table-container {
            margin-top: 1.5rem;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            background: white;
        }

        .preview-table th {
            background: #f8fafc;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .preview-table td {
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
            border-top: 1px solid #f1f5f9;
        }

        /* Background Options */
        .bg-option-card {
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .bg-radio:checked + .bg-option-card {
            border-color: #6366f1;
            background: #f5f3ff;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }
        .bg-option-card:hover {
            border-color: #cbd5e1;
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
    <div style="max-width:<?php echo $step == 2 ? '1200px' : '850px'; ?>; margin:0 auto;">
        
        <div class="step-indicator">
            <div class="step <?php echo $step == 1 ? 'active' : 'completed'; ?>">
                <span class="number"><?php echo $step == 1 ? '1' : '✓'; ?></span> Setup Batch
            </div>
            <div style="width:60px; height:2px; background:#e2e8f0; align-self:center;"></div>
            <div class="step <?php echo $step == 2 ? 'active' : ''; ?>">
                <span class="number">2</span> Data Mapping
            </div>
        </div>

        <?php if ($step == 1): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title-group">
                        <h2>🚀 Create New Batch</h2>
                        <p>Template: <span style="color:#4f46e5; font-weight:600;"><?php echo h($template['title']); ?></span></p>
                    </div>
                </div>

                <?php $flash = get_flash(); if($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:2rem; border-radius:16px;">
                        <?php echo h($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Batch Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. October 2023 Cohort" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload Data (CSV)</label>
                        <div class="drop-zone" id="dropZone">
                            <input type="file" name="csv" id="csvFile" accept=".csv" required style="display:none;">
                            <label for="csvFile" style="cursor:pointer; display:block;">
                                <div style="font-size:3rem; margin-bottom:1rem;">📄</div>
                                <h4 id="fileName" style="margin:0 0 0.5rem 0; font-size:1.1rem;">Choose CSV File</h4>
                                <p style="font-size:0.9rem; color:#94a3b8; margin:0;">or drag and drop it here</p>
                            </label>
                        </div>
                    </div>

                    <div id="previewSection" class="preview-table-container" style="display:none;">
                        <div style="padding:1rem 1.5rem; background:#f8fafc; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; font-size:0.85rem; color:#475569;">CSV DATA PREVIEW</span>
                            <span id="rowCount" style="font-weight:600; font-size:0.75rem; color:#6366f1; background:rgba(99,102,241,0.1); padding:4px 10px; border-radius:20px;"></span>
                        </div>
                        <div id="previewTableWrapper" style="max-height:300px; overflow:auto;"></div>
                    </div>

                    <div style="margin-top:2.5rem;">
                        <button type="submit" class="btn-modern btn-primary-modern" style="width:100%; font-size:1.1rem; padding:1.25rem;">
                            Next: Map Columns &rarr;
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($step == 2): ?>
            <div style="display:grid; grid-template-columns: 1fr 450px; gap:2.5rem; align-items:start;">
                
                <div class="card" style="margin-bottom:0;">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="card-title-group">
                            <h2>🔗 Map Columns</h2>
                            <p>Match template fields with CSV columns</p>
                        </div>
                        <a href="template_designer.php?template_id=<?php echo $template_id; ?>&return_to=<?php echo urlencode('batch_new.php?template_id=' . $template_id . '&resume=1'); ?>" class="btn-modern btn-secondary-modern" style="padding:0.75rem 1rem; border:1px solid #e2e8f0; background:white;">
                            🎨 Edit Layout
                        </a>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="step" value="2">
                        <input type="hidden" name="batch_name" value="<?php echo h($batch_name_input); ?>">
                        <input type="hidden" name="csv_path" value="<?php echo h($csv_path); ?>">

                        <!-- Background Setting -->
                        <div class="prop-card" style="margin-bottom: 2.5rem; #e2e8f0; background: #fff;">
                            <label class="form-label" style="font-weight: 700; color: #4f46e5; display: flex; align-items: center; gap: 8px;">
                                <span>🖼️</span> Background Artwork
                            </label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                                <label style="cursor: pointer; position: relative;">
                                    <input type="radio" name="use_background" value="1" checked class="bg-radio" style="display:none;">
                                    <div class="bg-option-card">
                                        <span style="font-size: 1.25rem;">🎨</span>
                                        <div style="font-weight: 700; font-size: 0.85rem;">Template Artwork</div>
                                        <div style="font-size: 0.7rem; color: #64748b;">Use uploaded image</div>
                                    </div>
                                </label>
                                <label style="cursor: pointer; position: relative;">
                                    <input type="radio" name="use_background" value="0" class="bg-radio" style="display:none;">
                                    <div class="bg-option-card">
                                        <span style="font-size: 1.25rem;">⬜</span>
                                        <div style="font-weight: 700; font-size: 0.85rem;">Plain White</div>
                                        <div style="font-size: 0.7rem; color: #64748b;">Clean white paper</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="mapping-list">
                            <?php foreach($layers as $layer): ?>
                                <div class="mapping-item">
                                    <div class="mapping-label">
                                        <span class="mapping-tag"><?php echo h($layer['key']); ?></span>
                                        <span><?php echo h($layer['label'] ?: $layer['key']); ?></span>
                                    </div>
                                    <select name="mapping[<?php echo h($layer['key']); ?>]" class="form-control mapping-select" data-key="<?php echo h($layer['key']); ?>" required>
                                        <option value="">-- Select Column --</option>
                                        <?php foreach($csv_headers as $idx => $header): ?>
                                            <option value="<?php echo $idx; ?>" 
                                                <?php 
                                                    $h = strtolower($header);
                                                    $k = strtolower($layer['key']);
                                                    $l = strtolower($layer['label']);
                                                    if ($h == $k || $h == $l || (strpos($h, $k) !== false && $k != '') || (strpos($h, 'name') !== false && $k == 'name')) {
                                                        echo 'selected';
                                                    }
                                                ?>>
                                                <?php echo h($header); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top:2.5rem; display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                            <button type="submit" class="btn-modern btn-primary-modern" style="padding:1.25rem;">
                                Complete & Generate Batch ✨
                            </button>
                            <a href="batch_new.php?template_id=<?php echo $template_id; ?>" class="btn-modern btn-secondary-modern">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <div class="preview-pane">
                    <div class="preview-header">
                        <span>LIVE MAPPING PREVIEW</span>
                        <span id="previewStatus" style="color:#10b981;">● Active</span>
                    </div>
                    <div class="preview-stage">
                        <?php 
                        $ext = isset($template['image_path']) ? strtolower(pathinfo($template['image_path'], PATHINFO_EXTENSION)) : '';
                        if ($ext === 'pdf'): ?>
                            <canvas id="bgCanvas" style="max-width:100%; display:block; z-index:1;"></canvas>
                            <img id="mappingPreview" src="" alt="Overlay" style="max-width:100%; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); z-index:2; opacity:0; pointer-events:none;">
                        <?php else: ?>
                            <img id="mappingPreview" src="" alt="Preview" style="max-width:100%; opacity:0; transition:opacity 0.3s;">
                        <?php endif; ?>
                        
                        <div id="previewLoader" style="position:absolute; background:rgba(15, 23, 42, 0.9); color:white; padding:1rem 2rem; border-radius:40px; font-weight:600; font-size:0.9rem; border:1px solid rgba(255,255,255,0.1); backdrop-filter:blur(10px);">
                            🔄 Loading preview...
                        </div>
                    </div>
                    <div style="padding:1rem 1.5rem; background:rgba(0,0,0,0.2); border-top:1px solid rgba(255,255,255,0.05); color:#64748b; font-size:0.75rem; text-align:center;">
                        Showing first row data for validation
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align:center; padding:2rem;">
            <a href="dashboard.php" style="color:#94a3b8; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
// Step 1 UI Logic
if (document.getElementById('csvFile')) {
    const fileInput = document.getElementById('csvFile');
    const fileNameDisplay = document.getElementById('fileName');
    const dropZone = document.getElementById('dropZone');
    const previewSection = document.getElementById('previewSection');
    const previewWrapper = document.getElementById('previewTableWrapper');
    const rowCount = document.getElementById('rowCount');

    fileInput.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;

        fileNameDisplay.textContent = file.name;
        dropZone.style.background = '#f5f3ff';
        dropZone.style.borderColor = '#6366f1';

        const reader = new FileReader();
        reader.onload = function(event) {
            const lines = event.target.result.split(/\r?\n/).filter(line => line.trim() != '');
            if (lines.length > 0) {
                let html = '<table class="preview-table" style="width:100%; border-collapse:collapse;"><thead><tr>';
                const headers = parseCSVLine(lines[0]);
                headers.forEach(h => html += `<th style="text-align:left;">${escapeHtml(h)}</th>`);
                html += '</tr></thead><tbody>';
                for (let i = 1; i < Math.min(lines.length, 11); i++) {
                    const cols = parseCSVLine(lines[i]);
                    html += '<tr>';
                    for (let j = 0; j < headers.length; j++) html += `<td>${escapeHtml(cols[j] || '')}</td>`;
                    html += '</tr>';
                }
                html += '</tbody></table>';
                if(lines.length > 11) html += '<div style="padding:1rem; text-align:center; color:#94a3b8; font-size:0.8rem; font-style:italic;">... and ' + (lines.length - 11) + ' more rows</div>';
                
                previewWrapper.innerHTML = html;
                previewSection.style.display = 'block';
                rowCount.textContent = (lines.length - 1) + ' ROWS FOUND';
            }
        };
        reader.readAsText(file);
    };

    dropZone.ondragover = e => { e.preventDefault(); dropZone.style.borderColor = '#6366f1'; };
    dropZone.ondragleave = e => { e.preventDefault(); dropZone.style.borderColor = '#e2e8f0'; };
    dropZone.ondrop = e => {
        e.preventDefault();
        fileInput.files = e.dataTransfer.files;
        fileInput.onchange({target: fileInput});
    };

    function parseCSVLine(text) {
        const result = [];
        let cur = '', inQuote = false;
        for (let i = 0; i < text.length; i++) {
            let char = text[i];
            if (char == '"') inQuote = !inQuote;
            else if (char == ',' && !inQuote) { result.push(cur); cur = ''; }
            else cur += char;
        }
        result.push(cur);
        return result.map(s => s.trim().replace(/^"|"$/g, ''));
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Step 2 Mapping Logic
if (document.getElementById('mappingPreview')) {
    const previewImg = document.getElementById('mappingPreview');
    const loader = document.getElementById('previewLoader');
    const selects = document.querySelectorAll('.mapping-select');
    const firstRowData = <?php echo json_encode(isset($first_row_data) ? $first_row_data : array()); ?>;
    const templateId = <?php echo $template_id; ?>;
    const templatePath = '<?php echo h($template['image_path']); ?>';
    const bgCanvas = document.getElementById('bgCanvas');
    const isPdf = !!bgCanvas;

    let pdfW = 0, pdfH = 0;

    async function renderPdfBackground() {
        if (!isPdf) return;
        try {
            const pdf = await pdfjsLib.getDocument(templatePath).promise;
            const page = await pdf.getPage(1);
            const viewport = page.getViewport({ scale: 1.5 });
            const context = bgCanvas.getContext('2d');
            bgCanvas.height = viewport.height;
            bgCanvas.width = viewport.width;
            pdfW = Math.round(viewport.width / 1.5);
            pdfH = Math.round(viewport.height / 1.5);
            await page.render({ canvasContext: context, viewport: viewport }).promise;
            updateLivePreview();
        } catch (err) { console.error('PDF Error:', err); }
    }

    function updateLivePreview() {
        loader.style.display = 'block';
        previewImg.style.opacity = '0.3';
        
        const mappedData = {};
        selects.forEach(select => {
            const colIndex = select.value;
            if (colIndex !== '') mappedData[select.dataset.key] = firstRowData[colIndex] || '';
        });

        const params = {
            template_id: templateId,
            data: JSON.stringify(mappedData),
            use_bg: document.querySelector('input[name="use_background"]:checked').value,
            t: Date.now()
        };
        if (isPdf) {
            params.layers_only = '1';
            if (pdfW > 0) { params.real_w = pdfW; params.real_h = pdfH; }
        }

        const qs = new URLSearchParams(params).toString();
        previewImg.onload = () => {
            loader.style.display = 'none';
            previewImg.style.opacity = '1';
            if (isPdf) {
                const rect = bgCanvas.getBoundingClientRect();
                previewImg.style.width = rect.width + 'px';
                previewImg.style.height = rect.height + 'px';
            }
        };
        previewImg.src = 'api/render_preview.php?' + qs;
    }

    selects.forEach(s => s.addEventListener('change', updateLivePreview));
    document.querySelectorAll('.bg-radio').forEach(r => r.addEventListener('change', updateLivePreview));
    if (isPdf) renderPdfBackground(); else updateLivePreview();
}
</script>
<script src="assets/app.js"></script>
</body>
</html>

