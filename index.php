<?php
require_once 'auth_check.php';
$required_page = 'index.php';
require_once 'db_config.php';
$page_title = "Dashboard Overview";

// ── Real Stats ────────────────────────────────────────────────────────────────
$totalEmployees   = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmployees  = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Active'")->fetchColumn();
$inactiveEmployees= $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Inactive'")->fetchColumn();
$onLeaveEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='On Leave'")->fetchColumn();

// Open vacancies
$openVacancies = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status='Active'")->fetchColumn();

// Pending leave requests
$pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status='Pending'")->fetchColumn();

// Today's attendance
$today = date('Y-m-d');
$presentToday = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='Present'");
$presentToday->execute([$today]);
$presentToday = $presentToday->fetchColumn();

// Upcoming birthdays (next 30 days)
$birthdayStmt = $pdo->prepare("
    SELECT first_name, last_name, full_name, date_of_birth, department, designation, position
    FROM employees
    WHERE date_of_birth IS NOT NULL AND status='Active'
    AND DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d')
        AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '%m-%d')
    ORDER BY DATE_FORMAT(date_of_birth, '%m-%d')
    LIMIT 8
");
$birthdayStmt->execute();
$birthdays = $birthdayStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent leave requests
$leaveStmt = $pdo->prepare("
    SELECT l.*, e.first_name, e.last_name, e.full_name, e.department, e.designation, e.position
    FROM leaves l
    JOIN employees e ON l.user_id = e.id
    ORDER BY l.created_at DESC
    LIMIT 6
");
$leaveStmt->execute();
$recentLeaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// Department breakdown
$deptStmt = $pdo->query("
    SELECT department, COUNT(*) as cnt
    FROM employees
    WHERE department != '' AND department IS NOT NULL
    GROUP BY department
    ORDER BY cnt DESC
    LIMIT 6
");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent employees
$recentEmpStmt = $pdo->query("
    SELECT first_name, last_name, full_name, designation, position, department, status, created_at
    FROM employees ORDER BY created_at DESC LIMIT 5
");
$recentEmployees = $recentEmpStmt->fetchAll(PDO::FETCH_ASSOC);

// New applicants this month
$newApplicants = $pdo->query("SELECT COUNT(*) FROM applicants WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();

include 'header.php';
include 'sidebar.php';
?>

<style>
  .dash-stat-card { transition: all 0.3s ease; }
  .dash-stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px -6px rgba(0,0,0,0.12); }
  .birthday-card { transition: all 0.25s ease; }
  .birthday-card:hover { transform: translateY(-2px); }
  .quick-action { transition: all 0.25s ease; }
  .quick-action:hover { transform: translateY(-3px); box-shadow: 0 8px 20px -4px rgba(0,0,0,0.15); }
  @keyframes pulse-ring { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.5);opacity:0} }
  .pulse-dot::before { content:''; position:absolute; inset:0; border-radius:50%; background:inherit; animation: pulse-ring 1.5s ease-out infinite; }
  .dept-bar { transition: width 0.8s ease; }
  .scroll-hidden::-webkit-scrollbar { display:none; }
</style>

<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-6 lg:p-8">

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-brand-900 via-brand-600 to-blue-500 rounded-2xl p-6 mb-8 text-white shadow-lg relative overflow-hidden">
            <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 70% 50%, white 0%, transparent 60%)"></div>
            <div class="relative z-10 flex items-center justify-between flex-wrap gap-4">
                <div>
                    <p class="text-blue-100 text-sm font-medium mb-1">Good <?php echo (date('H')<12)?'Morning':((date('H')<17)?'Afternoon':'Evening'); ?> 👋</p>
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></h2>
                    <p class="text-blue-200 text-sm mt-1"><?php echo date('l, d F Y'); ?> &nbsp;·&nbsp; Here's your HRMS overview</p>
                </div>
                <div class="flex gap-3">
                    <a href="employee_add.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/30 text-white text-sm font-semibold py-2.5 px-5 rounded-xl transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Add Employee
                    </a>
                    <a href="reports.php" class="bg-white text-brand-900 text-sm font-semibold py-2.5 px-5 rounded-xl hover:bg-blue-50 transition-all flex items-center gap-2 shadow">
                        <i class="fa-solid fa-chart-line"></i> Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Total Employees -->
            <a href="employees.php" class="dash-stat-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4 group">
                <div class="p-3.5 rounded-xl bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Employees</p>
                    <p class="text-3xl font-bold text-gray-800 leading-tight"><?php echo $totalEmployees; ?></p>
                    <p class="text-xs text-emerald-600 font-medium mt-0.5"><i class="fa-solid fa-circle-check mr-1"></i><?php echo $activeEmployees; ?> Active</p>
                </div>
            </a>

            <!-- Present Today -->
            <a href="attendance.php" class="dash-stat-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4 group">
                <div class="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-user-check text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Present Today</p>
                    <p class="text-3xl font-bold text-gray-800 leading-tight"><?php echo $presentToday; ?></p>
                    <p class="text-xs text-amber-600 font-medium mt-0.5"><i class="fa-solid fa-user-clock mr-1"></i><?php echo $onLeaveEmployees; ?> On Leave</p>
                </div>
            </a>

            <!-- Open Vacancies -->
            <a href="job_postings.php" class="dash-stat-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4 group">
                <div class="p-3.5 rounded-xl bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Open Vacancies</p>
                    <p class="text-3xl font-bold text-gray-800 leading-tight"><?php echo $openVacancies; ?></p>
                    <p class="text-xs text-purple-600 font-medium mt-0.5"><i class="fa-solid fa-user-plus mr-1"></i><?php echo $newApplicants; ?> New Applicants</p>
                </div>
            </a>

            <!-- Pending Leaves -->
            <a href="leave/admin_dashboard.php" class="dash-stat-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4 group">
                <div class="p-3.5 rounded-xl bg-orange-50 text-orange-600 group-hover:bg-orange-600 group-hover:text-white transition-colors">
                    <i class="fa-solid fa-calendar-xmark text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Pending Leaves</p>
                    <p class="text-3xl font-bold text-gray-800 leading-tight"><?php echo $pendingLeaves; ?></p>
                    <p class="text-xs text-orange-600 font-medium mt-0.5"><i class="fa-solid fa-clock mr-1"></i>Awaiting approval</p>
                </div>
            </a>
        </div>

        <!-- Main Grid: Left 2/3, Right 1/3 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- Recent Leave Requests (spans 2 cols) -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-bold text-gray-800">Recent Leave Requests</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Latest employee leave submissions</p>
                    </div>
                    <a href="leave/admin_dashboard.php" class="text-xs bg-brand-50 text-brand-600 hover:bg-brand-100 font-semibold px-3 py-1.5 rounded-lg transition-colors">View All →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-400 uppercase bg-gray-50/60 border-b border-gray-100">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Employee</th>
                                <th class="px-5 py-3 font-semibold">Type</th>
                                <th class="px-5 py-3 font-semibold">Duration</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (count($recentLeaves) > 0): ?>
                                <?php foreach ($recentLeaves as $lv):
                                    $empName = !empty($lv['full_name']) ? $lv['full_name'] : ($lv['first_name'].' '.$lv['last_name']);
                                    $dept = $lv['department'] ?: ($lv['designation'] ?: $lv['position'] ?: 'Staff');
                                    $badge = match($lv['status']) {
                                        'Pending'     => 'bg-amber-100 text-amber-700',
                                        'Approved','HR_Approved' => 'bg-emerald-100 text-emerald-700',
                                        'Rejected'    => 'bg-red-100 text-red-700',
                                        default       => 'bg-gray-100 text-gray-600'
                                    };
                                    $statusLabel = $lv['status'] === 'HR_Approved' ? 'HR Approved' : $lv['status'];
                                ?>
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-brand-50 border border-brand-100 flex items-center justify-center text-brand-700 text-xs font-bold flex-shrink-0">
                                                <?php echo strtoupper(substr($lv['first_name'],0,1).substr($lv['last_name'],0,1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800 text-xs"><?php echo htmlspecialchars($empName); ?></p>
                                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($dept); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-600 text-xs font-medium"><?php echo htmlspecialchars($lv['leave_type']); ?></td>
                                    <td class="px-5 py-3.5 text-gray-500 text-xs">
                                        <?php echo date('M d', strtotime($lv['start_date'])); ?>
                                        <?php if ($lv['start_date'] !== $lv['end_date']): ?> – <?php echo date('M d', strtotime($lv['end_date'])); ?><?php endif; ?>
                                        <span class="ml-1 text-gray-400">(<?php echo $lv['days']; ?>d)</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badge; ?>"><?php echo $statusLabel; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 text-sm">
                                    <i class="fa-solid fa-calendar-check text-2xl mb-2 block"></i>No leave requests found
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-base font-bold text-gray-800 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-3">
                    <a href="employee_add.php" class="quick-action flex flex-col items-center justify-center p-4 bg-blue-50 rounded-xl hover:bg-blue-600 hover:text-white group text-center">
                        <i class="fa-solid fa-user-plus text-blue-600 group-hover:text-white text-xl mb-2 transition-colors"></i>
                        <span class="text-xs font-semibold text-blue-800 group-hover:text-white transition-colors">Add Employee</span>
                    </a>
                    <a href="payroll_run.php" class="quick-action flex flex-col items-center justify-center p-4 bg-emerald-50 rounded-xl hover:bg-emerald-600 hover:text-white group text-center">
                        <i class="fa-solid fa-file-invoice-dollar text-emerald-600 group-hover:text-white text-xl mb-2 transition-colors"></i>
                        <span class="text-xs font-semibold text-emerald-800 group-hover:text-white transition-colors">Run Payroll</span>
                    </a>
                    <a href="job_postings.php" class="quick-action flex flex-col items-center justify-center p-4 bg-purple-50 rounded-xl hover:bg-purple-600 hover:text-white group text-center">
                        <i class="fa-solid fa-bullhorn text-purple-600 group-hover:text-white text-xl mb-2 transition-colors"></i>
                        <span class="text-xs font-semibold text-purple-800 group-hover:text-white transition-colors">Post Job</span>
                    </a>
                    <a href="attendance.php" class="quick-action flex flex-col items-center justify-center p-4 bg-cyan-50 rounded-xl hover:bg-cyan-600 hover:text-white group text-center">
                        <i class="fa-solid fa-clock text-cyan-600 group-hover:text-white text-xl mb-2 transition-colors"></i>
                        <span class="text-xs font-semibold text-cyan-800 group-hover:text-white transition-colors">Attendance</span>
                    </a>
                    <a href="reports.php" class="quick-action flex flex-col items-center justify-center p-4 bg-orange-50 rounded-xl hover:bg-orange-600 hover:text-white group text-center">
                        <i class="fa-solid fa-chart-pie text-orange-600 group-hover:text-white text-xl mb-2 transition-colors"></i>
                        <span class="text-xs font-semibold text-orange-800 group-hover:text-white transition-colors">Reports</span>
                    </a>
                    <a href="documents.php" class="quick-action flex flex-col items-center justify-center p-4 bg-rose-50 rounded-xl hover:bg-rose-600 hover:text-white group text-center">
                        <i class="fa-solid fa-folder-open text-rose-600 group-hover:text-white text-xl mb-2 transition-colors"></i>
                        <span class="text-xs font-semibold text-rose-800 group-hover:text-white transition-colors">Documents</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Upcoming Birthdays -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h2 class="text-base font-bold text-gray-800">🎂 Upcoming Birthdays</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Next 30 days</p>
                    </div>
                    <a href="employees.php" class="text-xs text-brand-600 hover:underline font-medium">View All</a>
                </div>
                <div class="space-y-3 max-h-72 overflow-y-auto scroll-hidden">
                    <?php if (count($birthdays) > 0): ?>
                        <?php foreach ($birthdays as $b):
                            $bName = !empty($b['full_name']) ? $b['full_name'] : ($b['first_name'].' '.$b['last_name']);
                            $bDate = date('d M', strtotime($b['date_of_birth']));
                            $isToday = (date('m-d', strtotime($b['date_of_birth'])) === date('m-d'));
                            $role = $b['designation'] ?: $b['position'] ?: $b['department'] ?: 'Employee';
                        ?>
                        <div class="birthday-card flex items-center gap-3 p-3 rounded-xl <?php echo $isToday ? 'bg-amber-50 border border-amber-200' : 'hover:bg-gray-50'; ?>">
                            <div class="h-9 w-9 rounded-full <?php echo $isToday ? 'bg-amber-400 text-white' : 'bg-brand-50 text-brand-700'; ?> border <?php echo $isToday ? 'border-amber-300' : 'border-brand-100'; ?> flex items-center justify-center text-xs font-bold flex-shrink-0">
                                <?php echo strtoupper(substr($b['first_name'],0,1).substr($b['last_name'],0,1)); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($bName); ?></p>
                                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($role); ?></p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-xs font-bold <?php echo $isToday ? 'text-amber-600' : 'text-brand-600'; ?>"><?php echo $bDate; ?></p>
                                <?php if ($isToday): ?>
                                    <p class="text-xs text-amber-500 font-semibold">Today! 🎉</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <i class="fa-solid fa-cake-candles text-3xl mb-2 block text-gray-300"></i>
                            <p class="text-sm">No birthdays in the next 30 days</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Department Breakdown -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h2 class="text-base font-bold text-gray-800">Department Breakdown</h2>
                        <p class="text-xs text-gray-400 mt-0.5"><?php echo $totalEmployees; ?> employees total</p>
                    </div>
                    <a href="employees.php" class="text-xs text-brand-600 hover:underline font-medium">View All</a>
                </div>
                <?php if (count($departments) > 0): ?>
                    <?php
                    $deptColors = ['bg-blue-500','bg-purple-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-cyan-500'];
                    $maxDept = max(array_column($departments, 'cnt'));
                    foreach ($departments as $i => $dept):
                        $pct = $maxDept > 0 ? round(($dept['cnt']/$maxDept)*100) : 0;
                        $color = $deptColors[$i % count($deptColors)];
                    ?>
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-xs font-semibold text-gray-700 truncate max-w-[60%]"><?php echo htmlspecialchars($dept['department']); ?></span>
                            <span class="text-xs font-bold text-gray-500"><?php echo $dept['cnt']; ?> emp</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="dept-bar h-full <?php echo $color; ?> rounded-full" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-400 text-sm">
                        <i class="fa-solid fa-building text-3xl mb-2 block text-gray-300"></i>
                        No department data yet
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recently Added Employees -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h2 class="text-base font-bold text-gray-800">New Joiners</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Recently registered employees</p>
                    </div>
                    <a href="employees.php" class="text-xs text-brand-600 hover:underline font-medium">View All</a>
                </div>
                <div class="space-y-3">
                    <?php if (count($recentEmployees) > 0): ?>
                        <?php foreach ($recentEmployees as $emp):
                            $eName = !empty($emp['full_name']) ? $emp['full_name'] : ($emp['first_name'].' '.$emp['last_name']);
                            $eRole = $emp['designation'] ?: $emp['position'] ?: 'Staff';
                            $statusClr = $emp['status'] === 'Active' ? 'bg-emerald-100 text-emerald-700' : ($emp['status'] === 'On Leave' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600');
                        ?>
                        <div class="flex items-center gap-3 group">
                            <div class="h-9 w-9 rounded-xl bg-brand-50 border border-brand-100 flex items-center justify-center text-brand-700 text-xs font-bold flex-shrink-0">
                                <?php echo strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1)); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($eName); ?></p>
                                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($eRole); ?> · <?php echo htmlspecialchars($emp['department'] ?: 'Unassigned'); ?></p>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $statusClr; ?> flex-shrink-0"><?php echo $emp['status']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400 text-sm">
                            <i class="fa-solid fa-user-plus text-3xl mb-2 block text-gray-300"></i>
                            No employees registered yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>
</body>
</html>
