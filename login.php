<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_set_cookie_params(2592000, '/');
    session_start();
}
include 'includes/dbconnection.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Query to find user by email
        $query = "SELECT * FROM employees WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (!$result) {
            $error = "Database Query Error: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);

            // Verify the hashed password
            if (password_verify($password, $user_data['password_hash'])) {
                // Check if inactive and grace period passed
                if ($user_data['status'] === 'Inactive') {
                    $user_id = $user_data['id'];
                    $notif_q = "SELECT created_at FROM notifications WHERE user_id = '$user_id' AND (message LIKE '%resignation%' OR message LIKE '%termination%') ORDER BY created_at DESC LIMIT 1";
                    $notif_res = mysqli_query($conn, $notif_q);
                    $allow_login = false;
                    if (mysqli_num_rows($notif_res) > 0) {
                        $notif_data = mysqli_fetch_assoc($notif_res);
                        $notif_time = strtotime($notif_data['created_at']);
                        if (time() - $notif_time <= 86400) { // 24 hours
                            $allow_login = true;
                        }
                    }
                    if (!$allow_login) {
                        $error = "Account inactive. Access denied.";
                    }
                }
                
                if (empty($error)) {
                    // Set session variables
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['user_name'] = $user_data['first_name'] . " " . $user_data['last_name'];
                    $_SESSION['user_email'] = $user_data['email'];
                    $_SESSION['position'] = $user_data['position'];

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    die;
                }
            } else {
                $error = "Password verification failed! (Incorrect password)";
            }
        } else {
            $error = "Email not found in database: " . htmlspecialchars($email);
        }
    } else {
        $error = "Please enter both email and password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | LeaveEase Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #020617;
            /* Slate 950 */
        }

        .bg-mesh {
            background-image:
                radial-gradient(at 0% 0%, hsla(222, 47%, 11%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(217, 91%, 60%, 0.15) 0, transparent 50%),
                radial-gradient(at 100% 100%, hsla(222, 47%, 11%, 1) 0, transparent 50%),
                radial-gradient(at 0% 100%, hsla(217, 91%, 60%, 0.15) 0, transparent 50%);
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(51, 65, 85, 0.5);
        }

        .input-glow:focus {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.5);
        }

        .btn-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.4);
        }

        @keyframes pulse-slow {

            0%,
            100% {
                opacity: 0.3;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }
        }

        .animate-pulse-slow {
            animation: pulse-slow 8s infinite ease-in-out;
        }

        .animate-shake {
            animation: shake 0.5s cubic-bezier(.36, .07, .19, .97) both;
        }

        @keyframes shake {

            10%,
            90% {
                transform: translate3d(-1px, 0, 0);
            }

            20%,
            80% {
                transform: translate3d(2px, 0, 0);
            }

            30%,
            50%,
            70% {
                transform: translate3d(-4px, 0, 0);
            }

            40%,
            60% {
                transform: translate3d(4px, 0, 0);
            }
        }
    </style>
</head>

<body class="bg-mesh min-h-screen flex items-center justify-center p-6 relative">

    <!-- Decorative background elements -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-600/20 rounded-full filter blur-[100px] animate-pulse-slow">
    </div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-600/10 rounded-full filter blur-[100px] animate-pulse-slow"
        style="animation-delay: 2s;"></div>

    <div class="max-w-md w-full relative z-10">
        <!-- Logo/Brand Section -->
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-600/20 border border-blue-500/30 mb-4 backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h1 class="text-4xl font-extrabold text-white tracking-tight">LeaveEase</h1>
            <p class="text-slate-400 mt-2 font-medium">Simplify your workflow.</p>
        </div>

        <!-- Login Card -->
        <div class="glass-card rounded-3xl p-8 md:p-10 shadow-2xl">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white">Sign In</h2>
                <p class="text-slate-400 text-sm mt-1">Enter your details to access your account.</p>
            </div>

            <form action="login.php" method="POST" class="space-y-6">
                <?php if (!empty($error)): ?>
                    <div
                        class="bg-red-500/10 border border-red-500/50 text-red-400 p-3 rounded-xl text-sm flex items-center gap-2 animate-shake">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Email Field -->
                <div class="space-y-2">
                    <label for="email" class="text-sm font-semibold text-slate-300 ml-1">Work Email</label>
                    <div class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 group-focus-within:text-blue-400 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                        <input type="email" id="email" name="email" required autocomplete="off"
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-900/50 border border-slate-700/50 rounded-2xl text-white outline-none focus:ring-2 focus:ring-blue-500/20 input-glow transition-all placeholder:text-slate-600"
                            placeholder="Enter your work email">
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label for="password" class="text-sm font-semibold text-slate-300">Password</label>
                    </div>
                    <div class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 group-focus-within:text-blue-400 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" id="password" name="password" required autocomplete="new-password"
                            class="w-full pl-11 pr-12 py-3.5 bg-slate-900/50 border border-slate-700/50 rounded-2xl text-white outline-none focus:ring-2 focus:ring-blue-500/20 input-glow transition-all placeholder:text-slate-600"
                            placeholder="Enter your password">
                        <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-blue-400 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" id="eyeIcon" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <script>
                    const togglePassword = document.querySelector('#togglePassword');
                    const password = document.querySelector('#password');
                    const eyeIcon = document.querySelector('#eyeIcon');

                    togglePassword.addEventListener('click', function (e) {
                        // toggle the type attribute
                        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                        password.setAttribute('type', type);

                        // toggle the eye icon
                        if (type === 'password') {
                            eyeIcon.innerHTML = `
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            `;
                        } else {
                            eyeIcon.innerHTML = `
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            `;
                        }
                    });
                </script>

                <!-- Remember Me -->
                <div class="flex items-center gap-2 px-1">
                    <input type="checkbox" id="remember"
                        class="w-4 h-4 rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-blue-500 focus:ring-offset-slate-950">
                    <label for="remember" class="text-sm text-slate-400 cursor-pointer select-none">Remember for 30
                        days</label>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full btn-gradient text-white font-bold py-4 px-4 rounded-2xl transition-all transform hover:-translate-y-1 active:scale-[0.98] flex items-center justify-center gap-2">
                    Sign In
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-800/50 text-center">
                <p class="text-sm text-slate-500">
                    New here?
                    <a href="#"
                        class="font-bold text-blue-400 hover:text-blue-300 underline underline-offset-4 decoration-2 decoration-blue-500/30 transition-colors">Contact
                        HR Office</a>
                </p>
            </div>
        </div>

        <!-- Footer Info -->
        <p class="text-center text-slate-600 text-xs mt-10">
            &copy; <?php echo date('Y'); ?> LeaveEase Systems. All rights reserved. <br>
            <span class="opacity-50">Enterprise Edition v2.4</span>
        </p>
    </div>

</body>

</html>