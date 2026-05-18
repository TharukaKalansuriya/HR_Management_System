<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Fetch role-based allocations into an associative array (case-insensitive keys)
$allocations_map = [];
$alloc_res = mysqli_query($conn, "SELECT * FROM leave_allocations");
while ($a = mysqli_fetch_assoc($alloc_res)) {
    // Store with a normalized key (lowercase, no spaces/underscores)
    $norm_role = strtolower(str_replace([' ', '_', '-'], '', $a['role_name']));
    $allocations_map[$norm_role] = $a;
}

// No hardcoded defaults - values must come from leave_allocations table

// Fetch employees - filtered by department for supervisors
$employees_query = "SELECT * FROM employees ORDER BY first_name ASC";
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'supervisor') {
    $dept = mysqli_real_escape_string($conn, $_SESSION['admin_dept'] ?? '');
    $employees_query = "SELECT * FROM employees WHERE department = '$dept' ORDER BY first_name ASC";
}
$employees_result = mysqli_query($conn, $employees_query);

function getUsedLeaveDays($conn, $employee_id, $type) {
    // Check if the employee is a supervisor
    $emp_query = mysqli_query($conn, "SELECT position FROM employees WHERE id = '$employee_id'");
    $emp_row = mysqli_fetch_assoc($emp_query);
    $position = $emp_row['position'] ?? '';
    
    if (stripos($position, 'Supervisor') !== false || stripos($position, 'HR Manager') !== false) {
        // For Supervisor and HR Manager roles: Only decrease leaves after both HR and Admin approved (status = 'Approved')
        $status_condition = "status = 'Approved'";
    } else {
        // For other employees: Decrease on HR_Approved or Approved
        $status_condition = "status IN ('HR_Approved', 'Approved')";
    }

    $query = "SELECT SUM(days) as total_used FROM leaves 
              WHERE user_id = '$employee_id' 
              AND leave_type = '$type' 
              AND $status_condition";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data['total_used'] ? $data['total_used'] : 0;
}

function formatDays($days) {
    if ($days == 0.5) return "1/2";
    if (floor($days) == $days) return $days;
    $int_part = floor($days);
    $dec_part = $days - $int_part;
    if ($dec_part == 0.5) {
        return ($int_part > 0 ? $int_part . " " : "") . "1/2";
    }
    return $days;
}
?>

<?php 
// Initialize departments based on role
$dept_groups = [];
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'supervisor') {
    $my_dept = $_SESSION['admin_dept'] ?? 'Unassigned';
    $dept_groups[$my_dept] = [];
} else {
    $all_depts_res = mysqli_query($conn, "SELECT DISTINCT department FROM department_roles ORDER BY department ASC");
    while ($d = mysqli_fetch_assoc($all_depts_res)) {
        $dept_groups[$d['department']] = [];
    }
}

// Group employees into these departments
if (mysqli_num_rows($employees_result) > 0) {
    while ($row = mysqli_fetch_assoc($employees_result)) {
        $dept = $row['department'] ?: 'Unassigned';
        if (!isset($dept_groups[$dept])) {
             // Case for employees in depts not in structure (shouldn't happen but safe)
             if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'supervisor') {
                $dept_groups[$dept] = [];
             } else {
                continue; // Skip if supervisor and not their dept
             }
        }
        $dept_groups[$dept][] = $row;
    }
}
ksort($dept_groups);
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
<header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Employee Directory</h1>
        <p class="text-xs text-slate-400 font-medium">Manage team members and leave balances by department</p>
    </div>
    <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
</header>
<main class="flex-1 p-6 lg:p-8 bg-slate-50">
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
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">Manage Employees</h1>
                <p class="text-slate-500 mt-1">Select a department to view and manage its members.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text" id="employeeSearch" placeholder="Search name or dept..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-64 transition-all shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Department Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <?php foreach ($dept_groups as $deptName => $members): ?>
            <div class="dept-card group cursor-pointer bg-white rounded-[32px] p-6 border border-slate-100 shadow-sm hover:shadow-xl hover:border-blue-100 transition-all duration-300" 
                 onclick="toggleDept('<?php echo md5($deptName); ?>')">
                <div class="flex items-center justify-between mb-6">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all duration-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <span class="px-3 py-1 bg-slate-50 text-slate-500 rounded-full text-[10px] font-bold uppercase tracking-widest"><?php echo count($members); ?> Members</span>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-1"><?php echo $deptName; ?></h3>
                <p class="text-xs text-slate-400 font-medium mb-4 italic">Click to view employee balances</p>
                
                <div class="flex -space-x-2 overflow-hidden">
                    <?php foreach (array_slice($members, 0, 5) as $m): ?>
                        <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white bg-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-600 uppercase">
                            <?php echo substr($m['first_name'], 0, 1) . substr($m['last_name'], 0, 1); ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($members) > 5): ?>
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 ring-2 ring-white">
                            +<?php echo count($members) - 5; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Detail Sections (Hidden by default) -->
        <?php foreach ($dept_groups as $deptName => $members): ?>
        <div id="dept-detail-<?php echo md5($deptName); ?>" class="dept-detail-section hidden space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button onclick="hideAllDepts()" class="p-2 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <h2 class="text-2xl font-bold text-slate-800"><?php echo $deptName; ?> <span class="text-slate-300 ml-2">/</span> <span class="text-sm font-medium text-slate-400 ml-2"><?php echo count($members); ?> Team Members</span></h2>
                </div>
            </div>

            <div class="bg-white rounded-[32px] shadow-sm border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Annual</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Casual</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Sick</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($members as $row): 
                                $annual_used = getUsedLeaveDays($conn, $row['id'], 'annual');
                                $sick_used = getUsedLeaveDays($conn, $row['id'], 'sick');
                                $casual_used = getUsedLeaveDays($conn, $row['id'], 'casual');
                                $half_day_used = getUsedLeaveDays($conn, $row['id'], 'half_day');

                                $norm_pos = strtolower(str_replace([' ', '_', '-'], '', $row['position']));
                                $pos_alloc = $allocations_map[$norm_pos] ?? null;
                                if (!$pos_alloc && $norm_pos == 'traineese') $pos_alloc = $allocations_map['trineesoftwareengineer'] ?? null;
                                
                                $TOTAL_ANNUAL = $pos_alloc['annual_limit'] ?? 0;
                                $TOTAL_CASUAL = $pos_alloc['casual_limit'] ?? 0;
                                
                                $annual_rem = $TOTAL_ANNUAL - ($annual_used + ($half_day_used / 2.0));
                                $casual_rem = $TOTAL_CASUAL - ($casual_used + $sick_used);
                            ?>
                            <tr class="employee-row hover:bg-slate-50/30 transition-colors">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xs border border-blue-100 uppercase">
                                            <?php echo substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm employee-name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></div>
                                            <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-tight"><?php echo $row['position']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <span class="text-sm font-black <?php echo $annual_rem <= 2 ? 'text-rose-500' : 'text-slate-800'; ?>"><?php echo formatDays(floor($annual_rem * 2) / 2); ?></span>
                                    <span class="text-[11px] text-slate-900 font-bold ml-1">/ <?php echo $TOTAL_ANNUAL; ?></span>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <span class="text-sm font-black <?php echo $casual_rem <= 1 ? 'text-rose-500' : 'text-slate-800'; ?>"><?php echo formatDays($casual_rem); ?></span>
                                    <span class="text-[11px] text-slate-900 font-bold ml-1">/ <?php echo $TOTAL_CASUAL; ?></span>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <span class="text-sm font-black <?php echo $casual_rem <= 1 ? 'text-rose-500' : 'text-slate-800'; ?>"><?php echo formatDays($casual_rem); ?></span>
                                    <span class="text-[11px] text-slate-900 font-bold ml-1">/ <?php echo $TOTAL_CASUAL; ?></span>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <?php 
                                        $statusClass = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                        if ($row['status'] == 'Inactive') $statusClass = 'bg-slate-100 text-slate-700 border-slate-200';
                                        if ($row['status'] == 'On Leave') $statusClass = 'bg-amber-100 text-amber-700 border-amber-200';
                                    ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border <?php echo $statusClass; ?>">
                                        <?php echo ($row['status'] == 'Inactive') ? 'Deactivate' : $row['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function toggleDept(id) {
        const grid = document.querySelector('.grid');
        const sections = document.querySelectorAll('.dept-detail-section');
        
        sections.forEach(s => s.classList.add('hidden'));
        grid.classList.add('hidden');
        
        const target = document.getElementById('dept-detail-' + id);
        if (target) target.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideAllDepts() {
        const grid = document.querySelector('.grid');
        const sections = document.querySelectorAll('.dept-detail-section');
        
        sections.forEach(s => s.classList.add('hidden'));
        grid.classList.remove('hidden');
    }

    // Search functionality - filters both cards and visible employee rows
    document.getElementById('employeeSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.dept-card');
        const rows = document.querySelectorAll('.employee-row');
        
        // Filter cards
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(q) ? '' : 'none';
        });

        // Filter rows in active detail view
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    });
</script>
