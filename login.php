<?php
// login.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

require_guest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CertiGen</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --surface: rgba(255, 255, 255, 0.9);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        }

        body {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            position: relative;
            overflow: hidden;
        }

        /* Ambient Background Shapes */
        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
        }
        .shape-1 { width: 400px; height: 400px; background: #818cf8; top: -100px; right: -100px; }
        .shape-2 { width: 300px; height: 300px; background: #c084fc; bottom: -50px; left: -50px; }

        .auth-card {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 32px;
            padding: 3rem;
            width: 420px;
            max-width: 90%;
            box-shadow: 0 25px 50px -12px rgba(99, 102, 241, 0.15);
        }

        .auth-logo {
            display: block;
            margin: 0 auto 1.5rem;
            height: 70px;
            background: white;
            padding: 12px;
            border-radius: 20px;
            box-shadow: 0 10px 15px rgba(0,0,0,0.05);
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
            padding: 1rem 1.25rem;
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            font-size: 0.95rem;
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

        .btn-auth {
            width: 100%;
            padding: 1rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 10px 15px rgba(99, 102, 241, 0.2);
            margin-top: 1rem;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px rgba(99, 102, 241, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .alert-error { background: #fee2e2; color: #ef4444; }
        .alert-success { background: #dcfce7; color: #10b981; }

        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }

        .password-toggle-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-toggle-btn {
            position: absolute;
            right: 1.25rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
            padding: 0;
            z-index: 10;
        }

        .password-toggle-btn:hover {
            color: #6366f1;
        }

        .auth-footer a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 700;
        }

        /* Status Modal (Success/Error) */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 32px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            transform: translateY(30px) scale(0.95);
            opacity: 0;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
        }
        .modal-overlay.active .modal-content { transform: translateY(0) scale(1); opacity: 1; }
        .btn-modern {
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }
        .btn-primary-modern {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 10px 15px rgba(99, 102, 241, 0.2);
        }
    </style>
</head>
<body>

<div class="shape shape-1"></div>
<div class="shape shape-2"></div>

<div class="auth-card">
    <img src="assets/logo.png" alt="CertiGen Logo" class="auth-logo">
    
    <div style="text-align:center; margin-bottom: 2rem;">
        <h2 style="margin:0; font-size:1.75rem; font-weight:800; color:#0f172a; letter-spacing:-0.025em;">Welcome Back Cikgu✨</h2>
        <p style="color:#64748b; font-size:0.95rem; margin-top:0.5rem;">Ready to generate some magic?</p>
    </div>

    <?php $flash = get_flash(); if($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>
    
    <form id="loginForm">
        <div style="margin-bottom: 1rem;">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="hello@certigen.com" required autofocus>
        </div>
        
        <div style="margin-bottom: 2rem;">
            <label class="form-label">Password</label>
            <div class="password-toggle-wrapper">
                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required style="padding-right: 3.5rem;">
                <button type="button" onclick="togglePasswordVisibility('passwordInput', 'toggleIcon')" class="password-toggle-btn" tabindex="-1">
                    <span id="toggleIcon">👁️</span>
                </button>
            </div>
        </div>
        
        <button type="submit" id="loginBtn" class="btn-auth">Login to CertiGen</button>
    </form>

    <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one now</a>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="modal-overlay">
    <div class="modal-content" style="text-align:center;">
        <div id="statusIcon" style="font-size:3.5rem; margin-bottom:1rem;">✨</div>
        <h3 id="statusTitle" style="margin:0 0 0.5rem 0; font-size:1.6rem; font-weight:800; color:#0f172a;">Success!</h3>
        <p id="statusDesc" style="color:#64748b; margin-bottom:2rem; line-height:1.6; font-size:1rem;"></p>
        <button onclick="closeStatusModal()" id="statusActionBtn" class="btn-modern btn-primary-modern" style="width:100%; padding:1rem; border-radius:16px; font-weight:700;">OK</button>
    </div>
</div>

<script>
function showStatus(title, message, icon = '✨', isSuccess = true) {
    document.getElementById('statusTitle').innerText = title;
    document.getElementById('statusDesc').innerText = message;
    document.getElementById('statusIcon').innerText = icon;
    document.getElementById('statusActionBtn').style.display = isSuccess ? 'none' : 'block';
    document.getElementById('statusModal').classList.add('active');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('active');
}

function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerText = '🙈';
    } else {
        input.type = 'password';
        icon.innerText = '👁️';
    }
}

document.getElementById('loginForm').onsubmit = async (e) => {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const oldText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Validating...';

    const formData = new FormData(e.target);
    try {
        const response = await fetch('api/login_handler.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showStatus('Welcome Back! ✨', result.message, '✨', true);
            setTimeout(() => {
                window.location.href = result.redirect;
            }, 1200);
        } else {
            showStatus('Login Failed ❌', result.error, '❌', false);
            btn.disabled = false;
            btn.innerHTML = oldText;
        }
    } catch (err) {
        showStatus('Error ⚠️', 'An unexpected error occurred.', '⚠️', false);
        btn.disabled = false;
        btn.innerHTML = oldText;
    }
};

// Click outside to close (only for errors)
document.getElementById('statusModal').onclick = (e) => {
    if (e.target.id === 'statusModal' && document.getElementById('statusActionBtn').style.display !== 'none') {
        closeStatusModal();
    }
};
</script>
</body>
</html>

