<?php
require_once 'auth_check.php';
$required_page = 'payroll.php';
require_once 'db_config.php';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$export = isset($_GET['export']) ? $_GET['export'] : '';

$pay_period = date('F', mktime(0,0,0,$month,1)) . ' ' . $year;

// Query all contributing processed/paid payroll runs for the period
$stmt = $pdo->prepare("
    SELECT pr.*, 
           e.first_name, e.last_name, e.full_name, e.nic_no, e.epf_number
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE pr.pay_month = ? AND pr.pay_year = ? AND pr.status IN ('Processed', 'Paid')
    ORDER BY CAST(e.epf_number AS UNSIGNED), e.first_name
");
$stmt->execute([$month, $year]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summaries
$total_members = count($records);
$total_earnings = array_sum(array_column($records, 'basic_salary')); // Standard EPF base is basic pay or custom gross
$total_epf_employee = array_sum(array_column($records, 'epf_employee'));
$total_epf_employer = array_sum(array_column($records, 'epf_employer'));
$total_remittance   = $total_epf_employee + $total_epf_employer;

// CSV Export for Form C Member return listing
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Form_C_EPF_Returns_' . $year . '_' . sprintf('%02d', $month) . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['E Track Biz Pvt Ltd - Statutory Form C Member Return Listing', 'Period: ' . $pay_period]);
    fputcsv($output, [
        'EPF Member Number',
        'Member Name',
        'NIC Number',
        'Total Earnings (Rs.)',
        'Member Contribution 8% (Rs.)',
        'Employer Contribution 12% (Rs.)',
        'Total EPF Remitted 20% (Rs.)'
    ]);
    
    foreach ($records as $r) {
        $name = !empty($r['full_name']) ? $r['full_name'] : $r['first_name'].' '.$r['last_name'];
        $member_epf = $r['epf_employee'];
        $employer_epf = $r['epf_employer'];
        $comb = $member_epf + $employer_epf;
        
        fputcsv($output, [
            $r['epf_number'] ?: 'N/A',
            $name,
            $r['nic_no'] ?: 'N/A',
            number_format($r['basic_salary'], 2, '.', ''),
            number_format($member_epf, 2, '.', ''),
            number_format($employer_epf, 2, '.', ''),
            number_format($comb, 2, '.', '')
        ]);
    }
    fclose($output);
    exit;
}

$page_title = "Statutory C & R Forms - " . $pay_period;
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <!-- Toolbar (Hidden in Print) -->
        <div class="flex items-center justify-between mb-6 no-print">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Statutory Filing Forms (C & R)</h1>
                <p class="text-sm text-gray-500 mt-1">Formatted monthly contribution returns for EPF statutory remitting compliance.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="payroll.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="text-brand-600 hover:text-brand-800 text-sm font-medium flex items-center gap-1 mr-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Payroll Overview
                </a>
                <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-4 rounded-xl shadow-md transition-all flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-print"></i> Print Official Forms
                </button>
                <?php if($total_members > 0): ?>
                <a href="cr_forms.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&export=csv" 
                   class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-md transition-all flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-file-csv"></i> Download Form C (CSV)
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Switcher Buttons for Live Screen Preview -->
        <div class="flex gap-2 mb-6 no-print bg-white p-1.5 rounded-xl border border-gray-200/80 max-w-md">
            <button onclick="switchTab('form_c')" id="btn_form_c" class="flex-1 py-2 px-4 rounded-lg font-bold text-xs transition-all bg-brand-600 text-white shadow-sm">
                📄 Form C (Member Listing)
            </button>
            <button onclick="switchTab('form_r')" id="btn_form_r" class="flex-1 py-2 px-4 rounded-lg font-bold text-xs transition-all text-gray-600 hover:bg-gray-50">
                📑 Form R (Summary Cover)
            </button>
        </div>

        <style>
            @media print {
                .no-print { display: none !important; }
                .print-visible-all { display: block !important; page-break-after: always; }
                body { background: white !important; }
                main { padding: 0 !important; background: white !important; }
                .custom-scrollbar { padding: 0 !important; overflow: visible !important; }
            }
        </style>

        <script>
            function switchTab(tab) {
                if (tab === 'form_c') {
                    document.getElementById('sec_form_c').classList.remove('hidden');
                    document.getElementById('sec_form_r').classList.add('hidden');
                    document.getElementById('btn_form_c').className = "flex-1 py-2 px-4 rounded-lg font-bold text-xs transition-all bg-brand-600 text-white shadow-sm";
                    document.getElementById('btn_form_r').className = "flex-1 py-2 px-4 rounded-lg font-bold text-xs transition-all text-gray-600 hover:bg-gray-50";
                } else {
                    document.getElementById('sec_form_c').classList.add('hidden');
                    document.getElementById('sec_form_r').classList.remove('hidden');
                    document.getElementById('btn_form_r').className = "flex-1 py-2 px-4 rounded-lg font-bold text-xs transition-all bg-brand-600 text-white shadow-sm";
                    document.getElementById('btn_form_c').className = "flex-1 py-2 px-4 rounded-lg font-bold text-xs transition-all text-gray-600 hover:bg-gray-50";
                }
            }
        </script>

        <!-- FORM C Section -->
        <div id="sec_form_c" class="print-visible-all bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-8">
            
            <!-- Standard Entity Header -->
            <div class="text-center pb-6 border-b-2 border-gray-800">
                <span class="text-xs font-bold px-3 py-1 bg-gray-100 text-gray-800 rounded border border-gray-300 inline-block mb-2">
                    EMPLOYEES' PROVIDENT FUND — FORM C
                </span>
                <h2 class="text-xl font-bold uppercase tracking-wide text-gray-900">E Track Biz Pvt Ltd</h2>
                <p class="text-xs text-gray-600 mt-1">Monthly Return of Contributions under Section 15 of the EPF Act</p>
                <div class="flex justify-between items-center mt-4 text-xs font-semibold text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-200 text-left">
                    <div>Employer Reg No: <strong class="font-mono text-brand-700">#EPF-ETB-9982</strong></div>
                    <div>Contribution Period: <strong class="text-brand-700"><?php echo $pay_period; ?></strong></div>
                    <div>Total Members: <strong class="font-mono"><?php echo $total_members; ?></strong></div>
                </div>
            </div>

            <!-- Members Table -->
            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse border border-gray-200">
                    <thead class="bg-gray-100 text-gray-800 uppercase font-semibold">
                        <tr>
                            <th class="p-2.5 border border-gray-200 text-center w-16">Sr.</th>
                            <th class="p-2.5 border border-gray-200 w-24">EPF No.</th>
                            <th class="p-2.5 border border-gray-200">Member Name</th>
                            <th class="p-2.5 border border-gray-200 w-32">NIC Number</th>
                            <th class="p-2.5 border border-gray-200 text-right w-28">Total Earnings</th>
                            <th class="p-2.5 border border-gray-200 text-right w-28">Member (8%)</th>
                            <th class="p-2.5 border border-gray-200 text-right w-28">Employer (12%)</th>
                            <th class="p-2.5 border border-gray-200 text-right w-28">Combined (20%)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if($total_members === 0): ?>
                        <tr><td colspan="8" class="p-8 text-center text-gray-400">No active contributor records found for this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach($records as $idx => $r): 
                            $name = !empty($r['full_name']) ? $r['full_name'] : $r['first_name'].' '.$r['last_name'];
                            $m_contrib = $r['epf_employee'];
                            $e_contrib = $r['epf_employer'];
                            $tot_contrib = $m_contrib + $e_contrib;
                        ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="p-2.5 border border-gray-200 text-center text-gray-500 font-mono"><?php echo $idx + 1; ?></td>
                            <td class="p-2.5 border border-gray-200 font-mono font-bold text-gray-800"><?php echo htmlspecialchars($r['epf_number'] ?: 'N/A'); ?></td>
                            <td class="p-2.5 border border-gray-200 font-medium text-gray-900"><?php echo htmlspecialchars($name); ?></td>
                            <td class="p-2.5 border border-gray-200 font-mono text-gray-600"><?php echo htmlspecialchars($r['nic_no'] ?: 'N/A'); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono text-gray-700">Rs. <?php echo number_format($r['basic_salary'], 2); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono text-gray-800">Rs. <?php echo number_format($m_contrib, 2); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono text-gray-800">Rs. <?php echo number_format($e_contrib, 2); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono font-bold text-emerald-700 bg-emerald-50/30">
                                Rs. <?php echo number_format($tot_contrib, 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if($total_members > 0): ?>
                        <tr class="bg-gray-50 font-bold text-gray-900">
                            <td colspan="4" class="p-2.5 border border-gray-200 text-right uppercase text-xs">Grand Totals</td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono">Rs. <?php echo number_format($total_earnings, 2); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono">Rs. <?php echo number_format($total_epf_employee, 2); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono">Rs. <?php echo number_format($total_epf_employer, 2); ?></td>
                            <td class="p-2.5 border border-gray-200 text-right font-mono text-emerald-800 bg-emerald-100/50">
                                Rs. <?php echo number_format($total_remittance, 2); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 pt-6 border-t border-gray-300 flex justify-between items-center text-xs text-gray-600">
                <div>
                    <p>Prepared by: ________________________</p>
                    <p class="mt-1 text-2xs text-gray-400">Payroll Officer</p>
                </div>
                <div class="text-right">
                    <p>Certified Correct: ________________________</p>
                    <p class="mt-1 text-2xs text-gray-400">Authorized Signatory / Managing Director</p>
                </div>
            </div>
        </div>

        <!-- FORM R Section -->
        <div id="sec_form_r" class="print-visible-all hidden bg-white rounded-2xl shadow-sm border border-gray-200 p-8 max-w-3xl mx-auto">
            
            <div class="border-4 border-double border-gray-800 p-6 rounded-xl">
                
                <div class="text-center pb-6 border-b border-gray-300">
                    <h3 class="text-lg font-bold tracking-widest text-gray-800">FORM R</h3>
                    <h2 class="text-2xl font-black uppercase text-gray-900 mt-1">E Track Biz Pvt Ltd</h2>
                    <p class="text-xs text-gray-500 mt-1">REMITTANCE STATEMENT SUMMARY COVER SHEET</p>
                    <p class="text-2xs text-gray-400">Contributions to the Employees' Provident Fund</p>
                </div>

                <div class="py-6 space-y-4 text-xs sm:text-sm">
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">1. Employer Registration No:</span>
                        <span class="font-mono font-bold text-gray-900 col-span-2">#EPF-ETB-9982</span>
                    </div>
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">2. Registered Corporate Name:</span>
                        <span class="font-bold text-gray-900 col-span-2">E Track Biz Pvt Ltd</span>
                    </div>
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">3. Corporate Address:</span>
                        <span class="text-gray-700 col-span-2">Headquarters, Main Commercial Suite, Colombo</span>
                    </div>
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">4. Month & Year of Contribution:</span>
                        <span class="font-bold text-brand-700 col-span-2"><?php echo $pay_period; ?></span>
                    </div>
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">5. Total Contributing Members:</span>
                        <span class="font-mono font-bold text-gray-900 col-span-2"><?php echo $total_members; ?> Members</span>
                    </div>
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">6. Total Covered Earnings Base:</span>
                        <span class="font-mono text-gray-800 col-span-2">Rs. <?php echo number_format($total_earnings, 2); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 bg-brand-50/50 px-3 rounded-lg border border-brand-100">
                        <span class="text-brand-900 font-bold col-span-1">7. Total Remitted Contribution:</span>
                        <span class="font-mono font-bold text-base text-brand-700 col-span-2">Rs. <?php echo number_format($total_remittance, 2); ?></span>
                    </div>
                    <div class="grid grid-cols-3 pb-2 border-b border-gray-100">
                        <span class="text-gray-500 font-medium col-span-1">8. Mode of Remittance:</span>
                        <span class="text-gray-800 col-span-2 italic">Online Direct Fund Transfer / Corporate Draft</span>
                    </div>
                </div>

                <div class="mt-8 p-4 bg-gray-50 rounded-xl border border-gray-200 text-xs text-gray-600 leading-relaxed">
                    <p class="font-bold text-gray-700 mb-1">Declaration of Compliance:</p>
                    I hereby certify that the remittance particulars mapped above represent accurate computations in complete compliance with the statutory contribution guidelines mandated under the governing Provident Fund legislation.
                </div>

                <div class="mt-16 pt-6 border-t border-gray-400 flex justify-between items-end text-xs text-gray-800">
                    <div>
                        <p>Date: <?php echo date('d / m / Y'); ?></p>
                        <p class="text-2xs text-gray-400 mt-0.5">System Stamp Validation</p>
                    </div>
                    <div class="text-center">
                        <p>______________________________________</p>
                        <p class="font-semibold mt-1">Authorized Seal & Signature</p>
                    </div>
                </div>

            </div>

        </div>

    </div>
</main>
</body>
</html>
