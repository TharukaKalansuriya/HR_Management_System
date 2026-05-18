<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_set_cookie_params(2592000, '/');
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    die;
}
include 'includes/dbconnection.php';

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New password and confirm password do not match!";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "New password must be at least 6 characters long!";
    } else {
        // Fetch current password hash
        $pwd_query = "SELECT password_hash FROM employees WHERE id = '$user_id' LIMIT 1";
        $pwd_result = mysqli_query($conn, $pwd_query);
        $pwd_data = mysqli_fetch_assoc($pwd_result);

        if ($pwd_data && password_verify($current_password, $pwd_data['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE employees SET password_hash = '$new_hash' WHERE id = '$user_id'";
            if (mysqli_query($conn, $update_query)) {
                $success_msg = "Password updated successfully! Please use your new password next time you login.";
            } else {
                $error_msg = "Database error: Failed to update password.";
            }
        } else {
            $error_msg = "Incorrect current password!";
        }
    }
}

// Fetch user profile data
$query = "SELECT * FROM employees WHERE id = '$user_id' LIMIT 1";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    include 'includes/header.php';
    include 'includes/navbar.php';
    echo "<div class='p-8 text-center text-rose-600 font-bold'>Employee Profile not found. Please contact HR.</div>";
    include 'includes/footer.php';
    die;
}

// Format Dates
$joined = !empty($user['date_joined']) ? date('F d, Y', strtotime($user['date_joined'])) : 'Not Provided';
$confirmed = !empty($user['confirmation_date']) ? date('F d, Y', strtotime($user['confirmation_date'])) : 'Pending Confirmation';

include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
    /* Premium High-Fidelity Custom Tokens */
    .profile-canvas {
        background: radial-gradient(circle at 10% 20%, rgba(243, 244, 246, 1) 0%, rgba(229, 231, 235, 0.4) 90%);
        position: relative;
        overflow: hidden;
    }
    .orb-glow-1 {
        position: absolute;
        top: -15%;
        left: -15%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, rgba(59, 130, 246, 0) 70%);
        pointer-events: none;
        border-radius: 50%;
    }
    .orb-glow-2 {
        position: absolute;
        bottom: -15%;
        right: -15%;
        width: 650px;
        height: 650px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0) 70%);
        pointer-events: none;
        border-radius: 50%;
    }
    .glass-card-premium {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.04);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .glass-card-premium:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.08);
        border-color: rgba(59, 130, 246, 0.25);
    }
    .hover-badge {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .hover-badge:hover {
        background-color: rgba(239, 246, 255, 0.9);
        transform: scale(1.04);
        border-color: rgba(59, 130, 246, 0.3);
    }
    .detail-item-card {
        background: rgba(255, 255, 255, 0.4);
        border: 1px solid rgba(241, 245, 249, 0.8);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .detail-item-card:hover {
        background: rgba(255, 255, 255, 0.9);
        border-color: rgba(59, 130, 246, 0.15);
        box-shadow: 0 10px 20px -10px rgba(15, 23, 42, 0.04);
        transform: translateY(-2px);
    }
    .icon-glow {
        box-shadow: 0 4px 10px -2px rgba(59, 130, 246, 0.2);
    }
    .gradient-text {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    @keyframes scale-up {
        0% { transform: scale(0.92); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .animate-scale-up {
        animation: scale-up 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
</style>

<main class="flex-grow pt-24 px-6 md:px-12 pb-6 md:pb-12 profile-canvas min-h-screen">
    <!-- Blur Background Elements -->
    <div class="orb-glow-1"></div>
    <div class="orb-glow-2"></div>

    <div class="max-w-5xl mx-auto space-y-10 relative z-10">
        
        <!-- Breadcrumbs -->
        <div class="flex items-center justify-between">
            <a href="dashboard.php" class="inline-flex items-center text-sm font-semibold text-slate-400 hover:text-blue-600 transition-colors gap-2 group">
                <div class="w-9 h-9 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center shadow-sm group-hover:bg-slate-50 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 transform group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </div>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <!-- Status Messages -->
        <?php if (!empty($success_msg)): ?>
            <div id="success-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 backdrop-blur-md transition-opacity duration-300">
                <div class="bg-white/95 rounded-[32px] p-8 max-w-sm w-full mx-4 shadow-2xl border border-slate-100 flex flex-col items-center text-center space-y-6 relative overflow-hidden transform scale-100 transition-all duration-300 animate-scale-up">
                    <!-- Subtle background blur effect inside modal -->
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-emerald-500/5 rounded-full filter blur-xl"></div>
                    
                    <!-- Animated Check Icon Circle -->
                    <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-emerald-600 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    
                    <div class="space-y-2">
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">Password Changed!</h3>
                        <p class="text-sm text-slate-400 font-semibold leading-relaxed px-2">
                            Your secure credentials have been successfully updated. Please use your new password next time you login.
                        </p>
                    </div>
                    
                    <button type="button" onclick="closeSuccessModal()" class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-500/20 transition-all duration-300">
                        Okay, Awesome
                    </button>
                </div>
            </div>
            
            <script>
                function closeSuccessModal() {
                    const modal = document.getElementById('success-modal');
                    if (modal) {
                        modal.style.opacity = '0';
                        setTimeout(() => {
                            modal.style.display = 'none';
                        }, 300);
                    }
                }
            </script>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-600 rounded-3xl text-sm font-semibold flex items-center gap-3 animate-fade-in shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Premium Header Hero Block -->
        <div class="glass-card-premium rounded-[36px] p-8 md:p-10 flex flex-col md:flex-row items-center md:items-start gap-8 relative overflow-hidden">
            <!-- Active Account Status Badge in top right corner -->
            <div class="absolute top-6 right-6 md:top-8 md:right-8 z-20">
                <span class="px-4 py-1.5 bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 rounded-full text-xs font-bold uppercase tracking-wider flex items-center gap-2 shadow-sm backdrop-blur-sm bg-white/50">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <?php echo htmlspecialchars($user['status']); ?> Account
                </span>
            </div>
            <!-- Subtle accent lines in background -->
            <div class="absolute -right-20 -top-20 w-40 h-40 rounded-full border-[10px] border-slate-100/40 pointer-events-none"></div>
            
            <!-- Avatar Section -->
            <div class="relative group flex-shrink-0">
                <div class="absolute inset-0 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-[30px] blur-md opacity-25 group-hover:opacity-40 transition-opacity duration-300"></div>
                <div class="relative bg-white p-1.5 rounded-[30px] shadow-lg border border-slate-100">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>&background=2563eb&color=fff&size=160&font-size=0.33&bold=true"
                         class="w-32 h-32 md:w-36 md:h-36 rounded-[24px] object-cover transition-transform group-hover:scale-[1.03] duration-300" alt="Employee Photo">
                </div>
                <div class="absolute -bottom-2 -right-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-2.5 rounded-2xl shadow-lg border-2 border-white icon-glow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4" />
                    </svg>
                </div>
            </div>

            <!-- Profile Info Stack -->
            <div class="flex-1 text-center md:text-left space-y-6">
                <div class="space-y-2">
                    <h1 class="text-3xl md:text-4xl font-extrabold text-slate-800 tracking-tight leading-none gradient-text"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p class="text-slate-400 font-semibold text-sm flex items-center justify-center md:justify-start gap-1.5 mt-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </p>
                </div>
                
                <!-- Quick Info Badges -->
                <div class="flex flex-wrap justify-center md:justify-start gap-3 pt-1">
                    <div class="hover-badge px-4 py-2 bg-white/80 border border-slate-200/80 rounded-2xl text-xs font-bold text-slate-600 flex items-center gap-2 shadow-sm cursor-default">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        Department: <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($user['department']); ?></span>
                    </div>
                    <div class="hover-badge px-4 py-2 bg-white/80 border border-slate-200/80 rounded-2xl text-xs font-bold text-slate-600 flex items-center gap-2 shadow-sm cursor-default">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Role: <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($user['position']); ?></span>
                    </div>
                    <div class="hover-badge px-4 py-2 bg-blue-50/50 border border-blue-100 rounded-2xl text-xs font-bold text-blue-600 flex items-center gap-2 shadow-sm cursor-default">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        Ref ID: <span class="font-mono text-blue-700 font-extrabold"><?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Employment Details Grid -->
        <div class="glass-card-premium rounded-[36px] p-8 md:p-10 space-y-8 relative overflow-hidden">
            <div class="absolute right-0 bottom-0 w-64 h-64 bg-indigo-50/80 rounded-full filter blur-[80px] -z-10 opacity-30"></div>
            
            <!-- Section Header -->
            <div class="flex items-center justify-between pb-6 border-b border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800 leading-tight">Employment Information</h2>
                        <p class="text-xs text-slate-400 font-semibold mt-0.5">Corporate contract & organization data</p>
                    </div>
                </div>
                
                <span class="px-4 py-2 bg-blue-50 text-blue-700 border border-blue-100 rounded-2xl text-xs font-black uppercase tracking-wider shadow-sm">
                    <?php echo htmlspecialchars($user['employment_type'] ?: 'Permanent'); ?>
                </span>
            </div>

            <!-- Detailed Grid Items with beautiful micro-cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Card: Designation -->
                <div class="detail-item-card rounded-2xl p-5 flex items-start gap-4">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Designation</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo htmlspecialchars($user['designation'] ?: $user['position']); ?></p>
                    </div>
                </div>

                <!-- Card: Grade -->
                <div class="detail-item-card rounded-2xl p-5 flex items-start gap-4">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Employment Grade</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo htmlspecialchars($user['grade'] ?: 'Standard Grade'); ?></p>
                    </div>
                </div>

                <!-- Card: Date Joined -->
                <div class="detail-item-card rounded-2xl p-5 flex items-start gap-4">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Date Joined</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo $joined; ?></p>
                    </div>
                </div>

                <!-- Card: Confirmation Date -->
                <div class="detail-item-card rounded-2xl p-5 flex items-start gap-4">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center text-purple-500 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Confirmation Date</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo $confirmed; ?></p>
                    </div>
                </div>

                <!-- Card: Work Location -->
                <div class="detail-item-card rounded-2xl p-5 flex items-start gap-4">
                    <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center text-rose-500 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Work Location</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo htmlspecialchars($user['branch_location'] ?: 'Head Office'); ?></p>
                    </div>
                </div>

                <!-- Card: Probation -->
                <div class="detail-item-card rounded-2xl p-5 flex items-start gap-4">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Probation Period</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo htmlspecialchars($user['probation_period'] ?: 'None'); ?></p>
                    </div>
                </div>

            </div>

            <!-- EPF / ETF / TIN Registry grid -->
            <div class="pt-8 border-t border-slate-100/80 space-y-6">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Fund & Tax Registrations</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="p-5 bg-slate-50/50 border border-slate-100/80 rounded-2xl hover:bg-white hover:border-blue-300 hover:shadow-md hover:shadow-blue-500/5 transition-all duration-300 flex items-start gap-4">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-black text-xs">EPF</div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest">EPF Reg No</p>
                            <p class="font-mono font-extrabold text-slate-800 text-[15px] mt-1"><?php echo htmlspecialchars($user['epf_number'] ?: 'Not Registered'); ?></p>
                        </div>
                    </div>
                    <div class="p-5 bg-slate-50/50 border border-slate-100/80 rounded-2xl hover:bg-white hover:border-indigo-300 hover:shadow-md hover:shadow-indigo-500/5 transition-all duration-300 flex items-start gap-4">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-black text-xs">ETF</div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest">ETF Reg No</p>
                            <p class="font-mono font-extrabold text-slate-800 text-[15px] mt-1"><?php echo htmlspecialchars($user['etf_number'] ?: 'Not Registered'); ?></p>
                        </div>
                    </div>
                    <div class="p-5 bg-slate-50/50 border border-slate-100/80 rounded-2xl hover:bg-white hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-500/5 transition-all duration-300 flex items-start gap-4">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-black text-xs">TIN</div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest">TIN Reg No</p>
                            <p class="font-mono font-extrabold text-slate-800 text-[15px] mt-1"><?php echo htmlspecialchars($user['tin_number'] ?: 'Not Registered'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Change Password Action & Form -->
        <div class="glass-card-premium rounded-[36px] p-8 md:p-10 space-y-6 relative overflow-hidden">
            <!-- Subtle accent lines in background -->
            <div class="absolute -right-20 -top-20 w-40 h-40 rounded-full border-[10px] border-slate-100/40 pointer-events-none"></div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gradient-to-tr from-amber-500 to-orange-500 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800 leading-tight">Security & Passwords</h2>
                        <p class="text-xs text-slate-400 font-semibold mt-0.5">Manage your account authentication settings</p>
                    </div>
                </div>
                
                <button type="button" onclick="togglePasswordForm()" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-2xl text-xs font-black uppercase tracking-wider shadow-md hover:shadow-lg hover:shadow-indigo-500/25 transition-all duration-300 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    <span>Change Password</span>
                </button>
            </div>

            <!-- Collapsible Change Password Form (Hidden by default) -->
            <form id="password-form" method="POST" action="profile.php" class="hidden space-y-6 pt-6 border-t border-slate-100/80 animate-fade-in relative z-10">
                <input type="hidden" name="change_password" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1.5">
                        <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider" for="current_password">Current Password</label>
                        <input type="password" name="current_password" id="current_password" required
                               class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white/50 text-slate-700 text-sm focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 transition-all duration-200 placeholder:text-slate-300" placeholder="••••••••">
                    </div>
                    
                    <div class="space-y-1.5">
                        <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider" for="new_password">New Password</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6"
                               class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white/50 text-slate-700 text-sm focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 transition-all duration-200 placeholder:text-slate-300" placeholder="••••••••">
                    </div>
                    
                    <div class="space-y-1.5">
                        <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider" for="confirm_password">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                               class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white/50 text-slate-700 text-sm focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 transition-all duration-200 placeholder:text-slate-300" placeholder="••••••••">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="togglePasswordForm()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-xs font-black uppercase tracking-wider transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white rounded-2xl text-xs font-black uppercase tracking-wider shadow-md hover:shadow-lg hover:shadow-emerald-500/25 transition-all duration-300">
                        Update Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</main>

<script>
    function togglePasswordForm() {
        const form = document.getElementById('password-form');
        if (form) {
            form.classList.toggle('hidden');
        }
    }
</script>

<?php if (!empty($error_msg)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('password-form');
        if (form) {
            form.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
