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

// Fetch employee details
$emp_query = "SELECT * FROM employees WHERE id = '$user_id' LIMIT 1";
$emp_result = mysqli_query($conn, $emp_query);
$user = mysqli_fetch_assoc($emp_result);

if (!$user) {
    include 'includes/header.php';
    include 'includes/navbar.php';
    echo "<div class='p-8 text-center text-rose-600 font-bold'>Employee profile not found. Please contact HR.</div>";
    include 'includes/footer.php';
    die;
}

// Fetch the latest resignation record for the user, regardless of status
$check_query = "SELECT * FROM employee_resignations WHERE employee_id = '$user_id' ORDER BY created_at DESC LIMIT 1";
$check_result = mysqli_query($conn, $check_query);
$existing_resignation = mysqli_fetch_assoc($check_result);

// Form submission handler
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_resignation'])) {
    $reason = trim($_POST['reason']);
    $resignation_date = trim($_POST['resignation_date']);
    $contact_number = trim($_POST['contact_number']);

    if (empty($reason) || empty($resignation_date) || empty($contact_number)) {
        $error_msg = "Please fill in all the required fields!";
    } elseif ($existing_resignation && in_array($existing_resignation['status'], ['Pending', 'HR_Approved'])) {
        $error_msg = "You already have an active resignation request undergoing review!";
    } elseif (strtotime($resignation_date) < strtotime(date('Y-m-d'))) {
        $error_msg = "Resignation date cannot be in the past!";
    } else {
        $reason_safe = mysqli_real_escape_string($conn, $reason);
        $date_safe = mysqli_real_escape_string($conn, $resignation_date);
        $contact_safe = mysqli_real_escape_string($conn, $contact_number);
        
        $insert_query = "INSERT INTO employee_resignations (employee_id, resignation_date, reason, contact_number, status) VALUES ('$user_id', '$date_safe', '$reason_safe', '$contact_safe', 'Pending')";
        
        if (mysqli_query($conn, $insert_query)) {
            $success_msg = "Your resignation application has been successfully submitted.";
            // Refresh check
            $check_result = mysqli_query($conn, $check_query);
            $existing_resignation = mysqli_fetch_assoc($check_result);
        } else {
            $error_msg = "Database error: Failed to save resignation details. " . mysqli_error($conn);
        }
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
    /* Custom design tokens for premium styling */
    .resign-canvas {
        background: radial-gradient(circle at 10% 20%, rgba(243, 244, 246, 1) 0%, rgba(229, 231, 235, 0.4) 90%);
        position: relative;
        overflow: hidden;
    }
    .orb-glow {
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0) 70%);
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
        transform: translateY(-2px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.08);
    }
    @keyframes scale-up {
        0% { transform: scale(0.92); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .animate-scale-up {
        animation: scale-up 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
</style>

<main class="flex-grow p-6 md:p-12 resign-canvas min-h-screen">
    <!-- Glow Background Elements -->
    <div class="orb-glow -top-20 -left-20"></div>
    <div class="orb-glow -bottom-20 -right-20" style="background: radial-gradient(circle, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0) 70%);"></div>

    <div class="max-w-3xl mx-auto space-y-8 relative z-10">
        
        <!-- Breadcrumbs & Navigation -->
        <div class="flex items-center justify-between">
            <a href="dashboard.php" class="inline-flex items-center text-sm font-semibold text-slate-400 hover:text-red-500 transition-colors gap-2 group">
                <div class="w-9 h-9 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center shadow-sm group-hover:bg-slate-50 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 transform group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </div>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <!-- Success Pop-up Modal -->
        <?php if (!empty($success_msg)): ?>
            <div id="success-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 backdrop-blur-md transition-opacity duration-300">
                <div class="bg-white/95 rounded-[32px] p-8 max-w-sm w-full mx-4 shadow-2xl border border-slate-100 flex flex-col items-center text-center space-y-6 relative overflow-hidden transform scale-100 transition-all duration-300 animate-scale-up">
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-red-500/5 rounded-full filter blur-xl"></div>
                    
                    <!-- Animated Paper Plane / Exit Icon -->
                    <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center shadow-lg shadow-red-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </div>
                    
                    <div class="space-y-2">
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">Resignation Applied Successfully!</h3>
                        <p class="text-sm text-slate-400 font-semibold leading-relaxed px-2">
                            Your resignation request has been securely processed and filed. HR and administration will evaluate your request immediately.
                        </p>
                    </div>
                    
                    <button type="button" onclick="closeSuccessModal()" class="w-full py-3 bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all duration-300">
                        Okay, Understand
                    </button>
                </div>
            </div>
            
            <script>
                // Reload page to refresh display state card
                function closeSuccessModal() {
                    const modal = document.getElementById('success-modal');
                    if (modal) {
                        modal.style.opacity = '0';
                        setTimeout(() => {
                            modal.style.display = 'none';
                            window.location.href = 'resign.php';
                        }, 300);
                    }
                }
            </script>
        <?php endif; ?>

        <!-- Error Messages Toast -->
        <?php if (!empty($error_msg)): ?>
            <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-600 rounded-3xl text-sm font-semibold flex items-center gap-3 animate-fade-in shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Header Card Block -->
        <div class="glass-card-premium rounded-[36px] p-8 md:p-10 flex flex-col sm:flex-row items-center gap-6 relative overflow-hidden">
            <!-- Red accent border glowing -->
            <div class="absolute left-0 top-0 bottom-0 w-2 bg-gradient-to-b from-red-500 to-orange-500"></div>

            <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 flex-shrink-0 shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </div>
            
            <div class="text-center sm:text-left">
                <h1 class="text-2xl font-black text-slate-800 tracking-tight leading-none">Resignation Portal</h1>
                <p class="text-slate-400 font-semibold text-xs mt-2">File your contract ending / notice application securely</p>
            </div>
        </div>

        <?php if ($existing_resignation): ?>
            <!-- Existing Resignation Progress Card -->
            <div class="glass-card-premium rounded-[36px] p-8 md:p-10 border-red-100 bg-red-50/20 space-y-6">
                <div class="flex items-center gap-4 pb-4 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <?php
                        $res_status = $existing_resignation['status'] ?? 'Pending';
                        if ($res_status === 'Approved') {
                            echo '<h3 class="text-md font-bold text-slate-800">Admin Approved Resignation Details</h3>';
                            echo '<p class="text-xs text-emerald-600 font-semibold">Your resignation has been finalized and approved.</p>';
                        } elseif ($res_status === 'Rejected') {
                            echo '<h3 class="text-md font-bold text-slate-800">Rejected Resignation Application</h3>';
                            echo '<p class="text-xs text-rose-600 font-semibold">Your resignation request has been rejected.</p>';
                        } else {
                            echo '<h3 class="text-md font-bold text-slate-800">Pending Resignation Application</h3>';
                            echo '<p class="text-xs text-slate-400 font-semibold">Your resignation notice is currently undergoing administrative evaluation.</p>';
                        }
                        ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-2">
                    <div class="p-4 bg-white/80 border border-slate-100 rounded-2xl">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-wider">Requested Resignation Date</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1">
                            <?php 
                            $res_date = $existing_resignation['resignation_date'] ?? $existing_resignation['last_working_date'] ?? '';
                            echo !empty($res_date) ? date('F d, Y', strtotime($res_date)) : 'N/A'; 
                            ?>
                        </p>
                    </div>
                    <div class="p-4 bg-white/80 border border-slate-100 rounded-2xl">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-wider">Contact Number</p>
                        <p class="font-extrabold text-slate-700 text-sm mt-1"><?php echo htmlspecialchars($existing_resignation['contact_number'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="p-4 bg-white/80 border border-slate-100 rounded-2xl">
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-wider">Notice Status</p>
                        <?php 
                        $res_status = $existing_resignation['status'] ?? 'Pending';
                        if ($res_status === 'Approved') {
                            echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-500/10 text-emerald-600 rounded-full text-xs font-bold mt-1.5 border border-emerald-500/20"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Admin Approved</span>';
                        } elseif ($res_status === 'Rejected') {
                            echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-500/10 text-rose-600 rounded-full text-xs font-bold mt-1.5 border border-rose-500/20"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected</span>';
                        } elseif ($res_status === 'HR_Approved') {
                            echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/10 text-blue-600 rounded-full text-xs font-bold mt-1.5 border border-blue-500/20"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>HR Approved &mdash; Awaiting Admin</span>';
                        } else {
                            echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/10 text-amber-600 rounded-full text-xs font-bold mt-1.5 border border-amber-500/20"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>Pending Review</span>';
                        }
                        ?>
                    </div>
                </div>

                <div class="p-5 bg-white/50 border border-slate-100 rounded-2xl space-y-1">
                    <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest">Reason Provided</p>
                    <p class="font-semibold text-slate-600 text-xs leading-relaxed mt-1"><?php echo nl2br(htmlspecialchars($existing_resignation['reason'])); ?></p>
                </div>
            </div>
        <?php else: ?>
            <!-- New Resignation Form -->
            <div class="glass-card-premium rounded-[36px] p-8 md:p-10 space-y-8">
                
                <div class="flex items-center gap-3 pb-6 border-b border-slate-100">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <h2 class="text-lg font-bold text-slate-800">Apply for Resignation</h2>
                </div>

                <form method="POST" action="resign.php" class="space-y-6">
                    <input type="hidden" name="apply_resignation" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Employee Reference ID -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider">Employee ID Reference</label>
                            <div class="w-full px-4 py-3.5 rounded-2xl border border-slate-100 bg-slate-50 text-slate-500 font-mono font-extrabold text-sm flex items-center gap-2 cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 00-2 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                                <span>EMP-<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                        </div>

                        <!-- Contact Number -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider" for="contact_number">Contact Number</label>
                            <div class="relative">
                                <input type="tel" name="contact_number" id="contact_number" required
                                       placeholder="e.g. +94 77 123 4567"
                                       class="w-full pl-10 pr-4 py-3 rounded-2xl border border-slate-200 bg-white/50 text-slate-700 text-sm focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/5 transition-all duration-200 font-semibold">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-[9px] text-slate-400 font-semibold mt-1">Provide an active phone number.</p>
                        </div>

                        <!-- Resignation Date -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider" for="resignation_date">Resignation Date</label>
                            <input type="date" name="resignation_date" id="resignation_date" required
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white/50 text-slate-700 text-sm focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/5 transition-all duration-200 font-semibold">
                            <p class="text-[9px] text-slate-400 font-semibold mt-1">Select your desired last working date.</p>
                        </div>
                    </div>

                    <!-- Resignation Reason -->
                    <div class="space-y-1.5">
                        <label class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider" for="reason">Reason for Resignation</label>
                        <textarea name="reason" id="reason" rows="4" required
                                  placeholder="Please provide the exact details or reasons for leaving..."
                                  class="w-full px-4 py-3.5 rounded-2xl border border-slate-200 bg-white/50 text-slate-700 text-sm focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/5 transition-all duration-200 placeholder:text-slate-300 font-semibold leading-relaxed"></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="dashboard.php" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-xs font-black uppercase tracking-wider transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-md hover:shadow-lg hover:shadow-red-500/25 transition-all duration-300 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <span>File Resignation</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
