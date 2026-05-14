<?php
require_once 'auth_check.php';
$required_page = 'payroll.php';
require_once 'db_config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: payroll.php");
    exit;
}

// Fetch payroll run with full employee profile details
$stmt = $pdo->prepare("
    SELECT pr.*, 
           e.first_name, e.last_name, e.full_name, e.nic_no, e.email, e.designation, e.position, e.department, 
           e.epf_number, e.bank_acc_no, e.bank_name, e.bank_branch
    FROM payroll_runs pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE pr.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    header("Location: payroll.php");
    exit;
}

$display_name = !empty($p['full_name']) ? $p['full_name'] : $p['first_name'] . ' ' . $p['last_name'];
$page_title = "Payslip - " . $display_name . " (" . $p['pay_period'] . ")";

// Safeguard extra dynamic fields if null/missing
$p['overtime_hours']     = (float)($p['overtime_hours'] ?? 0);
$p['overtime_pay']       = (float)($p['overtime_pay'] ?? 0);
$p['incentives_bonuses'] = (float)($p['incentives_bonuses'] ?? 0);
$p['apit_payee']         = (float)($p['apit_payee'] ?? 0);
$p['nopay_deductions']   = (float)($p['nopay_deductions'] ?? 0);
$p['loan_deductions']    = (float)($p['loan_deductions'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-viewport, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📄</text></svg>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            body {
                background-color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 py-10 px-4 font-sans text-gray-800 antialiased min-h-screen flex flex-col items-center">

    <!-- Action Toolbar (Hidden in Print) -->
    <div class="w-full max-w-4xl mb-6 flex flex-wrap items-center justify-between gap-4 no-print bg-white p-4 rounded-2xl shadow-sm border border-gray-200/80">
        <a href="payroll.php?month=<?php echo $p['pay_month']; ?>&year=<?php echo $p['pay_year']; ?>" class="text-brand-600 hover:text-brand-800 font-medium text-sm flex items-center gap-2 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Back to Payroll Overview
        </a>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-5 rounded-xl shadow-md transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-print"></i> Print / Download PDF
            </button>
        </div>
    </div>

    <!-- Printable Payslip Document Card -->
    <div class="w-full max-w-4xl bg-white rounded-3xl shadow-xl border border-gray-100 p-8 sm:p-12 print-container">
        
        <!-- Header Section Brand -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-8 border-b-2 border-gray-100 gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="h-10 w-10 bg-brand-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-md">
                        ET
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold font-heading text-gray-900 tracking-tight">
                        E Track Biz Pvt Ltd
                    </h1>
                </div>
                <p class="text-xs text-gray-500 max-w-xs">
                    Official Corporate Compensation Advice & Statement of Earnings
                </p>
            </div>
            <div class="sm:text-right flex flex-col items-start sm:items-end">
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest bg-brand-50 text-brand-700 border border-brand-100 mb-2">
                    PAYSLIP
                </span>
                <p class="text-sm font-semibold text-gray-800">Period: <span class="text-brand-600"><?php echo htmlspecialchars($p['pay_period']); ?></span></p>
                <p class="text-xs text-gray-400 mt-0.5">Disbursement Ref: #PR-<?php echo $p['id']; ?>-<?php echo $p['pay_year']; ?></p>
            </div>
        </div>

        <!-- Employee Info Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 py-8 border-b border-gray-100 bg-gray-50/40 px-6 rounded-2xl my-6">
            <div class="space-y-2">
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">Employee Name:</span>
                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($display_name); ?></span>
                </div>
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">Designation:</span>
                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars(!empty($p['designation']) ? $p['designation'] : $p['position']); ?></span>
                </div>
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">Department:</span>
                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars($p['department']); ?></span>
                </div>
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">NIC Number:</span>
                    <span class="font-mono text-gray-600"><?php echo htmlspecialchars($p['nic_no'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">EPF Number:</span>
                    <span class="font-mono text-gray-800 font-semibold"><?php echo htmlspecialchars($p['epf_number'] ?: 'N/A'); ?></span>
                </div>
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">Bank Name:</span>
                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars($p['bank_name'] ?: 'N/A'); ?></span>
                </div>
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">Branch:</span>
                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars($p['bank_branch'] ?: 'N/A'); ?></span>
                </div>
                <div class="flex justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 font-medium">Account Number:</span>
                    <span class="font-mono text-gray-700"><?php echo htmlspecialchars($p['bank_acc_no'] ?: 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Detailed Ledger Breakdown -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 my-8">
            
            <!-- Earnings Panel -->
            <div class="border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                <div class="bg-emerald-50 px-5 py-3 border-b border-emerald-100 flex justify-between items-center">
                    <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-trend-up"></i> Earnings
                    </span>
                    <span class="text-2xs bg-emerald-200/60 text-emerald-800 py-0.5 px-2 rounded-full font-bold">Credit</span>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Basic Pay</span>
                        <span class="font-mono text-gray-900 font-medium">Rs. <?php echo number_format($p['basic_salary'], 2); ?></span>
                    </div>
                    <?php if($p['housing_allowance'] > 0): ?>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Housing Allowance</span>
                        <span class="font-mono text-gray-700">Rs. <?php echo number_format($p['housing_allowance'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($p['transport_allowance'] > 0): ?>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Transport Allowance</span>
                        <span class="font-mono text-gray-700">Rs. <?php echo number_format($p['transport_allowance'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($p['medical_allowance'] > 0): ?>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Medical Allowance</span>
                        <span class="font-mono text-gray-700">Rs. <?php echo number_format($p['medical_allowance'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($p['other_allowances'] > 0): ?>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Other Allowances</span>
                        <span class="font-mono text-gray-700">Rs. <?php echo number_format($p['other_allowances'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($p['overtime_pay'] > 0): ?>
                    <div class="flex justify-between text-xs sm:text-sm items-center">
                        <span class="text-gray-600">Overtime Pay <small class="text-emerald-600 font-bold">(<?php echo number_format($p['overtime_hours'], 1); ?> hrs)</small></span>
                        <span class="font-mono text-emerald-700 font-medium">Rs. <?php echo number_format($p['overtime_pay'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($p['incentives_bonuses'] > 0): ?>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Incentives / Bonuses</span>
                        <span class="font-mono text-emerald-700 font-medium">Rs. <?php echo number_format($p['incentives_bonuses'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="bg-gray-50 px-5 py-3 border-t border-gray-100 flex justify-between items-center">
                    <span class="text-xs font-bold text-gray-700">Gross Earnings</span>
                    <span class="font-mono text-sm font-bold text-gray-900">Rs. <?php echo number_format($p['gross_salary'], 2); ?></span>
                </div>
            </div>

            <!-- Deductions Panel -->
            <div class="border border-gray-100 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
                <div>
                    <div class="bg-red-50 px-5 py-3 border-b border-red-100 flex justify-between items-center">
                        <span class="text-xs font-bold text-red-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-arrow-trend-down"></i> Deductions
                        </span>
                        <span class="text-2xs bg-red-200/60 text-red-800 py-0.5 px-2 rounded-full font-bold">Debit</span>
                    </div>
                    <div class="p-5 space-y-3">
                        <?php if($p['epf_employee'] > 0): ?>
                        <div class="flex justify-between text-xs sm:text-sm">
                            <span class="text-gray-600">EPF Contribution <small class="text-gray-400">(8%)</small></span>
                            <span class="font-mono text-red-600">Rs. <?php echo number_format($p['epf_employee'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($p['tax_deduction'] > 0): ?>
                        <div class="flex justify-between text-xs sm:text-sm">
                            <span class="text-gray-600">Standard Tax</span>
                            <span class="font-mono text-red-600">Rs. <?php echo number_format($p['tax_deduction'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($p['apit_payee'] > 0): ?>
                        <div class="flex justify-between text-xs sm:text-sm">
                            <span class="text-gray-600">APIT / PAYEE Tax</span>
                            <span class="font-mono text-red-600">Rs. <?php echo number_format($p['apit_payee'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($p['nopay_deductions'] > 0): ?>
                        <div class="flex justify-between text-xs sm:text-sm">
                            <span class="text-gray-600 font-medium">No-Pay Deduction</span>
                            <span class="font-mono text-red-600 font-semibold">Rs. <?php echo number_format($p['nopay_deductions'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($p['loan_deductions'] > 0): ?>
                        <div class="flex justify-between text-xs sm:text-sm">
                            <span class="text-gray-600">Loan Installment</span>
                            <span class="font-mono text-red-600 font-medium">Rs. <?php echo number_format($p['loan_deductions'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($p['other_deductions'] > 0): ?>
                        <div class="flex justify-between text-xs sm:text-sm">
                            <span class="text-gray-600">Other Deductions</span>
                            <span class="font-mono text-red-600">Rs. <?php echo number_format($p['other_deductions'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($p['total_deductions'] == 0): ?>
                        <p class="text-xs text-gray-400 italic text-center py-2">No deductions registered</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3 border-t border-gray-100 flex justify-between items-center">
                    <span class="text-xs font-bold text-gray-700">Total Deductions</span>
                    <span class="font-mono text-sm font-bold text-red-600">Rs. <?php echo number_format($p['total_deductions'], 2); ?></span>
                </div>
            </div>

        </div>

        <!-- Final Net Payable Highlight Card -->
        <div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-2xl p-6 text-white flex flex-col sm:flex-row justify-between items-center shadow-lg my-8 gap-4">
            <div>
                <p class="text-xs text-brand-100 uppercase tracking-widest font-semibold">Total Net Payable</p>
                <p class="text-xs text-brand-200 mt-0.5">Amount transferred directly to specified destination credentials</p>
            </div>
            <div class="text-center sm:text-right bg-white/10 py-3 px-6 rounded-xl border border-white/20 backdrop-blur-sm">
                <span class="text-2xl sm:text-3xl font-bold font-mono text-white">
                    Rs. <?php echo number_format($p['net_salary'], 2); ?>
                </span>
            </div>
        </div>

        <!-- Authorization & Signatures -->
        <div class="grid grid-cols-2 gap-8 mt-16 pt-8 border-t-2 border-dashed border-gray-200 text-center">
            <div>
                <div class="h-12 border-b border-gray-300 w-4/5 mx-auto mb-2"></div>
                <p class="text-xs font-semibold text-gray-700">Authorized Signature</p>
                <p class="text-2xs text-gray-400">E Track Biz Pvt Ltd HR/Finance</p>
            </div>
            <div>
                <div class="h-12 border-b border-gray-300 w-4/5 mx-auto mb-2"></div>
                <p class="text-xs font-semibold text-gray-700">Employee Signature</p>
                <p class="text-2xs text-gray-400">Date Received</p>
            </div>
        </div>

        <!-- Disclaimer Footer -->
        <p class="text-2xs text-gray-400 text-center mt-10 italic">
            This is a system-generated secure document issued by E Track Biz Pvt Ltd. No physical seal is mandatory for digital disbursement validation.
        </p>

    </div>
</body>
</html>
