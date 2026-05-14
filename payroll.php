<?php
require_once 'auth_check.php';
$required_page = 'payroll.php';
require_once 'db_config.php';
$page_title = "Payroll Management";

// Create payroll tables if they don't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS salary_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    housing_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    transport_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    incentives_bonuses DECIMAL(12,2) NOT NULL DEFAULT 0,
    overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    epf_employee DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    epf_employer DECIMAL(5,2) NOT NULL DEFAULT 12.00,
    etf DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    apit_payee DECIMAL(12,2) NOT NULL DEFAULT 0,
    loan_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee (employee_id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    pay_period VARCHAR(20) NOT NULL,
    pay_month INT NOT NULL,
    pay_year INT NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    housing_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    transport_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    overtime_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
    incentives_bonuses DECIMAL(12,2) NOT NULL DEFAULT 0,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    epf_employee DECIMAL(12,2) NOT NULL DEFAULT 0,
    epf_employer DECIMAL(12,2) NOT NULL DEFAULT 0,
    etf DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    apit_payee DECIMAL(12,2) NOT NULL DEFAULT 0,
    nopay_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    loan_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Draft','Processed','Paid') DEFAULT 'Draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_period (pay_year, pay_month)
)");

// Apply runtime migrations if tables already exist
if (function_exists('addColIfMissing')) {
    addColIfMissing($pdo, 'salary_structures', 'incentives_bonuses', "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER other_allowances");
    addColIfMissing($pdo, 'salary_structures', 'overtime_rate',      "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER incentives_bonuses");
    addColIfMissing($pdo, 'salary_structures', 'apit_payee',         "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction");
    addColIfMissing($pdo, 'salary_structures', 'loan_deductions',    "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER apit_payee");

    addColIfMissing($pdo, 'payroll_runs', 'overtime_hours',     "DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER other_allowances");
    addColIfMissing($pdo, 'payroll_runs', 'overtime_pay',       "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER overtime_hours");
    addColIfMissing($pdo, 'payroll_runs', 'incentives_bonuses', "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER overtime_pay");
    addColIfMissing($pdo, 'payroll_runs', 'apit_payee',         "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction");
    addColIfMissing($pdo, 'payroll_runs', 'nopay_deductions',   "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER apit_payee");
    addColIfMissing($pdo, 'payroll_runs', 'loan_deductions',    "DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER nopay_deductions");
}

// Filters
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filter_year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$where = "WHERE pr.pay_month = :month AND pr.pay_year = :year";
$params = [':month' => $filter_month, ':year' => $filter_year];
if ($filter_status !== '') {
    $where .= " AND pr.status = :status";
    $params[':status'] = $filter_status;
}

$stmt = $pdo->prepare("
    SELECT pr.*, e.first_name, e.last_name, e.department, e.position
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    $where
    ORDER BY e.first_name ASC
");
$stmt->execute($params);
$payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary totals
$totals = ['gross'=>0,'deductions'=>0,'net'=>0];
foreach ($payrolls as $p) {
    $totals['gross']      += $p['gross_salary'];
    $totals['deductions'] += $p['total_deductions'];
    $totals['net']        += $p['net_salary'];
}

// Count employees without salary structure
$emp_count = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Active'")->fetchColumn();
$struct_count = $pdo->query("SELECT COUNT(*) FROM salary_structures ss JOIN employees e ON e.id=ss.employee_id WHERE e.status='Active'")->fetchColumn();

include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <?php if(isset($_GET['msg'])): 
            $msgs = [
                'run_success' => ['bg-emerald-50','border-emerald-500','text-emerald-700','Payroll processed successfully for the selected period.'],
                'paid'        => ['bg-blue-50','border-blue-500','text-blue-700','Payroll status updated to Paid.'],
                'deleted'     => ['bg-red-50','border-red-500','text-red-700','Payroll record deleted.'],
            ];
            $m = $msgs[$_GET['msg']] ?? null;
            if ($m): ?>
            <div class="<?php echo $m[0]; ?> border-l-4 <?php echo $m[1]; ?> <?php echo $m[2]; ?> p-4 mb-6 rounded-r shadow-sm">
                <p><?php echo $m[3]; ?></p>
            </div>
        <?php endif; endif; ?>

        <!-- Outputs Action Ribbon -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <i class="fa-solid fa-layer-group text-brand-500"></i> Module Exports & Compliance
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="bank_transfer.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" 
                   class="bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold py-2 px-4 rounded-xl transition-all text-xs flex items-center gap-1.5 border border-brand-100">
                    <i class="fa-solid fa-building-columns"></i> Bank Transfer File
                </a>
                <a href="cr_forms.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" 
                   class="bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold py-2 px-4 rounded-xl transition-all text-xs flex items-center gap-1.5 border border-amber-100">
                    <i class="fa-solid fa-file-shield"></i> C & R Statutory Forms
                </a>
                <a href="payroll_report.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" 
                   class="bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold py-2 px-4 rounded-xl transition-all text-xs flex items-center gap-1.5 border border-slate-200">
                    <i class="fa-solid fa-chart-pie"></i> Master Payroll Report
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-blue-50 text-blue-600 mr-4"><i class="fa-solid fa-users text-xl"></i></div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Employees on Payroll</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($payrolls); ?></p>
                </div>
            </div>
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 mr-4"><i class="fa-solid fa-money-bill-wave text-xl"></i></div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Gross</p>
                    <p class="text-2xl font-bold text-gray-800">Rs. <?php echo number_format($totals['gross'],2); ?></p>
                </div>
            </div>
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-red-50 text-red-600 mr-4"><i class="fa-solid fa-circle-minus text-xl"></i></div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Deductions</p>
                    <p class="text-2xl font-bold text-gray-800">Rs. <?php echo number_format($totals['deductions'],2); ?></p>
                </div>
            </div>
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-purple-50 text-purple-600 mr-4"><i class="fa-solid fa-hand-holding-dollar text-xl"></i></div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Net Pay</p>
                    <p class="text-2xl font-bold text-gray-800">Rs. <?php echo number_format($totals['net'],2); ?></p>
                </div>
            </div>
        </div>

        <!-- Alert if some employees lack salary structures -->
        <?php if ($struct_count < $emp_count): ?>
        <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-800 p-4 mb-6 rounded-r shadow-sm flex items-center justify-between">
            <div><i class="fa-solid fa-triangle-exclamation mr-2"></i>
                <strong><?php echo ($emp_count - $struct_count); ?> active employee(s)</strong> don't have a salary structure set up yet.
            </div>
            <a href="salary_structures.php" class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium py-1.5 px-4 rounded-lg transition-colors">Setup Salaries</a>
        </div>
        <?php endif; ?>

        <!-- Actions Bar -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex flex-wrap gap-4 items-end">
            <form method="GET" class="flex flex-wrap gap-4 items-end flex-1">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Month</label>
                    <select name="month" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m==$filter_month?'selected':''; ?>><?php echo date('F',mktime(0,0,0,$m,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
                    <select name="year" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y==$filter_year?'selected':''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">All Statuses</option>
                        <option value="Draft"     <?php echo $filter_status=='Draft'?'selected':''; ?>>Draft</option>
                        <option value="Processed" <?php echo $filter_status=='Processed'?'selected':''; ?>>Processed</option>
                        <option value="Paid"      <?php echo $filter_status=='Paid'?'selected':''; ?>>Paid</option>
                    </select>
                </div>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
            </form>
            <div class="flex gap-3">
                <a href="salary_structures.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-sliders"></i> Salary Setup
                </a>
                <a href="payroll_run.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-play"></i> Run Payroll
                </a>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-sm">
                <h2 class="text-lg font-semibold text-gray-800">
                    Payroll — <?php echo date('F', mktime(0,0,0,$filter_month,1)); ?> <?php echo $filter_year; ?>
                </h2>
                <?php if(count($payrolls)>0): ?>
                <a href="payroll_export.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" 
                   class="text-sm text-brand-600 hover:text-brand-800 font-medium flex items-center gap-1 transition-colors">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-4 font-medium">Employee</th>
                            <th class="px-6 py-4 font-medium">Department</th>
                            <th class="px-6 py-4 font-medium text-right">Basic</th>
                            <th class="px-6 py-4 font-medium text-right">Gross</th>
                            <th class="px-6 py-4 font-medium text-right">Deductions</th>
                            <th class="px-6 py-4 font-medium text-right">Net Pay</th>
                            <th class="px-6 py-4 font-medium text-center">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(count($payrolls) > 0): ?>
                            <?php foreach($payrolls as $p): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-9 w-9 flex-shrink-0 bg-brand-100 rounded-full flex items-center justify-center text-brand-700 font-bold mr-3 border border-brand-200 text-xs">
                                            <?php echo strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($p['first_name'].' '.$p['last_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($p['position']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($p['department']); ?></td>
                                <td class="px-6 py-4 text-gray-700 text-right font-mono">Rs. <?php echo number_format($p['basic_salary'],2); ?></td>
                                <td class="px-6 py-4 text-gray-700 text-right font-mono">Rs. <?php echo number_format($p['gross_salary'],2); ?></td>
                                <td class="px-6 py-4 text-red-600 text-right font-mono">- Rs. <?php echo number_format($p['total_deductions'],2); ?></td>
                                <td class="px-6 py-4 text-emerald-700 text-right font-mono font-semibold">Rs. <?php echo number_format($p['net_salary'],2); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($p['status']=='Paid'): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Paid</span>
                                    <?php elseif($p['status']=='Processed'): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Processed</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="payslip.php?id=<?php echo $p['id']; ?>" 
                                           class="text-brand-600 hover:bg-brand-50 p-2 rounded-lg transition-colors border border-transparent hover:border-brand-200" title="View Payslip">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                        <?php if($p['status']=='Processed'): ?>
                                        <a href="payroll_action.php?action=mark_paid&id=<?php echo $p['id']; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>"
                                           class="text-emerald-600 hover:bg-emerald-50 p-2 rounded-lg transition-colors border border-transparent hover:border-emerald-200" title="Mark as Paid"
                                           onclick="return confirm('Mark this payslip as Paid?')">
                                            <i class="fa-solid fa-check-circle"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="payroll_action.php?action=delete&id=<?php echo $p['id']; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>"
                                           class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors border border-transparent hover:border-red-200" title="Delete"
                                           onclick="return confirm('Delete this payroll record?')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fa-solid fa-file-invoice-dollar text-3xl text-gray-300"></i>
                                        </div>
                                        <p class="text-lg font-medium text-gray-700">No payroll records found</p>
                                        <p class="text-sm mt-1 mb-6 text-gray-500">Run payroll to generate records for this period.</p>
                                        <a href="payroll_run.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" 
                                           class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition-colors flex items-center gap-2">
                                            <i class="fa-solid fa-play"></i> Run Payroll Now
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
