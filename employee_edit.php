<?php
require_once 'auth_check.php';
$required_page = 'employees.php';
require_once 'db_config.php';

// Fetch available shifts
$shifts = $pdo->query("SELECT * FROM work_shifts ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: employees.php");
    exit;
}

// Fetch existing employee
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    if (empty($full_name)) {
        $full_name = $first_name . ' ' . $last_name;
    }
    $nic_no = trim($_POST['nic_no'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $marital_status = !empty($_POST['marital_status']) ? $_POST['marital_status'] : null;
    $address = trim($_POST['address'] ?? '');

    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone_secondary = trim($_POST['phone_secondary'] ?? '');

    $emergency_name = trim($_POST['emergency_name'] ?? '');
    $emergency_relationship = trim($_POST['emergency_relationship'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');

    $epf_number = trim($_POST['epf_number'] ?? '');
    $etf_number = trim($_POST['etf_number'] ?? '');
    $has_tin = isset($_POST['has_tin']) && $_POST['has_tin'] == '1';
    $tin_number = $has_tin ? trim($_POST['tin_number'] ?? '') : null;

    $bank_acc_no = trim($_POST['bank_acc_no'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_branch = trim($_POST['bank_branch'] ?? '');
    $bank_acc_owner = trim($_POST['bank_acc_owner'] ?? '');

    $employment_type = $_POST['employment_type'] ?? 'Permanent';
    $designation = trim($_POST['designation'] ?? '');
    $position = $designation; // Sync position for backward compatibility
    $department = trim($_POST['department'] ?? '');
    $assigned_shift_id = !empty($_POST['assigned_shift_id']) ? (int)$_POST['assigned_shift_id'] : null;
    $grade = trim($_POST['grade'] ?? '');
    $date_joined = !empty($_POST['date_joined']) ? $_POST['date_joined'] : null;
    $probation_period = trim($_POST['probation_period'] ?? '');
    $confirmation_date = !empty($_POST['confirmation_date']) ? $_POST['confirmation_date'] : null;
    $branch_location = trim($_POST['branch_location'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($department) || empty($designation)) {
        $error = 'Please fill in all required fields (First Name, Last Name, Email, Department, Designation).';
    } else {
        try {
            $sql = "UPDATE employees SET 
                first_name=?, last_name=?, full_name=?, nic_no=?, date_of_birth=?, gender=?, marital_status=?, address=?,
                email=?, phone=?, phone_secondary=?,
                emergency_name=?, emergency_relationship=?, emergency_phone=?,
                epf_number=?, etf_number=?, tin_number=?,
                bank_acc_no=?, bank_name=?, bank_branch=?, bank_acc_owner=?,
                employment_type=?, designation=?, position=?, department=?, assigned_shift_id=?, grade=?,
                date_joined=?, probation_period=?, confirmation_date=?, branch_location=?, status=?
                WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $first_name, $last_name, $full_name, $nic_no, $date_of_birth, $gender, $marital_status, $address,
                $email, $phone, $phone_secondary,
                $emergency_name, $emergency_relationship, $emergency_phone,
                $epf_number, $etf_number, $tin_number,
                $bank_acc_no, $bank_name, $bank_branch, $bank_acc_owner,
                $employment_type, $designation, $position, $department, $assigned_shift_id, $grade,
                $date_joined, $probation_period, $confirmation_date, $branch_location, $status,
                $id
            ]);
            header("Location: employees.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Email address already exists in the system for another employee.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = "Edit Employee Profile";
include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
        <div class="max-w-5xl mx-auto">
            
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center">
                    <a href="employees.php" class="text-gray-500 hover:text-brand-600 mr-3 transition-colors">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Edit Employee: <?php echo htmlspecialchars($employee['full_name'] ?: ($employee['first_name'] . ' ' . $employee['last_name'])); ?></h2>
                        <p class="text-sm text-gray-500 mt-0.5">Update staff records and configurations across sections</p>
                    </div>
                </div>
                <a href="employee_view.php?id=<?php echo $id; ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
                    <i class="fa-regular fa-eye mr-2"></i> View Profile Card
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-100 flex items-center shadow-sm" role="alert">
                <i class="fa-solid fa-circle-exclamation mr-3 text-lg"></i>
                <span class="font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <!-- Tab Navigation Buttons -->
            <div class="flex space-x-1 bg-gray-200/60 p-1 rounded-xl mb-6 overflow-x-auto">
                <button type="button" onclick="switchTab('personal')" id="tab-btn-personal" class="flex-1 min-w-[120px] py-2.5 px-3 text-sm font-medium rounded-lg transition-all text-center tab-btn bg-white text-brand-700 shadow-sm">
                    <i class="fa-regular fa-user mr-1.5"></i> Personal
                </button>
                <button type="button" onclick="switchTab('contact')" id="tab-btn-contact" class="flex-1 min-w-[120px] py-2.5 px-3 text-sm font-medium rounded-lg transition-all text-center tab-btn text-gray-600 hover:text-gray-900">
                    <i class="fa-regular fa-address-book mr-1.5"></i> Contact
                </button>
                <button type="button" onclick="switchTab('emergency')" id="tab-btn-emergency" class="flex-1 min-w-[120px] py-2.5 px-3 text-sm font-medium rounded-lg transition-all text-center tab-btn text-gray-600 hover:text-gray-900">
                    <i class="fa-solid fa-heart-pulse mr-1.5"></i> Emergency
                </button>
                <button type="button" onclick="switchTab('statutory')" id="tab-btn-statutory" class="flex-1 min-w-[120px] py-2.5 px-3 text-sm font-medium rounded-lg transition-all text-center tab-btn text-gray-600 hover:text-gray-900">
                    <i class="fa-solid fa-scale-balanced mr-1.5"></i> Statutory
                </button>
                <button type="button" onclick="switchTab('bank')" id="tab-btn-bank" class="flex-1 min-w-[120px] py-2.5 px-3 text-sm font-medium rounded-lg transition-all text-center tab-btn text-gray-600 hover:text-gray-900">
                    <i class="fa-solid fa-building-columns mr-1.5"></i> Bank
                </button>
                <button type="button" onclick="switchTab('employment')" id="tab-btn-employment" class="flex-1 min-w-[120px] py-2.5 px-3 text-sm font-medium rounded-lg transition-all text-center tab-btn text-gray-600 hover:text-gray-900">
                    <i class="fa-solid fa-briefcase mr-1.5"></i> Employment
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <form action="employee_edit.php?id=<?php echo htmlspecialchars($id); ?>" method="POST" class="p-8" id="employeeForm">
                    
                    <!-- 1. PERSONAL INFORMATION -->
                    <div id="tab-personal" class="tab-content">
                        <div class="border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Personal Information</h3>
                            <p class="text-sm text-gray-500">Basic identification and personal records.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? $employee['first_name']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>
                            <div class="sm:col-span-3">
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? $employee['last_name']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name (Display Name)</label>
                                <input type="text" name="full_name" id="full_name" placeholder="Leave empty to auto-fill" value="<?php echo htmlspecialchars($_POST['full_name'] ?? $employee['full_name']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="nic_no" class="block text-sm font-medium text-gray-700 mb-1">NIC Number</label>
                                <input type="text" name="nic_no" id="nic_no" value="<?php echo htmlspecialchars($_POST['nic_no'] ?? $employee['nic_no']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                <div class="flex space-x-2">
                                    <input type="date" name="date_of_birth" id="date_of_birth" onchange="calculateAge()" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? $employee['date_of_birth']); ?>" class="flex-1 rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                                    <div class="bg-gray-50 border border-gray-300 rounded-lg px-4 flex items-center justify-center text-sm text-gray-600 min-w-[80px]">
                                        <span id="age_display">Age: --</span>
                                    </div>
                                </div>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <?php $selected_gender = $_POST['gender'] ?? $employee['gender']; ?>
                                <select name="gender" id="gender" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $selected_gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $selected_gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $selected_gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="marital_status" class="block text-sm font-medium text-gray-700 mb-1">Marital Status</label>
                                <?php $selected_marital = $_POST['marital_status'] ?? $employee['marital_status']; ?>
                                <select name="marital_status" id="marital_status" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="">Select Status</option>
                                    <option value="Single" <?php echo $selected_marital === 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo $selected_marital === 'Married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?php echo $selected_marital === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo $selected_marital === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>

                            <div class="sm:col-span-6">
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Residential Address</label>
                                <textarea name="address" id="address" rows="2" class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all"><?php echo htmlspecialchars($_POST['address'] ?? $employee['address']); ?></textarea>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end">
                            <button type="button" onclick="switchTab('contact')" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                Next: Contact Details <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 2. CONTACT DETAILS -->
                    <div id="tab-contact" class="tab-content hidden">
                        <div class="border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Contact Details</h3>
                            <p class="text-sm text-gray-500">Primary and secondary communication channels.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? $employee['email']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Mobile Phone</label>
                                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $employee['phone']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="phone_secondary" class="block text-sm font-medium text-gray-700 mb-1">Secondary / Home Phone</label>
                                <input type="text" name="phone_secondary" id="phone_secondary" value="<?php echo htmlspecialchars($_POST['phone_secondary'] ?? $employee['phone_secondary']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button type="button" onclick="switchTab('personal')" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Previous
                            </button>
                            <button type="button" onclick="switchTab('emergency')" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                Next: Emergency Contact <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 3. EMERGENCY CONTACT -->
                    <div id="tab-emergency" class="tab-content hidden">
                        <div class="border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Emergency Contact</h3>
                            <p class="text-sm text-gray-500">Contact person in case of urgent situations.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="emergency_name" class="block text-sm font-medium text-gray-700 mb-1">Contact Name</label>
                                <input type="text" name="emergency_name" id="emergency_name" value="<?php echo htmlspecialchars($_POST['emergency_name'] ?? $employee['emergency_name']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="emergency_relationship" class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                                <input type="text" name="emergency_relationship" id="emergency_relationship" placeholder="e.g. Spouse, Parent, Sibling" value="<?php echo htmlspecialchars($_POST['emergency_relationship'] ?? $employee['emergency_relationship']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="emergency_phone" class="block text-sm font-medium text-gray-700 mb-1">Emergency Phone Number</label>
                                <input type="text" name="emergency_phone" id="emergency_phone" value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? $employee['emergency_phone']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button type="button" onclick="switchTab('contact')" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Previous
                            </button>
                            <button type="button" onclick="switchTab('statutory')" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                Next: Statutory Numbers <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 4. STATUTORY NUMBERS -->
                    <div id="tab-statutory" class="tab-content hidden">
                        <div class="border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Statutory & Tax Records</h3>
                            <p class="text-sm text-gray-500">Provident funds and tax registration numbers.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="epf_number" class="block text-sm font-medium text-gray-700 mb-1">EPF Number</label>
                                <input type="text" name="epf_number" id="epf_number" value="<?php echo htmlspecialchars($_POST['epf_number'] ?? $employee['epf_number']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="etf_number" class="block text-sm font-medium text-gray-700 mb-1">ETF Number</label>
                                <input type="text" name="etf_number" id="etf_number" value="<?php echo htmlspecialchars($_POST['etf_number'] ?? $employee['etf_number']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <?php 
                            $prev_tin = $_POST['tin_number'] ?? $employee['tin_number'];
                            $has_tin_val = isset($_POST['has_tin']) ? ($_POST['has_tin'] == '1') : !empty($employee['tin_number']);
                            ?>
                            <div class="sm:col-span-6 bg-gray-50/70 p-4 rounded-xl border border-gray-200/80">
                                <div class="flex items-center mb-3">
                                    <input type="checkbox" name="has_tin" id="has_tin" value="1" onchange="toggleTinField()" <?php echo $has_tin_val ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600">
                                    <label for="has_tin" class="ml-2 block text-sm font-medium text-gray-800">Tax File Applicable (Has TIN Number)</label>
                                </div>
                                
                                <div id="tin_field_container" class="transition-all mt-3 <?php echo $has_tin_val ? '' : 'hidden'; ?>">
                                    <label for="tin_number" class="block text-xs font-medium text-gray-500 mb-1 uppercase">TIN Number</label>
                                    <input type="text" name="tin_number" id="tin_number" value="<?php echo htmlspecialchars($prev_tin); ?>" class="w-full sm:w-1/2 rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button type="button" onclick="switchTab('emergency')" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Previous
                            </button>
                            <button type="button" onclick="switchTab('bank')" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                Next: Bank Details <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 5. BANK ACCOUNT DETAILS -->
                    <div id="tab-bank" class="tab-content hidden">
                        <div class="border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Bank Account Details</h3>
                            <p class="text-sm text-gray-500">Salary transfer and bank profile info.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                <input type="text" name="bank_name" id="bank_name" placeholder="e.g. Commercial Bank, BOC" value="<?php echo htmlspecialchars($_POST['bank_name'] ?? $employee['bank_name']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="bank_branch" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                                <input type="text" name="bank_branch" id="bank_branch" value="<?php echo htmlspecialchars($_POST['bank_branch'] ?? $employee['bank_branch']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="bank_acc_no" class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                                <input type="text" name="bank_acc_no" id="bank_acc_no" value="<?php echo htmlspecialchars($_POST['bank_acc_no'] ?? $employee['bank_acc_no']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="bank_acc_owner" class="block text-sm font-medium text-gray-700 mb-1">Account Owner's Name</label>
                                <input type="text" name="bank_acc_owner" id="bank_acc_owner" placeholder="As displayed in bank passbook" value="<?php echo htmlspecialchars($_POST['bank_acc_owner'] ?? $employee['bank_acc_owner']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button type="button" onclick="switchTab('statutory')" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Previous
                            </button>
                            <button type="button" onclick="switchTab('employment')" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                Next: Employment Details <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 6. EMPLOYMENT DETAILS -->
                    <div id="tab-employment" class="tab-content hidden">
                        <div class="border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Employment Details</h3>
                            <p class="text-sm text-gray-500">Designation, job configuration, and assignment properties.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="employment_type" class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
                                <?php $selected_emp_type = $_POST['employment_type'] ?? $employee['employment_type'] ?? 'Permanent'; ?>
                                <select name="employment_type" id="employment_type" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="Permanent" <?php echo $selected_emp_type === 'Permanent' ? 'selected' : ''; ?>>Permanent</option>
                                    <option value="Contract" <?php echo $selected_emp_type === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="Casual" <?php echo $selected_emp_type === 'Casual' ? 'selected' : ''; ?>>Casual</option>
                                    <option value="Part-time" <?php echo $selected_emp_type === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                    <option value="Trainee" <?php echo $selected_emp_type === 'Trainee' ? 'selected' : ''; ?>>Trainee</option>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                                <?php $selected_dept = $_POST['department'] ?? $employee['department']; ?>
                                <select name="department" id="department" required onchange="updateRoles()" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="">Select Department</option>
                                    <?php
                                    $depts = ["Human Resources (HR)", "Finance & Accounts", "Operations", "Sales & Marketing", "Information Technology (IT)"];
                                    if (!empty($selected_dept) && !in_array($selected_dept, $depts)) {
                                        $depts[] = $selected_dept;
                                    }
                                    foreach ($depts as $d) {
                                        $sel = ($selected_dept === $d) ? 'selected' : '';
                                        echo "<option value=\"".htmlspecialchars($d)."\" $sel>".htmlspecialchars($d)."</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="designation" class="block text-sm font-medium text-gray-700 mb-1">Designation / Job Role <span class="text-red-500">*</span></label>
                                <select name="designation" id="designation" required class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="">Select Role</option>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="assigned_shift_id" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Work Shift</label>
                                <?php $selected_shift = $_POST['assigned_shift_id'] ?? $employee['assigned_shift_id']; ?>
                                <select name="assigned_shift_id" id="assigned_shift_id" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="">— No Default Shift (General) —</option>
                                    <?php foreach ($shifts as $sh): ?>
                                        <option value="<?php echo $sh['id']; ?>" <?php echo ($selected_shift == $sh['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sh['shift_name']); ?> (<?php echo date('h:i A', strtotime($sh['start_time'])) . ' - ' . date('h:i A', strtotime($sh['end_time'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label for="grade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                <input type="text" name="grade" id="grade" placeholder="e.g. Level 1, Executive, Managerial" value="<?php echo htmlspecialchars($_POST['grade'] ?? $employee['grade']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="date_joined" class="block text-sm font-medium text-gray-700 mb-1">Date Joined</label>
                                <input type="date" name="date_joined" id="date_joined" value="<?php echo htmlspecialchars($_POST['date_joined'] ?? $employee['date_joined']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="probation_period" class="block text-sm font-medium text-gray-700 mb-1">Probation Period</label>
                                <input type="text" name="probation_period" id="probation_period" placeholder="e.g. 3 Months, 6 Months" value="<?php echo htmlspecialchars($_POST['probation_period'] ?? $employee['probation_period']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="confirmation_date" class="block text-sm font-medium text-gray-700 mb-1">Confirmation Date</label>
                                <input type="date" name="confirmation_date" id="confirmation_date" value="<?php echo htmlspecialchars($_POST['confirmation_date'] ?? $employee['confirmation_date']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="branch_location" class="block text-sm font-medium text-gray-700 mb-1">Branch / Location</label>
                                <input type="text" name="branch_location" id="branch_location" placeholder="e.g. Head Office, Colombo" value="<?php echo htmlspecialchars($_POST['branch_location'] ?? $employee['branch_location']); ?>" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                                <?php $selected_status = $_POST['status'] ?? $employee['status']; ?>
                                <select name="status" id="status" class="w-full rounded-lg border border-gray-300 py-2.5 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all bg-white">
                                    <option value="Active" <?php echo $selected_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $selected_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="On Leave" <?php echo $selected_status === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-8 flex items-center justify-between pt-6 border-t border-gray-100">
                            <button type="button" onclick="switchTab('bank')" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Previous
                            </button>
                            <div class="flex space-x-3">
                                <a href="employees.php" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors text-sm flex items-center shadow-sm">Cancel</a>
                                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-8 rounded-lg transition-colors text-sm shadow-md flex items-center">
                                    <i class="fa-solid fa-floppy-disk mr-2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</main>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-white', 'text-brand-700', 'shadow-sm');
        btn.classList.add('text-gray-600');
    });

    const targetContent = document.getElementById('tab-' + tabId);
    if (targetContent) {
        targetContent.classList.remove('hidden');
    }

    const targetBtn = document.getElementById('tab-btn-' + tabId);
    if (targetBtn) {
        targetBtn.classList.remove('text-gray-600');
        targetBtn.classList.add('bg-white', 'text-brand-700', 'shadow-sm');
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function calculateAge() {
    const dobInput = document.getElementById('date_of_birth');
    const ageDisplay = document.getElementById('age_display');
    if (!dobInput || !dobInput.value) {
        ageDisplay.textContent = 'Age: --';
        return;
    }
    const dob = new Date(dobInput.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    ageDisplay.textContent = 'Age: ' + (age >= 0 ? age : '--');
}

function toggleTinField() {
    const cb = document.getElementById('has_tin');
    const container = document.getElementById('tin_field_container');
    if (cb && container) {
        if (cb.checked) {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
            const input = document.getElementById('tin_number');
            if (input) input.value = '';
        }
    }
}

const departmentRoles = {
    "Human Resources (HR)": ["HR Manager", "HR Executive", "Recruiter", "Training Officer"],
    "Finance & Accounts": ["Accountant", "Finance Manager", "Cashier", "Auditor"],
    "Operations": ["Operations Manager", "Operations Executive", "Supervisor"],
    "Sales & Marketing": ["Sales Executive", "Marketing Manager", "Digital Marketing Executive", "Business Development Officer"],
    "Information Technology (IT)": ["Software Engineer", "System Administrator", "Network Engineer", "IT Support Officer"]
};

function updateRoles(selectedRole = '') {
    const deptSelect = document.getElementById('department');
    const roleSelect = document.getElementById('designation');
    if (!deptSelect || !roleSelect) return;
    
    const selectedDept = deptSelect.value;
    roleSelect.innerHTML = '<option value="">Select Role</option>';
    
    let roleFound = false;
    if (selectedDept && departmentRoles[selectedDept]) {
        departmentRoles[selectedDept].forEach(role => {
            const option = document.createElement('option');
            option.value = role;
            option.textContent = role;
            if (role === selectedRole) {
                option.selected = true;
                roleFound = true;
            }
            roleSelect.appendChild(option);
        });
    }
    
    if (selectedRole && !roleFound && selectedRole.trim() !== '') {
        const option = document.createElement('option');
        option.value = selectedRole;
        option.textContent = selectedRole;
        option.selected = true;
        roleSelect.appendChild(option);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    calculateAge();
    toggleTinField();
    updateRoles(<?php echo json_encode($_POST['designation'] ?? $employee['designation'] ?: $employee['position']); ?>);
});
</script>
</body>
</html>
