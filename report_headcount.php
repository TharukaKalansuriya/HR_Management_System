<?php
require_once 'auth_check.php';
$required_page = 'reports.php';
require_once 'db_config.php';
$page_title = "Head Count Report";
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

// Filters
$filterDept   = $_GET['department'] ?? '';
$filterType   = $_GET['emp_type'] ?? '';
$filterStatus = $_GET['status'] ?? 'Active';

// Build query
$where = "WHERE 1=1";
$params = [];
if ($filterStatus) { $where .= " AND e.status = ?"; $params[] = $filterStatus; }
if ($filterDept)   { $where .= " AND e.department = ?"; $params[] = $filterDept; }
if ($filterType)   { $where .= " AND e.employment_type = ?"; $params[] = $filterType; }

$stmt = $pdo->prepare("SELECT e.* FROM employees e $where ORDER BY e.department, e.first_name");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Department summary
$deptSummary = [];
$genderSummary = ['Male'=>0,'Female'=>0,'Other'=>0];
$typeSummary = [];
$statusSummary = [];
foreach ($employees as $emp) {
    $d = $emp['department'] ?: 'Unassigned';
    $deptSummary[$d] = ($deptSummary[$d] ?? 0) + 1;
    $g = $emp['gender'] ?: 'Other';
    $genderSummary[$g] = ($genderSummary[$g] ?? 0) + 1;
    $t = $emp['employment_type'] ?: 'Permanent';
    $typeSummary[$t] = ($typeSummary[$t] ?? 0) + 1;
    $s = $emp['status'];
    $statusSummary[$s] = ($statusSummary[$s] ?? 0) + 1;
}

$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department!='' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

if (!$isPrint) { include 'header.php'; include 'sidebar.php'; }
?>
<?php if($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Head Count Report</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;padding:30px;color:#1f2937;font-size:12px;}
.header{text-align:center;border-bottom:3px solid #1e3a8a;padding-bottom:15px;margin-bottom:20px;}
.header h1{color:#1e3a8a;margin:0;font-size:20px;} .header p{color:#6b7280;margin:5px 0 0;}
table{width:100%;border-collapse:collapse;margin:15px 0;} th,td{border:1px solid #e5e7eb;padding:8px 10px;text-align:left;}
th{background:#f3f4f6;font-weight:600;color:#374151;font-size:11px;text-transform:uppercase;}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin:20px 0;}
.summary-box{border:1px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center;}
.summary-box .num{font-size:24px;font-weight:700;color:#1e3a8a;} .summary-box .lbl{font-size:10px;color:#6b7280;text-transform:uppercase;}
.section-title{font-size:14px;font-weight:700;color:#1e3a8a;margin:20px 0 10px;border-bottom:1px solid #e5e7eb;padding-bottom:5px;}
@media print{body{padding:15px;} .no-print{display:none!important;}}
</style></head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
<button onclick="window.print()" style="background:#1e3a8a;color:#fff;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;">⬇ Download PDF</button>
<a href="report_headcount.php" style="margin-left:10px;color:#1e3a8a;text-decoration:none;font-weight:500;">← Back</a></div>
<div class="header">
<h1>E Track Biz (Pvt) Ltd</h1>
<p>Head Count Report — Generated on <?= date('F d, Y \a\t h:i A') ?></p>
<?php if($filterDept): ?><p>Department: <?= htmlspecialchars($filterDept) ?></p><?php endif; ?>
</div>
<?php else: ?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
<a href="reports.php" class="hover:text-brand-600 transition-colors"><i class="fa-solid fa-chart-line mr-1"></i>Reports</a>
<i class="fa-solid fa-chevron-right text-xs"></i><span class="text-gray-800 font-medium">Head Count Report</span></div>

<!-- Filters -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
<form method="GET" class="flex flex-wrap gap-4 items-end">
<div><label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
<select name="department" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<option value="">All Departments</option>
<?php foreach($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>" <?= $filterDept==$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
</select></div>
<div><label class="block text-xs font-medium text-gray-600 mb-1">Employment Type</label>
<select name="emp_type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<option value="">All Types</option>
<?php foreach(['Permanent','Contract','Casual','Part-time','Trainee'] as $t): ?><option value="<?= $t ?>" <?= $filterType==$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
</select></div>
<div><label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
<select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<option value="">All</option>
<?php foreach(['Active','Inactive','On Leave'] as $s): ?><option value="<?= $s ?>" <?= $filterStatus==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
</select></div>
<button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-filter"></i> Filter</button>
<a href="report_headcount.php?<?= http_build_query(array_merge($_GET,['print'=>'1'])) ?>" target="_blank" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
</form></div>
<?php endif; ?>

<!-- Summary Cards -->
<?php if($isPrint): ?><div class="summary-grid"><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"><?php endif; ?>
<?php if($isPrint): ?>
<div class="summary-box"><div class="num"><?= count($employees) ?></div><div class="lbl">Total Headcount</div></div>
<div class="summary-box"><div class="num"><?= count($deptSummary) ?></div><div class="lbl">Departments</div></div>
<div class="summary-box"><div class="num"><?= $genderSummary['Male'] ?? 0 ?></div><div class="lbl">Male</div></div>
<div class="summary-box"><div class="num"><?= $genderSummary['Female'] ?? 0 ?></div><div class="lbl">Female</div></div>
<?php else: ?>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-blue-50 text-blue-600 mr-4"><i class="fa-solid fa-users text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Total Headcount</p><p class="text-2xl font-bold text-gray-800"><?= count($employees) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 mr-4"><i class="fa-solid fa-building text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Departments</p><p class="text-2xl font-bold text-gray-800"><?= count($deptSummary) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-purple-50 text-purple-600 mr-4"><i class="fa-solid fa-mars text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Male</p><p class="text-2xl font-bold text-gray-800"><?= $genderSummary['Male'] ?? 0 ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-pink-50 text-pink-600 mr-4"><i class="fa-solid fa-venus text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Female</p><p class="text-2xl font-bold text-gray-800"><?= $genderSummary['Female'] ?? 0 ?></p></div></div>
<?php endif; ?>
</div>

<!-- Department Breakdown -->
<?php if($isPrint): ?><div class="section-title">Department Breakdown</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Department Breakdown</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-4 font-medium">Department</th><th class="px-6 py-4 font-medium text-right">Count</th><th class="px-6 py-4 font-medium text-right">% of Total</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($deptSummary as $dept => $cnt): $pct = count($employees) > 0 ? round($cnt/count($employees)*100,1) : 0; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($dept) ?></td>
<td class="px-6 py-3 text-right font-mono text-gray-700"><?= $cnt ?></td>
<td class="px-6 py-3 text-right"><span class="text-xs font-medium bg-blue-50 text-blue-700 px-2 py-1 rounded-full"><?= $pct ?>%</span></td></tr>
<?php endforeach; ?>
<tr class="bg-gray-50 font-bold"><td class="px-6 py-3">Total</td><td class="px-6 py-3 text-right"><?= count($employees) ?></td><td class="px-6 py-3 text-right">100%</td></tr>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<!-- Employment Type Breakdown -->
<?php if($isPrint): ?><div class="section-title">Employment Type Breakdown</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Employment Type Breakdown</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-4 font-medium">Type</th><th class="px-6 py-4 font-medium text-right">Count</th><th class="px-6 py-4 font-medium text-right">% of Total</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($typeSummary as $type => $cnt): $pct = count($employees) > 0 ? round($cnt/count($employees)*100,1) : 0; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($type) ?></td>
<td class="px-6 py-3 text-right font-mono text-gray-700"><?= $cnt ?></td>
<td class="px-6 py-3 text-right"><span class="text-xs font-medium bg-emerald-50 text-emerald-700 px-2 py-1 rounded-full"><?= $pct ?>%</span></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<!-- Full Employee List -->
<?php if($isPrint): ?><div class="section-title">Employee List</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Employee List (<?= count($employees) ?>)</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-4 font-medium">#</th><th class="px-6 py-4 font-medium">Employee</th><th class="px-6 py-4 font-medium">Department</th>
<th class="px-6 py-4 font-medium">Position</th><th class="px-6 py-4 font-medium">Type</th><th class="px-6 py-4 font-medium">Gender</th>
<th class="px-6 py-4 font-medium">Joined</th><th class="px-6 py-4 font-medium text-center">Status</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php $i=0; foreach($employees as $emp): $i++; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 text-gray-500"><?= $i ?></td>
<td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></td>
<td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($emp['department'] ?: '-') ?></td>
<td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($emp['position'] ?: '-') ?></td>
<td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($emp['employment_type'] ?? 'Permanent') ?></td>
<td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($emp['gender'] ?? '-') ?></td>
<td class="px-6 py-3 text-gray-600"><?= $emp['date_joined'] ? date('M d, Y', strtotime($emp['date_joined'])) : '-' ?></td>
<td class="px-6 py-3 text-center"><?php
$sc = match($emp['status']){ 'Active'=>'bg-emerald-100 text-emerald-700','Inactive'=>'bg-red-100 text-red-700',default=>'bg-amber-100 text-amber-700' };
?><span class="px-3 py-1 rounded-full text-xs font-medium <?= $sc ?>"><?= $emp['status'] ?></span></td></tr>
<?php endforeach; ?>
<?php if(empty($employees)): ?><tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No employees found matching the criteria.</td></tr><?php endif; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<?php if($isPrint): ?>
<div style="margin-top:30px;text-align:center;color:#9ca3af;font-size:10px;border-top:1px solid #e5e7eb;padding-top:10px;">
Generated by HRMS — E Track Biz (Pvt) Ltd | <?= date('F d, Y h:i A') ?></div>
</body></html>
<?php else: ?>
</div></main></body></html>
<?php endif; ?>
