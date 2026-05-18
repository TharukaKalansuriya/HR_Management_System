<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_set_cookie_params(2592000, '/');
    session_start();
}

// Removed login session check as per user request

include '../includes/dbconnection.php';

$success_msg = "";
$error_msg = "";

if (isset($_SESSION['action_msg'])) {
    $success_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// Action Handler: Approve or Reject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['resignation_id'])) {
    $res_id = mysqli_real_escape_string($conn, $_POST['resignation_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']);
    $admin_remark = mysqli_real_escape_string($conn, trim($_POST['admin_remark'] ?? ''));

    $new_status = ($action === 'Approve') ? 'Approved' : 'Rejected';
    if (empty($admin_remark)) {
        $admin_remark = ($new_status === 'Approved') ? "Admin Approved" : "Rejected by Admin";
    }

    $update_query = "UPDATE employee_resignations SET status = '$new_status', admin_remark = '$admin_remark' WHERE id = '$res_id'";
    
    if (mysqli_query($conn, $update_query)) {
        if ($new_status === 'Approved') {
            // Get employee_id for this resignation
            $emp_query = "SELECT employee_id FROM employee_resignations WHERE id = '$res_id'";
            $emp_result = mysqli_query($conn, $emp_query);
            if ($emp_row = mysqli_fetch_assoc($emp_result)) {
                $employee_id = $emp_row['employee_id'];
                $deactivate_query = "UPDATE employees SET status = 'Inactive' WHERE id = '$employee_id'";
                mysqli_query($conn, $deactivate_query);
                
                // Insert notification for the employee
                $notif_msg = "Your resignation has been approved by the Admin.";
                $insert_notif = "INSERT INTO notifications (user_id, message) VALUES ('$employee_id', '$notif_msg')";
                mysqli_query($conn, $insert_notif);
            }
        }
        $_SESSION['action_msg'] = "Resignation notice has been " . strtolower($new_status) . " successfully.";
        header("Location: admin_resign_approve.php");
        exit();
    } else {
        $error_msg = "Database error: Failed to update status. " . mysqli_error($conn);
    }
}

// Fetch Stats Counts (For Admin, HR Recommended is the main pending count)
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM employee_resignations r JOIN employees e ON r.employee_id = e.id WHERE r.status = 'HR_Approved' OR (r.status = 'Pending' AND e.position LIKE '%HR Manager%')"))['total'];
$approved_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM employee_resignations WHERE status = 'Approved'"))['total'];
$rejected_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM employee_resignations WHERE status = 'Rejected'"))['total'];

$filter = $_GET['filter'] ?? 'Pending';
$where_clause = "WHERE r.status = 'HR_Approved' OR (r.status = 'Pending' AND e.position LIKE '%HR Manager%')"; // Default to active tasks
if ($filter === 'Approved') {
    $where_clause = "WHERE r.status = 'Approved'";
} elseif ($filter === 'Rejected') {
    $where_clause = "WHERE r.status = 'Rejected'";
} elseif ($filter === 'All') {
    $where_clause = "";
}

$query = "SELECT r.*, e.first_name, e.last_name, e.department, e.position, e.date_joined, e.email 
          FROM employee_resignations r 
          JOIN employees e ON r.employee_id = e.id 
          $where_clause
          ORDER BY FIELD(r.status, 'HR_Approved', 'Approved', 'Rejected', 'Pending'), r.created_at DESC";
$result = mysqli_query($conn, $query);

$back_url = "../index.php";

$is_employee_page = true; // Forces standard full-width layout without sidebar!
include '../includes/header.php';
?>

<!-- Content Column -->
<div class="flex flex-col flex-1 min-w-0 bg-slate-50" id="main-content">
    
    <!-- Top Bar / Back Navigation -->
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div class="flex items-center gap-4">
            <!-- Premium Back Button -->
            <a href="<?php echo $back_url; ?>" 
               class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-all duration-200 group" 
               title="Back to Dashboard">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Admin Resignation Oversight</h1>
                <p class="text-xs text-slate-400 font-medium">Final approval authority for HR-recommended resignation applications</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-grow p-6 lg:p-8 space-y-8 max-w-7xl w-full mx-auto">
        
        <!-- Alerts Toast -->
        <?php if (!empty($success_msg)): ?>
            <div id="alert-toast" class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 rounded-2xl text-sm font-semibold flex items-center gap-3 animate-fade-in shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo $success_msg; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div id="alert-toast" class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-600 rounded-2xl text-sm font-semibold flex items-center gap-3 animate-fade-in shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <!-- HR Recommended (Pending final Admin) -->
            <a href="?filter=Pending" class="block bg-white rounded-3xl p-5 border <?php echo $filter === 'Pending' ? 'border-blue-300 ring-2 ring-blue-100' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-amber-50 rounded-2xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-500 bg-amber-50 px-2.5 py-1 rounded-lg">HR Recommended</span>
                </div>
                <p class="text-3xl font-black text-slate-800"><?php echo $pending_count; ?></p>
                <p class="text-xs text-slate-400 font-bold mt-1">Awaiting Final Approval</p>
            </a>

            <!-- Approved (Final Approved) -->
            <a href="?filter=Approved" class="block bg-white rounded-3xl p-5 border <?php echo $filter === 'Approved' ? 'border-emerald-300 ring-2 ring-emerald-100' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-emerald-50 rounded-2xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-500 bg-emerald-50 px-2.5 py-1 rounded-lg">Final Approved</span>
                </div>
                <p class="text-3xl font-black text-slate-800"><?php echo $approved_count; ?></p>
                <p class="text-xs text-slate-400 font-bold mt-1">Process Completed</p>
            </a>

            <!-- Rejected -->
            <a href="?filter=Rejected" class="block bg-white rounded-3xl p-5 border <?php echo $filter === 'Rejected' ? 'border-rose-300 ring-2 ring-rose-100' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-rose-50 rounded-2xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-500 bg-rose-50 px-2.5 py-1 rounded-lg">Rejected</span>
                </div>
                <p class="text-3xl font-black text-slate-800"><?php echo $rejected_count; ?></p>
                <p class="text-xs text-slate-400 font-bold mt-1">Notice Declined</p>
            </a>
        </div>

        <!-- Filter Search Bar -->
        <div class="bg-white rounded-[24px] border border-slate-100 p-5 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Admin Resignation Approvals</h2>
                <p class="text-xs text-slate-400 font-semibold mt-0.5">Approve and finalize termination notice requests recommended by HR</p>
            </div>
            <div class="relative w-full md:w-80">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="resignationSearch" placeholder="Search employee or department..."
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold transition-all">
            </div>
        </div>

        <!-- Resignation List Grid -->
        <div id="resignationsContainer" class="grid grid-cols-1 gap-6">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php 
                    $fullName = $row['first_name'] . ' ' . $row['last_name'];
                    $statusClass = 'bg-amber-500/10 text-amber-600 border-amber-500/20';
                    $statusDot = 'bg-amber-500';
                    if ($row['status'] === 'HR_Approved') {
                        $statusClass = 'bg-blue-500/10 text-blue-600 border-blue-500/20';
                        $statusDot = 'bg-blue-500';
                    } elseif ($row['status'] === 'Approved') {
                        $statusClass = 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20';
                        $statusDot = 'bg-emerald-500';
                    } elseif ($row['status'] === 'Rejected') {
                        $statusClass = 'bg-rose-500/10 text-rose-600 border-rose-500/20';
                        $statusDot = 'bg-rose-500';
                    }
                    ?>
                    <!-- Individual Resignation Card -->
                    <div class="bg-white rounded-[32px] p-6 lg:p-8 border border-slate-100 shadow-sm hover:shadow-md transition-all flex flex-col lg:flex-row gap-6 justify-between items-start resignation-card"
                         data-employee="<?php echo strtolower($fullName); ?>"
                         data-dept="<?php echo strtolower($row['department']); ?>">
                        
                        <!-- Left Side: Employee Context -->
                        <div class="space-y-4 flex-grow max-w-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg border border-blue-100 flex-shrink-0 shadow-sm">
                                    <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <h3 class="text-md font-bold text-slate-800 leading-tight">
                                        <span class="text-blue-600 font-black mr-1.5">EMP-<?php echo str_pad($row['employee_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                        <span class="text-slate-300 font-medium mr-1.5">•</span>
                                        <span><?php echo htmlspecialchars($fullName); ?></span>
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase bg-slate-100 px-2 py-0.5 rounded-lg"><?php echo htmlspecialchars($row['department']); ?></span>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight"><?php echo htmlspecialchars($row['position']); ?></span>
                                    </div>
                                    <button type="button" onclick="document.getElementById('details-<?php echo $row['id']; ?>').classList.toggle('hidden')" class="mt-3 text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View Details
                                    </button>
                                </div>
                            </div>

                            <div id="details-<?php echo $row['id']; ?>" class="hidden space-y-4">
                                <!-- Meta Details Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100/80">
                                    <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Registration Date (Joined)</p>
                                    <p class="font-bold text-slate-600 text-xs mt-0.5">
                                        <?php echo !empty($row['date_joined']) ? date('M d, Y', strtotime($row['date_joined'])) : 'N/A'; ?>
                                    </p>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100/80">
                                    <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Application Date</p>
                                    <p class="font-bold text-slate-600 text-xs mt-0.5"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></p>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100/80">
                                    <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Desired Resignation Date</p>
                                    <p class="font-bold text-slate-700 text-xs mt-0.5"><?php echo date('M d, Y', strtotime($row['resignation_date'])); ?></p>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100/80">
                                    <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Contact Number</p>
                                    <p class="font-bold text-slate-700 text-xs mt-0.5"><?php echo htmlspecialchars($row['contact_number']); ?></p>
                                </div>
                            </div>

                            <!-- Short Message: Resignation Reason -->
                            <div class="p-4 bg-slate-50/60 border border-slate-100 rounded-2xl space-y-1">
                                <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest leading-none">Resignation Reason (Short Message)</p>
                                <blockquote class="text-xs text-slate-500 font-semibold italic leading-relaxed mt-1.5 border-l-2 border-red-400 pl-3">
                                    "<?php echo nl2br(htmlspecialchars($row['reason'])); ?>"
                                </blockquote>
                            </div>
                            </div>
                        </div>

                        <!-- Right Side: Decision Actions or Status display -->
                        <div class="flex flex-col gap-4 w-full lg:w-72 lg:items-end self-stretch justify-between">
                            <!-- Status Badge -->
                            <div class="flex items-center lg:justify-end">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border <?php echo $statusClass; ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?php echo $statusDot; ?>"></span>
                                    <?php 
                                    if ($row['status'] === 'HR_Approved') echo 'HR Approved';
                                    elseif ($row['status'] === 'Approved') echo 'Final Approved';
                                    elseif ($row['status'] === 'Pending') echo 'Awaiting HR Review';
                                    else echo $row['status']; 
                                    ?>
                                </span>
                            </div>

                            <?php if ($row['status'] === 'HR_Approved' || ($row['status'] === 'Pending' && stripos($row['position'], 'HR Manager') !== false)): ?>
                                <!-- Decision Action Form -->
                                <form method="POST" action="admin_resign_approve.php" class="w-full space-y-3 pt-4 lg:pt-0">
                                    <input type="hidden" name="resignation_id" value="<?php echo $row['id']; ?>">
                                    
                                    <div class="space-y-1">
                                        <label class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider block">Admin Remarks / Comment</label>
                                        <input type="text" name="admin_remark" placeholder="Provide final remarks..."
                                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold">
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <button type="submit" name="action" value="Reject"
                                                class="py-2 px-4 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-rose-200 transition-colors flex items-center justify-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span>Reject</span>
                                        </button>
                                        <button type="submit" name="action" value="Approve"
                                                class="py-2 px-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-emerald-200 transition-colors flex items-center justify-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Approve</span>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Completed Details Block -->
                                <div class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl">
                                    <p class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest leading-none">Remarks Log</p>
                                    <p class="text-xs text-slate-600 font-bold mt-2 leading-relaxed">
                                        <?php echo !empty($row['admin_remark']) ? htmlspecialchars($row['admin_remark']) : 'No remarks logged.'; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white rounded-[32px] border border-slate-100 p-16 text-center shadow-sm">
                    <div class="flex flex-col items-center gap-3 text-slate-400 max-w-sm mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-md font-bold text-slate-700 mt-2">No Resignation Applications Found</h3>
                        <p class="text-xs text-slate-400 font-semibold leading-relaxed">There are currently no resignation applications requiring administrative oversight in the database.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    // Live Search Filter Handler
    document.getElementById('resignationSearch').addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.resignation-card');
        
        cards.forEach(card => {
            const empName = card.getAttribute('data-employee') || '';
            const deptName = card.getAttribute('data-dept') || '';
            
            if (empName.includes(query) || deptName.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Alert toast autohide transition
    const toast = document.getElementById('alert-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }
</script>

<?php include '../includes/footer.php'; ?>
