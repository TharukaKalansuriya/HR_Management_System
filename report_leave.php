<?php
require_once 'auth_check.php';
$required_page = 'reports.php';
require_once 'db_config.php';
$page_title = "Leave Report";
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filterDept  = $_GET['department'] ?? '';

// Get leave data from attendance table (On Leave status)
$deptFilter = $filterDept ? "AND e.department=?" : "";
$params = [$filterMonth, $filterYear];
if ($filterDept) $params[] = $filterDept;

$stmt = $pdo->prepare("SELECT e.id, e.first_name, e.last_name, e.department, e.position,
    COUNT(CASE WHEN a.status='On Leave' THEN 1 END) as leave_days
    FROM employees e 
    LEFT JOIN attendance a ON e.id=a.employee_id AND MONTH(a.attendance_date)=? AND YEAR(a.attendance_date)=?
    WHERE e.status='Active' $deptFilter
    GROUP BY e.id HAVING leave_days > 0
    ORDER BY leave_days DESC, e.first_name");
$stmt->execute($params);
$leaveData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also check formal leaves table
$lStmt = $pdo->prepare("SELECT l.*, e.first_name, e.last_name, e.department 
    FROM leaves l JOIN employees e ON e.id=l.user_id 
    WHERE ((MONTH(l.start_date)=? AND YEAR(l.start_date)=?) OR (MONTH(l.end_date)=? AND YEAR(l.end_date)=?)) $deptFilter
    ORDER BY l.start_date DESC");
$lParams = [$filterMonth, $filterYear, $filterMonth, $filterYear];
if ($filterDept) $lParams[] = $filterDept;
$lStmt->execute($lParams);
$formalLeaves = $lStmt->fetchAll(PDO::FETCH_ASSOC);

// Summary
$totalLeaveDays = array_sum(array_column($leaveData, 'leave_days'));
$empOnLeave = count($leaveData);

// Department breakdown
$deptLeave = [];
foreach ($leaveData as $r) {
    $d = $r['department'] ?: 'Unassigned';
    $deptLeave[$d] = ($deptLeave[$d] ?? 0) + $r['leave_days'];
}
arsort($deptLeave);

// Leave type breakdown from formal leaves
$typeBreakdown = [];
foreach ($formalLeaves as $fl) {
    $t = $fl['leave_type'];
    $typeBreakdown[$t] = ($typeBreakdown[$t] ?? 0) + $fl['days'];
}

$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department!='' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$monthName = date('F', mktime(0,0,0,$filterMonth,1));

if (!$isPrint) { include 'header.php'; include 'sidebar.php'; }
?>
<?php if($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Leave Report</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;padding:30px;color:#1f2937;font-size:11px;}
.header{text-align:center;border-bottom:3px solid #ea580c;padding-bottom:15px;margin-bottom:20px;}
.header h1{color:#ea580c;margin:0;font-size:20px;} .header p{color:#6b7280;margin:5px 0 0;}
table{width:100%;border-collapse:collapse;margin:15px 0;} th,td{border:1px solid #e5e7eb;padding:6px 8px;text-align:left;}
th{background:#f3f4f6;font-weight:600;color:#374151;font-size:10px;text-transform:uppercase;}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:15px 0;}
.summary-box{border:1px solid #e5e7eb;border-radius:8px;padding:10px;text-align:center;}
.summary-box .num{font-size:20px;font-weight:700;color:#ea580c;} .summary-box .lbl{font-size:9px;color:#6b7280;text-transform:uppercase;}
.section-title{font-size:14px;font-weight:700;color:#ea580c;margin:20px 0 10px;border-bottom:1px solid #e5e7eb;padding-bottom:5px;}
@media print{body{padding:15px;} .no-print{display:none!important;}}
</style></head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
<button onclick="window.print()" style="background:#ea580c;color:#fff;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;">⬇ Download PDF</button>
<a href="report_leave.php" style="margin-left:10px;color:#ea580c;text-decoration:none;font-weight:500;">← Back</a></div>
<div class="header"><h1>E Track Biz (Pvt) Ltd</h1><p>Leave Report — <?= $monthName ?> <?= $filterYear ?></p>
<p>Generated on <?= date('F d, Y \a\t h:i A') ?></p></div>
<?php else: ?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
<a href="reports.php" class="hover:text-brand-600 transition-colors"><i class="fa-solid fa-chart-line mr-1"></i>Reports</a>
<i class="fa-solid fa-chevron-right text-xs"></i><span class="text-gray-800 font-medium">Leave Report</span></div>

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
<a href="report_leave.php?<?= http_build_query(array_merge($_GET,['print'=>'1'])) ?>" target="_blank" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
</form></div>
<?php endif; ?>

<!-- Summary -->
<?php if($isPrint): ?><div class="summary-grid"><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"><?php endif; ?>
<?php if($isPrint): ?>
<div class="summary-box"><div class="num"><?= $totalLeaveDays ?></div><div class="lbl">Total Leave Days</div></div>
<div class="summary-box"><div class="num"><?= $empOnLeave ?></div><div class="lbl">Employees on Leave</div></div>
<div class="summary-box"><div class="num"><?= count($formalLeaves) ?></div><div class="lbl">Formal Requests</div></div>
<div class="summary-box"><div class="num"><?= count($deptLeave) ?></div><div class="lbl">Departments Affected</div></div>
<?php else: ?>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-orange-50 text-orange-600 mr-4"><i class="fa-solid fa-calendar-minus text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Total Leave Days</p><p class="text-2xl font-bold text-gray-800"><?= $totalLeaveDays ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-blue-50 text-blue-600 mr-4"><i class="fa-solid fa-users text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Employees on Leave</p><p class="text-2xl font-bold text-gray-800"><?= $empOnLeave ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-purple-50 text-purple-600 mr-4"><i class="fa-solid fa-file-lines text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Formal Requests</p><p class="text-2xl font-bold text-gray-800"><?= count($formalLeaves) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 mr-4"><i class="fa-solid fa-building text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Depts Affected</p><p class="text-2xl font-bold text-gray-800"><?= count($deptLeave) ?></p></div></div>
<?php endif; ?>
</div>

<?php if(!empty($deptLeave)): ?>
<!-- Department Leave Breakdown -->
<?php if($isPrint): ?><div class="section-title">Department Leave Summary</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Department Leave Summary</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-3 font-medium">Department</th><th class="px-6 py-3 font-medium text-right">Leave Days</th>
<th class="px-6 py-3 font-medium text-right">% of Total</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($deptLeave as $dept=>$days): $pct = $totalLeaveDays > 0 ? round($days/$totalLeaveDays*100,1) : 0; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($dept) ?></td>
<td class="px-6 py-3 text-right font-mono text-gray-700"><?= $days ?></td>
<td class="px-6 py-3 text-right"><span class="text-xs font-medium bg-orange-50 text-orange-700 px-2 py-1 rounded-full"><?= $pct ?>%</span></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; endif; ?>

<?php if(!empty($typeBreakdown)): ?>
<!-- Leave Type Breakdown -->
<?php if($isPrint): ?><div class="section-title">Leave Type Breakdown</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Leave Type Breakdown</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-3 font-medium">Leave Type</th><th class="px-6 py-3 font-medium text-right">Days</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($typeBreakdown as $type=>$days): ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($type) ?></td>
<td class="px-6 py-3 text-right font-mono text-gray-700"><?= $days ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; endif; ?>

<!-- Employee Leave Details -->
<?php if($isPrint): ?><div class="section-title">Employee Leave Details</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Employee Leave Details</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-3 font-medium">#</th><th class="px-6 py-3 font-medium">Employee</th>
<th class="px-6 py-3 font-medium">Department</th><th class="px-6 py-3 font-medium">Position</th>
<th class="px-6 py-3 font-medium text-center">Leave Days</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php $i=0; foreach($leaveData as $r): $i++; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 text-gray-500"><?= $i ?></td>
<td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
<td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($r['department'] ?: '-') ?></td>
<td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($r['position'] ?: '-') ?></td>
<td class="px-6 py-3 text-center"><span class="font-mono font-semibold text-orange-600"><?= $r['leave_days'] ?></span></td></tr>
<?php endforeach; ?>
<?php if(empty($leaveData)): ?><tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No leave data found for this period.</td></tr><?php endif; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<?php if($isPrint): ?>
<div style="margin-top:30px;text-align:center;color:#9ca3af;font-size:10px;border-top:1px solid #e5e7eb;padding-top:10px;">
Generated by HRMS — E Track Biz (Pvt) Ltd | <?= date('F d, Y h:i A') ?></div></body></html>
<?php else: ?></div></main></body></html><?php endif; ?>
