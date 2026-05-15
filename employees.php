<?php
require_once 'auth_check.php';
$required_page = 'employees.php';
require_once 'db_config.php';
$page_title = "Employee Management";

// Fetch employees
$stmt = $pdo->query("SELECT * FROM employees ORDER BY created_at DESC");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'added'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-5 mb-6 rounded-2xl shadow-sm" role="alert">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-circle-check text-emerald-500 text-xl mt-0.5 flex-shrink-0"></i>
                <div class="flex-1">
                    <p class="font-bold text-sm">Employee Registered Successfully!</p>
                    <p class="text-xs text-emerald-700 mt-0.5">A portal account has been automatically created. Share the credentials below with the employee.</p>
                    <?php if (!empty($_GET['pw']) && !empty($_GET['em'])): ?>
                    <div class="mt-3 bg-white border border-emerald-200 rounded-xl p-4 flex flex-wrap gap-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Portal Email</p>
                            <code class="text-sm font-mono font-bold text-gray-800 bg-gray-100 px-2 py-1 rounded-lg"><?php echo htmlspecialchars(urldecode($_GET['em'])); ?></code>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Default Password</p>
                            <code class="text-sm font-mono font-bold text-emerald-700 bg-emerald-100 px-2 py-1 rounded-lg"><?php echo htmlspecialchars(base64_decode($_GET['pw'])); ?></code>
                        </div>
                        <div class="self-end">
                            <span class="text-xs text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Employee must change this password after first login.</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-r-xl shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center">
                <i class="fa-solid fa-circle-info text-blue-500 mr-3 text-lg"></i>
                <div>
                    <p class="font-bold text-sm">Profile Updated!</p>
                    <p class="text-xs mt-0.5">Employee records and configurations have been successfully updated.</p>
                </div>
            </div>
        </div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-xl shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center">
                <i class="fa-solid fa-trash text-red-500 mr-3 text-lg"></i>
                <div>
                    <p class="font-bold text-sm">Removed!</p>
                    <p class="text-xs mt-0.5">Employee record has been securely deleted from the database.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-sm">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Staff Directory</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Viewing all active, inactive, and leave-status personnel records</p>
                </div>
                <a href="employee_add.php" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold py-2.5 px-4 rounded-xl shadow-sm transition-colors flex items-center">
                    <i class="fa-solid fa-plus mr-2 text-xs"></i> Add Staff Member
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/70 border-b border-gray-100 font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Employee</th>
                            <th class="px-6 py-3.5">Contact Info</th>
                            <th class="px-6 py-3.5">Department & Title</th>
                            <th class="px-6 py-3.5">Employment Type</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $emp): ?>
                            <?php 
                                $disp_name = !empty($emp['full_name']) ? $emp['full_name'] : ($emp['first_name'] . ' ' . $emp['last_name']); 
                                $designation = !empty($emp['designation']) ? $emp['designation'] : $emp['position'];
                            ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-brand-50 rounded-xl flex items-center justify-center text-brand-700 font-bold mr-3 border border-brand-100/80 shadow-2xs">
                                            <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900"><?php echo htmlspecialchars($disp_name); ?></p>
                                            <?php if(!empty($emp['nic_no'])): ?>
                                                <span class="text-xs font-mono text-gray-400 bg-gray-100/80 px-1.5 py-0.5 rounded mt-0.5 inline-block">NIC: <?php echo htmlspecialchars($emp['nic_no']); ?></span>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 italic">No NIC specified</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-700 text-xs font-medium"><i class="fa-regular fa-envelope mr-1.5 text-gray-400"></i> <?php echo htmlspecialchars($emp['email']); ?></div>
                                    <div class="text-gray-500 text-xs mt-1"><i class="fa-solid fa-phone mr-1.5 text-gray-400"></i> <?php echo htmlspecialchars($emp['phone'] ?: 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-900 font-semibold text-xs"><?php echo htmlspecialchars($emp['department'] ?: 'Unassigned'); ?></div>
                                    <div class="text-gray-500 text-xs mt-0.5"><?php echo htmlspecialchars($designation ?: 'No Title'); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $type = $emp['employment_type'] ?? 'Permanent';
                                    $color = 'bg-indigo-50 text-indigo-700 border-indigo-100';
                                    if ($type === 'Contract') $color = 'bg-amber-50 text-amber-700 border-amber-100';
                                    if ($type === 'Casual') $color = 'bg-cyan-50 text-cyan-700 border-cyan-100';
                                    if ($type === 'Part-time') $color = 'bg-purple-50 text-purple-700 border-purple-100';
                                    if ($type === 'Trainee') $color = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                    ?>
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold border <?php echo $color; ?> inline-block">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($emp['status'] == 'Active'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center w-max"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Active</span>
                                    <?php elseif ($emp['status'] == 'On Leave'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 flex items-center w-max"><span class="h-1.5 w-1.5 rounded-full bg-amber-500 mr-1.5"></span> On Leave</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 flex items-center w-max"><span class="h-1.5 w-1.5 rounded-full bg-red-500 mr-1.5"></span> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end space-x-1">
                                        <a href="employee_view.php?id=<?php echo $emp['id']; ?>" class="text-gray-500 hover:text-brand-600 hover:bg-brand-50 p-2 rounded-lg transition-colors" title="View Profile Card">
                                            <i class="fa-regular fa-eye text-base"></i>
                                        </a>
                                        <a href="employee_edit.php?id=<?php echo $emp['id']; ?>" class="text-blue-500 hover:bg-blue-50 p-2 rounded-lg transition-colors" title="Edit">
                                            <i class="fa-solid fa-pen text-sm"></i>
                                        </a>
                                        <a href="employee_delete.php?id=<?php echo $emp['id']; ?>" onclick="return confirm('Are you sure you want to delete this employee record?');" class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Delete">
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                                            <i class="fa-solid fa-users-slash text-xl"></i>
                                        </div>
                                        <p class="text-base font-bold text-gray-700">No staff records found</p>
                                        <p class="text-xs text-gray-400 mt-0.5">Register your first employee profile to start populating the directory.</p>
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
