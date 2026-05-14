<?php
$manager_only = true;
require_once 'auth_check.php';
require_once 'db_config.php';

$success = $error = '';
$tab = $_GET['tab'] ?? 'officers';

$pages = ['index.php'=>'Dashboard','employees.php'=>'Employees','attendance.php'=>'Attendance',
          'payroll.php'=>'Payroll','salary_structures.php'=>'Salary Structures','payroll_run.php'=>'Payroll Run',
          'recruitment.php'=>'Recruitment','job_postings.php'=>'Job Postings','applicants.php'=>'Applicants','interviews.php'=>'Interviews'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_officer') {
        $fn = trim($_POST['first_name']??''); $ln = trim($_POST['last_name']??'');
        $em = trim($_POST['email']??'');      $cn = trim($_POST['contact_no']??'');
        $pw = $_POST['password']??'';
        if (!$fn||!$ln||!$em||!$pw) { $error='All fields required.'; }
        elseif (!filter_var($em,FILTER_VALIDATE_EMAIL)) { $error='Invalid email address.'; }
        elseif (strlen($pw)<6) { $error='Password must be at least 6 characters.'; }
        else {
            try {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ins  = $pdo->prepare("INSERT INTO system_users (first_name,last_name,email,contact_no,password_hash,role,is_active,created_by) VALUES (?,?,?,?,?,'hr_officer',1,?)");
                $ins->execute([$fn,$ln,$em,$cn,$hash,$_SESSION['user_id']]);
                $success = "Officer {$fn} {$ln} created successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "An account with the email '{$em}' already exists. Please use a different email.";
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'update_officer') {
        $uid=(int)($_POST['user_id']??0); $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
        $em=trim($_POST['email']??'');    $cn=trim($_POST['contact_no']??''); $pw=$_POST['password']??'';
        if(!$fn||!$ln||!$em){ $error='Name and email required.'; }
        else {
            try {
                if($pw){
                    $hash=password_hash($pw,PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=?,password_hash=? WHERE id=? AND role='hr_officer'")->execute([$fn,$ln,$em,$cn,$hash,$uid]);
                } else {
                    $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=? WHERE id=? AND role='hr_officer'")->execute([$fn,$ln,$em,$cn,$uid]);
                }
                $success='Officer updated successfully.';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "The email '{$em}' is already in use by another account.";
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'toggle_officer') {
        $uid=(int)($_POST['user_id']??0); $cur=(int)($_POST['current_status']??1);
        // HR Manager can enable/disable any hr_officer
        $pdo->prepare("UPDATE system_users SET is_active=? WHERE id=? AND role='hr_officer'")->execute([($cur?0:1),$uid]);
        $success='Status updated.';
    }

    if ($action === 'save_permissions') {
        $uid=(int)($_POST['perm_user_id']??0);
        // Verify target is an hr_officer (any officer, not just ones created by this manager)
        $chk=$pdo->prepare("SELECT id FROM system_users WHERE id=? AND role='hr_officer'");
        $chk->execute([$uid]);
        if($chk->fetch()){
            foreach($pages as $pg=>$lbl){
                $allowed=isset($_POST['perm'][$pg])?1:0;
                $pdo->prepare("INSERT INTO page_permissions (user_id,page_name,is_allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_allowed=?")->execute([$uid,$pg,$allowed,$allowed]);
            }
            $success='Permissions saved.';
        } else { $error='Unauthorized action.'; }
    }

    if ($action === 'update_own') {
        $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
        $em=trim($_POST['email']??'');      $cn=trim($_POST['contact_no']??''); $pw=$_POST['password']??'';
        if(!$fn||!$ln||!$em){$error='Name and email required.';}
        else {
            if($pw){ $hash=password_hash($pw,PASSWORD_BCRYPT); $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=?,password_hash=? WHERE id=?")->execute([$fn,$ln,$em,$cn,$hash,$_SESSION['user_id']]); }
            else { $pdo->prepare("UPDATE system_users SET first_name=?,last_name=?,email=?,contact_no=? WHERE id=?")->execute([$fn,$ln,$em,$cn,$_SESSION['user_id']]); }
            $_SESSION['user_name']="$fn $ln"; $success='Account updated.';
        }
    }
}

// All HR Officers in the system (visible to HR Manager regardless of who created them)
$officers = $pdo->query("SELECT u.*, CONCAT(c.first_name,' ',c.last_name) AS created_by_name
    FROM system_users u
    LEFT JOIN system_users c ON c.id = u.created_by
    WHERE u.role='hr_officer'
    ORDER BY u.first_name")->fetchAll(PDO::FETCH_ASSOC);

$me=$pdo->prepare("SELECT * FROM system_users WHERE id=?"); $me->execute([$_SESSION['user_id']]); $meRow=$me->fetch(PDO::FETCH_ASSOC);

$page_title = 'HR Manager Panel';
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative overflow-hidden">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-y-auto p-8 custom-scrollbar">

<?php if($success):?><div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-2"><i class="fa-solid fa-circle-check"></i><?=htmlspecialchars($success)?></div><?php endif;?>
<?php if($error):  ?><div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i><?=htmlspecialchars($error)?></div><?php endif;?>

<div class="mb-6"><h1 class="text-2xl font-bold text-gray-800">HR Manager Panel</h1><p class="text-gray-500 text-sm mt-1">View and manage all HR Officers and their page permissions</p></div>

<div class="flex gap-1 mb-6 bg-white border border-gray-200 rounded-xl p-1 w-fit shadow-sm">
<?php foreach(['officers'=>'All HR Officers','permissions'=>'Page Permissions','account'=>'My Account'] as $k=>$v):?>
<a href="?tab=<?=$k?>" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all <?=$tab===$k?'bg-brand-900 text-white shadow':'text-gray-600 hover:bg-gray-100'?>"><i class="fa-solid <?=['officers'=>'fa-users','permissions'=>'fa-shield-halved','account'=>'fa-circle-user'][$k]?> mr-2"></i><?=$v?></a>
<?php endforeach;?>
</div>

<?php if($tab==='officers'):?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
<h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-user-plus mr-2 text-brand-600"></i>Add New HR Officer</h2>
<form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
<input type="hidden" name="action" value="create_officer">
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">First Name *</label><input name="first_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Last Name *</label><input name="last_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email *</label><input type="email" name="email" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Contact No</label><input name="contact_no" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none"></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Password *</label><input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div class="flex items-end"><button type="submit" class="w-full bg-brand-900 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-brand-800 transition-colors">Create Officer</button></div>
</form>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<h2 class="font-semibold text-gray-800">All HR Officers (<?=count($officers)?>)</h2>
<span class="text-xs text-gray-400"><i class="fa-solid fa-circle-info mr-1"></i>Includes officers added by Super Admin and yourself</span>
</div>
<div class="overflow-x-auto"><table class="w-full text-sm text-left">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/70"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Contact</th><th class="px-6 py-3">Added By</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Actions</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($officers as $u):?>
<tr class="hover:bg-gray-50/50 transition-colors">
<td class="px-6 py-4"><div class="flex items-center gap-3"><img class="w-8 h-8 rounded-full border border-gray-200" src="https://ui-avatars.com/api/?name=<?=urlencode($u['first_name'].'+'.$u['last_name'])?>&background=dbeafe&color=1e3a8a" alt=""><div><p class="font-medium text-gray-800"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></p></div></div></td>
<td class="px-6 py-4 text-gray-600"><?=htmlspecialchars($u['email'])?></td>
<td class="px-6 py-4 text-gray-600"><?=htmlspecialchars($u['contact_no']??'—')?></td>
<td class="px-6 py-4">
<?php if($u['created_by'] == $_SESSION['user_id']): ?>
<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700"><i class="fa-solid fa-user-tie mr-1"></i>You</span>
<?php elseif($u['created_by_name']): ?>
<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700"><i class="fa-solid fa-user-shield mr-1"></i><?=htmlspecialchars($u['created_by_name'])?></span>
<?php else: ?>
<span class="text-gray-400 text-xs">—</span>
<?php endif; ?>
</td>
<td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?=$u['is_active']?'bg-emerald-100 text-emerald-700':'bg-red-100 text-red-600'?>"><?=$u['is_active']?'Active':'Disabled'?></span></td>
<td class="px-6 py-4 text-right flex justify-end gap-2">
<button onclick="openEdit(<?=htmlspecialchars(json_encode($u))?>)" class="px-3 py-1.5 bg-brand-50 text-brand-700 rounded-lg text-xs font-medium hover:bg-brand-100 transition-colors"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
<form method="POST" class="inline"><input type="hidden" name="action" value="toggle_officer"><input type="hidden" name="user_id" value="<?=$u['id']?>"><input type="hidden" name="current_status" value="<?=$u['is_active']?>"><button type="submit" class="px-3 py-1.5 <?=$u['is_active']?'bg-red-50 text-red-600 hover:bg-red-100':'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'?> rounded-lg text-xs font-medium transition-colors"><?=$u['is_active']?'<i class="fa-solid fa-ban mr-1"></i>Disable':'<i class="fa-solid fa-check mr-1"></i>Enable'?></button></form>
</td></tr>
<?php endforeach;?>
<?php if(!count($officers)):?><tr><td colspan="6" class="px-6 py-10 text-center text-gray-400"><i class="fa-solid fa-users text-2xl mb-2 block"></i>No HR Officers in the system yet.</td></tr><?php endif;?>
</tbody></table></div></div>

<?php elseif($tab==='permissions'):?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
<h2 class="text-lg font-semibold text-gray-800 mb-1"><i class="fa-solid fa-shield-halved mr-2 text-brand-600"></i>Officer Page Permissions</h2>
<p class="text-sm text-gray-500 mb-6">Control which pages each HR Officer can access. Applies to all officers system-wide.</p>
<?php if(!count($officers)):?><p class="text-gray-400 text-center py-8">No HR Officers in the system yet.</p>
<?php else:?>
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="text-xs text-gray-500 uppercase bg-gray-50/70"><tr><th class="px-4 py-3 text-left">Officer</th>
<?php foreach($pages as $pg=>$lbl):?><th class="px-2 py-3 text-center text-xs"><?=htmlspecialchars($lbl)?></th><?php endforeach;?>
<th class="px-4 py-3 text-center">Save</th></tr></thead>
<tbody class="divide-y divide-gray-100">
<?php foreach($officers as $u):
    $perms=[]; $ps=$pdo->prepare("SELECT page_name,is_allowed FROM page_permissions WHERE user_id=?"); $ps->execute([$u['id']]);
    foreach($ps->fetchAll(PDO::FETCH_ASSOC) as $p) $perms[$p['page_name']]=$p['is_allowed'];
?>
<tr class="hover:bg-gray-50/50">
<form method="POST">
<input type="hidden" name="action" value="save_permissions">
<input type="hidden" name="perm_user_id" value="<?=$u['id']?>">
<input type="hidden" name="tab" value="permissions">
<td class="px-4 py-3 font-medium text-gray-800"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
<?php foreach($pages as $pg=>$lbl): $checked=isset($perms[$pg])?$perms[$pg]:1;?>
<td class="px-2 py-3 text-center"><input type="checkbox" name="perm[<?=htmlspecialchars($pg)?>]" value="1" <?=$checked?'checked':''?> class="w-4 h-4 accent-blue-600 cursor-pointer rounded"></td>
<?php endforeach;?>
<td class="px-4 py-3 text-center"><button type="submit" class="px-3 py-1.5 bg-brand-900 text-white rounded-lg text-xs font-semibold hover:bg-brand-800 transition-colors">Save</button></td>
</form></tr>
<?php endforeach;?>
</tbody></table></div>
<?php endif;?>
</div>

<?php elseif($tab==='account'):?>
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
<?php endif;?>

</div></main>

<div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
<div class="flex justify-between items-center mb-5">
<h3 class="text-lg font-semibold text-gray-800">Edit HR Officer</h3>
<button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
</div>
<form method="POST" class="space-y-4">
<input type="hidden" name="action" value="update_officer">
<input type="hidden" name="user_id" id="e_id">
<div class="grid grid-cols-2 gap-4">
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">First Name *</label><input id="e_fn" name="first_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Last Name *</label><input id="e_ln" name="last_name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
</div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email *</label><input type="email" id="e_em" name="email" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" required></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Contact No</label><input id="e_cn" name="contact_no" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none"></div>
<div><label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">New Password</label><input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none" placeholder="••••••••"></div>
<div class="flex gap-3"><button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 border border-gray-200 text-gray-600 px-4 py-2.5 rounded-xl font-semibold hover:bg-gray-50 transition-colors">Cancel</button><button type="submit" class="flex-1 bg-brand-900 text-white px-4 py-2.5 rounded-xl font-semibold hover:bg-brand-800 transition-colors">Save Changes</button></div>
</form></div></div>
<script>
function openEdit(u){document.getElementById('e_id').value=u.id;document.getElementById('e_fn').value=u.first_name;document.getElementById('e_ln').value=u.last_name;document.getElementById('e_em').value=u.email;document.getElementById('e_cn').value=u.contact_no??'';document.getElementById('editModal').classList.remove('hidden');}
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)this.classList.add('hidden');});
</script>
</body></html>
