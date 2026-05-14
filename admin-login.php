<?php
session_start();
include 'includes/dbconnection.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $query = "SELECT * FROM system_users WHERE email = '$email' AND is_active = 1 LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            if (password_verify($password, $user_data['password_hash'])) {
                $allowed_roles = ['super_admin', 'hr_manager', 'hr_officer'];
                
                if (in_array($user_data['role'], $allowed_roles)) {
                    $_SESSION['admin_id'] = $user_data['id'];
                    $_SESSION['admin_name'] = $user_data['first_name'] . " " . $user_data['last_name'];
                    $_SESSION['admin_role'] = $user_data['role'];
                    
                    $redirect_page = ($user_data['role'] == 'super_admin') ? "admin_dashboard.php" : "hr_dashboard.php";
                    header("Location: $redirect_page");
                    die;
                } else {
                    $error = "Unauthorized role! Access denied.";
                }
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Admin account not found or inactive.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | LeaveEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Background Decor -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-900/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-indigo-900/20 rounded-full blur-[120px]"></div>
    </div>

    <div class="max-w-md w-full relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-3xl shadow-2xl shadow-blue-500/20 mb-6 transform hover:rotate-12 transition-transform duration-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h1 class="text-4xl font-black text-white tracking-tight">Admin Portal</h1>
            <p class="text-slate-400 mt-2 font-medium">System Administration & HR Management</p>
        </div>

        <div class="glass rounded-[40px] p-10 shadow-2xl">
            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-100 rounded-2xl flex items-center gap-3 text-rose-600 animate-pulse">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-bold"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form action="admin-login.php" method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label class="text-sm font-bold text-slate-700 ml-1">Admin Email</label>
                    <input type="email" name="email" required placeholder="admin@company.com"
                        class="w-full px-6 py-4 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-blue-500 focus:bg-white outline-none transition-all duration-300 text-slate-800 font-medium">
                </div>

                <div class="space-y-2 relative">
                    <label class="text-sm font-bold text-slate-700 ml-1">Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••"
                        class="w-full px-6 py-4 rounded-2xl bg-slate-50 border-2 border-transparent focus:border-blue-500 focus:bg-white outline-none transition-all duration-300 text-slate-800 font-medium">
                    <button type="button" id="togglePassword" class="absolute right-4 top-[46px] text-slate-400 hover:text-blue-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>

                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-2xl shadow-xl shadow-blue-500/30 transition-all transform hover:-translate-y-1 active:scale-[0.98] text-lg tracking-wide mt-4">
                    LOGIN TO SYSTEM
                </button>
            </form>
        </div>
        
        <p class="text-center mt-8 text-slate-500 text-sm font-medium">
            &copy; <?php echo date('Y'); ?> LeaveEase Admin Portal. All rights reserved.
        </p>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('text-blue-600');
        });
    </script>
</body>
</html>
