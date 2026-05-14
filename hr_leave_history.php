<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php'; 
include 'includes/navbar.php'; 

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$query = "SELECT l.*, e.first_name, e.last_name, e.department 
          FROM leaves l 
          JOIN employees e ON l.user_id = e.id 
          WHERE 1=1";

if ($status_filter !== 'all') {
    $query .= " AND l.status = '$status_filter'";
}

if (!empty($search_query)) {
    $query .= " AND (e.first_name LIKE '%$search_query%' OR e.last_name LIKE '%$search_query%' OR l.leave_type LIKE '%$search_query%')";
}

$query .= " ORDER BY l.created_at DESC";
$result = mysqli_query($conn, $query);

// Helper function for status display
function getStatusDisplay($status) {
    $status = strtolower($status);
    if ($status == 'approved') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                    Approved
                </span>';
    } elseif ($status == 'rejected') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700 border border-red-200">
                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-2"></span>
                    Rejected
                </span>';
    } elseif ($status == 'hr_approved') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-700 border border-blue-200">
                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                    HR Approved
                </span>';
    } else {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200">
                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-2"></span>
                    Pending
                </span>';
    }
}
?>

<main class="flex-grow p-6 md:p-10 bg-slate-50">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
            <div>
                <?php $dash_link = ($_SESSION['admin_role'] == 'super_admin') ? "admin_dashboard.php" : "hr_dashboard.php"; ?>
                <a href="<?php echo $dash_link; ?>" class="inline-flex items-center text-sm font-semibold text-slate-400 hover:text-blue-600 transition-colors mb-4 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="text-3xl font-bold text-slate-800">Master Leave History</h1>
                <p class="text-slate-500 mt-1">Review all employee leave applications across the organization.</p>
            </div>
            
            <form action="hr_leave_history.php" method="GET" class="flex items-center gap-3">
                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search employee or type..." 
                        class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all w-full md:w-64">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="submit" class="bg-blue-600 text-white p-2.5 rounded-xl hover:bg-blue-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </form>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-8">
            <a href="hr_leave_history.php?status=all&search=<?php echo $search_query; ?>" 
                class="px-5 py-2 rounded-xl text-sm font-semibold transition-all <?php echo $status_filter === 'all' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                All Records
            </a>
            <a href="hr_leave_history.php?status=Approved&search=<?php echo $search_query; ?>" 
                class="px-5 py-2 rounded-xl text-sm font-semibold transition-all <?php echo $status_filter === 'Approved' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                Approved
            </a>
            <a href="hr_leave_history.php?status=Pending&search=<?php echo $search_query; ?>" 
                class="px-5 py-2 rounded-xl text-sm font-semibold transition-all <?php echo $status_filter === 'Pending' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                Pending
            </a>
            <a href="hr_leave_history.php?status=Rejected&search=<?php echo $search_query; ?>" 
                class="px-5 py-2 rounded-xl text-sm font-semibold transition-all <?php echo $status_filter === 'Rejected' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                Rejected
            </a>
        </div>

        <!-- History Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Type</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Applied Date</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Documents</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Admin Remark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xs border border-white shadow-sm">
                                            <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-800 text-sm"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></div>
                                            <div class="text-[10px] text-slate-400 font-medium uppercase tracking-wider"><?php echo $row['department']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase tracking-wider"><?php echo $row['leave_type']; ?></span>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="text-sm text-slate-600 font-semibold"><?php echo date('M d', strtotime($row['start_date'])) . ' - ' . date('M d', strtotime($row['end_date'])); ?></div>
                                    <div class="text-xs font-bold text-slate-400"><?php echo $row['days']; ?> Days</div>
                                </td>
                                <td class="px-8 py-5">
                                    <?php echo getStatusDisplay($row['status']); ?>
                                </td>
                                <td class="px-8 py-5 text-sm text-slate-500">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($row['document_path'])): ?>
                                            <a href="<?php echo $row['document_path']; ?>" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="View Document">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 font-bold ml-2">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-sm text-slate-500">
                                    <p class="truncate max-w-[200px] italic" title="<?php echo $row['admin_remark']; ?>">
                                        <?php echo !empty($row['admin_remark']) ? htmlspecialchars($row['admin_remark']) : '<span class="text-slate-300">Pending Decision</span>'; ?>
                                    </p>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                        </svg>
                                        <p class="font-medium">No leave records found matching your criteria.</p>
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

<?php include 'includes/footer.php'; ?>
