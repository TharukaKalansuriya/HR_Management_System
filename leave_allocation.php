<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php';
include 'includes/sidebar.php';

$msg = "";

// Handle Leave Allocation Updates
if (isset($_POST['update_allocations'])) {
    $dept     = mysqli_real_escape_string($conn, $_POST['department_name']);
    $role     = mysqli_real_escape_string($conn, $_POST['role_name']);
    
    if (empty($role)) {
        $msg = "Error: Please select a role.";
    } else {
        $annual = (int) $_POST['annual_limit'];
        $casual = (int) $_POST['casual_limit'];
        $half_day = (int) $_POST['half_day_limit'];
        $type = mysqli_real_escape_string($conn, $_POST['allocation_type']);

        $upsert_query = "INSERT INTO leave_allocations (role_name, department_name, annual_limit, casual_limit, half_day_limit, allocation_type)
                         VALUES ('$role', '$dept', '$annual', '$casual', '$half_day', '$type')
                         ON DUPLICATE KEY UPDATE
                         department_name = '$dept', annual_limit = '$annual', casual_limit = '$casual', half_day_limit = '$half_day', allocation_type = '$type'";
        if (mysqli_query($conn, $upsert_query)) {
            $msg = "Leave allocations for $role ($dept) updated successfully.";
        }
    }
}

// Handle Delete Allocation
if (isset($_POST['delete_allocation'])) {
    $del_role = mysqli_real_escape_string($conn, $_POST['del_role_name']);
    if (mysqli_query($conn, "DELETE FROM leave_allocations WHERE role_name = '$del_role'")) {
        $msg = "Allocation for '$del_role' deleted successfully.";
    }
}

// Fetch Allocations
$allocations_result = mysqli_query($conn, "SELECT * FROM leave_allocations ORDER BY updated_at DESC");
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
<header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Leave Allocation</h1>
        <p class="text-xs text-slate-400 font-medium">Manage role-based leave limits across departments</p>
    </div>
    <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
</header>
<main class="flex-1 p-6 lg:p-8 bg-slate-100">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Leave Allocation</h1>
                <p class="text-slate-500 mt-1">Manage role-based leave limits across departments.</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div id="status-alert"
                class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-2xl flex items-center gap-3 animate-fade-in">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-semibold text-sm"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <div id="allocationSection" class="animate-fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Allocation Form -->
                <div class="lg:col-span-1 bg-white rounded-3xl shadow-sm border border-slate-100 p-8 h-fit">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Set Leave Amounts</h2>
                    <form method="POST" class="space-y-5">
                        <!-- Allocation Type Selection (FIRST) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">1.
                                Select Period</label>
                            <select name="allocation_type" id="mainAllocType" required
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                                <option value="Month">Month</option>
                                <option value="Year" selected>Year</option>
                            </select>
                        </div>

                        <!-- Department Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">2.
                                Select Department</label>
                            <select name="department_name" id="mainDeptName" onchange="updateRoleOptions()" required
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                                <option value="">Select Department</option>
                                <?php
                                $depts_query = mysqli_query($conn, "SELECT DISTINCT department FROM department_roles ORDER BY department");
                                while ($d = mysqli_fetch_assoc($depts_query)):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($d['department']); ?>">
                                        <?php echo htmlspecialchars($d['department']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Role Type Selection -->
                    <div class="mb-4">
                        <label id="roleLabel" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">3. Select Role</label>
                        <select name="role_name" id="mainRoleName" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Department First</option>
                        </select>
                    </div>


                        <div class="pt-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">4. Set
                                Limits (Optional)</label>
                            <div class="grid grid-cols-2 gap-4 bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
                                <div>
                                    <label
                                        class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 ml-1">Annual</label>
                                    <input type="number" name="annual_limit" value="14" min="0"
                                        class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 ml-1">Casual</label>
                                    <input type="number" name="casual_limit" value="7" min="0"
                                        class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 ml-1">Half
                                        Day</label>
                                    <input type="number" name="half_day_limit" value="10" min="0"
                                        class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="update_allocations"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-100 transition-all flex items-center justify-center gap-2 mt-2">
                            Update All Settings
                        </button>
                    </form>
                </div>

                <!-- Allocation List -->
                <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden h-fit">
                    <div class="px-8 py-6 border-b border-slate-50">
                        <h2 class="text-xl font-bold text-slate-800">Current Allocations</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Department & Role</th>
                                    <th
                                        class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Period</th>
                                    <th
                                        class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Annual</th>
                                    <th
                                        class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Casual</th>
                                    <th
                                        class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Half Day</th>
                                    <th
                                        class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (mysqli_num_rows($allocations_result) > 0): ?>
                                    <?php while ($alloc = mysqli_fetch_assoc($allocations_result)): ?>
                                        <tr class="hover:bg-slate-50/30 transition-colors">
                                            <td class="px-8 py-5">
                                                <div
                                                    class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">
                                                    <?php echo htmlspecialchars($alloc['department_name'] ?? 'General'); ?>
                                                </div>
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100"><?php echo htmlspecialchars($alloc['role_name']); ?></span>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <span
                                                    class="text-xs font-semibold text-slate-500"><?php echo htmlspecialchars($alloc['allocation_type'] ?? 'Year'); ?></span>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <span
                                                    class="text-sm font-bold text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg"><?php echo $alloc['annual_limit']; ?></span>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <span
                                                    class="text-sm font-bold text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg"><?php echo $alloc['casual_limit']; ?></span>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <span
                                                    class="text-sm font-bold text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg"><?php echo $alloc['half_day_limit']; ?></span>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <!-- Edit Button -->
                                                    <button type="button"
                                                        onclick="openEditModal('<?php echo htmlspecialchars($alloc['role_name']); ?>', <?php echo $alloc['annual_limit']; ?>, <?php echo $alloc['casual_limit']; ?>, <?php echo $alloc['half_day_limit']; ?>, '<?php echo $alloc['allocation_type'] ?? 'Year'; ?>', '<?php echo htmlspecialchars($alloc['department_name'] ?? 'General'); ?>')"
                                                        class="p-2 text-blue-500 hover:text-white hover:bg-blue-500 bg-blue-50 rounded-lg transition-all"
                                                        title="Edit">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <form method="POST"
                                                        onsubmit="return confirm('Delete allocation for &quot;<?php echo htmlspecialchars($alloc['role_name']); ?>&quot;? This cannot be undone.')">
                                                        <input type="hidden" name="del_role_name"
                                                            value="<?php echo htmlspecialchars($alloc['role_name']); ?>">
                                                        <button type="submit" name="delete_allocation"
                                                            class="p-2 text-red-500 hover:text-white hover:bg-red-500 bg-red-50 rounded-lg transition-all"
                                                            title="Delete">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                                viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-8 py-10 text-center text-slate-400 font-medium italic">No
                                            allocations set yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Allocation Modal -->
    <div id="editAllocModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div onclick="closeEditModal()" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md mx-4 p-8 animate-fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-bold text-slate-800">Edit Leave Allocation</h3>
                    <p id="modalRoleName" class="text-sm font-semibold text-blue-600 mt-1"></p>
                </div>
                <button onclick="closeEditModal()"
                    class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="role_name" id="editRoleInput">
                <input type="hidden" name="department_name" id="editDeptInput">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Allocation
                        Period</label>
                    <select name="allocation_type" id="editType" required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                        <option value="Month">Month</option>
                        <option value="Year">Year</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Annual
                            Leave</label>
                        <input type="number" name="annual_limit" id="editAnnual" min="0" required
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Casual
                            Leave</label>
                        <input type="number" name="casual_limit" id="editCasual" min="0" required
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Half
                            Day</label>
                        <input type="number" name="half_day_limit" id="editHalfDay" min="0" required
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" name="update_allocations"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-100 transition-all flex items-center justify-center gap-2">
                        Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="px-6 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-3.5 rounded-xl transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    setTimeout(() => {
        const alert = document.getElementById('status-alert');
        if (alert) {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 3000);

    function updateRoleOptions() {
        const deptSelect = document.getElementById('mainDeptName');
        const roleSelect = document.getElementById('mainRoleName');
        if (!deptSelect || !roleSelect) return;
        const dept = deptSelect.value;
        roleSelect.innerHTML = '';
        if (!dept) {
            roleSelect.innerHTML = '<option value="">Select Department First</option>';
            return;
        }

        // Add a default "Select Role" option
        const placeholder = document.createElement('option');
        placeholder.value = "";
        placeholder.textContent = "Select Role";
        roleSelect.appendChild(placeholder);

        const deptRoles = <?php
        $dept_map = [];
        $all_roles_db = mysqli_query($conn, "SELECT * FROM department_roles");
        while ($r = mysqli_fetch_assoc($all_roles_db)) {
            $dept_map[$r['department']][] = $r['role_name'];
        }
        echo json_encode($dept_map);
        ?>;

        const roles = deptRoles[dept] || [];
        if (!roles.includes('Intern')) {
            roles.push('Intern');
        }
        roles.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            roleSelect.appendChild(o);
        });
    }

    // Add auto-fill logic for the main form
    const roleSelectInput = document.getElementById('mainRoleName');
    const annualInput = document.querySelector('input[name="annual_limit"]');
    const casualInput = document.querySelector('input[name="casual_limit"]');
    const halfDayInput = document.querySelector('input[name="half_day_limit"]');

    const existingAllocations = <?php 
        $all_allocs = [];
        mysqli_data_seek($allocations_result, 0);
        while($a = mysqli_fetch_assoc($allocations_result)) {
            $all_allocs[$a['role_name']] = $a;
        }
        echo json_encode($all_allocs);
    ?>;

    roleSelectInput.addEventListener('change', function() {
        const role = this.value;
        if (existingAllocations[role]) {
            annualInput.value = existingAllocations[role].annual_limit;
            casualInput.value = existingAllocations[role].casual_limit;
            halfDayInput.value = existingAllocations[role].half_day_limit;
            document.getElementById('mainAllocType').value = existingAllocations[role].allocation_type || 'Year';
        } else {
            // Default values for new roles
            annualInput.value = 14;
            casualInput.value = 7;
            halfDayInput.value = 10;
            document.getElementById('mainAllocType').value = 'Year';
        }
    });

    function openEditModal(role, annual, casual, halfDay, type, dept) {
        document.getElementById('modalRoleName').textContent = role + (dept ? ' (' + dept + ')' : '');
        document.getElementById('editRoleInput').value = role;
        document.getElementById('editDeptInput').value = dept || 'General';
        document.getElementById('editAnnual').value = annual;
        document.getElementById('editCasual').value = casual;
        document.getElementById('editHalfDay').value = halfDay;
        if (type) document.getElementById('editType').value = type;
        document.getElementById('editAllocModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editAllocModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeEditModal();
    });
</script>