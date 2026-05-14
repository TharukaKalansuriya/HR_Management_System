<?php
require_once 'auth_check.php';
$required_page = 'payroll_run.php';
require_once 'db_config.php';
$page_title = "Run Payroll";

$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filter_year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$pay_period   = date('F', mktime(0,0,0,$filter_month,1)) . ' ' . $filter_year;

// Handle form submit (Final Process)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $month = (int)$_POST['pay_month'];
    $year  = (int)$_POST['pay_year'];
    $pp    = date('F', mktime(0,0,0,$month,1)) . ' ' . $year;

    // Get all active employees WITH salary structures
    $stmt = $pdo->query("
        SELECT e.id AS employee_id, e.first_name, e.last_name,
               ss.basic_salary, ss.housing_allowance, ss.transport_allowance,
               ss.medical_allowance, ss.other_allowances, ss.incentives_bonuses, ss.overtime_rate,
               ss.epf_employee, ss.epf_employer, ss.etf, ss.tax_deduction, ss.apit_payee, ss.loan_deductions, ss.other_deductions
        FROM employees e
        JOIN salary_structures ss ON ss.employee_id = e.id
        WHERE e.status = 'Active'
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare dynamic query for Overtime Hours from attendance records
    $ot_stmt = $pdo->prepare("SELECT SUM(overtime_hours) FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");

    // Read customized manual inputs from array
    $custom_bonuses = $_POST['custom_bonuses'] ?? [];
    $nopay_deductions = $_POST['nopay_deductions'] ?? [];

    // Delete existing drafts/runs for this period if requested to recalculate cleanly
    $del = $pdo->prepare("DELETE FROM payroll_runs WHERE pay_month = ? AND pay_year = ? AND status = 'Draft'");
    $del->execute([$month, $year]);

    $ins = $pdo->prepare("
        INSERT INTO payroll_runs
            (employee_id, pay_period, pay_month, pay_year,
             basic_salary, housing_allowance, transport_allowance, medical_allowance, other_allowances,
             overtime_hours, overtime_pay, incentives_bonuses,
             gross_salary, epf_employee, epf_employer, etf, tax_deduction, apit_payee, nopay_deductions, loan_deductions, other_deductions,
             total_deductions, net_salary, status)
        VALUES
            (:eid, :pp, :month, :year,
             :basic, :housing, :transport, :medical, :other_a,
             :ot_hrs, :ot_pay, :incentives,
             :gross, :epf_e, :epf_er, :etf, :tax, :apit, :nopay, :loan, :other_d,
             :total_ded, :net, 'Processed')
    ");

    foreach ($employees as $e) {
        $eid = $e['employee_id'];
        
        // Compute Overtime dynamically
        $ot_stmt->execute([$eid, $month, $year]);
        $ot_hrs = (float)$ot_stmt->fetchColumn();
        
        // Determine hourly overtime pay multiplier rate
        $ot_rate = (float)$e['overtime_rate'];
        if ($ot_rate <= 0 && $e['basic_salary'] > 0) {
            // Standard assumption: Base salary / 160 hours * 1.5 standard rate
            $ot_rate = round(($e['basic_salary'] / 160) * 1.5, 2);
        }
        $ot_pay = round($ot_hrs * $ot_rate, 2);

        // Fetch custom manual overrides
        $extra_bonus = isset($custom_bonuses[$eid]) ? (float)$custom_bonuses[$eid] : 0.00;
        $nopay_amt   = isset($nopay_deductions[$eid]) ? (float)$nopay_deductions[$eid] : 0.00;

        $total_incentives = $e['incentives_bonuses'] + $extra_bonus;
        $allowances = $e['housing_allowance'] + $e['transport_allowance'] + $e['medical_allowance'] + $e['other_allowances'];
        
        $gross = $e['basic_salary'] + $allowances + $ot_pay + $total_incentives;
        
        $epf_e_amt  = round($e['basic_salary'] * ($e['epf_employee'] / 100), 2);
        $epf_er_amt = round($e['basic_salary'] * ($e['epf_employer'] / 100), 2);
        $etf_amt    = round($e['basic_salary'] * ($e['etf'] / 100), 2);
        
        $total_ded  = $epf_e_amt + $e['tax_deduction'] + $e['apit_payee'] + $nopay_amt + $e['loan_deductions'] + $e['other_deductions'];
        $net        = $gross - $total_ded;

        $ins->execute([
            ':eid'      => $eid,
            ':pp'       => $pp,
            ':month'    => $month,
            ':year'     => $year,
            ':basic'    => $e['basic_salary'],
            ':housing'  => $e['housing_allowance'],
            ':transport'=> $e['transport_allowance'],
            ':medical'  => $e['medical_allowance'],
            ':other_a'  => $e['other_allowances'],
            ':ot_hrs'   => $ot_hrs,
            ':ot_pay'   => $ot_pay,
            ':incentives'=> $total_incentives,
            ':gross'    => $gross,
            ':epf_e'    => $epf_e_amt,
            ':epf_er'   => $epf_er_amt,
            ':etf'      => $etf_amt,
            ':tax'      => $e['tax_deduction'],
            ':apit'     => $e['apit_payee'],
            ':nopay'    => $nopay_amt,
            ':loan'     => $e['loan_deductions'],
            ':other_d'  => $e['other_deductions'],
            ':total_ded'=> $total_ded,
            ':net'      => $net,
        ]);
    }

    header("Location: payroll.php?msg=run_success&month=$month&year=$year");
    exit;
}

// Interactively selected view/preview target period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    $filter_month = (int)$_POST['pay_month'];
    $filter_year  = (int)$_POST['pay_year'];
    $pay_period   = date('F', mktime(0,0,0,$filter_month,1)) . ' ' . $filter_year;
}

// Preview query: active employees with configured structure
$preview = $pdo->query("
    SELECT e.id, e.first_name, e.last_name, e.department, e.position,
           ss.basic_salary, ss.housing_allowance, ss.transport_allowance,
           ss.medical_allowance, ss.other_allowances, ss.incentives_bonuses, ss.overtime_rate,
           ss.epf_employee, ss.tax_deduction, ss.apit_payee, ss.loan_deductions, ss.other_deductions
    FROM employees e
    JOIN salary_structures ss ON ss.employee_id = e.id
    WHERE e.status='Active'
    ORDER BY e.department, e.first_name
")->fetchAll(PDO::FETCH_ASSOC);

$no_structure = $pdo->query("
    SELECT e.first_name, e.last_name, e.department
    FROM employees e
    LEFT JOIN salary_structures ss ON ss.employee_id = e.id
    WHERE e.status='Active' AND ss.id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare attendance statement for rendering preview OT hours dynamically
$ot_stmt = $pdo->prepare("SELECT SUM(overtime_hours) FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?");

include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Run Payroll Engine</h1>
                <p class="text-sm text-gray-500 mt-1">Dynamically process pay cycles incorporating live attendance overtime and adjustable extras.</p>
            </div>
            <a href="payroll.php" class="text-brand-600 hover:text-brand-800 text-sm font-medium flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Back to Payroll
            </a>
        </div>

        <!-- Period Selection Form -->
        <form method="POST" class="mb-6">
            <input type="hidden" name="action" value="preview">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-wrap gap-4 items-end justify-between">
                <div class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Target Month</label>
                        <select name="pay_month" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                            <?php for($m=1;$m<=12;$m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m==$filter_month?'selected':''; ?>>
                                <?php echo date('F',mktime(0,0,0,$m,1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Target Year</label>
                        <select name="pay_year" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                            <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y==$filter_year?'selected':''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                        🔄 Refresh Preview Data
                    </button>
                </div>
                <span class="text-xs bg-gray-100 text-gray-600 py-1.5 px-3 rounded-full font-medium">
                    Showing status mapping for: <strong><?php echo $pay_period; ?></strong>
                </span>
            </div>
        </form>

        <!-- Warning list -->
        <?php if(count($no_structure) > 0): ?>
        <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-800 p-4 mb-6 rounded-r shadow-sm">
            <p class="font-semibold mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i> <?php echo count($no_structure); ?> employee(s) will be skipped (missing salary structure defaults):</p>
            <ul class="list-disc list-inside text-sm space-y-0.5">
                <?php foreach($no_structure as $n): ?>
                <li><?php echo htmlspecialchars($n['first_name'].' '.$n['last_name']); ?> — <?php echo htmlspecialchars($n['department']); ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="salary_structures.php" class="inline-block mt-2 text-sm font-medium underline">Configure default salary structures →</a>
        </div>
        <?php endif; ?>

        <!-- Processing Submission Form -->
        <form method="POST" action="payroll_run.php">
            <input type="hidden" name="action" value="process">
            <input type="hidden" name="pay_month" value="<?php echo $filter_month; ?>">
            <input type="hidden" name="pay_year" value="<?php echo $filter_year; ?>">

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-white/50">
                    <h2 class="text-base font-semibold text-gray-800">
                        <i class="fa-solid fa-sliders mr-2 text-brand-500"></i>Interactive Compensation Adjustment — <?php echo $pay_period; ?>
                    </h2>
                    <span class="text-xs text-brand-600 font-bold bg-brand-50 py-1 px-3 rounded-full">Attendance-Driven Overtime Active</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-gray-500 uppercase bg-gray-50/70 tracking-wider">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Employee</th>
                                <th class="px-4 py-3 font-semibold text-right">Basic Pay</th>
                                <th class="px-3 py-3 font-semibold text-right">Allowances</th>
                                <th class="px-3 py-3 font-semibold text-center">Pulled OT Hours</th>
                                <th class="px-3 py-3 font-semibold text-right">Est. OT Pay</th>
                                <th class="px-3 py-3 font-semibold text-center">Extra Bonus</th>
                                <th class="px-3 py-3 font-semibold text-center">No-Pay Deduct</th>
                                <th class="px-4 py-3 font-semibold text-right">Statutory & Loans</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(count($preview)===0): ?>
                            <tr><td colspan="8" class="px-6 py-12 text-center text-gray-400">No active structures ready for computation.</td></tr>
                            <?php endif; ?>
                            <?php 
                            foreach($preview as $e):
                                $eid = $e['id'];
                                // Fetch OT dynamically
                                $ot_stmt->execute([$eid, $filter_month, $filter_year]);
                                $ot_hrs = (float)$ot_stmt->fetchColumn();
                                
                                $ot_rate = (float)$e['overtime_rate'];
                                if ($ot_rate <= 0 && $e['basic_salary'] > 0) {
                                    $ot_rate = round(($e['basic_salary'] / 160) * 1.5, 2);
                                }
                                $ot_pay = round($ot_hrs * $ot_rate, 2);

                                $allow = $e['housing_allowance'] + $e['transport_allowance'] + $e['medical_allowance'] + $e['other_allowances'];
                                $fixed_deductions = round($e['basic_salary'] * ($e['epf_employee'] / 100), 2) + $e['tax_deduction'] + $e['apit_payee'] + $e['loan_deductions'] + $e['other_deductions'];
                            ?>
                            <tr class="hover:bg-gray-50/40">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-7 w-7 bg-brand-100 rounded-full flex items-center justify-center text-brand-700 font-bold border border-brand-200 text-2xs flex-shrink-0">
                                            <?php echo strtoupper(substr($e['first_name'],0,1).substr($e['last_name'],0,1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800 text-xs"><?php echo htmlspecialchars($e['first_name'].' '.$e['last_name']); ?></p>
                                            <p class="text-2xs text-gray-400"><?php echo htmlspecialchars($e['department']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-gray-700 font-medium">Rs. <?php echo number_format($e['basic_salary'],2); ?></td>
                                <td class="px-3 py-3.5 text-right font-mono text-gray-500">Rs. <?php echo number_format($allow,2); ?></td>
                                <td class="px-3 py-3.5 text-center font-mono">
                                    <span class="<?php echo $ot_hrs > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold' : 'bg-gray-50 text-gray-400'; ?> py-1 px-2 rounded-md block">
                                        <?php echo number_format($ot_hrs, 1); ?> hrs
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-right font-mono text-emerald-600 font-medium">+Rs. <?php echo number_format($ot_pay,2); ?></td>
                                <td class="px-3 py-3.5 w-28">
                                    <input type="number" step="0.01" min="0" name="custom_bonuses[<?php echo $eid; ?>]" placeholder="Bonus Amt"
                                           class="w-full border border-gray-200 rounded-md px-2 py-1 text-xs text-center focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white"
                                           title="Default structured incentives: Rs. <?php echo number_format($e['incentives_bonuses'], 2); ?>">
                                </td>
                                <td class="px-3 py-3.5 w-28">
                                    <input type="number" step="0.01" min="0" name="nopay_deductions[<?php echo $eid; ?>]" placeholder="No-Pay Amt"
                                           class="w-full border border-gray-200 rounded-md px-2 py-1 text-xs text-center focus:outline-none focus:ring-1 focus:ring-red-500 bg-white">
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-red-600">-Rs. <?php echo number_format($fixed_deductions,2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submit Engine Action -->
            <?php if(count($preview)>0): ?>
            <div class="flex justify-end gap-3 items-center bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                <div class="text-right mr-3">
                    <p class="text-xs text-gray-500">Ready for statutory recording</p>
                    <p class="text-xs font-semibold text-gray-800">Outputs generated in real-time</p>
                </div>
                <button type="submit" onclick="return confirm('Process Final Payroll for <?php echo $pay_period; ?>? Automatic payslips, direct deposit export entries, and Form C/R contribution rows will be established.')"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-xl shadow-md transition-all flex items-center gap-2 hover:shadow-lg text-sm">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Execute Payroll Processing
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</main>
</body>
</html>
