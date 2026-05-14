<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php'; 
include 'includes/navbar.php'; 

// Fetch role-based allocations into an associative array (case-insensitive keys)
$allocations_map = [];
$alloc_res = mysqli_query($conn, "SELECT * FROM leave_allocations");
while ($a = mysqli_fetch_assoc($alloc_res)) {
    // Store with a normalized key (lowercase, no spaces/underscores)
    $norm_role = strtolower(str_replace([' ', '_', '-'], '', $a['role_name']));
    $allocations_map[$norm_role] = $a;
}

// No hardcoded defaults - values must come from leave_allocations table

// Fetch all employees
$employees_query = "SELECT * FROM employees ORDER BY first_name ASC";
$employees_result = mysqli_query($conn, $employees_query);

// Helper function to get approved leave days for an employee by type
function getUsedLeaveDays($conn, $employee_id, $type) {
    $query = "SELECT SUM(days) as total_used FROM leaves 
              WHERE user_id = '$employee_id' 
              AND leave_type = '$type' 
              AND status = 'Approved'";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data['total_used'] ? $data['total_used'] : 0;
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
                <h1 class="text-3xl font-bold text-slate-800">Manage Employees</h1>
                <p class="text-slate-500 mt-1">Monitor employee leave balances and personal details.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text" id="employeeSearch" placeholder="Search employees..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full md:w-64 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>
        <!-- Employees Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="employeeTable">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Department</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Annual Leave</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Casual Leave</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Sick Leave</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Half Day Leave</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (mysqli_num_rows($employees_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($employees_result)): 
                                $annual_used = getUsedLeaveDays($conn, $row['id'], 'annual');
                                $sick_used = getUsedLeaveDays($conn, $row['id'], 'sick');
                                $casual_used = getUsedLeaveDays($conn, $row['id'], 'casual');
                                $half_day_used = getUsedLeaveDays($conn, $row['id'], 'half_day');

                                $norm_pos = strtolower(str_replace([' ', '_', '-'], '', $row['position']));
                                $pos_alloc = $allocations_map[$norm_pos] ?? null;
                                
                                // Special case for common abbreviations if not matched
                                if (!$pos_alloc) {
                                    if ($norm_pos == 'traineese') $pos_alloc = $allocations_map['trineesoftwareengineer'] ?? null;
                                }
                                $TOTAL_ANNUAL = $pos_alloc['annual_limit'] ?? 0;
                                $TOTAL_CASUAL = $pos_alloc['casual_limit'] ?? 0;
                                $TOTAL_SICK = $TOTAL_CASUAL; // Shared with Casual
                                $TOTAL_HALF_DAY = $pos_alloc['half_day_limit'] ?? 0;

                                // Business Rules: Half Day reduces Annual (2=1), Sick reduces Casual
                                $annual_total_used = $annual_used + ($half_day_used / 2.0);
                                $casual_total_used = $casual_used + $sick_used;

                                $annual_rem = $TOTAL_ANNUAL - $annual_total_used;
                                $casual_rem = $TOTAL_CASUAL - $casual_total_used;
                                $sick_rem = $casual_rem; // Shared
                                
                                $TOTAL_HALF_DAY = $TOTAL_ANNUAL; // Shared
                                $half_day_rem = $annual_rem; // Shared
                            ?>
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold border-2 border-white shadow-sm uppercase">
                                            <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-800 text-sm"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></div>
                                            <div class="text-[10px] text-slate-400 font-medium uppercase tracking-tighter"><?php echo $row['position']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                                    <span class="text-xs font-medium text-slate-600"><?php echo $row['department']; ?></span>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-black <?php echo $annual_rem <= 2 ? 'text-rose-600' : 'text-slate-800'; ?>"><?php echo $annual_rem; ?></span>
                                            <span class="text-xs text-slate-300 font-bold">/</span>
                                            <span class="text-xs text-slate-500 font-bold"><?php echo $TOTAL_ANNUAL; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-black <?php echo $casual_rem <= 1 ? 'text-rose-600' : 'text-slate-800'; ?>"><?php echo $casual_rem; ?></span>
                                            <span class="text-xs text-slate-300 font-bold">/</span>
                                            <span class="text-xs text-slate-500 font-bold"><?php echo $TOTAL_CASUAL; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-black <?php echo $sick_rem <= 1 ? 'text-rose-600' : 'text-slate-800'; ?>"><?php echo $sick_rem; ?></span>
                                            <span class="text-xs text-slate-300 font-bold">/</span>
                                            <span class="text-xs text-slate-500 font-bold"><?php echo $TOTAL_CASUAL; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-black <?php echo $half_day_rem <= 1 ? 'text-rose-600' : 'text-slate-800'; ?>"><?php echo $half_day_rem; ?></span>
                                            <span class="text-xs text-slate-300 font-bold">/</span>
                                            <span class="text-xs text-slate-500 font-bold"><?php echo $TOTAL_HALF_DAY; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <?php 
                                        $statusClass = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                        if ($row['status'] == 'Inactive') $statusClass = 'bg-slate-100 text-slate-700 border-slate-200';
                                        if ($row['status'] == 'On Leave') $statusClass = 'bg-amber-100 text-amber-700 border-amber-200';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border <?php echo $statusClass; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-8 py-10 text-center text-slate-400 font-medium">No employees found.</td>
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
    document.getElementById('employeeSearch').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#employeeTable tbody tr');
        
        rows.forEach(row => {
            const employeeName = row.querySelector('.font-semibold').textContent.toLowerCase();
            const position = row.querySelector('.text-[10px]').textContent.toLowerCase();
            const department = row.cells[1].textContent.toLowerCase();
            
            if (employeeName.includes(searchTerm) || position.includes(searchTerm) || department.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>
