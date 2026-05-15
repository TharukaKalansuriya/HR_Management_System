<?php
$admin_only = true;
require_once 'auth_check.php';
require_once 'db_config.php';

$success = $error = '';
$tab = $_GET['tab'] ?? 'managers';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Reset employee portal password (super_admin only)
    if ($action === 'reset_emp_password') {
        $emp_id = (int)($_POST['emp_id'] ?? 0);
        $new_pw = trim($_POST['new_password'] ?? '');
        if ($emp_id && strlen($new_pw) >= 6) {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE employees SET password_hash=? WHERE id=?")->execute([$hash, $emp_id]);
            $success = "Password updated. New password: <code class='bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-mono'>" . htmlspecialchars($new_pw) . "</code> — Share with the employee.";
        } else {
            $error = 'Password must be at least 6 characters.';
        }
    }

    // Create user
    if ($action === 'create_user') {
        $fn   = trim($_POST['first_name'] ?? '');
        $ln   = trim($_POST['last_name']  ?? '');
        $em   = trim($_POST['email']      ?? '');
        $cn   = trim($_POST['contact_no'] ?? '');
        $role = $_POST['role'] ?? 'hr_officer';
        $pw   = $_POST['password'] ?? '';

        if (!$fn || !$ln || !$em || !$pw) {
            $error = 'All fields are required.';
        } elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($pw) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ins  = $pdo->prepare("INSERT INTO system_users (first_name,last_name,email,contact_no,password_hash,role,is_active,created_by) VALUES (?,?,?,?,?,?,1,?)");
                $ins->execute([$fn, $ln, $em, $cn, $hash, $role, $_SESSION['user_id']]);
                $success = "User {$fn} {$ln} created successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "An account with the email '{$em}' already exists. Please use a different email.";
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    // Update user
    if ($action === 'update_user') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $fn   = trim($_POST['first_name'] ?? '');
        $ln   = trim($_POST['last_name']  ?? '');
        $em   = trim($_POST['email']      ?? '');
        $cn   = trim($_POST['contact_no'] ?? '');
        $role = $_POST['role'] ?? 'hr_officer';
        $pw   = $_POST['password'] ?? '';

        if (!$fn || !$ln || !$em) { $error = 'Name and email are required.'; }
        else {
            try {
                if ($pw) {
                    $hash = password_hash($pw, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=?,role=?,password_hash=? WHERE id=?");
                    $stmt->execute([$fn,$ln,$em,$cn,$role,$hash,$uid]);
                } else {
                    $stmt = $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=?,role=? WHERE id=?");
                    $stmt->execute([$fn,$ln,$em,$cn,$role,$uid]);
                }
                $success = 'User updated successfully.';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "The email '{$em}' is already in use by another account.";
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    // Toggle active
    if ($action === 'toggle_active') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $cur = (int)($_POST['current_status'] ?? 1);
        $pdo->prepare("UPDATE system_users SET is_active=? WHERE id=?")->execute([($cur ? 0 : 1), $uid]);
        $success = 'User status updated.';
    }

    // Save permissions
    if ($action === 'save_permissions') {
        $uid   = (int)($_POST['perm_user_id'] ?? 0);
        $pages = ['index.php','employees.php','attendance.php','payroll.php','recruitment.php',
                  'salary_structures.php','payroll_run.php','job_postings.php','applicants.php','interviews.php'];
        foreach ($pages as $pg) {
            $allowed = isset($_POST['perm'][$pg]) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO page_permissions (user_id,page_name,is_allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_allowed=?");
            $stmt->execute([$uid,$pg,$allowed,$allowed]);
        }
        $success = 'Permissions saved.';
    }

    // Update own account
    if ($action === 'update_own') {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name']  ?? '');
        $em = trim($_POST['email']      ?? '');
        $cn = trim($_POST['contact_no'] ?? '');
        $pw = $_POST['password'] ?? '';
        if (!$fn || !$ln || !$em) { $error = 'Name and email required.'; }
        else {
            if ($pw) {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=?,password_hash=? WHERE id=?")->execute([$fn,$ln,$em,$cn,$hash,$_SESSION['user_id']]);
            } else {
                $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=? WHERE id=?")->execute([$fn,$ln,$em,$cn,$_SESSION['user_id']]);
            }
            $_SESSION['user_name'] = "$fn $ln";
            $success = 'Your account updated.';
        }
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
// Fetch all non-super-admin users with creator name (for Added By column)
$allUsers = $pdo->query("
    SELECT u.*, CONCAT(c.first_name,' ',c.last_name) AS created_by_name, c.role AS creator_role
    FROM system_users u
    LEFT JOIN system_users c ON c.id = u.created_by
    WHERE u.role != 'super_admin'
    ORDER BY u.role, u.first_name
")->fetchAll(PDO::FETCH_ASSOC);
$managers  = array_filter($allUsers, fn($u) => $u['role'] === 'hr_manager');
$officers  = array_filter($allUsers, fn($u) => $u['role'] === 'hr_officer');
$me        = $pdo->prepare("SELECT * FROM system_users WHERE id=?");
$me->execute([$_SESSION['user_id']]);
$meRow = $me->fetch(PDO::FETCH_ASSOC);

$pages = ['index.php'=>'Dashboard','employees.php'=>'Employees','attendance.php'=>'Attendance',
          'payroll.php'=>'Payroll','salary_structures.php'=>'Salary Structures','payroll_run.php'=>'Payroll Run',
          'recruitment.php'=>'Recruitment','job_postings.php'=>'Job Postings','applicants.php'=>'Applicants','interviews.php'=>'Interviews'];

$page_title = 'Super Admin Panel';
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative overflow-hidden">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-y-auto p-8 custom-scrollbar">

<?php if ($success): ?><div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-2"><i class="fa-solid fa-circle-check"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if ($error):   ?><div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="mb-6"><h1 class="text-2xl font-bold text-gray-800">Super Admin Panel</h1><p class="text-gray-500 text-sm mt-1">Manage all system users and access permissions</p></div>

<!-- Tabs -->
<div class="flex gap-1 mb-6 bg-white border border-gray-200 rounded-xl p-1 w-fit shadow-sm">
<?php foreach(['managers'=>'HR Managers','officers'=>'HR Officers','permissions'=>'Page Permissions','employee_accounts'=>'Employee Accounts','account'=>'My Account'] as $k=>$v): ?>
<a href="?tab=<?=$k?>" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all <?=$tab===$k?'bg-brand-900 text-white shadow':'text-gray-600 hover:bg-gray-100'?>">
<i class="fa-solid <?=['managers'=>'fa-user-tie','officers'=>'fa-users','permissions'=>'fa-shield-halved','employee_accounts'=>'fa-id-card','account'=>'fa-circle-user'][$k]?> mr-2"></i><?=$v?></a>
<?php endforeach; ?>
</div>

<?php if ($tab === 'managers' || $tab === 'officers'):
    $listUsers = $tab === 'managers' ? $managers : $officers;
    $roleVal   = $tab === 'managers' ? 'hr_manager' : 'hr_officer';
    $roleLabel = $tab === 'managers' ? 'HR Manager' : 'HR Officer';
?>
<!-- Create User Form -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
<h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-user-plus mr-2 text-brand-600"></i>Add New <?=$roleLabel?></h2>
<form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
<input type="hidden" name="action" value="create_user">
<input type="hidden" name="role" value="<?=$roleVal?>">
<input type="hidden" name="tab" value="<?=$tab?>">
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">First Name *</label><input name="first_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Last Name *</label><input name="last_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email *</label><input type="email" name="email" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Contact No</label><input name="contact_no" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Password *</label><input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none" required></div>
<div class="flex items-end"><button type="submit" class="w-full bg-brand-900 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-brand-800 transition-colors">Create <?=$roleLabel?></button></div>
</form>
</div>

<!-- Users Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<h2 class="font-semibold text-gray-800">All <?=$roleLabel?>s (<?=count($listUsers)?>)</h2>
<?php if($tab === 'officers'): ?>
<span class="text-xs text-gray-400"><i class="fa-solid fa-circle-info mr-1"></i>Includes officers added by you and HR Managers</span>
<?php endif; ?>
</div>
<div class="overflow-x-auto">
<table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/70"><tr>
<th class="px-6 py-3">Name</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Contact</th>
<?php if($tab === 'officers'): ?><th class="px-6 py-3">Added By</th><?php endif; ?>
<th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Actions</th>
</tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($listUsers as $u): ?>
<tr class="hover:bg-gray-50/50 transition-colors">
<td class="px-6 py-4"><div class="flex items-center gap-3"><img class="w-8 h-8 rounded-full border border-gray-200" src="https://ui-avatars.com/api/?name=<?=urlencode($u['first_name'].'+'.$u['last_name'])?>&background=dbeafe&color=1e3a8a" alt=""><div><p class="font-medium text-gray-800"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></p><p class="text-xs text-gray-400">ID #<?=$u['id']?></p></div></div></td>
<td class="px-6 py-4 text-gray-600"><?=htmlspecialchars($u['email'])?></td>
<td class="px-6 py-4 text-gray-600"><?=htmlspecialchars($u['contact_no'] ?? '—')?></td>
<?php if($tab === 'officers'): ?>
<td class="px-6 py-4">
<?php if(empty($u['created_by_name'])): ?>
  <span class="text-gray-400 text-xs">—</span>
<?php elseif($u['creator_role'] === 'super_admin'): ?>
  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700"><i class="fa-solid fa-user-shield mr-1"></i>Super Admin</span>
<?php else: ?>
  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700"><i class="fa-solid fa-user-tie mr-1"></i><?=htmlspecialchars($u['created_by_name'])?></span>
<?php endif; ?>
</td>
<?php endif; ?>
<td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?=$u['is_active']?'bg-emerald-100 text-emerald-700':'bg-red-100 text-red-600'?>"><?=$u['is_active']?'Active':'Disabled'?></span></td>
<td class="px-6 py-4 text-right flex justify-end gap-2">
<button onclick="openEditModal(<?=htmlspecialchars(json_encode($u))?>,<?=htmlspecialchars(json_encode($roleVal))?>,<?=htmlspecialchars(json_encode($tab))?>)" class="px-3 py-1.5 bg-brand-50 text-brand-700 rounded-lg text-xs font-medium hover:bg-brand-100 transition-colors"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
<form method="POST" class="inline"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?=$u['id']?>"><input type="hidden" name="current_status" value="<?=$u['is_active']?>"><input type="hidden" name="tab" value="<?=$tab?>"><button type="submit" class="px-3 py-1.5 <?=$u['is_active']?'bg-red-50 text-red-600 hover:bg-red-100':'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'?> rounded-lg text-xs font-medium transition-colors"><?=$u['is_active']?'<i class="fa-solid fa-ban mr-1"></i>Disable':'<i class="fa-solid fa-check mr-1"></i>Enable'?></button></form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!count($listUsers)): ?><tr><td colspan="<?=$tab==='officers'?6:5?>" class="px-6 py-10 text-center text-gray-400"><i class="fa-solid fa-users text-2xl mb-2 block"></i>No <?=$roleLabel?>s yet.</td></tr><?php endif; ?>
</tbody></table></div></div>

<?php elseif ($tab === 'permissions'): ?>
<!-- Permissions Matrix -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
<h2 class="text-lg font-semibold text-gray-800 mb-1"><i class="fa-solid fa-shield-halved mr-2 text-brand-600"></i>Page Permissions</h2>
<p class="text-sm text-gray-500 mb-6">Control which pages each user can access. Super Admin always has full access.</p>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/70">
<tr><th class="px-4 py-3 text-left">User</th><th class="px-4 py-3 text-left">Role</th>
<?php foreach($pages as $pg=>$lbl): ?><th class="px-2 py-3 text-center text-xs"><?=htmlspecialchars($lbl)?></th><?php endforeach; ?>
<th class="px-4 py-3 text-center">Save</th></tr>
</thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($allUsers as $u):
    $perms = [];
    $ps = $pdo->prepare("SELECT page_name,is_allowed FROM page_permissions WHERE user_id=?");
    $ps->execute([$u['id']]);
    foreach($ps->fetchAll(PDO::FETCH_ASSOC) as $p) $perms[$p['page_name']] = $p['is_allowed'];
?>
<tr class="hover:bg-gray-50/50">
<form method="POST">
<input type="hidden" name="action" value="save_permissions">
<input type="hidden" name="perm_user_id" value="<?=$u['id']?>">
<input type="hidden" name="tab" value="permissions">
<td class="px-4 py-3 font-medium text-gray-800"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
<td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700"><?=htmlspecialchars(ucwords(str_replace('_',' ',$u['role'])))?></span></td>
<?php foreach($pages as $pg=>$lbl):
    $checked = isset($perms[$pg]) ? $perms[$pg] : 1;
?>
<td class="px-2 py-3 text-center"><input type="checkbox" name="perm[<?=htmlspecialchars($pg)?>]" value="1" <?=$checked?'checked':''?> class="w-4 h-4 accent-blue-600 cursor-pointer rounded"></td>
<?php endforeach; ?>
<td class="px-4 py-3 text-center"><button type="submit" class="px-3 py-1.5 bg-brand-900 text-white rounded-lg text-xs font-semibold hover:bg-brand-800 transition-colors">Save</button></td>
</form></tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php elseif ($tab === 'employee_accounts'):
    // Fetch all employees with their portal password status
    $empAccStmt = $pdo->query("SELECT id, first_name, last_name, full_name, email, department, designation, position, status, password_hash FROM employees ORDER BY first_name");
    $empAccounts = $empAccStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Employee Portal Accounts -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-id-card text-brand-600"></i> Employee Portal Accounts</h2>
            <p class="text-xs text-gray-400 mt-0.5">View account status and reset leave-portal passwords for all employees</p>
        </div>
        <span class="text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full font-medium"><?=count($empAccounts)?> employees</span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
    <thead class="text-xs text-gray-500 uppercase bg-gray-50/70 border-b border-gray-100">
        <tr>
            <th class="px-6 py-3">Employee</th>
            <th class="px-6 py-3">Email (Portal Login)</th>
            <th class="px-6 py-3">Department</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Portal Account</th>
            <th class="px-6 py-3 text-right">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
    <?php foreach($empAccounts as $ea):
        $eName = !empty($ea['full_name']) ? $ea['full_name'] : ($ea['first_name'].' '.$ea['last_name']);
        $eRole = $ea['designation'] ?: $ea['position'] ?: 'Staff';
        $hasPortal = !empty($ea['password_hash']);
        $empStatusClr = $ea['status'] === 'Active' ? 'bg-emerald-100 text-emerald-700' : ($ea['status'] === 'On Leave' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500');
    ?>
    <tr class="hover:bg-gray-50/50 transition-colors" id="emp-row-<?=$ea['id']?>">
        <td class="px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-brand-50 border border-brand-100 flex items-center justify-center text-brand-700 text-xs font-bold flex-shrink-0">
                    <?=strtoupper(substr($ea['first_name'],0,1).substr($ea['last_name'],0,1))?>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-xs"><?=htmlspecialchars($eName)?></p>
                    <p class="text-xs text-gray-400"><?=htmlspecialchars($eRole)?></p>
                </div>
            </div>
        </td>
        <td class="px-6 py-4"><code class="text-xs font-mono text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded"><?=htmlspecialchars($ea['email'])?></code></td>
        <td class="px-6 py-4 text-xs text-gray-600"><?=htmlspecialchars($ea['department'] ?: '—')?></td>
        <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?=$empStatusClr?>"><?=htmlspecialchars($ea['status'])?></span></td>
        <td class="px-6 py-4">
            <?php if ($hasPortal): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Active
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600">
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span> No Password
                </span>
            <?php endif; ?>
        </td>
        <td class="px-6 py-4 text-right">
            <button onclick="openEmpPwModal(<?=$ea['id']?>, <?=htmlspecialchars(json_encode($eName))?>, <?=htmlspecialchars(json_encode($ea['email']))?>)"
                class="px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-medium transition-colors">
                <i class="fa-solid fa-key mr-1"></i>Reset Password
            </button>
            <a href="employee_edit.php?id=<?=$ea['id']?>" class="ml-1 px-3 py-1.5 bg-brand-50 text-brand-700 hover:bg-brand-100 rounded-lg text-xs font-medium transition-colors inline-block">
                <i class="fa-solid fa-pen mr-1"></i>Edit
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!count($empAccounts)): ?>
    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400"><i class="fa-solid fa-users text-2xl mb-2 block"></i>No employees found.</td></tr>
    <?php endif; ?>
    </tbody></table>
    </div>
</div>

<?php elseif ($tab === 'account'): ?>
<!-- My Account -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-lg">
<h2 class="text-lg font-semibold text-gray-800 mb-6"><i class="fa-solid fa-circle-user mr-2 text-brand-600"></i>My Account</h2>
<form method="POST" class="space-y-4">
<input type="hidden" name="action" value="update_own">
<input type="hidden" name="tab" value="account">
<div class="grid grid-cols-2 gap-4">
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">First Name *</label><input name="first_name" value="<?=htmlspecialchars($meRow['first_name'])?>" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Last Name *</label><input name="last_name" value="<?=htmlspecialchars($meRow['last_name'])?>" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
</div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email *</label><input type="email" name="email" value="<?=htmlspecialchars($meRow['email'])?>" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Contact No</label><input name="contact_no" value="<?=htmlspecialchars($meRow['contact_no']??'')?>" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none"></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">New Password <span class="text-gray-400 normal-case">(leave blank to keep)</span></label><input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" placeholder="••••••••"></div>
<button type="submit" class="w-full bg-brand-900 text-white px-4 py-3 rounded-xl font-semibold hover:bg-brand-800 transition-colors">Update My Account</button>
</form></div>
<?php endif; ?>

</div>
</main>

<!-- Edit User Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
<div class="flex justify-between items-center mb-5">
<h3 class="text-lg font-semibold text-gray-800"><i class="fa-solid fa-pen mr-2 text-brand-600"></i>Edit User</h3>
<button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
</div>
<form method="POST" class="space-y-4" id="editForm">
<input type="hidden" name="action" value="update_user">
<input type="hidden" name="user_id" id="edit_user_id">
<input type="hidden" name="tab" id="edit_tab">
<input type="hidden" name="role" id="edit_role">
<div class="grid grid-cols-2 gap-4">
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">First Name *</label><input id="edit_fn" name="first_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Last Name *</label><input id="edit_ln" name="last_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
</div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email *</label><input type="email" id="edit_em" name="email" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Contact No</label><input id="edit_cn" name="contact_no" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none"></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">New Password <span class="text-gray-400 normal-case">(leave blank to keep)</span></label><input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" placeholder="••••••••"></div>
<div class="flex gap-3 pt-2">
<button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 border border-gray-200 text-gray-600 px-4 py-2.5 rounded-xl font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
<button type="submit" class="flex-1 bg-brand-900 text-white px-4 py-2.5 rounded-xl font-semibold hover:bg-brand-800 transition-colors">Save Changes</button>
</div>
</form>
</div>
</div>

<script>
function openEditModal(user, role, tab) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_fn').value       = user.first_name;
    document.getElementById('edit_ln').value       = user.last_name;
    document.getElementById('edit_em').value       = user.email;
    document.getElementById('edit_cn').value       = user.contact_no ?? '';
    document.getElementById('edit_role').value     = role;
    document.getElementById('edit_tab').value      = tab;
    document.getElementById('editModal').classList.remove('hidden');
}
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) this.classList.add('hidden'); });
</script>

<!-- Employee Portal Password Reset Modal -->
<div id="empPwModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
    <div class="flex justify-between items-center mb-5">
        <div>
            <h3 class="text-lg font-semibold text-gray-800"><i class="fa-solid fa-key mr-2 text-amber-500"></i>Reset Portal Password</h3>
            <p class="text-sm text-gray-500 mt-0.5" id="empPwSubtitle"></p>
        </div>
        <button onclick="document.getElementById('empPwModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-5 text-xs text-gray-600">
        <span class="font-semibold">Portal Email:</span> <code id="empPwEmail" class="font-mono text-brand-600"></code>
    </div>
    <form method="POST" action="?tab=employee_accounts" class="space-y-4">
        <input type="hidden" name="action" value="reset_emp_password">
        <input type="hidden" name="emp_id" id="empPwId">
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">New Password <span class="text-red-500">*</span></label>
            <div class="relative">
                <input type="password" name="new_password" id="empNewPw" required minlength="6"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-amber-400 outline-none"
                    placeholder="Min. 6 characters">
                <button type="button" onclick="toggleEmpPw()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <i id="empEye" class="fa-regular fa-eye text-sm"></i>
                </button>
            </div>
        </div>
        <button type="button" onclick="genEmpPw()" class="w-full border border-dashed border-gray-300 hover:border-amber-400 text-gray-600 hover:text-amber-600 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
            <i class="fa-solid fa-shuffle"></i> Generate Random Password
        </button>
        <div class="flex gap-3 pt-1">
            <button type="button" onclick="document.getElementById('empPwModal').classList.add('hidden')" class="flex-1 border border-gray-200 text-gray-600 px-4 py-2.5 rounded-xl font-semibold hover:bg-gray-50 transition-colors text-sm">Cancel</button>
            <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm"><i class="fa-solid fa-key mr-1"></i>Reset Password</button>
        </div>
    </form>
</div>
</div>

<script>
function openEmpPwModal(empId, empName, empEmail) {
    document.getElementById('empPwId').value = empId;
    document.getElementById('empPwSubtitle').textContent = empName;
    document.getElementById('empPwEmail').textContent = empEmail;
    document.getElementById('empNewPw').value = '';
    document.getElementById('empNewPw').type = 'password';
    document.getElementById('empEye').className = 'fa-regular fa-eye text-sm';
    document.getElementById('empPwModal').classList.remove('hidden');
}
function toggleEmpPw() {
    const inp = document.getElementById('empNewPw');
    const ico = document.getElementById('empEye');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'text' ? 'fa-regular fa-eye-slash text-sm' : 'fa-regular fa-eye text-sm';
}
function genEmpPw() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    let pw = 'Hrms@';
    for (let i = 0; i < 6; i++) pw += chars[Math.floor(Math.random() * chars.length)];
    const inp = document.getElementById('empNewPw');
    inp.type = 'text';
    inp.value = pw;
    document.getElementById('empEye').className = 'fa-regular fa-eye-slash text-sm';
}
document.getElementById('empPwModal').addEventListener('click', function(e){ if(e.target===this) this.classList.add('hidden'); });
</script>
</body></html>
