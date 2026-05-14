<?php
$admin_only = true;
require_once 'auth_check.php';
require_once 'db_config.php';

$success = $error = '';
$tab = $_GET['tab'] ?? 'managers';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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
<?php foreach(['managers'=>'HR Managers','officers'=>'HR Officers','permissions'=>'Page Permissions','account'=>'My Account'] as $k=>$v): ?>
<a href="?tab=<?=$k?>" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all <?=$tab===$k?'bg-brand-900 text-white shadow':'text-gray-600 hover:bg-gray-100'?>"><i class="fa-solid <?=['managers'=>'fa-user-tie','officers'=>'fa-users','permissions'=>'fa-shield-halved','account'=>'fa-circle-user'][$k]?> mr-2"></i><?=$v?></a>
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
</body></html>
