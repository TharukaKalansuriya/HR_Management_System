<?php
require_once 'auth_check.php';
$required_page = 'applicants.php';
require_once 'db_config.php';

$success_msg = '';
$error_msg   = '';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_applicant') {
        $job_id      = (int)($_POST['job_id']    ?? 0);
        $full_name   = trim($_POST['full_name']   ?? '');
        $email       = trim($_POST['email']       ?? '');
        $phone       = trim($_POST['phone']       ?? '');
        $stage       = $_POST['stage']            ?? 'New';
        $notes       = trim($_POST['notes']       ?? '');
        $applied_date= $_POST['applied_date']     ?? date('Y-m-d');

        if ($job_id && $full_name && $email) {
            // Check duplicate email per job
            $dup = $pdo->prepare("SELECT id FROM applicants WHERE job_id=? AND email=?");
            $dup->execute([$job_id, $email]);
            if ($dup->fetch()) {
                $error_msg = "An applicant with email '$email' already exists for this job.";
            } else {
                $pdo->prepare("INSERT INTO applicants (job_id,full_name,email,phone,stage,notes,applied_date)
                               VALUES (?,?,?,?,?,?,?)")
                    ->execute([$job_id,$full_name,$email,$phone,$stage,$notes,$applied_date]);
                $success_msg = "Applicant '$full_name' added successfully.";
            }
        } else {
            $error_msg = "Job, Full Name, and Email are required.";
        }
    }

    if ($_POST['action'] === 'update_stage') {
        $id    = (int)($_POST['applicant_id'] ?? 0);
        $stage = $_POST['new_stage'] ?? 'Screening';
        $pdo->prepare("UPDATE applicants SET stage=? WHERE id=?")->execute([$stage, $id]);
        header("Location: applicants.php?msg=stage_updated"); exit;
    }

    if ($_POST['action'] === 'delete_applicant') {
        $id = (int)($_POST['applicant_id'] ?? 0);
        $pdo->prepare("DELETE FROM applicants WHERE id=?")->execute([$id]);
        header("Location: applicants.php?msg=deleted"); exit;
    }
}

// ── GET: fetch applicants ─────────────────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$filter_job  = (int)($_GET['job_id']  ?? 0);
$filter_stage= trim($_GET['stage']    ?? '');
$page        = max(1,(int)($_GET['page'] ?? 1));
$per_page    = 12;
$offset      = ($page-1)*$per_page;

$where  = ["1=1"];
$params = [];
if ($search) {
    $where[] = "(a.full_name LIKE ? OR a.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter_job)  { $where[] = "a.job_id = ?"; $params[] = $filter_job;   }
if ($filter_stage){ $where[] = "a.stage  = ?"; $params[] = $filter_stage; }

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM applicants a WHERE $where_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total/$per_page));

$stmt = $pdo->prepare("
    SELECT a.*, jp.title AS job_title, jp.department
    FROM applicants a
    JOIN job_postings jp ON jp.id = a.job_id
    WHERE $where_sql
    ORDER BY a.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active jobs for dropdown
$jobs = $pdo->query("SELECT id,title,department FROM job_postings WHERE status != 'Closed' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['msg'])) {
    $msgs = ['stage_updated'=>'Applicant stage updated.','deleted'=>'Applicant removed.'];
    $success_msg = $msgs[$_GET['msg']] ?? '';
}

$stages = ['New','Screening','Interviewing','Offered','Hired','Rejected'];
$stage_colors = [
    'New'         => 'bg-gray-100 text-gray-700',
    'Screening'   => 'bg-blue-100 text-blue-700',
    'Interviewing'=> 'bg-purple-100 text-purple-700',
    'Offered'     => 'bg-amber-100 text-amber-700',
    'Hired'       => 'bg-emerald-100 text-emerald-700',
    'Rejected'    => 'bg-red-100 text-red-700',
];

$page_title = "Applicants - Recruitment";
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <?php if ($success_msg): ?>
        <div class="mb-5 flex items-center gap-3 px-5 py-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm font-medium">
            <i class="fa-solid fa-circle-check text-emerald-500"></i> <?= $success_msg ?>
        </div>
        <?php elseif ($error_msg): ?>
        <div class="mb-5 flex items-center gap-3 px-5 py-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm font-medium">
            <i class="fa-solid fa-circle-exclamation text-red-500"></i> <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Applicants</h1>
                <p class="text-sm text-gray-500 mt-1">Review and manage candidates for open positions.</p>
            </div>
            <button onclick="openModal('addApplicantModal')" id="btn-add-applicant"
                class="mt-4 md:mt-0 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm flex items-center gap-2 transition-colors">
                <i class="fa-solid fa-user-plus"></i> Add Applicant
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-1 mb-8 bg-gray-200/50 p-1 rounded-xl w-max">
            <a href="recruitment.php"  class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Overview</a>
            <a href="job_postings.php" class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Job Postings</a>
            <a href="applicants.php"   class="px-4 py-2 text-sm font-medium rounded-lg bg-white text-brand-700 shadow-sm transition-all">Applicants</a>
            <a href="interviews.php"   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Interviews</a>
        </div>

        <!-- Stage Summary Pills -->
        <div class="flex flex-wrap gap-2 mb-6">
            <?php
            $stage_counts = $pdo->query("SELECT stage, COUNT(*) as cnt FROM applicants GROUP BY stage")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($stages as $s):
                $cnt = $stage_counts[$s] ?? 0;
                $active = $filter_stage === $s;
                $qs = http_build_query(array_filter(['search'=>$search,'job_id'=>$filter_job,'stage'=>$s]));
            ?>
            <a href="applicants.php?<?= $qs ?>"
               class="px-3 py-1.5 rounded-full text-xs font-semibold flex items-center gap-1.5 transition-all
                      <?= $active ? $stage_colors[$s] . ' ring-2 ring-offset-1 ring-current' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' ?>">
                <?= $s ?> <span class="<?= $active?'':'bg-gray-100 text-gray-500' ?> px-1.5 py-0.5 rounded-full"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
            <?php if ($filter_stage): ?>
            <a href="applicants.php" class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors">✕ Clear filter</a>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-8 flex flex-col md:flex-row gap-4 items-center">
            <div class="flex flex-1 w-full relative">
                <i class="fa-solid fa-search absolute left-4 top-3 text-gray-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search by name or email..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
            </div>
            <input type="hidden" name="stage" value="<?= htmlspecialchars($filter_stage) ?>">
            <div class="flex gap-3 flex-wrap w-full md:w-auto">
                <select name="job_id" class="border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                    <option value="">All Jobs</option>
                    <?php foreach ($jobs as $j): ?>
                        <option value="<?= $j['id'] ?>" <?= $filter_job===$j['id']?'selected':'' ?>>
                            <?= htmlspecialchars($j['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-xl text-sm font-medium hover:bg-brand-700">Search</button>
            </div>
        </form>

        <!-- Applicants Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 font-medium">Candidate</th>
                            <th class="px-6 py-4 font-medium">Applied For</th>
                            <th class="px-6 py-4 font-medium">Applied Date</th>
                            <th class="px-6 py-4 font-medium">Stage</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($applicants)): ?>
                        <tr><td colspan="5" class="px-6 py-14 text-center">
                            <i class="fa-solid fa-users text-5xl text-gray-200 mb-4"></i>
                            <p class="font-medium text-gray-500">No applicants found</p>
                            <p class="text-xs text-gray-400 mt-1">Add your first applicant using the button above.</p>
                        </td></tr>
                        <?php else: foreach ($applicants as $ap): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors <?= $ap['stage']==='Rejected'?'opacity-60':'' ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img class="w-10 h-10 rounded-full border border-gray-200"
                                         src="https://ui-avatars.com/api/?name=<?= urlencode($ap['full_name']) ?>&background=dbeafe&color=1e3a8a"
                                         alt="">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($ap['full_name']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($ap['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <p class="font-medium"><?= htmlspecialchars($ap['job_title']) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($ap['department']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">
                                <?= $ap['applied_date'] ? date('M d, Y', strtotime($ap['applied_date'])) : '—' ?>
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" class="inline-flex items-center gap-2">
                                    <input type="hidden" name="action"       value="update_stage">
                                    <input type="hidden" name="applicant_id" value="<?= $ap['id'] ?>">
                                    <select name="new_stage" onchange="this.form.submit()"
                                        class="text-xs border-0 rounded-full px-3 py-1.5 font-semibold cursor-pointer outline-none focus:ring-2 focus:ring-brand-500
                                               <?= $stage_colors[$ap['stage']] ?? 'bg-gray-100 text-gray-700' ?>">
                                        <?php foreach ($stages as $s): ?>
                                            <option value="<?= $s ?>" <?= $ap['stage']===$s?'selected':'' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if (!in_array($ap['stage'],['Hired','Rejected'])): ?>
                                    <a href="interviews.php?applicant_id=<?= $ap['id'] ?>"
                                       class="p-1.5 text-gray-400 hover:text-indigo-600 transition-colors" title="Schedule Interview">
                                        <i class="fa-regular fa-calendar-plus"></i>
                                    </a>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Remove this applicant?')">
                                        <input type="hidden" name="action"       value="delete_applicant">
                                        <input type="hidden" name="applicant_id" value="<?= $ap['id'] ?>">
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Remove">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <span class="text-sm text-gray-500">Showing <?= $total===0?0:$offset+1 ?> to <?= min($offset+$per_page,$total) ?> of <?= $total ?> applicant<?= $total!==1?'s':'' ?></span>
                <div class="flex space-x-2">
                    <?php $qs = http_build_query(array_filter(['search'=>$search,'job_id'=>$filter_job,'stage'=>$filter_stage])); ?>
                    <a href="?<?= $qs ?>&page=<?= max(1,$page-1) ?>" class="px-3 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm <?= $page<=1?'pointer-events-none opacity-40':'' ?>">Previous</a>
                    <?php for($p=1;$p<=$total_pages;$p++): ?>
                        <a href="?<?= $qs ?>&page=<?= $p ?>" class="px-3 py-1 rounded-lg border text-sm <?= $p===$page?'border-brand-500 bg-brand-50 text-brand-600 font-medium':'border-gray-200 text-gray-500 hover:bg-gray-50' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="?<?= $qs ?>&page=<?= min($total_pages,$page+1) ?>" class="px-3 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm <?= $page>=$total_pages?'pointer-events-none opacity-40':'' ?>">Next</a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ── ADD APPLICANT MODAL ─────────────────────────────────────────────────── -->
<div id="addApplicantModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('addApplicantModal')"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-7 py-5 border-b border-gray-100 bg-gradient-to-r from-brand-600 to-brand-900">
            <div class="flex items-center gap-3 text-white">
                <i class="fa-solid fa-user-plus text-xl"></i>
                <h2 class="text-lg font-semibold">Add Applicant</h2>
            </div>
            <button onclick="closeModal('addApplicantModal')" class="text-white/70 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST" class="overflow-y-auto flex-1 p-7 space-y-4 custom-scrollbar">
            <input type="hidden" name="action" value="add_applicant">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Position <span class="text-red-500">*</span></label>
                <select name="job_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                    <option value="">Select Job</option>
                    <?php foreach ($jobs as $j): ?>
                        <option value="<?= $j['id'] ?>" <?= $filter_job===$j['id']?'selected':'' ?>>
                            <?= htmlspecialchars($j['title']) ?> (<?= htmlspecialchars($j['department']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" required placeholder="Applicant full name"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required placeholder="email@example.com"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone</label>
                    <input type="text" name="phone" placeholder="+94 71 234 5678"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Initial Stage</label>
                    <select name="stage" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <?php foreach ($stages as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Applied Date</label>
                    <input type="date" name="applied_date" value="<?= date('Y-m-d') ?>"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="3" placeholder="Any notes about this applicant..."
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeModal('addApplicantModal')"
                    class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium">Cancel</button>
                <button type="submit"
                    class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-medium shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-user-plus"></i> Add Applicant
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar{width:6px}
.custom-scrollbar::-webkit-scrollbar-track{background:transparent}
.custom-scrollbar::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:3px}
</style>
<script>
function openModal(id){const m=document.getElementById(id);m.classList.remove('hidden');m.classList.add('flex');document.body.style.overflow='hidden';}
function closeModal(id){const m=document.getElementById(id);m.classList.add('hidden');m.classList.remove('flex');document.body.style.overflow='';}
<?php if ($error_msg): ?>openModal('addApplicantModal');<?php endif; ?>
</script>
</body>
</html>
