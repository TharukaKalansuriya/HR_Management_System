<?php
require_once 'auth_check.php';
$required_page = 'payroll.php';
require_once 'db_config.php';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$export = isset($_GET['export']) ? $_GET['export'] : '';

$pay_period = date('F', mktime(0,0,0,$month,1)) . ' ' . $year;

// Fetch all payroll records for the target period
$stmt = $pdo->prepare("
    SELECT pr.*, 
           e.first_name, e.last_name, e.full_name, e.nic_no,
           e.bank_acc_no, e.bank_name, e.bank_branch, e.bank_acc_owner
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE pr.pay_month = ? AND pr.pay_year = ? AND pr.status IN ('Processed', 'Paid')
    ORDER BY e.bank_name, e.first_name
");
$stmt->execute([$month, $year]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If requested export CSV directly
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=E_Track_Biz_Bank_Transfer_' . $year . '_' . sprintf('%02d', $month) . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV Headers
    fputcsv($output, [
        'Company Name',
        'Pay Period',
        'Employee Name',
        'NIC Number',
        'Bank Name',
        'Branch Name',
        'Account Number',
        'Beneficiary Name',
        'Transfer Amount (Rs.)'
    ]);
    
    foreach ($records as $r) {
        $emp_name = !empty($r['full_name']) ? $r['full_name'] : $r['first_name'].' '.$r['last_name'];
        $owner    = !empty($r['bank_acc_owner']) ? $r['bank_acc_owner'] : $emp_name;
        
        fputcsv($output, [
            'E Track Biz Pvt Ltd',
            $pay_period,
            $emp_name,
            $r['nic_no'] ?: 'N/A',
            $r['bank_name'] ?: 'Not Configured',
            $r['bank_branch'] ?: 'N/A',
            "'" . ($r['bank_acc_no'] ?: '000000'), // Prefix quote to prevent excel stripping leading zeros
            $owner,
            number_format($r['net_salary'], 2, '.', '')
        ]);
    }
    fclose($output);
    exit;
}

$page_title = "Bank Transfer Advice - " . $pay_period;
include 'header.php';
include 'sidebar.php';

$total_disbursement = array_sum(array_column($records, 'net_salary'));
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Bank Transfer Advices</h1>
                <p class="text-sm text-gray-500 mt-1">Review bank routing rows and export disbursement files for direct deposit processing.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="payroll.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="text-brand-600 hover:text-brand-800 text-sm font-medium flex items-center gap-1 mr-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Payroll Overview
                </a>
                <?php if(count($records) > 0): ?>
                <a href="bank_transfer.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv" 
                   class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-5 rounded-xl shadow-md transition-all flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-file-csv"></i> Download CSV File
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Entity Disburser Info Box -->
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-2xl p-6 text-white mb-6 flex flex-wrap items-center justify-between gap-4 shadow-md">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 bg-white/10 rounded-xl flex items-center justify-center text-brand-400 text-xl font-bold border border-white/10">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <span class="text-2xs text-slate-400 uppercase tracking-widest font-bold">Disbursing Entity</span>
                    <h2 class="text-lg font-bold text-white tracking-wide">E Track Biz Pvt Ltd</h2>
                    <p class="text-xs text-slate-300">Period: <strong class="text-brand-300"><?php echo $pay_period; ?></strong></p>
                </div>
            </div>
            <div class="text-right bg-white/5 py-3 px-6 rounded-xl border border-white/10">
                <p class="text-xs text-slate-400">Total File Payout</p>
                <p class="text-xl font-mono font-bold text-emerald-400">Rs. <?php echo number_format($total_disbursement, 2); ?></p>
            </div>
        </div>

        <!-- Records Listing -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Generated Routing List</span>
                <span class="text-xs text-gray-500 font-medium"><?php echo count($records); ?> direct deposit rows</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/30">
                        <tr>
                            <th class="px-6 py-3.5 font-medium">Beneficiary / Employee</th>
                            <th class="px-6 py-3.5 font-medium">NIC Number</th>
                            <th class="px-6 py-3.5 font-medium">Bank Name</th>
                            <th class="px-6 py-3.5 font-medium">Branch</th>
                            <th class="px-6 py-3.5 font-medium">Account Number</th>
                            <th class="px-6 py-3.5 font-medium text-right">Net Transfer (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(count($records) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                No processed or paid payroll records available for this cycle. Process payroll runs first.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($records as $r): 
                            $emp_name = !empty($r['full_name']) ? $r['full_name'] : $r['first_name'].' '.$r['last_name'];
                            $owner    = !empty($r['bank_acc_owner']) ? $r['bank_acc_owner'] : $emp_name;
                        ?>
                        <tr class="hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-gray-800 text-xs"><?php echo htmlspecialchars($owner); ?></p>
                                <?php if($owner !== $emp_name): ?>
                                <p class="text-2xs text-gray-400">Emp: <?php echo htmlspecialchars($emp_name); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500"><?php echo htmlspecialchars($r['nic_no'] ?: 'N/A'); ?></td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-medium <?php echo empty($r['bank_name']) ? 'text-amber-600 bg-amber-50 px-2 py-0.5 rounded' : 'text-gray-700'; ?>">
                                    <?php echo htmlspecialchars($r['bank_name'] ?: 'Not Configured'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600"><?php echo htmlspecialchars($r['bank_branch'] ?: 'N/A'); ?></td>
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-800">
                                <?php echo htmlspecialchars($r['bank_acc_no'] ?: 'Missing Account'); ?>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-emerald-600">
                                Rs. <?php echo number_format($r['net_salary'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
</body>
</html>
