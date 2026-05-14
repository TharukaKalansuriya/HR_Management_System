<?php
require_once 'auth_check.php';
$required_page = 'reports.php';
require_once 'db_config.php';
$page_title = "Employee Turnover Report";
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterDept = $_GET['department'] ?? '';

// Active employees
$activeCount = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Active'")->fetchColumn();

// Inactive (terminated) employees
$deptFilter = $filterDept ? "AND department=?" : "";
$params = [];
if ($filterDept) $params[] = $filterDept;

$inactiveStmt = $pdo->prepare("SELECT * FROM employees WHERE status='Inactive' $deptFilter ORDER BY department, first_name");
$inactiveStmt->execute($params);
$inactiveEmps = $inactiveStmt->fetchAll(PDO::FETCH_ASSOC);

// All employees for rate calc
$totalEmpCount = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$turnoverRate = $totalEmpCount > 0 ? round(count($inactiveEmps) / $totalEmpCount * 100, 1) : 0;

// Tenure analysis for inactive employees
$tenureBuckets = ['< 6 months'=>0, '6-12 months'=>0, '1-2 years'=>0, '2-5 years'=>0, '5+ years'=>0, 'Unknown'=>0];
foreach ($inactiveEmps as $emp) {
    if (!empty($emp['date_joined'])) {
        $joined = new DateTime($emp['date_joined']);
        $now = new DateTime();
        $diff = $joined->diff($now);
        $months = $diff->y * 12 + $diff->m;
        if ($months < 6) $tenureBuckets['< 6 months']++;
        elseif ($months < 12) $tenureBuckets['6-12 months']++;
        elseif ($months < 24) $tenureBuckets['1-2 years']++;
        elseif ($months < 60) $tenureBuckets['2-5 years']++;
        else $tenureBuckets['5+ years']++;
    } else {
        $tenureBuckets['Unknown']++;
    }
}

// Department breakdown for inactive
$deptTurnover = [];
foreach ($inactiveEmps as $emp) {
    $d = $emp['department'] ?: 'Unassigned';
    $deptTurnover[$d] = ($deptTurnover[$d] ?? 0) + 1;
}
arsort($deptTurnover);

// Gender breakdown for inactive
$genderTurnover = ['Male'=>0, 'Female'=>0, 'Other'=>0];
foreach ($inactiveEmps as $emp) {
    $g = $emp['gender'] ?: 'Other';
    $genderTurnover[$g] = ($genderTurnover[$g] ?? 0) + 1;
}

// Employment type breakdown for inactive
$typeTurnover = [];
foreach ($inactiveEmps as $emp) {
    $t = $emp['employment_type'] ?? 'Permanent';
    $typeTurnover[$t] = ($typeTurnover[$t] ?? 0) + 1;
}

$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department!='' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

if (!$isPrint) { include 'header.php'; include 'sidebar.php'; }
?>
<?php if($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Employee Turnover Report</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;padding:30px;color:#1f2937;font-size:11px;}
.header{text-align:center;border-bottom:3px solid #e11d48;padding-bottom:15px;margin-bottom:20px;}
.header h1{color:#e11d48;margin:0;font-size:20px;} .header p{color:#6b7280;margin:5px 0 0;}
table{width:100%;border-collapse:collapse;margin:15px 0;} th,td{border:1px solid #e5e7eb;padding:6px 8px;text-align:left;}
th{background:#f3f4f6;font-weight:600;color:#374151;font-size:10px;text-transform:uppercase;}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:15px 0;}
.summary-box{border:1px solid #e5e7eb;border-radius:8px;padding:10px;text-align:center;}
.summary-box .num{font-size:20px;font-weight:700;color:#e11d48;} .summary-box .lbl{font-size:9px;color:#6b7280;text-transform:uppercase;}
.section-title{font-size:14px;font-weight:700;color:#e11d48;margin:20px 0 10px;border-bottom:1px solid #e5e7eb;padding-bottom:5px;}
@media print{body{padding:15px;} .no-print{display:none!important;}}
</style></head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
<button onclick="window.print()" style="background:#e11d48;color:#fff;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;">⬇ Download PDF</button>
<a href="report_turnover.php" style="margin-left:10px;color:#e11d48;text-decoration:none;font-weight:500;">← Back</a></div>
<div class="header"><h1>E Track Biz (Pvt) Ltd</h1><p>Employee Turnover Report — <?= $filterYear ?></p>
<p>Generated on <?= date('F d, Y \a\t h:i A') ?></p></div>
<?php else: ?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
<a href="reports.php" class="hover:text-brand-600 transition-colors"><i class="fa-solid fa-chart-line mr-1"></i>Reports</a>
<i class="fa-solid fa-chevron-right text-xs"></i><span class="text-gray-800 font-medium">Employee Turnover Report</span></div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
<form method="GET" class="flex flex-wrap gap-4 items-end">
<div><label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
<select name="year" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<?php for($y=date('Y')-3;$y<=date('Y')+1;$y++): ?><option value="<?= $y ?>" <?= $y==$filterYear?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
</select></div>
<div><label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
<select name="department" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<option value="">All Departments</option>
<?php foreach($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>" <?= $filterDept==$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
</select></div>
<button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-filter"></i> Filter</button>
<a href="report_turnover.php?<?= http_build_query(array_merge($_GET,['print'=>'1'])) ?>" target="_blank" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
</form></div>
<?php endif; ?>

<!-- Summary -->
<?php if($isPrint): ?><div class="summary-grid"><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"><?php endif; ?>
<?php if($isPrint): ?>
<div class="summary-box"><div class="num"><?= $activeCount ?></div><div class="lbl">Active Employees</div></div>
<div class="summary-box"><div class="num"><?= count($inactiveEmps) ?></div><div class="lbl">Terminated</div></div>
<div class="summary-box"><div class="num"><?= $turnoverRate ?>%</div><div class="lbl">Turnover Rate</div></div>
<div class="summary-box"><div class="num"><?= $totalEmpCount ?></div><div class="lbl">Total (All Time)</div></div>
<?php else: ?>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 mr-4"><i class="fa-solid fa-users text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Active Employees</p><p class="text-2xl font-bold text-gray-800"><?= $activeCount ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-rose-50 text-rose-600 mr-4"><i class="fa-solid fa-person-walking-arrow-right text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Terminated</p><p class="text-2xl font-bold text-gray-800"><?= count($inactiveEmps) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-red-50 text-red-600 mr-4"><i class="fa-solid fa-chart-line text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Turnover Rate</p><p class="text-2xl font-bold text-red-600"><?= $turnoverRate ?>%</p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-blue-50 text-blue-600 mr-4"><i class="fa-solid fa-user-group text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Total (All Time)</p><p class="text-2xl font-bold text-gray-800"><?= $totalEmpCount ?></p></div></div>
<?php endif; ?>
</div>

<!-- Tenure Analysis -->
<?php if($isPrint): ?><div class="section-title">Tenure at Time of Exit</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Tenure at Time of Exit</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-3 font-medium">Tenure Bracket</th><th class="px-6 py-3 font-medium text-right">Count</th>
<th class="px-6 py-3 font-medium text-right">% of Exits</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php $totalInactive = count($inactiveEmps); foreach($tenureBuckets as $bucket => $cnt): if($cnt == 0 && $bucket == 'Unknown') continue;
$pct = $totalInactive > 0 ? round($cnt/$totalInactive*100,1) : 0; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= $bucket ?></td>
<td class="px-6 py-3 text-right font-mono text-gray-700"><?= $cnt ?></td>
<td class="px-6 py-3 text-right"><span class="text-xs font-medium bg-rose-50 text-rose-700 px-2 py-1 rounded-full"><?= $pct ?>%</span></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<!-- Department Turnover -->
<?php if(!empty($deptTurnover)): ?>
<?php if($isPrint): ?><div class="section-title">Turnover by Department</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Turnover by Department</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-3 font-medium">Department</th><th class="px-6 py-3 font-medium text-right">Exits</th>
<th class="px-6 py-3 font-medium text-right">% of Total Exits</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($deptTurnover as $dept => $cnt): $pct = $totalInactive > 0 ? round($cnt/$totalInactive*100,1) : 0; ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($dept) ?></td>
<td class="px-6 py-3 text-right font-mono text-gray-700"><?= $cnt ?></td>
<td class="px-6 py-3 text-right"><span class="text-xs font-medium bg-red-50 text-red-700 px-2 py-1 rounded-full"><?= $pct ?>%</span></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; endif; ?>

<!-- Exited Employees List -->
<?php if($isPrint): ?><div class="section-title">Exited Employees</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Exited Employees (<?= count($inactiveEmps) ?>)</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-5 py-3 font-medium">#</th><th class="px-5 py-3 font-medium">Employee</th>
<th class="px-5 py-3 font-medium">Department</th><th class="px-5 py-3 font-medium">Position</th>
<th class="px-5 py-3 font-medium">Type</th><th class="px-5 py-3 font-medium">Date Joined</th>
<th class="px-5 py-3 font-medium">Gender</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php $i=0; foreach($inactiveEmps as $emp): $i++; ?>
<tr class="hover:bg-gray-50/50">
<td class="px-5 py-3 text-gray-500"><?= $i ?></td>
<td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></td>
<td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($emp['department'] ?: '-') ?></td>
<td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($emp['position'] ?: '-') ?></td>
<td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($emp['employment_type'] ?? 'Permanent') ?></td>
<td class="px-5 py-3 text-gray-600"><?= !empty($emp['date_joined']) ? date('M d, Y', strtotime($emp['date_joined'])) : '-' ?></td>
<td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($emp['gender'] ?? '-') ?></td></tr>
<?php endforeach; ?>
<?php if(empty($inactiveEmps)): ?><tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">
<div class="flex flex-col items-center">
<div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mb-3"><i class="fa-solid fa-face-smile text-2xl text-emerald-400"></i></div>
<p class="text-lg font-medium text-gray-700">No employee exits recorded</p>
<p class="text-sm text-gray-500 mt-1">Great news — zero turnover!</p></div>
</td></tr><?php endif; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<?php if($isPrint): ?>
<div style="margin-top:30px;text-align:center;color:#9ca3af;font-size:10px;border-top:1px solid #e5e7eb;padding-top:10px;">
Generated by HRMS — E Track Biz (Pvt) Ltd | <?= date('F d, Y h:i A') ?></div></body></html>
<?php else: ?></div></main></body></html><?php endif; ?>
