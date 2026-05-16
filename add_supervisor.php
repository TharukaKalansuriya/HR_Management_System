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
$error = "";

if (isset($_POST['add_supervisor'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact_no']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $role = mysqli_real_escape_string($conn, $_POST['position']); // Store the specific role name in the role column

    // Check if email already exists
    $check_email = mysqli_query($conn, "SELECT id FROM system_users WHERE email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $error = "Email already registered!";
    } else {
        $query = "INSERT INTO system_users (first_name, last_name, email, contact_no, password_hash, role, department, is_active) 
                  VALUES ('$first_name', '$last_name', '$email', '$contact', '$password', '$role', '$department', 1)";

        if (mysqli_query($conn, $query)) {
            $msg = "Supervisor added successfully!";
        } else {
            $error = "Error adding supervisor: " . mysqli_error($conn);
        }
    }
}
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
    <header
        class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Add Supervisor</h1>
            <p class="text-xs text-slate-400 font-medium">Create a new supervisor account for a department</p>
        </div>
        <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
    </header>

    <main class="flex-1 p-6 lg:p-8 bg-slate-100">
        <div class="max-w-3xl mx-auto">
            <div class="mb-8">
                <a href="admin_dashboard.php"
                    class="inline-flex items-center text-sm font-semibold text-slate-400 hover:text-blue-600 transition-colors mb-4 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
                            clip-rule="evenodd" />
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="text-3xl font-bold text-slate-800">Add New Supervisor</h1>
                <p class="text-slate-500 mt-1">Fill in the details to register a new supervisor in the system.</p>
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

            <?php if ($error): ?>
                <div id="error-alert"
                    class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl flex items-center gap-3 animate-fade-in">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-semibold text-sm"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <form method="POST" class="p-8 md:p-10 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none"
                                placeholder="Enter first name">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none"
                                placeholder="Enter last name">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="email">Email Address</label>
                            <input type="email" id="email" name="email" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none"
                                placeholder="e.g. supervisor@company.com">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="contact_no">Contact
                                Number</label>
                            <input type="text" id="contact_no" name="contact_no" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none"
                                placeholder="e.g. 0712345678">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="department">Department</label>
                            <select id="department" name="department" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none appearance-none cursor-pointer">
                                <option value="">Select Department</option>
                                <?php
                                $depts_query = mysqli_query($conn, "SELECT DISTINCT department FROM department_roles ORDER BY department");
                                while ($d = mysqli_fetch_assoc($depts_query)):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($d['department']); ?>">
                                        <?php echo htmlspecialchars($d['department']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="position">Role / Position</label>
                            <select id="position" name="position" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none appearance-none cursor-pointer">
                                <option value="">Select Role</option>
                                <!-- Roles will be dynamically populated -->
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700" for="password">Login Password</label>
                            <input type="password" id="password" name="password" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none"
                                placeholder="Create a secure password">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" name="add_supervisor"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-blue-100 transition-all transform hover:-translate-y-1 active:scale-95">
                            Register Supervisor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // Pre-load roles mapping from PHP
    <?php
    $dept_roles_query = mysqli_query($conn, "SELECT department, role_name FROM department_roles ORDER BY department, role_name");
    $dept_roles_map = [];
    while ($row = mysqli_fetch_assoc($dept_roles_query)) {
        $dept_roles_map[$row['department']][] = $row['role_name'];
    }
    ?>
    const rolesMap = <?php echo json_encode($dept_roles_map); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const deptSelect = document.getElementById('department');
        const roleSelect = document.getElementById('position');

        deptSelect.addEventListener('change', function () {
            const dept = this.value;
            // Clear current options
            roleSelect.innerHTML = '<option value="">Select Role</option>';

            if (dept && rolesMap[dept]) {
                rolesMap[dept].forEach(role => {
                    const option = document.createElement('option');
                    option.value = role;
                    option.textContent = role;
                    roleSelect.appendChild(option);
                });
            }
        });

        // Auto-remove alerts
        setTimeout(() => {
            const alert = document.getElementById('status-alert') || document.getElementById('error-alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 3000);
    });
</script>