<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_set_cookie_params(2592000, '/');
    session_start();
}
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';

$msg = "";
$error = "";

// Handle Approve/Reject Actions
if (isset($_POST['action']) && isset($_POST['term_id'])) {
    $term_id = mysqli_real_escape_string($conn, $_POST['term_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']);
    $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
    
    $update_query = "UPDATE terminations SET status = '$new_status' WHERE id = '$term_id'";
    if (mysqli_query($conn, $update_query)) {
        $msg = "Termination record has been " . strtolower($new_status) . " successfully.";
        
        // Deactivate employee if approved
        if ($new_status === 'Approved') {
            $get_emp = "SELECT employee_id FROM terminations WHERE id = '$term_id'";
            $res = mysqli_query($conn, $get_emp);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $employee_id = $row['employee_id'];
                
                // Update employees table
                $deactivate_query = "UPDATE employees SET status = 'Inactive' WHERE id = '$employee_id'";
                mysqli_query($conn, $deactivate_query);
                
                // Also update system_users if they have an account
                $update_users = "UPDATE system_users SET is_active = 0 WHERE email = (SELECT email FROM employees WHERE id = '$employee_id')";
                mysqli_query($conn, $update_users);
            }
        }
    } else {
        $error = "Error updating record: " . mysqli_error($conn);
    }
}

// Fetch Stats
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM terminations WHERE status = 'Pending'"))['total'];
$approved_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM terminations WHERE status = 'Approved'"))['total'];
$rejected_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM terminations WHERE status = 'Rejected'"))['total'];

// Fetch All Terminations
$term_query = "SELECT t.*, e.first_name, e.last_name, e.department, e.position 
               FROM terminations t 
               JOIN employees e ON t.employee_id = e.id 
               ORDER BY t.created_at DESC";
$term_res = mysqli_query($conn, $term_query);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Termination Oversight</h1>
            <p class="text-xs text-slate-400 font-medium">Review and approve employee terminations</p>
        </div>
        <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
    </header>

    <main class="flex-1 p-6 lg:p-8 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="admin_dashboard.php" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            <!-- Title -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-slate-800">Termination Management</h1>
                <p class="text-sm text-slate-500 font-medium">Manage pending terminations and review history.</p>
            </div>

            <?php if ($msg): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-2xl flex items-center gap-3 animate-fade-in">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-semibold text-sm"><?php echo $msg; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl flex items-center gap-3 animate-fade-in">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-semibold text-sm"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- STATS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                <!-- Pending Card -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 bg-amber-50 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-500 bg-amber-50 px-2 py-1 rounded-lg">Pending</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-800"><?php echo $pending_count; ?></p>
                    <p class="text-xs text-slate-400 font-medium mt-1">Awaiting Approval</p>
                </div>

                <!-- Approved Card -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-500 bg-emerald-50 px-2 py-1 rounded-lg">Approved</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-800"><?php echo $approved_count; ?></p>
                    <p class="text-xs text-slate-400 font-medium mt-1">Finalized</p>
                </div>

                <!-- Rejected Card -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 bg-rose-50 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-500 bg-rose-50 px-2 py-1 rounded-lg">Rejected</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-800"><?php echo $rejected_count; ?></p>
                    <p class="text-xs text-slate-400 font-medium mt-1">Declined</p>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">Termination Requests</h2>
                    <p class="text-xs text-slate-400 mt-0.5">All termination records sorted by date</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (mysqli_num_rows($term_res) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($term_res)): ?>
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-sm border border-blue-100">
                                                <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-800 text-sm"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></p>
                                                <p class="text-[10px] text-slate-400 uppercase tracking-tight"><?php echo $row['position']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 font-medium"><?php echo $row['department']; ?></td>
                                    <td class="px-6 py-4">
                                        <?php $type_class = $row['type'] === 'Disciplinary' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'; ?>
                                        <span class="px-2.5 py-1 text-xs rounded-full font-semibold <?php echo $type_class; ?>">
                                            <?php echo $row['type']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 font-medium"><?php echo date('M d, Y', strtotime($row['termination_date'])); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($row['reason']); ?></td>
                                    <td class="px-6 py-4">
                                        <?php 
                                        $status = $row['status'];
                                        $status_class = "bg-amber-50 text-amber-500";
                                        if ($status === 'Approved') $status_class = "bg-emerald-50 text-emerald-500";
                                        if ($status === 'Rejected') $status_class = "bg-rose-50 text-rose-500";
                                        ?>
                                        <span class="text-[10px] font-bold uppercase <?php echo $status_class; ?> px-2.5 py-1 rounded-lg"><?php echo $status; ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($status === 'Pending'): ?>
                                            <form method="POST" class="flex items-center justify-center gap-2">
                                                <input type="hidden" name="term_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="action" value="approve" 
                                                    class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 shadow-sm shadow-emerald-200" title="Approve">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" 
                                                    class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 shadow-sm shadow-rose-200" title="Reject">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Reject
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-medium">No action needed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3 text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <p class="text-sm font-medium">No termination records found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
