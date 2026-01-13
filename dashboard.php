<?php
// dashboard.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_login();
$user_id = current_user_id();

// Fetch Templates
$stmt = $pdo->prepare("SELECT * FROM templates WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute(array($user_id));
$templates = $stmt->fetchAll();

// Fetch Batches - Added t.image_path as template_image
$stmt = $pdo->prepare("SELECT b.*, t.title as template_title, t.image_path as template_image FROM batches b JOIN templates t ON b.template_id = t.id WHERE b.user_id = ? ORDER BY b.created_at DESC");
$stmt->execute(array($user_id));
$batches = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CertiGen</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --surface: #ffffff;
            --bg-muted: #f8fafc;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
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

        .dashboard-header {
            padding-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .header-title h1 {
            font-size: 2.25rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
        }

        .header-title p {
            color: #64748b;
            font-size: 1.1rem;
        }

        /* Template Cards */
        .section-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            margin-top: 1.5rem;
        }

        .section-label h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .template-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .template-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .card-preview {
            aspect-ratio: 1.414;
            background: #f1f5f9;
            overflow: hidden;
            position: relative;
        }

        .card-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-body h3 {
            margin: 0 0 1rem 0;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-modern {
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-secondary-modern {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-modern:hover {
            opacity: 0.9;
            transform: scale(1.02);
            text-decoration: none !important;
        }

        /* Modern Table */
        .table-container {
            background: white;
            border-radius: 24px;
            padding: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            text-align: left;
            padding: 1.25rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #f1f5f9;
        }

        .modern-table td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f8fafc;
        }

        .batch-row:hover {
            background-color: #f8fafc;
        }

        .row-num {
            color: #94a3b8;
            font-weight: 500;
            font-family: monospace;
        }

        .batch-thumb {
            width: 60px;
            height: 42px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(0,0,0,0.1);
            background: #f1f5f9;
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-done { background: #dcfce7; color: #166534; }
        .badge-ready { background: #dbeafe; color: #1e40af; }
        .badge-failed { background: #fee2e2; color: #991b1b; }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border: 2px dashed #e2e8f0;
            border-radius: 24px;
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
            z-index: 9999;
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

        /* Modal Base */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }

        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 32px;
            width: 420px;
            max-width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            transform: translateY(30px) scale(0.95);
            opacity: 0;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
            text-align: left;
        }
        .modal-overlay.active .modal-content { transform: translateY(0) scale(1); opacity: 1; }

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

        /* Delete Modal */
        .btn-danger-modern {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 10px 15px rgba(239, 68, 68, 0.1);
        }
        .btn-danger-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px rgba(239, 68, 68, 0.2);
        }

        /* Donate & Feedback */
        .btn-nav {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-nav:hover {
            text-decoration: none !important;
        }
        .btn-donate {
            background: #fff1f2;
            color: #e11d48;
        }
        .btn-donate:hover {
            background: #ffe4e6;
            transform: translateY(-1px);
        }
        .btn-feedback {
            background: #f0f9ff;
            color: #0284c7;
        }
        .btn-feedback:hover {
            background: #e0f2fe;
            transform: translateY(-1px);
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
        <ul class="navbar-nav" style="display:flex; gap:1.25rem; list-style:none; align-items:center;">
            <li><a href="#" onclick="openDonateModal()" class="btn-nav btn-donate">🎁 Donate</a></li>
            <li><a href="#" onclick="openFeedbackModal()" class="btn-nav btn-feedback">💬 Feedback</a></li>
            <li><a href="dashboard.php" style="font-weight:600; color:var(--primary); text-decoration:none; font-size:0.9rem;">Dashboard</a></li>
            <li><a href="#" onclick="openLogoutModal()" style="font-weight:600; color:#ef4444; text-decoration:none; font-size:0.9rem;">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <?php $flash = get_flash(); if($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-top:20px; border-radius:12px;">
            <?php echo h($flash['message']); ?>
        </div>
    <?php endif; ?>

    <header class="dashboard-header">
        <div class="header-title">
            <h1>Workspace</h1>
            <p>Welcome back, <?php echo h(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'); ?></p>
        </div>
        <a href="template_new.php" class="btn-modern btn-primary-modern" style="padding:1rem 2rem; font-size:1rem; border-radius:16px;">
            <span style="font-size:1.2rem;">+</span> Create New Template
        </a>
    </header>

    <!-- Templates Section -->
    <div class="section-label">
        <div style="width:4px; height:24px; background:var(--primary); border-radius:2px;"></div>
        <h2>Your Templates</h2>
    </div>

    <?php if (empty($templates)): ?>
        <div class="empty-state">
            <div style="font-size:3rem; margin-bottom:1rem;">🎨</div>
            <h3>No Templates Found</h3>
            <p style="color:#64748b; margin-bottom:2rem;">Start by uploading a design to create your first certificate.</p>
            <a href="template_new.php" class="btn-modern btn-primary-modern">Upload My First Template</a>
        </div>
    <?php else: ?>
        <div class="template-grid">
            <?php foreach($templates as $tpl): ?>
                <div class="template-card">
                    <div class="card-preview">
                        <img src="<?php echo h($tpl['image_path']); ?>" alt="Preview">
                    </div>
                    <div class="card-body">
                        <h3><?php echo h($tpl['title']); ?></h3>
                        <div class="card-actions">
                            <a href="template_designer.php?template_id=<?php echo $tpl['id']; ?>" class="btn-modern btn-secondary-modern" style="flex:1;">
                                🖌️ Design
                            </a>
                            <a href="batch_new.php?template_id=<?php echo $tpl['id']; ?>" class="btn-modern btn-primary-modern" style="flex:1;">
                                🚀 New Batch
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Batches Section -->
    <div class="section-label">
        <div style="width:4px; height:24px; background:var(--primary); border-radius:2px;"></div>
        <h2>Recent Batches</h2>
    </div>

    <?php if (empty($batches)): ?>
        <div class="table-container" style="padding:3rem; text-align:center;">
            <p style="color:#64748b;">You haven't generated any certificate batches yet.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="50">No.</th>
                        <th width="80">Preview</th>
                        <th>Batch Name</th>
                        <th>Template Used</th>
                        <th>Status</th>
                        <th width="150">Date</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach($batches as $batch): ?>
                        <tr class="batch-row">
                            <td class="row-num"><?php echo str_pad($counter++, 2, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <img src="<?php echo h($batch['template_image']); ?>" class="batch-thumb" alt="Thumb">
                            </td>
                            <td>
                                <div style="font-weight:600;"><?php echo h($batch['name']); ?></div>
                            </td>
                            <td>
                                <div style="font-size:0.9rem; color:#64748b;"><?php echo h($batch['template_title']); ?></div>
                            </td>
                            <td>
                                <span class="status-badge badge-<?php echo $batch['status']; ?>">
                                    <?php echo strtoupper($batch['status']); ?>
                                </span>
                            </td>
                            <td style="color:#64748b; font-size:0.875rem;">
                                <?php echo date('M d, Y', strtotime($batch['created_at'])); ?>
                            </td>
                            <td>
                                <?php if ($batch['status'] == 'ready'): ?>
                                    <a href="batch_preview.php?batch_id=<?php echo $batch['id']; ?>" class="btn-modern btn-secondary-modern" style="padding:0.5rem 1rem;">Preview</a>
                                <?php elseif ($batch['status'] == 'done'): ?>
                                    <div style="display:flex; gap:0.5rem;">
                                        <a href="batch_preview.php?batch_id=<?php echo $batch['id']; ?>" class="btn-modern btn-secondary-modern" style="padding:0.5rem 1rem;">View</a>
                                        <button onclick="openDownloadModal(<?php echo $batch['id']; ?>)" class="btn-modern btn-primary-modern" style="padding:0.5rem 1rem; background:var(--success-gradient); cursor:pointer;">
                                            📥 DOWNLOAD
                                        </button>
                                        <button onclick="confirmDelete(<?php echo $batch['id']; ?>)" class="btn-modern btn-danger-modern" style="padding:0.5rem; background:#fee2e2; color:#ef4444; border:1px solid #fee2e2;" title="Delete Batch">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        </button>
                                    </div>
                                <?php elseif ($batch['status'] == 'failed'): ?>
                                    <span style="color:#ef4444; font-size:0.75rem;" title="<?php echo h($batch['error_message']); ?>">❌ Error</span>
                                <?php else: ?>
                                    <span style="color:#64748b; font-size:0.75rem;">⏳ Processing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Choice Modal -->
<div id="choiceModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin:0 0 0.5rem 0; font-size:1.5rem; font-weight:700;">Generate Batch ✨</h3>
        <p style="color:#64748b; margin-bottom:2rem;">Choose your preferred delivery format:</p>
        
        <div class="download-option" onclick="triggerDownload('all_one')">
            <div class="download-icon">📚</div>
            <div style="display:flex; flex-direction:column;">
                <span style="font-weight:700; color:#1e293b;">Combined PDF</span>
                <span style="font-size:0.8rem; color:#64748b;">All certificates in one file</span>
            </div>
        </div>
        
        <div class="download-option" onclick="triggerDownload('all_zip')">
            <div class="download-icon">📦</div>
            <div style="display:flex; flex-direction:column;">
                <span style="font-weight:700; color:#1e293b;">Individual DOWNLOAD</span>
                <span style="font-size:0.8rem; color:#64748b;">Each certificate as a file</span>
            </div>
        </div>

        <button onclick="closeChoiceModal()" class="btn-modern btn-secondary-modern" style="width:100%; margin-top:1rem;">Cancel</button>
    </div>
</div>

<!-- Processing Modal -->
<div id="processingModal" class="processing-overlay">
    <div class="processing-card">
        <div class="loader-spinner"></div>
        <h2 style="margin-bottom:0.5rem; font-weight:700; color:#1e293b;">Generating... ✨</h2>
        <p style="color:#64748b; line-height:1.6;">Please wait while we prepare your high-quality certificates. This might take a few moments.</p>
        <div style="margin-top:2rem; font-size:0.8rem; color:#94a3b8;">
            Don't close this window
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <div style="font-size:3rem; margin-bottom:1rem;">⚠️</div>
        <h3 style="margin:0 0 0.5rem 0; font-size:1.5rem; font-weight:700;">Delete Batch?</h3>
        <p style="color:#64748b; margin-bottom:2rem; line-height:1.6;">Are you sure you want to delete this batch? All generated data and recipient records will be permanently removed.</p>
        
        <div style="display:flex; gap:1rem;">
            <button onclick="closeDeleteModal()" class="btn-modern btn-secondary-modern" style="flex:1;">Cancel</button>
            <button id="confirmDeleteBtn" class="btn-modern btn-danger-modern" style="flex:1;">Delete Permanently</button>
        </div>
    </div>
</div>

<!-- Donate Modal -->
<div id="donateModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <h3 style="margin:0 0 1.5rem 0; font-size:1.5rem; font-weight:700;">Support CertiGen 🎁</h3>
        <p style="color:#64748b; margin-bottom:1.5rem; font-size:0.9rem;">If you find this tool helpful, consider supporting its development!</p>
        
        <div style="background:#f1f5f9; padding:1.5rem; border-radius:24px; margin-bottom:1.5rem;">
            <img src="assets/donate_qr.jpeg" id="qrImage" alt="Donate QR" style="width:200px; height:200px; border-radius:12px; display:block; margin:0 auto; background:white;">
            <p style="margin-top:1rem; font-weight:700; color:#1e293b; font-size:0.85rem;">Scan to Donate (Bank), Thank U!💖</p>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
            <button onclick="closeDonateModal()" class="btn-modern btn-secondary-modern">Close</button>
            <a href="assets/donate_qr.jpeg" download="CertiGen_Donate_QR.jpeg" class="btn-modern btn-primary-modern" style="text-decoration:none; background:#e11d48; color:white;">
                📥 Download QR
            </a>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin:0 0 0.5rem 0; font-size:1.5rem; font-weight:700;">Send Feedback 💬</h3>
        <p style="color:#64748b; margin-bottom:1.5rem; font-size:0.9rem;">Report a bug or suggest a feature.</p>
        
        <form id="feedbackForm">
            <div class="form-group">
                <label class="form-label">Your Message</label>
                <textarea name="feedback" class="form-control" rows="4" placeholder="How can we improve?" required style="resize:none;"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" style="margin-top:1rem;">Attach Screenshot (Optional)</label>
                <input type="file" name="screenshot" accept="image/*" class="form-control" style="padding:0.5rem;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:1rem; margin-top:1.5rem;">
                <button type="button" onclick="closeFeedbackModal()" class="btn-modern btn-secondary-modern">Cancel</button>
                <button type="submit" id="submitFeedback" class="btn-modern btn-primary-modern" style="background:#0284c7; color:white;">
                    🚀 Send Feedback
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <div style="font-size:3rem; margin-bottom:1rem;">👋</div>
        <h3 style="margin:0 0 0.5rem 0; font-size:1.5rem; font-weight:700;">Ready to Leave?</h3>
        <p style="color:#64748b; margin-bottom:2rem; line-height:1.6;">Are you sure you want to log out? You'll need to log in again to manage your certificates.</p>
        
        <div style="display:flex; gap:1rem;">
            <button onclick="closeLogoutModal()" class="btn-modern btn-secondary-modern" style="flex:1;">Stay here</button>
            <a href="logout.php" class="btn-modern btn-danger-modern" style="flex:1; text-decoration:none; display:flex; align-items:center; justify-content:center;">Yes, Logout</a>
        </div>
    </div>
</div>

<div style="height:100px;"></div>

<script src="assets/app.js"></script>
<!-- Status Modal (Success/Error) -->
<div id="statusModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <div id="statusIcon" style="font-size:3.5rem; margin-bottom:1rem; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));">✨</div>
        <h3 id="statusTitle" style="margin:0 0 0.5rem 0; font-size:1.6rem; font-weight:800; color:#0f172a;">Success!</h3>
        <p id="statusDesc" style="color:#64748b; margin-bottom:2rem; line-height:1.6; font-size:1rem;">Your request has been processed successfully.</p>
        <button onclick="closeStatusModal()" class="btn-modern btn-primary-modern" style="width:100%; padding:1rem; border-radius:16px; font-weight:700;">Great, thanks! 💖</button>
    </div>
</div>

<script>
// Status Modal Helper
function showStatus(title, message, icon = '✨') {
    document.getElementById('statusTitle').innerText = title;
    document.getElementById('statusDesc').innerText = message;
    document.getElementById('statusIcon').innerText = icon;
    document.getElementById('statusModal').classList.add('active');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('active');
}

let activeBatchId = null;

function openDownloadModal(batchId) {
    activeBatchId = batchId;
    document.getElementById('choiceModal').classList.add('active');
}

function closeChoiceModal() {
    document.getElementById('choiceModal').classList.remove('active');
}

function triggerDownload(mode) {
    closeChoiceModal();
    const procModal = document.getElementById('processingModal');
    procModal.classList.add('active');
    
    const token = Date.now();
    window.location.href = `api/download.php?batch_id=${activeBatchId}&mode=${mode}&format=pdf&download_token=${token}`;

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

// Close modals on escape or backdrop click
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeChoiceModal();
        closeDeleteModal();
        closeDonateModal();
        closeFeedbackModal();
    }
});

let batchToDelete = null;

function confirmDelete(batchId) {
    batchToDelete = batchId;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    batchToDelete = null;
    document.getElementById('deleteModal').classList.remove('active');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    if (!batchToDelete) return;
    
    this.disabled = true;
    this.textContent = 'Deleting...';
    
    try {
        const formData = new FormData();
        formData.append('batch_id', batchToDelete);
        
        const response = await fetch('api/batch_delete.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Invalid JSON response:', responseText);
            alert('Server Error: The response was not valid JSON. See console for details.');
            this.disabled = false;
            this.textContent = 'Delete Permanently';
            return;
        }
        
        if (result.success) {
            window.location.reload();
        } else {
            showStatus('Delete Failed', result.error || 'Failed to delete batch', '❌');
            this.disabled = false;
            this.textContent = 'Delete Permanently';
        }
    } catch (err) {
        console.error(err);
        showStatus('Error', 'An unexpected error occurred.', '⚠️');
        this.disabled = false;
        this.textContent = 'Delete Permanently';
    }
});

// Donate / Feedback Modal Logic
function openDonateModal() { document.getElementById('donateModal').classList.add('active'); }
function closeDonateModal() { document.getElementById('donateModal').classList.remove('active'); }
function openFeedbackModal() { document.getElementById('feedbackModal').classList.add('active'); }
function closeFeedbackModal() { document.getElementById('feedbackModal').classList.remove('active'); }

document.getElementById('feedbackForm').onsubmit = async (e) => {
    e.preventDefault();
    const btn = document.getElementById('submitFeedback');
    const oldText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Sending...';

    const formData = new FormData(e.target);
    try {
        const response = await fetch('api/send_feedback.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            closeFeedbackModal();
            showStatus('Thank You! 💖', 'Your feedback has been sent to the developer. We appreciate your support!', '💬');
            e.target.reset();
        } else {
            showStatus('Submission Failed', result.error, '❌');
        }
    } catch (err) {
        showStatus('Error', 'An error occurred while sending feedback.', '⚠️');
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldText;
    }
};

// Logout Modal Logic
function openLogoutModal() { document.getElementById('logoutModal').classList.add('active'); }
function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('active'); }

// Click outside to close
[ 'donateModal', 'feedbackModal', 'deleteModal', 'choiceModal', 'processingModal', 'statusModal', 'logoutModal' ].forEach(id => {
    const el = document.getElementById(id);
    if(el) el.onclick = (e) => { if(e.target === el) el.classList.remove('active'); };
});
</script>
</body>
</html>
