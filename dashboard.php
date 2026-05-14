<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    die;
}

include 'includes/dbconnection.php';
include 'includes/header.php'; 
include 'includes/navbar.php'; 

// Fetch user's recent leave requests
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM leaves WHERE user_id = '$user_id' AND status IN ('Pending', 'HR_Approved') ORDER BY created_at DESC LIMIT 5";
$result = mysqli_query($conn, $query);

// Fetch all leave allocations to find a match flexibly
$user_position = $_SESSION['position'];
$norm_user_pos = strtolower(str_replace([' ', '_', '-'], '', $user_position));
$allocation = null;

$all_alloc_res = mysqli_query($conn, "SELECT * FROM leave_allocations");
while ($a = mysqli_fetch_assoc($all_alloc_res)) {
    $norm_role = strtolower(str_replace([' ', '_', '-'], '', $a['role_name']));
    if ($norm_role === $norm_user_pos) {
        $allocation = $a;
        break;
    }
}

// Special case for common abbreviations if still no match
if (!$allocation && $norm_user_pos == 'traineese') {
    mysqli_data_seek($all_alloc_res, 0);
    while ($a = mysqli_fetch_assoc($all_alloc_res)) {
        if (strtolower(str_replace([' ', '_', '-'], '', $a['role_name'])) === 'trineesoftwareengineer') {
            $allocation = $a;
            break;
        }
    }
}

// Fallback to defaults if no allocation found
$TOTAL_ANNUAL = $allocation['annual_limit'] ?? 14;
$TOTAL_CASUAL = $allocation['casual_limit'] ?? 7;
$TOTAL_SICK = $allocation['half_day_limit'] ?? 7; // Mapping sick leave to half_day_limit or similar if sick is not separate

// Function to get used leave days
function getUsedDays($conn, $user_id, $type) {
    $q = "SELECT SUM(days) as total FROM leaves WHERE user_id = '$user_id' AND leave_type = '$type' AND status = 'Approved'";
    $res = mysqli_query($conn, $q);
    $data = mysqli_fetch_assoc($res);
    return $data['total'] ? $data['total'] : 0;
}

$annual_used = getUsedDays($conn, $user_id, 'annual');
$sick_used = getUsedDays($conn, $user_id, 'sick');
$casual_only_used = getUsedDays($conn, $user_id, 'casual');
$half_day_used = getUsedDays($conn, $user_id, 'half_day');

// Business Rule: Two half-days = 1 full day, deducted from Annual Leave
$half_day_equivalent = $half_day_used / 2.0;
$annual_total_used = $annual_used + $half_day_equivalent;
$casual_total_used = $casual_only_used + $sick_used; // Sick leaves reduce the casual leave balance

$annual_rem = $TOTAL_ANNUAL - $annual_total_used;
$casual_rem = $TOTAL_CASUAL - $casual_total_used;
$sick_rem = $casual_rem; // Sick leave is shared with the Casual balance
$total_rem = $annual_rem + $casual_rem;

// Fetch pending count (Pending or HR Approved)
$pending_q = "SELECT COUNT(*) as total FROM leaves WHERE user_id = '$user_id' AND (status = 'Pending' OR status = 'HR_Approved')";
$pending_res = mysqli_query($conn, $pending_q);
$pending_count = mysqli_fetch_assoc($pending_res)['total'];
?>

<main class="flex-grow p-6 md:p-10 bg-slate-50">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Welcome back, <?php echo explode(' ', $_SESSION['user_name'])[0]; ?>! 👋</h1>
                <p class="text-slate-500 mt-1">Here's what's happening with your leave status.</p>
            </div>
            <a href="apply_leave.php" 
                class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-blue-100 transition-all transform hover:-translate-y-1 active:scale-95 gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Apply for Leave
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- Total Section -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 transition-all hover:shadow-md">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-4 text-xl font-bold"><?php echo str_pad(max(0, $total_rem), 2, '0', STR_PAD_LEFT); ?></div>
                <h3 class="text-slate-500 text-sm font-medium">Total Balance</h3>
                <p class="text-2xl font-bold text-slate-800">Days Left</p>
            </div>
            <!-- Annual Section -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 transition-all hover:shadow-md">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl font-bold"><?php echo str_pad(max(0, $annual_rem), 2, '0', STR_PAD_LEFT); ?></div>
                    <span class="text-slate-300 font-bold text-xl">/</span>
                    <span class="text-slate-400 font-bold text-lg"><?php echo str_pad($TOTAL_ANNUAL, 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Annual Leave</h3>
            </div>
            <!-- Casual Section -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 transition-all hover:shadow-md">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl font-bold"><?php echo str_pad(max(0, $casual_rem), 2, '0', STR_PAD_LEFT); ?></div>
                    <span class="text-slate-300 font-bold text-xl">/</span>
                    <span class="text-slate-400 font-bold text-lg"><?php echo str_pad($TOTAL_CASUAL, 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Casual Leave</h3>
            </div>
            <!-- Sick Section -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 transition-all hover:shadow-md">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl font-bold"><?php echo str_pad(max(0, $sick_rem), 2, '0', STR_PAD_LEFT); ?></div>
                    <span class="text-slate-300 font-bold text-xl">/</span>
                    <span class="text-slate-400 font-bold text-lg"><?php echo str_pad($TOTAL_CASUAL, 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Sick Leave</h3>
            </div>
        </div>

        <!-- Recent Requests Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">Recent Leave Requests</h2>
                
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Type</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Applied Date</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Document</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr class="hover:bg-slate-50/30 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="font-semibold text-slate-800 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $row['leave_type'])); ?> Leave</div>
                                        <div class="text-xs text-slate-400 max-w-[150px] truncate" title="<?php echo htmlspecialchars($row['reason']); ?>"><?php echo htmlspecialchars($row['reason']); ?></div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="text-sm text-slate-600"><?php echo date('M d', strtotime($row['start_date'])) . ' - ' . date('M d', strtotime($row['end_date'])); ?></div>
                                        <div class="text-xs font-medium text-blue-500"><?php echo htmlspecialchars(($row['leave_type'] === 'half_day') ? ($row['days'] == 1 ? '1/2' : ($row['days'] * 0.5)) : $row['days']); ?> Days</div>
                                    </td>
                                    <td class="px-8 py-5 text-sm text-slate-600"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td class="px-8 py-5">
                                        <?php if (!empty($row['document_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['document_path']); ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-300 font-bold uppercase tracking-widest">No Doc</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-5">
                                        <?php 
                                            $statusClass = '';
                                            $displayStatus = $row['status'];
                                            if ($row['status'] == 'Pending') $statusClass = 'bg-amber-100 text-amber-700 border-amber-200';
                                            else if ($row['status'] == 'HR_Approved') {
                                                $statusClass = 'bg-blue-100 text-blue-700 border-blue-200';
                                                $displayStatus = 'HR Approved';
                                            }
                                            else if ($row['status'] == 'Approved') $statusClass = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                            else if ($row['status'] == 'Rejected') $statusClass = 'bg-red-100 text-red-700 border-red-200';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border <?php echo $statusClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-8 py-6 text-center text-slate-500">No recent leave requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
