<?php
require_once 'auth_check.php';
$required_page = 'reports.php';
require_once 'db_config.php';
$page_title = "Reporting & Analysis";

// ── KPI Queries ──
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Active'")->fetchColumn();
$curMonth = (int)date('m');
$curYear  = (int)date('Y');

$attStmt = $pdo->prepare("SELECT COUNT(CASE WHEN status IN ('Present','Late','Half Day') THEN 1 END) as present, COUNT(*) as total FROM attendance WHERE MONTH(attendance_date)=? AND YEAR(attendance_date)=?");
$attStmt->execute([$curMonth, $curYear]);
$attRow = $attStmt->fetch(PDO::FETCH_ASSOC);
$attendanceRate = ($attRow['total'] > 0) ? round(($attRow['present'] / $attRow['total']) * 100, 1) : 0;

$leaveToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='On Leave'")->fetchColumn();

$payStmt = $pdo->prepare("SELECT COALESCE(SUM(net_salary),0) FROM payroll_runs WHERE pay_month=? AND pay_year=?");
$payStmt->execute([$curMonth, $curYear]);
$totalPayroll = $payStmt->fetchColumn();

$otStmt = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours),0) FROM attendance WHERE MONTH(attendance_date)=? AND YEAR(attendance_date)=?");
$otStmt->execute([$curMonth, $curYear]);
$totalOT = $otStmt->fetchColumn();

$deptStmt = $pdo->query("SELECT department, COUNT(*) as cnt FROM employees WHERE status='Active' AND department!='' GROUP BY department ORDER BY cnt DESC LIMIT 8");
$deptData = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
$deptLabels = json_encode(array_column($deptData, 'department'));
$deptCounts = json_encode(array_column($deptData, 'cnt'));

$trendLabels = []; $trendRates = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (int)date('m', strtotime("-$i months")); $y = (int)date('Y', strtotime("-$i months"));
    $trendLabels[] = date('M Y', strtotime("-$i months"));
    $tStmt = $pdo->prepare("SELECT COUNT(CASE WHEN status IN ('Present','Late','Half Day') THEN 1 END) as present, COUNT(*) as total FROM attendance WHERE MONTH(attendance_date)=? AND YEAR(attendance_date)=?");
    $tStmt->execute([$m, $y]);
    $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
    $trendRates[] = ($tRow['total'] > 0) ? round(($tRow['present'] / $tRow['total']) * 100, 1) : 0;
}
$turnoverCount = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Inactive'")->fetchColumn();

include 'header.php'; include 'sidebar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto custom-scrollbar">
<!-- Hero -->
<div class="relative overflow-hidden">
<div class="absolute inset-0 bg-gradient-to-br from-brand-900 via-blue-800 to-indigo-900"></div>
<div class="absolute inset-0 opacity-10"><div class="absolute top-0 right-0 w-96 h-96 bg-white rounded-full -translate-y-1/2 translate-x-1/3"></div></div>
<div class="relative px-8 py-10">
<div class="flex items-center gap-4 mb-2">
<div class="w-14 h-14 bg-white/15 backdrop-blur-sm rounded-2xl flex items-center justify-center border border-white/20"><i class="fa-solid fa-chart-line text-2xl text-white"></i></div>
<div><h1 class="text-3xl font-bold text-white">Reporting & Analysis</h1><p class="text-blue-200 text-sm mt-1">Generate real-time reports and download as PDF</p></div>
</div></div></div>

<div class="p-8">
<!-- KPI Strip -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-5 mb-8 -mt-12 relative z-10">
<div class="bg-white rounded-2xl p-5 shadow-lg border border-gray-100 flex items-center group hover:-translate-y-1 transition-all duration-300">
<div class="p-3.5 rounded-xl bg-blue-500/10 text-blue-600 mr-4 group-hover:bg-blue-500 group-hover:text-white transition-colors"><i class="fa-solid fa-users text-lg"></i></div>
<div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Employees</p><p class="text-2xl font-bold text-gray-800"><?= number_format($totalEmployees) ?></p></div></div>

<div class="bg-white rounded-2xl p-5 shadow-lg border border-gray-100 flex items-center group hover:-translate-y-1 transition-all duration-300">
<div class="p-3.5 rounded-xl bg-emerald-500/10 text-emerald-600 mr-4 group-hover:bg-emerald-500 group-hover:text-white transition-colors"><i class="fa-solid fa-clipboard-check text-lg"></i></div>
<div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Attendance Rate</p><p class="text-2xl font-bold text-gray-800"><?= $attendanceRate ?>%</p></div></div>

<div class="bg-white rounded-2xl p-5 shadow-lg border border-gray-100 flex items-center group hover:-translate-y-1 transition-all duration-300">
<div class="p-3.5 rounded-xl bg-orange-500/10 text-orange-600 mr-4 group-hover:bg-orange-500 group-hover:text-white transition-colors"><i class="fa-solid fa-calendar-xmark text-lg"></i></div>
<div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">On Leave Today</p><p class="text-2xl font-bold text-gray-800"><?= $leaveToday ?></p></div></div>

<div class="bg-white rounded-2xl p-5 shadow-lg border border-gray-100 flex items-center group hover:-translate-y-1 transition-all duration-300">
<div class="p-3.5 rounded-xl bg-purple-500/10 text-purple-600 mr-4 group-hover:bg-purple-500 group-hover:text-white transition-colors"><i class="fa-solid fa-money-bill-trend-up text-lg"></i></div>
<div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Payroll Cost</p><p class="text-xl font-bold text-gray-800">Rs. <?= number_format($totalPayroll,0) ?></p></div></div>

<div class="bg-white rounded-2xl p-5 shadow-lg border border-gray-100 flex items-center group hover:-translate-y-1 transition-all duration-300">
<div class="p-3.5 rounded-xl bg-teal-500/10 text-teal-600 mr-4 group-hover:bg-teal-500 group-hover:text-white transition-colors"><i class="fa-solid fa-clock text-lg"></i></div>
<div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">OT Hours</p><p class="text-2xl font-bold text-gray-800"><?= number_format($totalOT,1) ?></p></div></div>
</div>

<!-- Report Cards -->
<h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2"><i class="fa-solid fa-file-lines text-brand-500"></i> Available Reports</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
<?php
$reports = [
    ['Head Count Report','Employee distribution by department, gender, employment type, and status.','report_headcount.php','blue','fa-people-group','HR'],
    ['Attendance Report','Monthly attendance summary with present, absent, late, and half-day counts.','report_attendance.php','emerald','fa-clipboard-check','Attendance'],
    ['Leave Report','Leave utilization by type, department, and employee with approvals.','report_leave.php','orange','fa-calendar-minus','Leave'],
    ['Payroll Report','Complete payroll register with gross, deductions, EPF/ETF, net pay.','report_payroll.php','purple','fa-money-check-dollar','Payroll'],
    ['Overtime Report','Overtime hours and pay breakdown per employee and department.','report_overtime.php','teal','fa-business-time','Overtime'],
    ['Employee Turnover','Turnover rate analysis, tenure distribution, and termination trends.','report_turnover.php','rose','fa-person-walking-arrow-right','Analytics'],
];
foreach($reports as $r): ?>
<div class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
<div class="h-2 bg-gradient-to-r from-<?= $r[3] ?>-500 to-<?= $r[3] ?>-600"></div>
<div class="p-6">
<div class="flex items-start justify-between mb-4">
<div class="w-12 h-12 bg-<?= $r[3] ?>-50 rounded-xl flex items-center justify-center group-hover:bg-<?= $r[3] ?>-500 transition-colors duration-300"><i class="fa-solid <?= $r[4] ?> text-xl text-<?= $r[3] ?>-600 group-hover:text-white transition-colors duration-300"></i></div>
<span class="text-xs font-medium text-<?= $r[3] ?>-600 bg-<?= $r[3] ?>-50 px-2.5 py-1 rounded-full"><?= $r[5] ?></span></div>
<h3 class="text-lg font-bold text-gray-800 mb-2"><?= $r[0] ?></h3>
<p class="text-sm text-gray-500 mb-5 leading-relaxed"><?= $r[1] ?></p>
<a href="<?= $r[2] ?>" class="flex items-center justify-center gap-2 w-full py-2.5 bg-<?= $r[3] ?>-50 hover:bg-<?= $r[3] ?>-600 text-<?= $r[3] ?>-600 hover:text-white font-semibold rounded-xl text-sm transition-all duration-300"><i class="fa-solid fa-arrow-right"></i> Generate Report</a>
</div></div>
<?php endforeach; ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
<h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-bar text-brand-500"></i> Headcount by Department</h3>
<div style="height:280px;"><canvas id="deptChart"></canvas></div></div>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
<h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-area text-emerald-500"></i> Attendance Rate Trend</h3>
<div style="height:280px;"><canvas id="attendChart"></canvas></div></div>
</div>

<!-- Bottom Summary -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white">
<div class="flex items-center justify-between mb-4"><h4 class="font-semibold">Active Workforce</h4><i class="fa-solid fa-users text-white/40 text-2xl"></i></div>
<p class="text-4xl font-bold mb-1"><?= $totalEmployees ?></p><p class="text-blue-200 text-sm">employees currently active</p></div>
<div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white">
<div class="flex items-center justify-between mb-4"><h4 class="font-semibold">Overtime This Month</h4><i class="fa-solid fa-clock text-white/40 text-2xl"></i></div>
<p class="text-4xl font-bold mb-1"><?= number_format($totalOT,1) ?>h</p><p class="text-emerald-200 text-sm">total overtime hours logged</p></div>
<div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-2xl p-6 text-white">
<div class="flex items-center justify-between mb-4"><h4 class="font-semibold">Turnover (Year)</h4><i class="fa-solid fa-person-walking-arrow-right text-white/40 text-2xl"></i></div>
<p class="text-4xl font-bold mb-1"><?= $turnoverCount ?></p><p class="text-rose-200 text-sm">employees marked inactive</p></div>
</div>
</div></div>
</main>
<script>
new Chart(document.getElementById('deptChart'),{type:'bar',data:{labels:<?= $deptLabels ?>,datasets:[{label:'Employees',data:<?= $deptCounts ?>,backgroundColor:['rgba(59,130,246,0.8)','rgba(16,185,129,0.8)','rgba(245,158,11,0.8)','rgba(139,92,246,0.8)','rgba(236,72,153,0.8)','rgba(20,184,166,0.8)','rgba(249,115,22,0.8)','rgba(99,102,241,0.8)'],borderRadius:8,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('attendChart'),{type:'line',data:{labels:<?= json_encode($trendLabels) ?>,datasets:[{label:'Attendance %',data:<?= json_encode($trendRates) ?>,borderColor:'rgb(16,185,129)',backgroundColor:'rgba(16,185,129,0.1)',fill:true,tension:0.4,pointBackgroundColor:'rgb(16,185,129)',pointBorderColor:'#fff',pointBorderWidth:2,pointRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,ticks:{callback:v=>v+'%'},grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}});
</script>
</body></html>
