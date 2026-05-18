<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';
// Handle Form Submission
$msg = isset($_SESSION['msg']) ? $_SESSION['msg'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['msg'], $_SESSION['error']);

if (isset($_POST['submit_termination']) || isset($_POST['update_termination'])) {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $termination_date = mysqli_real_escape_string($conn, $_POST['termination_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    if (empty($employee_id) || empty($type) || empty($termination_date)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } else {
        if (isset($_POST['update_termination'])) {
            $edit_id = mysqli_real_escape_string($conn, $_POST['edit_id']);
            $query = "UPDATE terminations SET employee_id='$employee_id', type='$type', termination_date='$termination_date', reason='$reason' WHERE id='$edit_id'";
            $action_msg = "Termination updated successfully!";
        } else {
            $query = "INSERT INTO terminations (employee_id, type, termination_date, reason) 
                      VALUES ('$employee_id', '$type', '$termination_date', '$reason')";
            $action_msg = "Termination recorded successfully!";
        }
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['msg'] = $action_msg;
            
            // Insert notification for the employee
            $notif_msg = "A termination notice has been issued/updated for you. Type: $type, Date: " . date('M d, Y', strtotime($termination_date)) . ".";
            $insert_notif = "INSERT INTO notifications (user_id, message) VALUES ('$employee_id', '$notif_msg')";
            mysqli_query($conn, $insert_notif);
        } else {
            $_SESSION['error'] = "Error: " . mysqli_error($conn);
        }
    }
    header("Location: termination.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete']);
    $delete_query = "DELETE FROM terminations WHERE id = '$delete_id'";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['msg'] = "Termination record deleted successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    header("Location: termination.php");
    exit;
}

// Handle Edit Fetch
$edit_data = null;
$edit_dept = "";
$edit_role = "";
if (isset($_GET['edit'])) {
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit']);
    $edit_query = mysqli_query($conn, "SELECT t.*, e.department, e.position FROM terminations t JOIN employees e ON t.employee_id = e.id WHERE t.id = '$edit_id'");
    if ($edit_query && mysqli_num_rows($edit_query) > 0) {
        $edit_data = mysqli_fetch_assoc($edit_query);
        $edit_dept = $edit_data['department'];
        $edit_role = $edit_data['position'];
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';

// Fetch Departments
$depts_query = mysqli_query($conn, "SELECT DISTINCT department FROM department_roles ORDER BY department");

// Fetch Roles Map
$dept_roles_query = mysqli_query($conn, "SELECT department, role_name FROM department_roles ORDER BY department, role_name");
$dept_roles_map = [];
while ($row = mysqli_fetch_assoc($dept_roles_query)) {
    $dept_roles_map[$row['department']][] = $row['role_name'];
}

// Fetch Employees Map
$emp_query = mysqli_query($conn, "SELECT id, first_name, last_name, department, position FROM employees ORDER BY first_name");
$emp_map = [];
while ($row = mysqli_fetch_assoc($emp_query)) {
    $emp_map[$row['department']][$row['position']][] = [
        'id' => $row['id'],
        'name' => $row['first_name'] . ' ' . $row['last_name']
    ];
}

// Fetch Existing Terminations
$term_list_query = "SELECT t.*, e.first_name, e.last_name, e.department, e.position 
                     FROM terminations t 
                     JOIN employees e ON t.employee_id = e.id 
                     ORDER BY t.created_at DESC";
$term_list_res = mysqli_query($conn, $term_list_query);

$term_by_dept = [];
while ($row = mysqli_fetch_assoc($term_list_res)) {
    $term_by_dept[$row['department']][] = $row;
}
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Termination</h1>
            <p class="text-xs text-slate-400 font-medium">All employee leave records across the organisation</p>
        </div>
        <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
    </header>

    <main class="flex-1 p-6 lg:p-8 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="hr_dashboard.php" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            <!-- Title moved below the header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-slate-800">Employer Termination</h1>
                <p class="text-sm text-slate-500 font-medium">Initiate termination and record reasons.</p>
            </div>
            
            <?php if ($msg): ?>
                <div id="status-alert" class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-2xl flex items-center gap-3 animate-fade-in">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-semibold text-sm"><?php echo $msg; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-semibold text-sm"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Column: Form -->
                <div class="lg:col-span-4">
                    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-lg shadow-slate-50 sticky top-24">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center font-bold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-800">Record Termination</h2>
                                <p class="text-xs text-slate-400 font-medium">Fill in details below.</p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-4">
                            <!-- Department -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700" for="department">Department</label>
                                <select id="department" name="department" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none text-sm">
                                    <option value="">-- Select Department --</option>
                                    <?php while ($row = mysqli_fetch_assoc($depts_query)): ?>
                                        <option value="<?php echo htmlspecialchars($row['department']); ?>"><?php echo htmlspecialchars($row['department']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Role -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700" for="role">Role</label>
                                <select id="role" name="role" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none text-sm">
                                    <option value="">-- Select Role --</option>
                                </select>
                            </div>

                            <!-- Employee -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700" for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none text-sm">
                                    <option value="">-- Select Employee --</option>
                                </select>
                            </div>

                            <!-- Type -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700" for="type">Type</label>
                                <select id="type" name="type" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none text-sm">
                                    <option value="">-- Select Type --</option>
                                    <option value="Disciplinary" <?php echo ($edit_data && $edit_data['type'] === 'Disciplinary') ? 'selected' : ''; ?>>Disciplinary</option>
                                    <option value="Non-Disciplinary" <?php echo ($edit_data && $edit_data['type'] === 'Non-Disciplinary') ? 'selected' : ''; ?>>Non-Disciplinary</option>
                                </select>
                            </div>

                            <!-- Date -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700" for="termination_date">Termination Date</label>
                                <input type="date" id="termination_date" name="termination_date" required value="<?php echo $edit_data ? $edit_data['termination_date'] : ''; ?>" class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none text-sm">
                            </div>

                            <!-- Reason -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700" for="reason">Reason / Notes</label>
                                <textarea id="reason" name="reason" rows="3" class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none text-sm" placeholder="Enter reason..."><?php echo $edit_data ? htmlspecialchars($edit_data['reason']) : ''; ?></textarea>
                            </div>

                            <div class="pt-2">
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="edit_id" value="<?php echo $edit_data['id']; ?>">
                                    <button type="submit" name="update_termination" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg shadow-emerald-100 transition-all transform hover:-translate-y-1 active:scale-95 text-sm">
                                        Update Termination
                                    </button>
                                    <a href="termination.php" class="w-full block text-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 px-4 rounded-lg transition-all mt-2 text-sm">
                                        Cancel Edit
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="submit_termination" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg shadow-rose-100 transition-all transform hover:-translate-y-1 active:scale-95 text-sm">
                                        Record Termination
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Accordion -->
                <div class="lg:col-span-8">
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-lg shadow-slate-50 overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <h2 class="text-base font-bold text-slate-800">Termination Records</h2>
                            <p class="text-xs text-slate-400 font-medium mt-1">Grouped by Department. Click to expand.</p>
                        </div>
                        
                        <?php if (count($term_by_dept) > 0): ?>
                            <div class="divide-y divide-slate-100">
                                <?php foreach ($term_by_dept as $dept => $rows): 
                                    $dept_safe = md5($dept); // Safe ID for toggle
                                ?>
                                    <div class="border-b border-slate-100 last:border-0">
                                        <!-- Accordion Header -->
                                        <button onclick="toggleAccordion('<?php echo $dept_safe; ?>')" class="w-full px-6 py-4 flex items-center justify-between bg-slate-50 hover:bg-slate-100 transition-colors">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($dept); ?> <span class="text-blue-600">(<?php echo count($rows); ?>)</span></span>
                                            </div>
                                            <svg id="icon-<?php echo $dept_safe; ?>" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>

                                        <!-- Accordion Content -->
                                        <div id="content-<?php echo $dept_safe; ?>" class="hidden overflow-x-auto">
                                            <table class="w-full text-sm text-left text-slate-500">
                                                <thead class="text-xs text-slate-700 uppercase bg-slate-50 font-bold border-b border-slate-100">
                                                    <tr>
                                                        <th class="px-6 py-3">ID</th>
                                                        <th class="px-6 py-3">Employee</th>
                                                        <th class="px-6 py-3">Type</th>
                                                        <th class="px-6 py-3">Date</th>
                                                        <th class="px-6 py-3">Reason</th>
                                                        <th class="px-6 py-3">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($rows as $row): 
                                                        $type_class = $row['type'] === 'Disciplinary' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700';
                                                    ?>
                                                        <tr class="bg-white border-b hover:bg-slate-50">
                                                            <td class="px-6 py-4 text-slate-500 font-medium"><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                                            <td class="px-6 py-4 font-medium text-slate-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                            <td class="px-6 py-4">
                                                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold <?php echo $type_class; ?>">
                                                                    <?php echo $row['type']; ?>
                                                                </span>
                                                            </td>
                                                            <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($row['termination_date'])); ?></td>
                                                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['reason']); ?></td>
                                                            <td class="px-6 py-4">
                                                                <div class="flex gap-2">
                                                                    <a href="termination.php?edit=<?php echo $row['id']; ?>" class="p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors flex items-center justify-center" title="Edit Termination">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                        </svg>
                                                                    </a>
                                                                    <a href="termination.php?delete=<?php echo $row['id']; ?>" class="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg transition-colors flex items-center justify-center" title="Delete Termination" onclick="return confirm('Are you sure you want to delete this termination record?')">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                        </svg>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-2xl border border-slate-100 shadow-lg shadow-slate-50 p-10 text-center text-slate-400">
                                <div class="w-16 h-16 bg-slate-100 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="font-semibold text-slate-600">No termination records recorded yet.</p>
                                <p class="text-xs text-slate-400 mt-1">Record a termination using the form on the left.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleAccordion(id) {
        const content = document.getElementById('content-' + id);
        const icon = document.getElementById('icon-' + id);
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }

    // Pre-load maps from PHP
    const rolesMap = <?php echo json_encode($dept_roles_map); ?>;
    const empMap = <?php echo json_encode($emp_map); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const deptSelect = document.getElementById('department');
        const roleSelect = document.getElementById('role');
        const empSelect = document.getElementById('employee_id');

        // If edit mode
        <?php if ($edit_data): ?>
            setTimeout(() => {
                deptSelect.value = "<?php echo $edit_dept; ?>";
                deptSelect.dispatchEvent(new Event('change'));
                
                setTimeout(() => {
                    roleSelect.value = "<?php echo $edit_role; ?>";
                    roleSelect.dispatchEvent(new Event('change'));
                    
                    setTimeout(() => {
                        empSelect.value = "<?php echo $edit_data['employee_id']; ?>";
                    }, 50);
                }, 50);
            }, 50);
        <?php endif; ?>

        // Department Change
        deptSelect.addEventListener('change', function () {
            const dept = this.value;
            roleSelect.innerHTML = '<option value="">-- Select Role --</option>';
            empSelect.innerHTML = '<option value="">-- Select Employee --</option>';

            if (dept && rolesMap[dept]) {
                rolesMap[dept].forEach(role => {
                    const option = document.createElement('option');
                    option.value = role;
                    option.textContent = role;
                    roleSelect.appendChild(option);
                });
            }
        });

        // Role Change
        roleSelect.addEventListener('change', function () {
            const dept = deptSelect.value;
            const role = this.value;
            empSelect.innerHTML = '<option value="">-- Select Employee --</option>';

            if (dept && role && empMap[dept] && empMap[dept][role]) {
                empMap[dept][role].forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `${emp.name} (ID: ${emp.id})`;
                    empSelect.appendChild(option);
                });
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
