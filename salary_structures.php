<?php
require_once 'auth_check.php';
$required_page = 'salary_structures.php';
require_once 'db_config.php';
$page_title = "Salary Structures";

// Fetch all active employees with their salary structure (if exists)
$stmt = $pdo->query("
    SELECT e.id, e.first_name, e.last_name, e.position, e.department,
           ss.id AS ss_id, ss.basic_salary, ss.housing_allowance, ss.transport_allowance,
           ss.medical_allowance, ss.other_allowances, ss.incentives_bonuses, ss.overtime_rate,
           ss.epf_employee, ss.epf_employer, ss.etf, ss.tax_deduction, ss.apit_payee, ss.loan_deductions, ss.other_deductions
    FROM employees e
    LEFT JOIN salary_structures ss ON ss.employee_id = e.id
    WHERE e.status = 'Active'
    ORDER BY e.department, e.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <?php if(isset($_GET['msg']) && $_GET['msg']=='saved'): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-r shadow-sm">
            <p class="font-medium">Saved!</p><p>Salary structure updated successfully.</p>
        </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Salary Structures</h1>
                <p class="text-sm text-gray-500 mt-1">Configure basic pay, allowances and deductions for each employee.</p>
            </div>
            <a href="payroll.php" class="text-brand-600 hover:text-brand-800 text-sm font-medium flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Back to Payroll
            </a>
        </div>

        <div class="space-y-4">
            <?php if(count($employees) === 0): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center text-gray-500">
                    <i class="fa-solid fa-users-slash text-4xl text-gray-300 mb-3"></i>
                    <p class="text-lg font-medium">No active employees found.</p>
                </div>
            <?php endif; ?>
            <?php foreach($employees as $emp):
                $gross = ($emp['basic_salary'] ?? 0) + ($emp['housing_allowance'] ?? 0) + ($emp['transport_allowance'] ?? 0) + ($emp['medical_allowance'] ?? 0) + ($emp['other_allowances'] ?? 0) + ($emp['incentives_bonuses'] ?? 0);
                $epf_emp_amt = ($emp['basic_salary'] ?? 0) * (($emp['epf_employee'] ?? 8) / 100);
                $total_ded = $epf_emp_amt + ($emp['tax_deduction'] ?? 0) + ($emp['apit_payee'] ?? 0) + ($emp['loan_deductions'] ?? 0) + ($emp['other_deductions'] ?? 0);
                $net = $gross - $total_ded;
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header -->
                <button onclick="toggleCard(<?php echo $emp['id']; ?>)" 
                        class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-brand-100 rounded-full flex items-center justify-center text-brand-700 font-bold border border-brand-200 text-sm">
                            <?php echo strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1)); ?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($emp['position']); ?> &bull; <?php echo htmlspecialchars($emp['department']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <?php if($emp['ss_id']): ?>
                            <div class="text-right hidden sm:block">
                                <p class="text-xs text-gray-500">Net Salary</p>
                                <p class="text-base font-bold text-emerald-600">Rs. <?php echo number_format($net, 2); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Configured</span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Not Set</span>
                        <?php endif; ?>
                        <i id="icon-<?php echo $emp['id']; ?>" class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-200"></i>
                    </div>
                </button>

                <!-- Form (collapsed by default) -->
                <div id="card-<?php echo $emp['id']; ?>" class="hidden border-t border-gray-100 p-6">
                    <form method="POST" action="salary_save.php">
                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                            <!-- Earnings -->
                            <div class="md:col-span-2 lg:col-span-3">
                                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-plus-circle text-emerald-500"></i> Earnings
                                </h3>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Basic Salary (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="basic_salary" value="<?php echo $emp['basic_salary'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Housing Allowance (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="housing_allowance" value="<?php echo $emp['housing_allowance'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Transport Allowance (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="transport_allowance" value="<?php echo $emp['transport_allowance'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Medical Allowance (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="medical_allowance" value="<?php echo $emp['medical_allowance'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Other Allowances (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="other_allowances" value="<?php echo $emp['other_allowances'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Incentives / Bonuses (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="incentives_bonuses" value="<?php echo $emp['incentives_bonuses'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Hourly Overtime Rate (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="overtime_rate" value="<?php echo $emp['overtime_rate'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="e.g. 500.00">
                            </div>

                            <!-- Deductions -->
                            <div class="md:col-span-2 lg:col-span-3 mt-2">
                                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-minus-circle text-red-500"></i> Deductions
                                </h3>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">EPF Employee % (default 8%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="epf_employee" value="<?php echo $emp['epf_employee'] ?? 8; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">EPF Employer % (default 12%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="epf_employer" value="<?php echo $emp['epf_employer'] ?? 12; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">ETF % (default 3%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="etf" value="<?php echo $emp['etf'] ?? 3; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">APIT / PAYEE Tax (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="apit_payee" value="<?php echo $emp['apit_payee'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Loan Deductions (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="loan_deductions" value="<?php echo $emp['loan_deductions'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Other Deductions / Misc (Rs.)</label>
                                <input type="number" step="0.01" min="0" name="other_deductions" value="<?php echo $emp['other_deductions'] ?? ''; ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="0.00">
                            </div>
                        </div>
                        <div class="flex justify-end mt-6 pt-4 border-t border-gray-100">
                            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg transition-colors flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk"></i> Save Structure
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<script>
function toggleCard(id) {
    const card = document.getElementById('card-' + id);
    const icon = document.getElementById('icon-' + id);
    card.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}
</script>
</body>
</html>
