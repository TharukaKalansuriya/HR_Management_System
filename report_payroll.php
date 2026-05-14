<?php
require_once 'auth_check.php';
$required_page = 'reports.php';
require_once 'db_config.php';
$page_title = "Payroll Report";
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

$filterMonth  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear   = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filterDept   = $_GET['department'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$where = "WHERE pr.pay_month=? AND pr.pay_year=?";
$params = [$filterMonth, $filterYear];
if ($filterDept)   { $where .= " AND e.department=?"; $params[] = $filterDept; }
if ($filterStatus) { $where .= " AND pr.status=?"; $params[] = $filterStatus; }

$stmt = $pdo->prepare("SELECT pr.*, e.first_name, e.last_name, e.department, e.position
    FROM payroll_runs pr JOIN employees e ON e.id=pr.employee_id $where ORDER BY e.department, e.first_name");
$stmt->execute($params);
$payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = ['basic'=>0,'gross'=>0,'epf_ee'=>0,'epf_er'=>0,'etf'=>0,'deductions'=>0,'net'=>0,'ot_pay'=>0];
$deptTotals = [];
foreach ($payrolls as $p) {
    $totals['basic']      += $p['basic_salary'];
    $totals['gross']      += $p['gross_salary'];
    $totals['epf_ee']     += $p['epf_employee'];
    $totals['epf_er']     += $p['epf_employer'];
    $totals['etf']        += $p['etf'];
    $totals['deductions'] += $p['total_deductions'];
    $totals['net']        += $p['net_salary'];
    $totals['ot_pay']     += $p['overtime_pay'];
    $d = $p['department'] ?: 'Unassigned';
    if (!isset($deptTotals[$d])) $deptTotals[$d] = ['gross'=>0,'net'=>0,'count'=>0];
    $deptTotals[$d]['gross'] += $p['gross_salary'];
    $deptTotals[$d]['net']   += $p['net_salary'];
    $deptTotals[$d]['count']++;
}

$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department!='' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$monthName = date('F', mktime(0,0,0,$filterMonth,1));

if (!$isPrint) { include 'header.php'; include 'sidebar.php'; }
?>
<?php if($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Payroll Report</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;padding:20px;color:#1f2937;font-size:10px;}
.header{text-align:center;border-bottom:3px solid #7c3aed;padding-bottom:15px;margin-bottom:15px;}
.header h1{color:#7c3aed;margin:0;font-size:18px;} .header p{color:#6b7280;margin:4px 0 0;font-size:11px;}
table{width:100%;border-collapse:collapse;margin:10px 0;} th,td{border:1px solid #e5e7eb;padding:5px 6px;text-align:left;}
th{background:#f3f4f6;font-weight:600;color:#374151;font-size:9px;text-transform:uppercase;}
.text-right{text-align:right;} .font-mono{font-family:monospace;} .font-bold{font-weight:700;}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:12px 0;}
.summary-box{border:1px solid #e5e7eb;border-radius:8px;padding:8px;text-align:center;}
.summary-box .num{font-size:16px;font-weight:700;color:#7c3aed;} .summary-box .lbl{font-size:8px;color:#6b7280;text-transform:uppercase;}
.section-title{font-size:13px;font-weight:700;color:#7c3aed;margin:18px 0 8px;border-bottom:1px solid #e5e7eb;padding-bottom:4px;}
.total-row{background:#f3f4f6;font-weight:bold;}
@media print{body{padding:10px;} .no-print{display:none!important;}}
</style></head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
<button onclick="window.print()" style="background:#7c3aed;color:#fff;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-weight:600;">⬇ Download PDF</button>
<a href="report_payroll.php" style="margin-left:10px;color:#7c3aed;text-decoration:none;font-weight:500;">← Back</a></div>
<div class="header"><h1>E Track Biz (Pvt) Ltd</h1><p>Payroll Report — <?= $monthName ?> <?= $filterYear ?></p>
<p>Generated on <?= date('F d, Y \a\t h:i A') ?></p></div>
<?php else: ?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
<a href="reports.php" class="hover:text-brand-600 transition-colors"><i class="fa-solid fa-chart-line mr-1"></i>Reports</a>
<i class="fa-solid fa-chevron-right text-xs"></i><span class="text-gray-800 font-medium">Payroll Report</span></div>

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
<div><label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
<select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
<option value="">All</option>
<?php foreach(['Draft','Processed','Paid'] as $s): ?><option value="<?= $s ?>" <?= $filterStatus==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
</select></div>
<button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-filter"></i> Filter</button>
<a href="report_payroll.php?<?= http_build_query(array_merge($_GET,['print'=>'1'])) ?>" target="_blank" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
</form></div>
<?php endif; ?>

<!-- Summary -->
<?php if($isPrint): ?><div class="summary-grid"><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"><?php endif; ?>
<?php if($isPrint): ?>
<div class="summary-box"><div class="num"><?= count($payrolls) ?></div><div class="lbl">Employees</div></div>
<div class="summary-box"><div class="num">Rs. <?= number_format($totals['gross'],0) ?></div><div class="lbl">Total Gross</div></div>
<div class="summary-box"><div class="num">Rs. <?= number_format($totals['deductions'],0) ?></div><div class="lbl">Total Deductions</div></div>
<div class="summary-box"><div class="num">Rs. <?= number_format($totals['net'],0) ?></div><div class="lbl">Total Net Pay</div></div>
<?php else: ?>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-blue-50 text-blue-600 mr-4"><i class="fa-solid fa-users text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Employees</p><p class="text-2xl font-bold text-gray-800"><?= count($payrolls) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 mr-4"><i class="fa-solid fa-money-bill-wave text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Total Gross</p><p class="text-xl font-bold text-gray-800">Rs. <?= number_format($totals['gross'],2) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-red-50 text-red-600 mr-4"><i class="fa-solid fa-circle-minus text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Total Deductions</p><p class="text-xl font-bold text-gray-800">Rs. <?= number_format($totals['deductions'],2) ?></p></div></div>
<div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
<div class="p-4 rounded-xl bg-purple-50 text-purple-600 mr-4"><i class="fa-solid fa-hand-holding-dollar text-xl"></i></div>
<div><p class="text-sm font-medium text-gray-500">Total Net Pay</p><p class="text-xl font-bold text-gray-800">Rs. <?= number_format($totals['net'],2) ?></p></div></div>
<?php endif; ?>
</div>

<!-- Department Cost Summary -->
<?php if(!empty($deptTotals)): ?>
<?php if($isPrint): ?><div class="section-title">Department Cost Summary</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Department Cost Summary</h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-6 py-3 font-medium">Department</th><th class="px-6 py-3 font-medium text-right">Employees</th>
<th class="px-6 py-3 font-medium text-right">Total Gross</th><th class="px-6 py-3 font-medium text-right">Total Net</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($deptTotals as $dept=>$dt): ?>
<tr class="hover:bg-gray-50/50"><td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($dept) ?></td>
<td class="px-6 py-3 text-right font-mono"><?= $dt['count'] ?></td>
<td class="px-6 py-3 text-right font-mono">Rs. <?= number_format($dt['gross'],2) ?></td>
<td class="px-6 py-3 text-right font-mono font-semibold text-emerald-700">Rs. <?= number_format($dt['net'],2) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; endif; ?>

<!-- Full Payroll Register -->
<?php if($isPrint): ?><div class="section-title">Payroll Register</div><?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100"><h2 class="text-lg font-semibold text-gray-800">Payroll Register — <?= $monthName ?> <?= $filterYear ?></h2></div><div class="overflow-x-auto"><?php endif; ?>
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/50"><tr>
<th class="px-4 py-3 font-medium">#</th><th class="px-4 py-3 font-medium">Employee</th>
<th class="px-4 py-3 font-medium">Dept</th><th class="px-4 py-3 font-medium text-right">Basic</th>
<th class="px-4 py-3 font-medium text-right">OT Pay</th><th class="px-4 py-3 font-medium text-right">Gross</th>
<th class="px-4 py-3 font-medium text-right">EPF(EE)</th><th class="px-4 py-3 font-medium text-right">EPF(ER)</th>
<th class="px-4 py-3 font-medium text-right">ETF</th><th class="px-4 py-3 font-medium text-right">Deductions</th>
<th class="px-4 py-3 font-medium text-right">Net Pay</th><th class="px-4 py-3 font-medium text-center">Status</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php $i=0; foreach($payrolls as $p): $i++; ?>
<tr class="hover:bg-gray-50/50">
<td class="px-4 py-2.5 text-gray-500"><?= $i ?></td>
<td class="px-4 py-2.5 font-medium text-gray-800 whitespace-nowrap"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
<td class="px-4 py-2.5 text-gray-600"><?= htmlspecialchars($p['department'] ?: '-') ?></td>
<td class="px-4 py-2.5 text-right font-mono"><?= number_format($p['basic_salary'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono text-teal-600"><?= number_format($p['overtime_pay'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono font-semibold"><?= number_format($p['gross_salary'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono text-red-500"><?= number_format($p['epf_employee'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono text-red-500"><?= number_format($p['epf_employer'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono text-red-500"><?= number_format($p['etf'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono text-red-600 font-semibold"><?= number_format($p['total_deductions'],2) ?></td>
<td class="px-4 py-2.5 text-right font-mono text-emerald-700 font-bold"><?= number_format($p['net_salary'],2) ?></td>
<td class="px-4 py-2.5 text-center"><?php
$sc = match($p['status']){'Paid'=>'bg-emerald-100 text-emerald-700','Processed'=>'bg-blue-100 text-blue-700',default=>'bg-amber-100 text-amber-700'};
?><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>"><?= $p['status'] ?></span></td></tr>
<?php endforeach; ?>
<?php if(!empty($payrolls)): ?>
<tr class="bg-gray-50 font-bold text-gray-800">
<td class="px-4 py-3" colspan="3">TOTALS</td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['basic'],2) ?></td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['ot_pay'],2) ?></td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['gross'],2) ?></td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['epf_ee'],2) ?></td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['epf_er'],2) ?></td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['etf'],2) ?></td>
<td class="px-4 py-3 text-right font-mono"><?= number_format($totals['deductions'],2) ?></td>
<td class="px-4 py-3 text-right font-mono text-emerald-700"><?= number_format($totals['net'],2) ?></td>
<td class="px-4 py-3"></td></tr>
<?php endif; ?>
<?php if(empty($payrolls)): ?><tr><td colspan="12" class="px-6 py-12 text-center text-gray-500">No payroll records found for this period.</td></tr><?php endif; ?>
</tbody></table>
<?php if(!$isPrint): ?></div></div><?php endif; ?>

<?php if($isPrint): ?>
<div style="margin-top:30px;text-align:center;color:#9ca3af;font-size:10px;border-top:1px solid #e5e7eb;padding-top:10px;">
Generated by HRMS — E Track Biz (Pvt) Ltd | <?= date('F d, Y h:i A') ?></div></body></html>
<?php else: ?></div></main></body></html><?php endif; ?>
