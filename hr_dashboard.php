<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
// HR Dashboard is for HR roles. Super Admin should use admin_dashboard.php
if ($_SESSION['admin_role'] == 'super_admin') {
    header("Location: admin_dashboard.php");
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
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $admin_remark = mysqli_real_escape_string($conn, $_POST['admin_remark']);
    $role = $_SESSION['admin_role'];

    // HR specific approval logic
    if ($new_status == 'Approved') {
        $new_status = 'HR_Approved';
        if (empty($admin_remark)) $admin_remark = 'Recommended by ' . strtoupper(str_replace('_', ' ', $role));
    }

    $update_query = "UPDATE leaves SET status = '$new_status', admin_remark = '$admin_remark' WHERE id = '$leave_id'";
    if (mysqli_query($conn, $update_query)) {
        $display_status = ($new_status == 'HR_Approved') ? 'HR Approved' : $new_status;
        $_SESSION['action_msg'] = "Request has been " . strtolower($display_status) . " successfully.";
        header("Location: hr_dashboard.php");
        exit();
    }
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 

// Fetch Stats for HR
$pending_label = "Pending HR Review";
$pending_query_val = 'Pending';

$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status = 'Pending'"))['total'];
$hr_approved_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status IN ('HR_Approved', 'Approved')"))['total'];
$rejected_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status = 'Rejected'"))['total'];
$on_leave_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM leaves WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date"))['total'];

// Fetch Pending Leaves
$pending_query = "SELECT l.*, e.first_name, e.last_name, e.department, e.position 
               FROM leaves l 
               JOIN employees e ON l.user_id = e.id 
               WHERE l.status = 'Pending' 
               ORDER BY l.created_at DESC";
$pending_result = mysqli_query($conn, $pending_query);
?>

<main class="flex-grow p-6 md:p-10 bg-slate-50">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">HR Leave Dashboard</h1>
                <p class="text-slate-500 mt-1">Process employee requests and review recent actions.</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div id="status-alert" class="mb-6 p-4 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl flex items-center gap-3 animate-float">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold text-sm"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- Stats cards... -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800"><?php echo $pending_count; ?></p>
                        <h3 class="text-slate-500 text-xs font-medium uppercase tracking-wider"><?php echo $pending_label; ?></h3>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800"><?php echo $hr_approved_total; ?></p>
                        <h3 class="text-slate-500 text-xs font-medium uppercase tracking-wider">HR Approved</h3>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-rose-50 text-rose-600 rounded-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800"><?php echo $rejected_count; ?></p>
                        <h3 class="text-slate-500 text-xs font-medium uppercase tracking-wider">Rejected</h3>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800"><?php echo $on_leave_today; ?></p>
                        <h3 class="text-slate-500 text-xs font-medium uppercase tracking-wider">On Leave Today</h3>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'leave_calendar.php'; ?>

        <!-- Pending Approvals Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-10">
            <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">Pending Review Requests</h2>
                <div class="relative">
                    <input type="text" id="employeeSearch" placeholder="Search employee..." 
                        class="pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none w-64">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Type</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Reason</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Document</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody id="leavesTableBody" class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($pending_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($pending_result)): ?>
                                <tr class="hover:bg-slate-50/30 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold border-2 border-white shadow-sm">
                                                <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-slate-800 text-sm employee-name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></div>
                                                <div class="text-[10px] text-slate-400 font-medium uppercase tracking-tighter"><?php echo $row['department']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase tracking-wider"><?php echo str_replace('_', ' ', $row['leave_type']); ?></span>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="text-sm text-slate-600 font-semibold"><?php echo date('M d', strtotime($row['start_date'])) . ' - ' . date('M d', strtotime($row['end_date'])); ?></div>
                                        <div class="text-xs font-bold text-slate-400"><?php echo ($row['leave_type'] === 'half_day') ? ($row['days'] == 1 ? '1/2' : ($row['days'] * 0.5)) : $row['days']; ?> Days</div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <p class="text-sm text-slate-500 max-w-xs truncate" title="<?php echo $row['reason']; ?>"><?php echo $row['reason']; ?></p>
                                    </td>
                                    <td class="px-8 py-5">
                                        <?php if (!empty($row['document_path'])): ?>
                                            <a href="<?php echo $row['document_path']; ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 bg-blue-50 px-3 py-2 rounded-xl hover:bg-blue-100 transition-colors w-fit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                </svg>
                                                View Doc
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 font-bold">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-5">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="admin_remark" placeholder="Add comment..." class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 outline-none w-40">
                                            <button type="submit" name="status" value="Approved" class="bg-emerald-500 hover:bg-emerald-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-emerald-100 flex items-center gap-2 group" title="Recommend">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span class="text-[10px] font-bold uppercase tracking-wider hidden group-hover:block">Approve</span>
                                            </button>
                                            <button type="submit" name="status" value="Rejected" class="bg-red-500 hover:bg-red-600 text-white p-2.5 rounded-xl transition-all shadow-lg shadow-red-100 flex items-center gap-2 group" title="Reject">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                <span class="text-[10px] font-bold uppercase tracking-wider hidden group-hover:block">Reject</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-8 py-10 text-center text-slate-400 font-medium">No pending leave requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    // Search functionality
    document.getElementById('employeeSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#leavesTableBody tr');
        
        rows.forEach(row => {
            const name = row.querySelector('.employee-name');
            if (name) {
                const text = name.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            }
        });
    });

    // Auto-hide alert
    const alert = document.getElementById('status-alert');
    if (alert) {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.style.display = 'none', 500);
        }, 3000);
    }
</script>
