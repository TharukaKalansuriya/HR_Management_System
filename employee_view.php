<?php
require_once 'auth_check.php';
$required_page = 'employees.php';
require_once 'db_config.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: employees.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        header("Location: employees.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$disp_name = !empty($employee['full_name']) ? $employee['full_name'] : ($employee['first_name'] . ' ' . $employee['last_name']);
$designation = !empty($employee['designation']) ? $employee['designation'] : $employee['position'];

// Calculate Age
$age_str = 'Not specified';
if (!empty($employee['date_of_birth'])) {
    try {
        $dob = new DateTime($employee['date_of_birth']);
        $now = new DateTime();
        $age_str = $now->diff($dob)->y . ' years old (' . date('d M Y', strtotime($employee['date_of_birth'])) . ')';
    } catch(Exception $e) {}
}

$page_title = "Employee Profile Card";
include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
        <div class="max-w-5xl mx-auto">
            
            <!-- Navigation Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center">
                    <a href="employees.php" class="text-gray-500 hover:text-brand-600 mr-3 transition-colors">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Staff Profile Card</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Read-only overview of registered personnel credentials</p>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <a href="employee_edit.php?id=<?php echo $id; ?>" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold py-2 px-4 rounded-xl shadow-sm transition-colors flex items-center">
                        <i class="fa-solid fa-pen mr-2 text-xs"></i> Edit Profile
                    </a>
                </div>
            </div>

            <!-- Profile Summary Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6 flex flex-col sm:flex-row items-center justify-between bg-gradient-to-r from-white via-brand-50/10 to-brand-50/30">
                <div class="flex flex-col sm:flex-row items-center text-center sm:text-left mb-4 sm:mb-0">
                    <div class="h-20 w-20 bg-brand-100 rounded-2xl flex items-center justify-center text-brand-700 font-black text-2xl border-2 border-white shadow-md mb-3 sm:mb-0 sm:mr-5">
                        <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($disp_name); ?></h3>
                        <p class="text-sm text-gray-600 font-medium mt-0.5"><?php echo htmlspecialchars($designation ?: 'No Designation Assigned'); ?> &bull; <span class="text-brand-700"><?php echo htmlspecialchars($employee['department'] ?: 'Unassigned Dept'); ?></span></p>
                        
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-2.5">
                            <?php if(!empty($employee['nic_no'])): ?>
                                <span class="text-xs bg-white border border-gray-200 px-2 py-0.5 rounded-md font-mono text-gray-600 shadow-2xs">NIC: <?php echo htmlspecialchars($employee['nic_no']); ?></span>
                            <?php endif; ?>
                            
                            <?php 
                            $type = $employee['employment_type'] ?? 'Permanent';
                            $color = 'bg-indigo-50 text-indigo-700 border-indigo-100';
                            if ($type === 'Contract') $color = 'bg-amber-50 text-amber-700 border-amber-100';
                            if ($type === 'Casual') $color = 'bg-cyan-50 text-cyan-700 border-cyan-100';
                            if ($type === 'Part-time') $color = 'bg-purple-50 text-purple-700 border-purple-100';
                            if ($type === 'Trainee') $color = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                            ?>
                            <span class="text-xs px-2 py-0.5 rounded-md font-bold border <?php echo $color; ?>">
                                <?php echo htmlspecialchars($type); ?>
                            </span>

                            <?php if ($employee['status'] == 'Active'): ?>
                                <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-emerald-100 text-emerald-800">Active</span>
                            <?php elseif ($employee['status'] == 'On Leave'): ?>
                                <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-amber-100 text-amber-800">On Leave</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-red-100 text-red-800">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-xs text-gray-400 sm:text-right border-t sm:border-t-0 sm:border-l border-gray-100 pt-3 sm:pt-0 sm:pl-6">
                    <p>System ID: <span class="font-mono text-gray-600 font-bold">#<?php echo str_pad($employee['id'], 4, '0', STR_PAD_LEFT); ?></span></p>
                    <p class="mt-1">Registered: <span class="text-gray-600"><?php echo date('d M Y', strtotime($employee['created_at'])); ?></span></p>
                </div>
            </div>

            <!-- Detailed Profile Sections Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Section 1: Personal Information -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center mb-4 pb-3 border-b border-gray-50 text-brand-700 font-bold text-sm uppercase tracking-wider">
                        <i class="fa-regular fa-user mr-2 text-base"></i> Personal Information
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">First Name:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['first_name']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Last Name:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['last_name']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Full Name:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['full_name'] ?: 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Date of Birth / Age:</dt>
                            <dd class="font-medium text-gray-900"><?php echo $age_str; ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Gender:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['gender'] ?: 'Not specified'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Marital Status:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['marital_status'] ?: 'Not specified'); ?></dd>
                        </div>
                        <div class="pt-2 border-t border-gray-50/80">
                            <dt class="text-gray-500 text-xs mb-0.5">Residential Address:</dt>
                            <dd class="font-medium text-gray-800 text-xs leading-relaxed"><?php echo nl2br(htmlspecialchars($employee['address'] ?: 'No address logged')); ?></dd>
                        </div>
                    </dl>
                </div>

                <!-- Section 2: Contact Details -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center mb-4 pb-3 border-b border-gray-50 text-brand-700 font-bold text-sm uppercase tracking-wider">
                        <i class="fa-regular fa-address-book mr-2 text-base"></i> Contact Details
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-500">Email Address:</dt>
                            <dd class="font-semibold text-brand-600"><?php echo htmlspecialchars($employee['email']); ?></dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-500">Mobile Phone:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['phone'] ?: 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-500">Secondary / Home Phone:</dt>
                            <dd class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['phone_secondary'] ?: 'N/A'); ?></dd>
                        </div>
                    </dl>

                    <div class="mt-6 pt-4 border-t border-gray-50 flex items-center text-amber-700 font-bold text-xs uppercase tracking-wider mb-3">
                        <i class="fa-solid fa-heart-pulse mr-2 text-sm"></i> Emergency Contact
                    </div>
                    <dl class="space-y-2 text-xs bg-amber-50/50 p-3 rounded-xl border border-amber-100/60">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Contact Name:</dt>
                            <dd class="font-bold text-gray-900"><?php echo htmlspecialchars($employee['emergency_name'] ?: 'None registered'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Relationship:</dt>
                            <dd class="font-medium text-gray-800"><?php echo htmlspecialchars($employee['emergency_relationship'] ?: 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Emergency Phone:</dt>
                            <dd class="font-bold text-red-600"><?php echo htmlspecialchars($employee['emergency_phone'] ?: 'N/A'); ?></dd>
                        </div>
                    </dl>
                </div>

                <!-- Section 3: Employment Configuration -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:col-span-2">
                    <div class="flex items-center mb-4 pb-3 border-b border-gray-50 text-brand-700 font-bold text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-briefcase mr-2 text-base"></i> Employment & Assignment Info
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Designation Title</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($designation ?: 'N/A'); ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Assigned Department</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($employee['department'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Staff Grade</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($employee['grade'] ?: 'Unassigned'); ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Branch / Work Location</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($employee['branch_location'] ?: 'Standard Office'); ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Date Joined</span>
                            <span class="font-bold text-gray-900"><?php echo !empty($employee['date_joined']) ? date('d M Y', strtotime($employee['date_joined'])) : 'N/A'; ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Probation Period</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($employee['probation_period'] ?: 'None / Completed'); ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Confirmation Date</span>
                            <span class="font-bold text-gray-900"><?php echo !empty($employee['confirmation_date']) ? date('d M Y', strtotime($employee['confirmation_date'])) : 'Pending / N/A'; ?></span>
                        </div>
                        <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                            <span class="block text-xs text-gray-400 mb-0.5">Contract Basis</span>
                            <span class="font-bold text-brand-700"><?php echo htmlspecialchars($employee['employment_type'] ?? 'Permanent'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Statutory & Tax Numbers -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center mb-4 pb-3 border-b border-gray-50 text-brand-700 font-bold text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-scale-balanced mr-2 text-base"></i> Statutory & Tax Compliance
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between items-center p-2 rounded-lg hover:bg-gray-50">
                            <dt class="text-gray-500 font-medium">EPF Number:</dt>
                            <dd class="font-mono font-bold text-gray-900"><?php echo htmlspecialchars($employee['epf_number'] ?: 'Not recorded'); ?></dd>
                        </div>
                        <div class="flex justify-between items-center p-2 rounded-lg hover:bg-gray-50">
                            <dt class="text-gray-500 font-medium">ETF Number:</dt>
                            <dd class="font-mono font-bold text-gray-900"><?php echo htmlspecialchars($employee['etf_number'] ?: 'Not recorded'); ?></dd>
                        </div>
                        <div class="flex justify-between items-center p-2 rounded-lg bg-gray-50/60 border border-gray-100">
                            <dt class="text-gray-500 font-medium">Tax File (TIN Number):</dt>
                            <dd class="font-mono font-bold <?php echo !empty($employee['tin_number']) ? 'text-emerald-700' : 'text-gray-400 italic'; ?>">
                                <?php echo htmlspecialchars($employee['tin_number'] ?: 'N/A / Not Applicable'); ?>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Section 5: Bank Account Details -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center mb-4 pb-3 border-b border-gray-50 text-brand-700 font-bold text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-building-columns mr-2 text-base"></i> Salary Bank Parameters
                    </div>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 font-medium">Bank Name:</dt>
                            <dd class="font-bold text-gray-900"><?php echo htmlspecialchars($employee['bank_name'] ?: 'Not specified'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 font-medium">Branch Location:</dt>
                            <dd class="font-medium text-gray-800"><?php echo htmlspecialchars($employee['bank_branch'] ?: 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <dt class="text-gray-500 font-medium">Account Number:</dt>
                            <dd class="font-mono font-bold text-brand-600 bg-brand-50/50 px-2 py-0.5 rounded border border-brand-100/50">
                                <?php echo htmlspecialchars($employee['bank_acc_no'] ?: 'None configured'); ?>
                            </dd>
                        </div>
                        <div class="pt-2 border-t border-gray-50">
                            <dt class="text-gray-400 text-xs mb-0.5">Account Owner's Name:</dt>
                            <dd class="font-medium text-gray-800 text-xs"><?php echo htmlspecialchars($employee['bank_acc_owner'] ?: 'Same as employee full name'); ?></dd>
                        </div>
                    </dl>
                </div>

            </div>

        </div>
    </div>
</main>
</body>
</html>
