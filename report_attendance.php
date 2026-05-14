<?php
require_once 'auth_check.php';
$required_page = 'reports.php';
require_once 'db_config.php';
$page_title = "Attendance Report";
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filterDept  = $_GET['department'] ?? '';

$where = "WHERE MONTH(a.attendance_date)=? AND YEAR(a.attendance_date)=?";
$params = [$filterMonth, $filterYear];
if ($filterDept) { $where .= " AND e.department=?"; $params[] = $filterDept; }

$stmt = $pdo->prepare("SELECT e.id, e.first_name, e.last_name, e.department, e.position,
    COUNT(CASE WHEN a.status='Present' THEN 1 END) as present_days,
    COUNT(CASE WHEN a.status='Absent' THEN 1 END) as absent_days,
    COUNT(CASE WHEN a.status='Late' THEN 1 END) as late_days,
    COUNT(CASE WHEN a.status='Half Day' THEN 1 END) as half_days,
    COUNT(CASE WHEN a.status='On Leave' THEN 1 END) as leave_days,
    COUNT(CASE WHEN a.status='Holiday' THEN 1 END) as holiday_days,
    COUNT(CASE WHEN a.status='Weekend' THEN 1 END) as weekend_days,
    COUNT(*) as total_records,
    ROUND(COALESCE(SUM(a.work_hours),0),1) as total_work_hours
    FROM employees e LEFT JOIN attendance a ON e.id=a.employee_id AND MONTH(a.attendance_date)=? AND YEAR(a.attendance_date)=?
    WHERE e.status='Active' " . ($filterDept ? "AND e.department=?" : "") . "
    GROUP BY e.id ORDER BY e.department, e.first_name");
$p2 = [$filterMonth, $filterYear];
if ($filterDept) $p2[] = $filterDept;
$stmt->execute($p2);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall stats
$totalPresent = array_sum(array_column($data, 'present_days'));
$totalAbsent  = array_sum(array_column($data, 'absent_days'));
$totalLate    = array_sum(array_column($data, 'late_days'));
$totalLeave   = array_sum(array_column($data, 'leave_days'));
$totalRecords = array_sum(array_column($data, 'total_records'));
$overallRate  = $totalRecords > 0 ? round(($totalPresent + array_sum(array_column($data,'late_days')) + array_sum(array_column($data,'half_days'))) / $totalRecords * 100, 1) : 0;

$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department!='' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$monthName = date('F', mktime(0,0,0,$filterMonth,1));

if (!$isPrint) { include 'header.php'; include 'sidebar.php'; }
?>
<?php if($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Attendance Report</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;padding:30px;color:#1f2937;font-size:11px;}
.header{text-align:center;border-bottom:3px solid #059669;padding-bottom:15px;margin-bottom:20px;}
.header h1{color:#059669;margin:0;font-size:20px;} .header p{color:#6b7280;margin:5px 0 0;}
table{width:100%;border-collapse:collapse;margin:15px 0;} th,td{border:1px solid #e5e7eb;padding:6px 8px;text-align:left;}
th{background:#f3f4f6;font-weight:600;color:#374151;font-size:10px;text-transform:uppercase;}
.summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin:15px 0;}
.summary-box{border:1px solid #e5e7eb;border-radius:8px;padding:10px;text-align:center;}
.summary-box .num{font-size:20px;font-weight:700;} .summary-box .lbl{font-size:9px;color:#6b7280;text-transform:uppercase;}
.section-title{font-size:14px;font-weight:700;color:#059669;margin:20px 0 10px;border-bottom:1px solid #e5e7eb;padding-bottom:5px;}
@media print{body{padding:15px;} .no-print{display:none!important;}}
</style></head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
<button onclick="window.print()" style="background:#059669;color:#fff;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;">⬇ Download PDF</button>
<a href="report_attendance.php" style="margin-left:10px;color:#059669;text-decoration:none;font-weight:500;">← Back</a></div>
<div class="header"><h1>E Track Biz (Pvt) Ltd</h1><p>Attendance Report — <?= $monthName ?> <?= $filterYear ?></p>
<p>Generated on <?= date('F d, Y \a\t h:i A') ?></p></div>
<?php else: ?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
<a href="reports.php" class="hover:text-brand-600 transition-colors"><i class="fa-solid fa-chart-line mr-1"></i>Reports</a>
<i class="fa-solid fa-chevron-right text-xs"></i><span class="text-gray-800 font-medium">Attendance Report</span></div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
<form method="GET" class="flex flex-wrap gap-4 items-end">
<div><label class="block text-xs font-medium text-gray-600 mb-1">Month</label>
<select name="month" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==$filterMonth?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?>
</select></div>
<div><label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
<select name="year" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?><option value="<?= $y ?>" <?= $y==$filterYear?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
</select></div>
<div><label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
<select name="department" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<option value="">All Departments</option>
<?php foreach($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>" <?= $filterDept==$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
</select></div>
<button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-filter"></i> Filter</button>
<a href="report_attendance.php?<?= http_build_query(array_merge($_GET,['print'=>'1'])) ?>" target="_blank" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
</form></div>
<?php endif; ?>

<!-- Summary -->
<?php if($isPrint): ?><div class="summary-grid"><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-5 mb-8"><?php endif; ?>
<?php
$stats = [
    ['Present Days',$totalPresent,'emerald'],['Absent Days',$totalAbsent,'red'],['Late Days',$totalLate,'amber'],
    ['Leave Days',$totalLeave,'orange'],['Attendance Rate',$overallRate.'%','blue']
];
foreach($stats as $s):
if($isPrint): ?><div class="summary-box"><div class="num"><?= $s[1] ?></div><div class="lbl"><?= $s[0] ?></div></div>
<?php else: ?>
<div class="stat-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center">
<div class="p-3.5 rounded-xl bg-<?= $s[2] ?>-50 text-<?= $s[2] ?>-600 mr-4"><i class="fa-solid fa-chart-simple text-lg"></i></div>
<div><p class="text-xs font-semibold text-gray-400 uppercase"><?= $s[0] ?></p><p class="text-2xl font-bold text-gray-800"><?= $s[1] ?></p></div></div>
<?php endif; endforeach; ?>
</div>

<!-- Data Table -->
<?php if(!$isPrint): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Attendance Details — <?= $monthName ?> <?= $filterYear ?></h2></div>
<div class="overflow-x-auto"><?php else: ?><div class="section-title">Attendance Details</div><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-5 py-3 font-medium">#</th><th class="px-5 py-3 font-medium">Employee</th><th class="px-5 py-3 font-medium">Department</th>
<th class="px-5 py-3 font-medium text-center">Present</th><th class="px-5 py-3 font-medium text-center">Absent</th>
<th class="px-5 py-3 font-medium text-center">Late</th><th class="px-5 py-3 font-medium text-center">Half Day</th>
<th class="px-5 py-3 font-medium text-center">Leave</th><th class="px-5 py-3 font-medium text-center">Work Hrs</th>
<th class="px-5 py-3 font-medium text-center">Rate</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php $i=0; foreach($data as $r): $i++;
$worked = $r['present_days'] + $r['late_days'] + $r['half_days'];
$total = $r['total_records'] - $r['holiday_days'] - $r['weekend_days'];
$rate = $total > 0 ? round($worked/$total*100,1) : 0;
$rateColor = $rate >= 90 ? 'emerald' : ($rate >= 70 ? 'amber' : 'red');
?>
<tr class="hover:bg-gray-50/50">
<td class="px-5 py-3 text-gray-500"><?= $i ?></td>
<td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
<td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($r['department'] ?: '-') ?></td>
<td class="px-5 py-3 text-center font-mono text-emerald-600 font-semibold"><?= $r['present_days'] ?></td>
<td class="px-5 py-3 text-center font-mono text-red-600 font-semibold"><?= $r['absent_days'] ?></td>
<td class="px-5 py-3 text-center font-mono text-amber-600"><?= $r['late_days'] ?></td>
<td class="px-5 py-3 text-center font-mono text-orange-600"><?= $r['half_days'] ?></td>
<td class="px-5 py-3 text-center font-mono text-gray-600"><?= $r['leave_days'] ?></td>
<td class="px-5 py-3 text-center font-mono text-gray-700"><?= $r['total_work_hours'] ?></td>
<td class="px-5 py-3 text-center"><span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-<?= $rateColor ?>-100 text-<?= $rateColor ?>-700"><?= $rate ?>%</span></td>
</tr>
<?php endforeach; ?>
<?php if(empty($data)): ?><tr><td colspan="10" class="px-6 py-12 text-center text-gray-500">No attendance data found for this period.</td></tr><?php endif; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<?php if($isPrint): ?>
<div style="margin-top:30px;text-align:center;color:#9ca3af;font-size:10px;border-top:1px solid #e5e7eb;padding-top:10px;">
Generated by HRMS — E Track Biz (Pvt) Ltd | <?= date('F d, Y h:i A') ?></div></body></html>
<?php else: ?></div></main></body></html><?php endif; ?>
