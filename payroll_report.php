<?php
require_once 'auth_check.php';
$required_page = 'payroll.php';
require_once 'db_config.php';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$dept_filter = isset($_GET['department']) ? trim($_GET['department']) : '';

$pay_period = date('F', mktime(0,0,0,$month,1)) . ' ' . $year;

// Query distinct departments for dropdown
$depts = $pdo->query("SELECT DISTINCT department FROM employees WHERE department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Fetch summary metrics
$where_clause = "WHERE pr.pay_month = ? AND pr.pay_year = ? AND pr.status IN ('Processed', 'Paid')";
$params = [$month, $year];

if (!empty($dept_filter)) {
    $where_clause .= " AND e.department = ?";
    $params[] = $dept_filter;
}

$stmt = $pdo->prepare("
    SELECT pr.*, e.first_name, e.last_name, e.department, e.designation, e.position
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    $where_clause
    ORDER BY e.department, e.first_name
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculation totals
$tot_basic      = array_sum(array_column($records, 'basic_salary'));
$tot_allowances = array_sum(array_map(function($r){
    return $r['housing_allowance'] + $r['transport_allowance'] + $r['medical_allowance'] + $r['other_allowances'];
}, $records));
$tot_ot         = array_sum(array_column($records, 'overtime_pay'));
$tot_incentives = array_sum(array_column($records, 'incentives_bonuses'));
$tot_gross      = array_sum(array_column($records, 'gross_salary'));
$tot_deductions = array_sum(array_column($records, 'total_deductions'));
$tot_net        = array_sum(array_column($records, 'net_salary'));

// Group totals by department for breakdown visual
$dept_summary = [];
foreach ($records as $r) {
    $d = $r['department'] ?: 'General';
    if (!isset($dept_summary[$d])) {
        $dept_summary[$d] = ['count' => 0, 'gross' => 0, 'ded' => 0, 'net' => 0];
    }
    $dept_summary[$d]['count']++;
    $dept_summary[$d]['gross'] += $r['gross_salary'];
    $dept_summary[$d]['ded']   += $r['total_deductions'];
    $dept_summary[$d]['net']   += $r['net_salary'];
}

$page_title = "Payroll Master Report - " . $pay_period;
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <!-- Header Actions -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6 no-print">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Master Payroll Analytics Report</h1>
                <p class="text-sm text-gray-500 mt-1">Detailed period-based enterprise expenditure breakdown and compensation metrics.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="payroll.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="text-brand-600 hover:text-brand-800 text-sm font-medium flex items-center gap-1 mr-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Payroll Overview
                </a>
                <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-4 rounded-xl shadow-md transition-all flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-print"></i> Print Executive Report
                </button>
            </div>
        </div>

        <!-- Filter banner -->
        <form method="GET" class="mb-8 no-print bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm flex flex-wrap gap-4 items-end justify-between">
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Month</label>
                    <select name="month" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                        <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>><?php echo date('F',mktime(0,0,0,$m,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Year</label>
                    <select name="year" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                        <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Department Filter</label>
                    <select name="department" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                        <option value="">All Departments</option>
                        <?php foreach($depts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $dept_filter===$d?'selected':''; ?>><?php echo htmlspecialchars($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2 px-5 rounded-lg text-sm transition-colors">
                    Apply Filter
                </button>
            </div>
            <div class="text-right">
                <span class="text-xs bg-brand-50 text-brand-700 font-bold px-3 py-1 rounded-full border border-brand-100">
                    E Track Biz Pvt Ltd
                </span>
            </div>
        </form>

        <style>
            @media print {
                .no-print { display: none !important; }
                body { background: white !important; }
                main { padding: 0 !important; background: white !important; }
            }
        </style>

        <!-- Printable Document Title Header -->
        <div class="text-center pb-6 border-b-2 border-gray-800 mb-8">
            <h2 class="text-2xl font-black uppercase text-gray-900 tracking-tight">E Track Biz Pvt Ltd</h2>
            <p class="text-xs font-bold text-brand-700 mt-0.5 uppercase tracking-widest">Master Period Payroll Summary Statement</p>
            <p class="text-sm text-gray-600 mt-1 font-medium">Reporting Cycle: <strong class="text-gray-900"><?php echo $pay_period; ?></strong> <?php echo !empty($dept_filter) ? " | Dept: " . htmlspecialchars($dept_filter) : ""; ?></p>
        </div>

        <!-- Expenditures Master Metrics Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                <p class="text-xs font-semibold text-gray-500 uppercase">Gross Payout Total</p>
                <p class="text-xl font-bold font-mono text-gray-900 mt-1">Rs. <?php echo number_format($tot_gross, 2); ?></p>
                <p class="text-2xs text-gray-400 mt-1">Basic + Allowances + OT + Incentives</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                <p class="text-xs font-semibold text-gray-500 uppercase">Total Allowances & OT</p>
                <p class="text-xl font-bold font-mono text-blue-600 mt-1">Rs. <?php echo number_format($tot_allowances + $tot_ot, 2); ?></p>
                <p class="text-2xs text-gray-400 mt-1">Includes attendance OT pay</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                <p class="text-xs font-semibold text-gray-500 uppercase">Total File Deductions</p>
                <p class="text-xl font-bold font-mono text-red-600 mt-1">- Rs. <?php echo number_format($tot_deductions, 2); ?></p>
                <p class="text-2xs text-gray-400 mt-1">EPF + Tax + No-Pay + Loans</p>
            </div>
            <div class="bg-emerald-50 p-5 rounded-2xl border border-emerald-100 shadow-sm">
                <p class="text-xs font-bold text-emerald-800 uppercase">Total Net Bank Disbursed</p>
                <p class="text-xl font-bold font-mono text-emerald-700 mt-1">Rs. <?php echo number_format($tot_net, 2); ?></p>
                <p class="text-2xs text-emerald-600 mt-1"><?php echo count($records); ?> active pay records mapped</p>
            </div>
        </div>

        <!-- Department Aggregate Distribution Panel -->
        <?php if(empty($dept_filter) && count($dept_summary) > 0): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Department Financial Overview Distributions</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach($dept_summary as $d => $stat): ?>
                <div class="border border-gray-100 rounded-xl p-4 bg-gray-50/30 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($d); ?></span>
                            <span class="text-2xs font-bold px-2 py-0.5 rounded bg-gray-200 text-gray-700"><?php echo $stat['count']; ?> Emp</span>
                        </div>
                        <div class="space-y-1 text-xs mt-3">
                            <div class="flex justify-between text-gray-600"><span>Gross:</span> <span class="font-mono">Rs. <?php echo number_format($stat['gross'],2); ?></span></div>
                            <div class="flex justify-between text-red-600"><span>Deductions:</span> <span class="font-mono">-Rs. <?php echo number_format($stat['ded'],2); ?></span></div>
                        </div>
                    </div>
                    <div class="mt-4 pt-2 border-t border-gray-200 flex justify-between text-xs font-bold text-emerald-700">
                        <span>Net Allocation:</span> <span class="font-mono">Rs. <?php echo number_format($stat['net'],2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detailed Ledger Breakdown Listing -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Itemized Employee Master Roll</h3>
                <span class="text-xs text-gray-500 font-mono"><?php echo count($records); ?> Employees</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase font-semibold">
                        <tr>
                            <th class="p-3 border-b border-gray-200">Employee Name</th>
                            <th class="p-3 border-b border-gray-200">Department</th>
                            <th class="p-3 border-b border-gray-200 text-right">Basic Pay</th>
                            <th class="p-3 border-b border-gray-200 text-right">Allowances</th>
                            <th class="p-3 border-b border-gray-200 text-right">OT Pay</th>
                            <th class="p-3 border-b border-gray-200 text-right">Incentives</th>
                            <th class="p-3 border-b border-gray-200 text-right font-bold text-gray-900">Gross</th>
                            <th class="p-3 border-b border-gray-200 text-right text-red-600">Deductions</th>
                            <th class="p-3 border-b border-gray-200 text-right font-bold text-emerald-700">Net Pay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(count($records)===0): ?>
                        <tr><td colspan="9" class="p-8 text-center text-gray-400">No matching processed records found.</td></tr>
                        <?php endif; ?>
                        <?php foreach($records as $r): 
                            $name  = $r['first_name'].' '.$r['last_name'];
                            $allow = $r['housing_allowance'] + $r['transport_allowance'] + $r['medical_allowance'] + $r['other_allowances'];
                        ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="p-3 font-semibold text-gray-800"><?php echo htmlspecialchars($name); ?></td>
                            <td class="p-3 text-gray-600"><?php echo htmlspecialchars($r['department']); ?></td>
                            <td class="p-3 text-right font-mono text-gray-700">Rs. <?php echo number_format($r['basic_salary'], 2); ?></td>
                            <td class="p-3 text-right font-mono text-gray-500">Rs. <?php echo number_format($allow, 2); ?></td>
                            <td class="p-3 text-right font-mono <?php echo $r['overtime_pay'] > 0 ? 'text-emerald-600 font-medium' : 'text-gray-400'; ?>">
                                Rs. <?php echo number_format($r['overtime_pay'], 2); ?>
                            </td>
                            <td class="p-3 text-right font-mono <?php echo $r['incentives_bonuses'] > 0 ? 'text-brand-600 font-medium' : 'text-gray-400'; ?>">
                                Rs. <?php echo number_format($r['incentives_bonuses'], 2); ?>
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-gray-900 bg-gray-50/40">Rs. <?php echo number_format($r['gross_salary'], 2); ?></td>
                            <td class="p-3 text-right font-mono text-red-600">-Rs. <?php echo number_format($r['total_deductions'], 2); ?></td>
                            <td class="p-3 text-right font-mono font-bold text-emerald-700 bg-emerald-50/20">Rs. <?php echo number_format($r['net_salary'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($records)>0): ?>
                        <tr class="bg-gray-100 font-bold text-gray-900 border-t-2 border-gray-300">
                            <td colspan="2" class="p-3 text-right uppercase text-2xs tracking-wider">Report Column Totals</td>
                            <td class="p-3 text-right font-mono">Rs. <?php echo number_format($tot_basic, 2); ?></td>
                            <td class="p-3 text-right font-mono">Rs. <?php echo number_format($tot_allowances, 2); ?></td>
                            <td class="p-3 text-right font-mono text-emerald-700">Rs. <?php echo number_format($tot_ot, 2); ?></td>
                            <td class="p-3 text-right font-mono text-brand-700">Rs. <?php echo number_format($tot_incentives, 2); ?></td>
                            <td class="p-3 text-right font-mono text-gray-900">Rs. <?php echo number_format($tot_gross, 2); ?></td>
                            <td class="p-3 text-right font-mono text-red-600">-Rs. <?php echo number_format($tot_deductions, 2); ?></td>
                            <td class="p-3 text-right font-mono text-emerald-700 bg-emerald-200/50">Rs. <?php echo number_format($tot_net, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-12 text-center text-2xs text-gray-400 italic">
            End of Enterprise Period Master Summary &bull; System Assured File Integrity
        </div>

    </div>
</main>
</body>
</html>
