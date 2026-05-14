<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php'; 

$msg = "";
if (isset($_SESSION['action_msg'])) {
    $msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// Handle Approve/Reject Actions
if (isset($_POST['status']) && isset($_POST['leave_id'])) {
    $leave_id = mysqli_real_escape_string($conn, $_POST['leave_id']);
    $check_res = mysqli_query($conn, "SELECT status FROM leaves WHERE id = '$leave_id'");
    $current_leave = mysqli_fetch_assoc($check_res);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    if ($current_leave['status'] == 'Rejected') {
        $new_status = 'Rejected';
        $admin_remark = 'Final Approved by Admin';
    } else {
        $admin_remark = isset($_POST['admin_remark']) ? mysqli_real_escape_string($conn, $_POST['admin_remark']) : '';
        if ($new_status == 'Approved') $admin_remark = 'Final Approved by Admin';
    }
    $update_query = "UPDATE leaves SET status = '$new_status', admin_remark = '$admin_remark' WHERE id = '$leave_id'";
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['action_msg'] = "Request has been " . strtolower($new_status) . " successfully.";
        header("Location: admin_dashboard.php");
        exit();
    }
}

// Fetch Stats
$hr_recommended_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status IN ('HR_Approved','Approved')"))['total'];
$final_approved_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE admin_remark = 'Final Approved by Admin'"))['total'];
$staff_count          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM employees"))['total'];
$on_leave_today       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date"))['total'];
$pending_admin        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status = 'HR_Approved'"))['total'];

// Fetch Leave Requests
$main_query = "SELECT l.*, e.first_name, e.last_name, e.department, e.position 
               FROM leaves l 
               JOIN employees e ON l.user_id = e.id 
               WHERE l.status = 'HR_Approved' OR (l.status = 'Rejected' AND l.admin_remark != 'Final Approved by Admin')
               ORDER BY FIELD(l.status, 'HR_Approved', 'Rejected'), l.created_at DESC LIMIT 20";
$main_result = mysqli_query($conn, $main_query);

include 'includes/header.php'; 
include 'includes/sidebar.php'; 
?>

<!-- Page wrapper: sidebar already rendered, now we open the content column -->
<div class="flex flex-col flex-1 min-h-screen min-w-0" id="main-content">

    <!-- TOP BAR -->
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Admin Dashboard</h1>
            <p class="text-xs text-slate-400 font-medium">System-wide leave oversight & final approvals</p>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_name']); ?>&background=3b82f6&color=fff&size=80"
                     class="w-9 h-9 rounded-xl object-cover" alt="Avatar">
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-6 lg:p-8 overflow-y-auto">

        <?php if ($msg): ?>
        <div id="status-alert" class="mb-6 p-4 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-semibold text-sm"><?php echo $msg; ?></span>
        </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

            <!-- Awaiting Final Approval -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-amber-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-500 bg-amber-50 px-2 py-1 rounded-lg">Pending</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $pending_admin; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">Awaiting Final Approval</p>
            </div>

            <!-- HR Recommended -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-500 bg-blue-50 px-2 py-1 rounded-lg">HR</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $hr_recommended_total; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">HR Recommended</p>
            </div>

            <!-- Final Approved -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-500 bg-emerald-50 px-2 py-1 rounded-lg">Approved</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $final_approved_count; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">Final Approved</p>
            </div>

            <!-- On Leave Today -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-violet-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-violet-500 bg-violet-50 px-2 py-1 rounded-lg">Today</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $on_leave_today; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">On Leave Today</p>
            </div>
        </div>


        <!-- Leave Oversight Table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Leave Oversight</h2>
                    <p class="text-xs text-slate-400 mt-0.5">HR-recommended requests awaiting your final decision</p>
                </div>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="employeeSearch" placeholder="Search employee..." 
                        class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none w-56">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Leave Type</th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Document</th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody id="leavesTableBody" class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($main_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($main_result)): ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-sm border border-blue-100">
                                            <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800 text-sm employee-name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></p>
                                            <p class="text-[10px] text-slate-400 uppercase tracking-tight"><?php echo $row['department']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-[11px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase tracking-wide"><?php echo str_replace('_',' ',$row['leave_type']); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-700"><?php echo date('M d', strtotime($row['start_date'])) . ' – ' . date('M d', strtotime($row['end_date'])); ?></p>
                                    <p class="text-[11px] text-slate-400 font-medium"><?php echo ($row['leave_type'] === 'half_day') ? '0.5' : $row['days']; ?> Days</p>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['document_path'])): ?>
                                        <a href="<?php echo $row['document_path']; ?>" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            View Doc
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-300 font-medium">No Doc</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php $status = $row['status']; ?>
                                    <?php if ($status == 'HR_Approved'): ?>
                                        <span class="text-[10px] font-bold uppercase text-blue-500 bg-blue-50 px-2 py-1 rounded-lg">HR Approved</span>
                                    <?php elseif ($status == 'Rejected'): ?>
                                        <span class="text-[10px] font-bold uppercase text-rose-500 bg-rose-50 px-2 py-1 rounded-lg">Rejected by HR</span>
                                    <?php elseif ($status == 'Approved'): ?>
                                        <span class="text-[10px] font-bold uppercase text-emerald-500 bg-emerald-50 px-2 py-1 rounded-lg">Approved</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <form method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="status" value="Approved" 
                                            class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 shadow-sm shadow-emerald-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Approve
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3 text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <p class="text-sm font-medium">All clear — no requests pending.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div><!-- end flex-1 content col -->

<?php include 'includes/footer.php'; ?>

<script>
    document.getElementById('employeeSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#leavesTableBody tr').forEach(row => {
            const n = row.querySelector('.employee-name');
            row.style.display = (!n || n.textContent.toLowerCase().includes(q)) ? '' : 'none';
        });
    });

    const alert = document.getElementById('status-alert');
    if (alert) {
        setTimeout(() => { alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }, 3500);
    }
</script>
