<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php';
include 'includes/sidebar.php';

$msg = "";

// Handle Adding New Department/Role Pair
if (isset($_POST['add_dept_role'])) {
    $new_dept = mysqli_real_escape_string($conn, $_POST['new_department']);
    $new_role = mysqli_real_escape_string($conn, $_POST['new_role']);
    
    if (!empty($new_dept) && !empty($new_role)) {
        $insert_dept_role = "INSERT IGNORE INTO department_roles (department, role_name) VALUES ('$new_dept', '$new_role')";
        if (mysqli_query($conn, $insert_dept_role)) {
            $msg = "Role '$new_role' added to '$new_dept' successfully.";
        }
    }
}

// Handle Deleting Department/Role Pair
if (isset($_POST['delete_dept_role'])) {
    $pair_id = (int)$_POST['pair_id'];
    mysqli_query($conn, "DELETE FROM department_roles WHERE id = $pair_id");
    $msg = "Role removed from department successfully.";
}
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
<header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Manage Roles</h1>
        <p class="text-xs text-slate-400 font-medium">Add departments and roles to the system structure</p>
    </div>
    <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
</header>
<main class="flex-1 p-6 lg:p-8 bg-slate-100">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-10">
            <h1 class="text-3xl font-bold text-slate-800">Manage Organization Structure</h1>
            <p class="text-slate-500 mt-1">Add departments and roles to the system structure.</p>
        </div>

        <?php if ($msg): ?>
            <div id="status-alert" class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-2xl flex items-center gap-3 animate-fade-in">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-semibold text-sm"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Add New Section -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 mb-8">
            <h2 class="text-xl font-bold text-slate-800 mb-6">Add New Department & Role</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Department Name</label>
                    <input type="text" name="new_department" placeholder="e.g. IT Department" required 
                        class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Role Name</label>
                    <input type="text" name="new_role" placeholder="e.g. Software Engineer" required 
                        class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-2 pt-2">
                    <button type="submit" name="add_dept_role" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-slate-200 flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add to Structure
                    </button>
                </div>
            </form>
        </div>

        <!-- Structure List -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-50">
                <h2 class="text-xl font-bold text-slate-800">Current Structure</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Department</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Role</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php 
                            $all_roles_query = mysqli_query($conn, "SELECT * FROM department_roles ORDER BY department, role_name");
                            $total_roles = mysqli_num_rows($all_roles_query);
                            $counter = 0;
                            if ($total_roles > 0):
                                while($pair = mysqli_fetch_assoc($all_roles_query)):
                                    $counter++;
                                    $isHidden = ($counter > 3) ? 'hidden-row hidden' : '';
                        ?>
                                <tr class="hover:bg-slate-50/30 transition-colors <?php echo $isHidden; ?>">
                                    <td class="px-8 py-5 text-sm text-slate-500 font-medium"><?php echo htmlspecialchars($pair['department']); ?></td>
                                    <td class="px-8 py-5 text-sm font-bold text-slate-700"><?php echo htmlspecialchars($pair['role_name']); ?></td>
                                    <td class="px-8 py-5 text-right">
                                        <form method="POST" class="inline" onsubmit="return confirm('Remove this role from the structure?')">
                                            <input type="hidden" name="pair_id" value="<?php echo $pair['id']; ?>">
                                            <button type="submit" name="delete_dept_role" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-8 py-10 text-center text-slate-400 italic">No departments or roles defined yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_roles > 3): ?>
                <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-center">
                    <button id="viewAllBtn" data-state="collapsed" class="text-sm font-bold text-blue-600 hover:text-blue-700 flex items-center gap-2 transition-colors">
                        <span id="viewBtnText">View All (<?php echo $total_roles; ?>)</span>
                        <svg id="viewBtnIcon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
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

    const viewAllBtn = document.getElementById('viewAllBtn');
    const viewBtnText = document.getElementById('viewBtnText');
    const viewBtnIcon = document.getElementById('viewBtnIcon');
    const totalRoles = <?php echo $total_roles ?? 0; ?>;

    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function() {
            const isCollapsed = this.getAttribute('data-state') === 'collapsed';
            const hiddenRows = document.querySelectorAll('.hidden-row');
            
            if (isCollapsed) {
                hiddenRows.forEach(row => row.classList.remove('hidden'));
                viewBtnText.textContent = 'Show Less';
                viewBtnIcon.classList.add('rotate-180');
                this.setAttribute('data-state', 'expanded');
            } else {
                hiddenRows.forEach(row => row.classList.add('hidden'));
                viewBtnText.textContent = `View All (${totalRoles})`;
                viewBtnIcon.classList.remove('rotate-180');
                this.setAttribute('data-state', 'collapsed');
                // Scroll back to top of list if needed
                this.closest('.bg-white').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
</script>
