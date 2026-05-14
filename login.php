<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db_config.php';

$error   = '';
$success = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'disabled')     $error   = 'Your account has been disabled. Please contact your administrator.';
    if ($_GET['error'] === 'unauthorized') $error   = 'You do not have permission to access that page.';
}
if (isset($_GET['msg']) && $_GET['msg'] === 'loggedout') {
    $success = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM system_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password. Please try again.';
        } elseif (!$user['is_active']) {
            $error = 'Your account has been disabled. Please contact your administrator.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['is_active'] = $user['is_active'];
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – HRMS by ETrack Biz</title>
    <meta name="description" content="Secure login portal for the HRMS system by ETrack Biz.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            /* Solid dark base — always visible regardless of CDN issues */
            background-color: #0f172a;
            background-image:
                radial-gradient(ellipse at 15% 15%, rgba(37,99,235,0.30) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 85%, rgba(124,58,237,0.22) 0%, transparent 50%),
                radial-gradient(ellipse at 65% 5%,  rgba(6,182,212,0.16)  0%, transparent 40%);
            overflow: hidden;
            position: relative;
        }

        /* Dot grid */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
        }

        /* Glow orbs */
        .orb { position: fixed; border-radius: 50%; filter: blur(90px); pointer-events: none; }
        .orb-1 { width: 550px; height: 550px; background: rgba(37,99,235,0.28);  top: -180px; left: -180px; }
        .orb-2 { width: 420px; height: 420px; background: rgba(124,58,237,0.22); bottom: -140px; right: -140px; }

        /* Card */
        .login-card {
            position: relative; z-index: 10;
            width: 100%; max-width: 420px; margin: 16px;
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 24px;
            padding: 44px 40px 36px;
            box-shadow: 0 32px 72px rgba(0,0,0,0.55), 0 0 0 1px rgba(255,255,255,0.04) inset;
            animation: slideUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* Logo */
        .logo-wrap {
            width: 68px; height: 68px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 10px 36px rgba(37,99,235,0.45);
        }
        .logo-wrap i { font-size: 28px; color: #fff; }

        /* Brand text */
        .brand-name { text-align: center; font-size: 30px; font-weight: 800; color: #f1f5f9; letter-spacing: -0.5px; }
        .brand-sub  { text-align: center; font-size: 11px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 3px; margin-top: 4px; }
        .divider    { width: 40px; height: 2px; background: linear-gradient(90deg, transparent, #3b82f6, transparent); margin: 14px auto 26px; border-radius: 2px; }

        /* Headings */
        .welcome-title { font-size: 19px; font-weight: 700; color: #f1f5f9; margin-bottom: 4px; }
        .welcome-sub   { font-size: 13px; color: #64748b; margin-bottom: 26px; }

        /* Alerts */
        .alert {
            border-radius: 12px; padding: 12px 14px; margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .alert-error   { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.30); }
        .alert-success { background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.25); }
        .alert-error   i { color: #f87171; }
        .alert-success i { color: #34d399; }
        .alert-error   p { font-size: 13px; color: #fca5a5; line-height: 1.5; }
        .alert-success p { font-size: 13px; color: #6ee7b7; line-height: 1.5; }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* Form */
        .field-group  { margin-bottom: 18px; }
        .field-label  {
            display: block; font-size: 11px; font-weight: 700;
            color: #94a3b8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px;
        }
        .input-wrap   { position: relative; }
        .input-icon   {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #475569; font-size: 14px; pointer-events: none;
        }
        .auth-input {
            width: 100%;
            background: #0f172a;
            border: 1.5px solid #334155;
            border-radius: 12px;
            padding: 13px 14px 13px 42px;
            font-size: 14px; font-weight: 500;
            color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .auth-input::placeholder { color: #475569; }
        .auth-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.20);
        }
        .eye-btn {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #475569; font-size: 14px; transition: color 0.2s;
        }
        .eye-btn:hover { color: #94a3b8; }

        /* Button */
        .btn-submit {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none; border-radius: 12px;
            color: #fff; font-size: 15px; font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 9px;
            margin-top: 6px;
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
            box-shadow: 0 4px 24px rgba(37,99,235,0.42);
        }
        .btn-submit:hover   { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(37,99,235,0.55); }
        .btn-submit:active  { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        /* Spinner */
        .spin-ring {
            display: none; width: 17px; height: 17px;
            border: 2.5px solid rgba(255,255,255,0.30);
            border-top-color: #fff; border-radius: 50%;
            animation: spin 0.65s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Bottom hint */
        .hint-bar {
            border-top: 1px solid #334155; margin-top: 26px; padding-top: 16px;
            text-align: center; font-size: 12px; color: #475569; line-height: 1.5;
        }
        .hint-bar i { margin-right: 4px; }

        /* Footer outside card */
        .page-footer { position: relative; z-index: 10; font-size: 11px; color: #1e293b; margin-top: 18px; }

        /* Shake */
        .shake { animation: shake 0.4s ease; }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%     { transform: translateX(-8px); }
            40%     { transform: translateX(8px); }
            60%     { transform: translateX(-5px); }
            80%     { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card" id="loginCard">

        <!-- Logo & Brand -->
        <div class="logo-wrap"><i class="fa-solid fa-layer-group"></i></div>
        <div class="brand-name">HRMS</div>
        <div class="brand-sub">by ETrack Biz</div>
        <div class="divider"></div>

        <div class="welcome-title">Welcome back</div>
        <div class="welcome-sub">Sign in to access your dashboard</div>

        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <p><?= htmlspecialchars($success) ?></p>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="login.php" id="loginForm" novalidate>

            <div class="field-group">
                <label class="field-label" for="email">Email Address</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" class="auth-input"
                           placeholder="you@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autocomplete="email">
                </div>
            </div>

            <div class="field-group" style="margin-bottom:26px;">
                <label class="field-label" for="password">Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="auth-input"
                           style="padding-right:44px;"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
                    <button type="button" class="eye-btn" id="togglePwd" aria-label="Toggle password">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span id="btnText">Sign In</span>
                <i class="fa-solid fa-arrow-right" id="btnArrow"></i>
                <div class="spin-ring" id="btnSpinner"></div>
            </button>
        </form>

        <div class="hint-bar">
            <i class="fa-solid fa-shield-halved"></i>
            Access is role-restricted &middot; Contact your Super Admin for credentials
        </div>
    </div>

    <div class="page-footer">© <?= date('Y') ?> ETrack Biz &middot; HRMS v2.0 &middot; All rights reserved</div>

    <script>
        // Toggle password
        document.getElementById('togglePwd').addEventListener('click', function () {
            const inp = document.getElementById('password');
            const ico = document.getElementById('eyeIcon');
            const show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            ico.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });

        // Loading state
        document.getElementById('loginForm').addEventListener('submit', function () {
            const email = document.getElementById('email').value.trim();
            const pwd   = document.getElementById('password').value.trim();
            if (!email || !pwd) return;
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            document.getElementById('btnText').textContent = 'Signing in…';
            document.getElementById('btnArrow').style.display = 'none';
            document.getElementById('btnSpinner').style.display = 'block';
        });

        // Shake on error
        <?php if ($error): ?>
        (function () {
            const card = document.getElementById('loginCard');
            card.classList.add('shake');
            setTimeout(function () { card.classList.remove('shake'); }, 450);
        })();
        <?php endif; ?>
    </script>
</body>
</html>
