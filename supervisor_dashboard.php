<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
if ($_SESSION['admin_role'] !== 'supervisor') {
    // If they are not a supervisor, redirect to their respective dashboard
    if ($_SESSION['admin_role'] == 'super_admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: hr_dashboard.php");
    }
    die;
}
include 'includes/dbconnection.php';

$dept = $_SESSION['admin_dept'] ?? '';

$msg = "";
if (isset($_SESSION['action_msg'])) {
    $msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// Handle Approve / Reject
if (isset($_POST['status']) && isset($_POST['leave_id'])) {
    $leave_id = mysqli_real_escape_string($conn, $_POST['leave_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $admin_remark = mysqli_real_escape_string($conn, $_POST['admin_remark'] ?? '');
    $role = $_SESSION['admin_role'];

    if ($new_status == 'Approved') {
        $new_status = 'Recommended';
        if (empty($admin_remark))
            $admin_remark = 'Recommended by ' . htmlspecialchars($_SESSION['db_role'] ?? 'Supervisor') . ' (Dept: ' . $dept . ')';
    }

    $update_query = "UPDATE leaves SET status = '$new_status', admin_remark = '$admin_remark' WHERE id = '$leave_id'";
    if (mysqli_query($conn, $update_query)) {
        $display_status = ($new_status == 'Recommended') ? 'Recommended' : $new_status;
        $_SESSION['action_msg'] = "Request has been " . strtolower($display_status) . " successfully.";
        header("Location: supervisor_dashboard.php");
        exit();
    }
}

// Fetch Stats filtered by Department
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(l.id) as total FROM leaves l JOIN employees e ON l.user_id = e.id WHERE l.status = 'Pending' AND e.department = '$dept' AND e.position NOT LIKE '%HR Manager%'"))['total'];
$approved_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(l.id) as total FROM leaves l JOIN employees e ON l.user_id = e.id WHERE l.status = 'Recommended' AND e.department = '$dept' AND e.position NOT LIKE '%HR Manager%'"))['total'];
$rejected_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(l.id) as total FROM leaves l JOIN employees e ON l.user_id = e.id WHERE l.status = 'Rejected' AND e.department = '$dept' AND e.position NOT LIKE '%HR Manager%'"))['total'];
$on_leave_today_res = mysqli_query($conn, "SELECT l.*, e.first_name, e.last_name 
                                         FROM leaves l 
                                         JOIN employees e ON l.user_id = e.id 
                                         WHERE l.status IN ('HR_Approved', 'Approved') 
                                         AND e.department = '$dept' AND e.position NOT LIKE '%HR Manager%'
                                         AND CURDATE() BETWEEN l.start_date AND l.end_date");
$on_leave_today_count = mysqli_num_rows($on_leave_today_res);
$on_leave_employees = [];
while ($row = mysqli_fetch_assoc($on_leave_today_res)) {
    $on_leave_employees[] = $row['first_name'] . ' ' . $row['last_name'];
}

// Fetch Pending Leaves for this Department
$pending_result = mysqli_query(
    $conn,
    "SELECT l.*, e.first_name, e.last_name, e.department, e.position 
     FROM leaves l JOIN employees e ON l.user_id = e.id 
     WHERE l.status = 'Pending' AND e.department = '$dept' AND e.position NOT LIKE '%HR Manager%'
     ORDER BY l.created_at DESC"
);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Content column -->
<div class="flex flex-col flex-1 min-w-0" id="main-content">

    <!-- TOP BAR -->
    <header
        class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                <?php echo htmlspecialchars($_SESSION['db_role'] ?? 'Supervisor'); ?> Dashboard</h1>
            <p class="text-xs text-slate-400 font-medium">Review leave requests for
                <strong><?php echo htmlspecialchars($dept); ?></strong> department
            </p>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_name']); ?>&background=3b82f6&color=fff&size=80"
                class="w-9 h-9 rounded-xl object-cover" alt="Avatar">
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-6 lg:p-8">

        <?php if ($msg): ?>
            <div id="status-alert"
                class="mb-6 p-4 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-semibold text-sm"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

            <!-- Pending -->
            <a href="sup_leave_history.php?status=Pending"
                class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow block group">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-11 h-11 bg-amber-50 rounded-xl flex items-center justify-center group-hover:bg-amber-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-amber-500 bg-amber-50 px-2 py-1 rounded-lg">Review</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $pending_count; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">Pending Review</p>
            </a>

            <!-- Approved -->
            <a href="sup_leave_history.php?status=Recommended"
                class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow block group">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center group-hover:bg-emerald-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-emerald-500 bg-emerald-50 px-2 py-1 rounded-lg">Approved</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $approved_total; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">Approved</p>
            </a>

            <!-- Rejected -->
            <a href="sup_leave_history.php?status=Rejected"
                class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow block group">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-11 h-11 bg-rose-50 rounded-xl flex items-center justify-center group-hover:bg-rose-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-rose-500 bg-rose-50 px-2 py-1 rounded-lg">Rejected</span>
                </div>
                <p class="text-3xl font-bold text-slate-800"><?php echo $rejected_count; ?></p>
                <p class="text-xs text-slate-400 font-medium mt-1">Rejected Requests</p>
            </a>

            <!-- On Leave Today -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-blue-500 bg-blue-50 px-2 py-1 rounded-lg">Today</span>
                </div>
                <div class="flex items-baseline justify-between">
                    <p class="text-3xl font-bold text-slate-800"><?php echo $on_leave_today_count; ?></p>
                    <?php if ($on_leave_today_count > 0): ?>
                        <div class="text-[10px] font-black text-blue-400 uppercase tracking-tighter">Active</div>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-400 font-medium mt-1">Dept. Employees On Leave</p>
                
                <?php if (!empty($on_leave_employees)): ?>
                    <div class="mt-4 pt-4 border-t border-slate-50 space-y-2">
                        <?php foreach($on_leave_employees as $name): ?>
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-blue-400"></div>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight"><?php echo $name; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- Pending Requests Table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div
                class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Department Leave Requests</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Manage leave applications for the
                        <?php echo htmlspecialchars($dept); ?> department
                    </p>
                </div>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" id="employeeSearch" placeholder="Search employee..."
                        class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none w-56">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Employee
                            </th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Leave
                                Type</th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Duration
                            </th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Reason
                            </th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Document
                            </th>
                            <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Action
                            </th>
                        </tr>
                    </thead>
                    <tbody id="leavesTableBody" class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($pending_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($pending_result)): ?>
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-sm border border-blue-100">
                                                <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-800 text-sm employee-name">
                                                    <?php echo $row['first_name'] . ' ' . $row['last_name']; ?>
                                                </p>
                                                <p class="text-[10px] text-slate-400 uppercase tracking-tight">
                                                    <?php echo $row['position']; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-[11px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase tracking-wide"><?php echo str_replace('_', ' ', $row['leave_type']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-semibold text-slate-700">
                                            <?php echo date('M d', strtotime($row['start_date'])) . ' – ' . date('M d', strtotime($row['end_date'])); ?>
                                        </p>
                                        <p class="text-[11px] text-slate-400 font-medium">
                                            <?php echo ($row['leave_type'] === 'half_day') ? '0.5' : $row['days']; ?> Days
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-slate-500 max-w-[200px] truncate"
                                            title="<?php echo htmlspecialchars($row['reason']); ?>">
                                            <?php echo htmlspecialchars($row['reason']); ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($row['document_path'])): ?>
                                            <a href="<?php echo $row['document_path']; ?>" target="_blank"
                                                class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                </svg>
                                                View Doc
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="admin_remark" placeholder="Note..."
                                                class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-400 outline-none w-32">
                                            <button type="submit" name="status" value="Approved"
                                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 shadow-sm shadow-emerald-200"
                                                title="Approve">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                                Approve
                                            </button>
                                            <button type="submit" name="status" value="Rejected"
                                                class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 shadow-sm shadow-rose-200"
                                                title="Reject">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3 text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-200" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p class="text-sm font-medium">No pending leave requests in your department.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

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