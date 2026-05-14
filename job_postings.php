<?php
require_once 'auth_check.php';
$required_page = 'job_postings.php';
require_once 'db_config.php';

$success_msg = '';
$error_msg   = '';

// ── POST: Create new job ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_job') {
        $title       = trim($_POST['title'] ?? '');
        $department  = trim($_POST['department'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $job_type    = $_POST['job_type'] ?? 'Full-time';
        $description = trim($_POST['description'] ?? '');
        $requirements= trim($_POST['requirements'] ?? '');
        $status      = $_POST['status'] ?? 'Draft';
        $posted_by   = $_SESSION['user_id'];

        if ($title && $department && $location) {
            $stmt = $pdo->prepare("INSERT INTO job_postings
                (title, department, location, job_type, description, requirements, status, posted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $department, $location, $job_type, $description, $requirements, $status, $posted_by]);
            $success_msg = "Job posting \"" . htmlspecialchars($title) . "\" created successfully.";
        } else {
            $error_msg = "Title, Department, and Location are required.";
        }
    }

    if ($_POST['action'] === 'toggle_status') {
        $job_id     = (int)($_POST['job_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'Closed';
        $stmt = $pdo->prepare("UPDATE job_postings SET status=? WHERE id=?");
        $stmt->execute([$new_status, $job_id]);
        header("Location: job_postings.php?msg=status_updated");
        exit;
    }

    if ($_POST['action'] === 'delete_job') {
        $job_id = (int)($_POST['job_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id=? AND status='Draft'");
        $stmt->execute([$job_id]);
        header("Location: job_postings.php?msg=deleted");
        exit;
    }
}

// ── GET: Fetch jobs with filters ──────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$filter_dept= trim($_GET['department'] ?? '');
$filter_stat= trim($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 10;
$offset     = ($page - 1) * $per_page;

$where = ["1=1"];
$params = [];
if ($search) {
    $where[] = "(title LIKE ? OR department LIKE ? OR location LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter_dept) { $where[] = "department = ?"; $params[] = $filter_dept; }
if ($filter_stat) { $where[] = "status = ?";     $params[] = $filter_stat; }

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE $where_sql");
$count_stmt->execute($params);
$total_jobs = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_jobs / $per_page));

$stmt = $pdo->prepare("
    SELECT jp.*, su.first_name, su.last_name,
           (SELECT COUNT(*) FROM applicants a WHERE a.job_id = jp.id) AS applicant_count
    FROM job_postings jp
    LEFT JOIN system_users su ON su.id = jp.posted_by
    WHERE $where_sql
    ORDER BY jp.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Departments list for filter/form
$depts = $pdo->query("SELECT DISTINCT department FROM job_postings ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// flash message from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'status_updated') $success_msg = "Job status updated.";
    if ($_GET['msg'] === 'deleted')        $success_msg = "Draft job deleted.";
}

$page_title = "Job Postings - Recruitment";
include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <!-- Alerts -->
        <?php if ($success_msg): ?>
        <div class="mb-6 flex items-center gap-3 px-5 py-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm font-medium">
            <i class="fa-solid fa-circle-check text-emerald-500"></i> <?= $success_msg ?>
        </div>
        <?php elseif ($error_msg): ?>
        <div class="mb-6 flex items-center gap-3 px-5 py-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm font-medium">
            <i class="fa-solid fa-circle-exclamation text-red-500"></i> <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <!-- Header & Actions -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Job Postings</h1>
                <p class="text-sm text-gray-500 mt-1">Create and manage your organisation's job openings.</p>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <button onclick="openModal('postJobModal')" id="btn-post-job"
                    class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center">
                    <i class="fa-solid fa-plus mr-2"></i> Post New Job
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex space-x-1 mb-8 bg-gray-200/50 p-1 rounded-xl w-max">
            <a href="recruitment.php"  class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Overview</a>
            <a href="job_postings.php" class="px-4 py-2 text-sm font-medium rounded-lg bg-white text-brand-700 shadow-sm transition-all">Job Postings</a>
            <a href="applicants.php"   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Applicants</a>
            <a href="interviews.php"   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Interviews</a>
        </div>

        <!-- Filters & Search -->
        <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-8 flex flex-col md:flex-row gap-4 items-center">
            <div class="flex flex-1 w-full relative">
                <i class="fa-solid fa-search absolute left-4 top-3 text-gray-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search jobs..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all text-sm">
            </div>
            <div class="flex gap-3 w-full md:w-auto flex-wrap">
                <select name="department" class="border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                    <option value="">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $filter_dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                    <?php foreach (['Human Resources (HR)','Finance & Accounts','Operations','Sales & Marketing','Information Technology (IT)'] as $od): ?>
                        <?php if (!in_array($od, $depts)): ?>
                            <option value="<?= $od ?>" <?= $filter_dept === $od ? 'selected' : '' ?>><?= $od ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                    <option value="">All Statuses</option>
                    <option value="Active"  <?= $filter_stat === 'Active'  ? 'selected' : '' ?>>Active</option>
                    <option value="Draft"   <?= $filter_stat === 'Draft'   ? 'selected' : '' ?>>Draft</option>
                    <option value="Closed"  <?= $filter_stat === 'Closed'  ? 'selected' : '' ?>>Closed</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-xl text-sm font-medium hover:bg-brand-700 transition-colors">Filter</button>
                <?php if ($search || $filter_dept || $filter_stat): ?>
                    <a href="job_postings.php" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition-colors">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Job Postings Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 font-medium">Job Title</th>
                            <th class="px-6 py-4 font-medium">Department</th>
                            <th class="px-6 py-4 font-medium">Location & Type</th>
                            <th class="px-6 py-4 font-medium">Applicants</th>
                            <th class="px-6 py-4 font-medium">Posted By</th>
                            <th class="px-6 py-4 font-medium">Posted Date</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($jobs)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center text-gray-400">
                                    <i class="fa-solid fa-briefcase text-5xl mb-4 opacity-30"></i>
                                    <p class="font-medium text-gray-500">No job postings found</p>
                                    <p class="text-xs mt-1">Click "Post New Job" to create your first listing.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: foreach ($jobs as $job): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($job['title']) ?></p>
                                <p class="text-xs text-gray-400">ID: JOB-<?= str_pad($job['id'], 4, '0', STR_PAD_LEFT) ?></p>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($job['department']) ?></td>
                            <td class="px-6 py-4 text-gray-600">
                                <p><?= htmlspecialchars($job['location']) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($job['job_type']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <?php $cnt = (int)$job['applicant_count']; ?>
                                <a href="applicants.php?job_id=<?= $job['id'] ?>"
                                   class="bg-brand-50 text-brand-700 px-2.5 py-1 rounded-full font-medium text-xs hover:bg-brand-100 transition-colors">
                                    <?= $cnt ?> Total
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-600 text-xs">
                                <?php if ($job['first_name']): ?>
                                    <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                                <?php else: ?><span class="text-gray-400">—</span><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <?php
                                $sBadge = match($job['status']) {
                                    'Active' => 'bg-emerald-100 text-emerald-800',
                                    'Draft'  => 'bg-amber-100 text-amber-800',
                                    'Closed' => 'bg-gray-100 text-gray-600',
                                    default  => 'bg-gray-100 text-gray-600'
                                };
                                $sDot = match($job['status']) {
                                    'Active' => 'bg-emerald-500',
                                    'Draft'  => 'bg-amber-500',
                                    'Closed' => 'bg-gray-400',
                                    default  => 'bg-gray-400'
                                };
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sBadge ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $sDot ?> mr-1.5"></span>
                                    <?= $job['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <!-- View applicants -->
                                    <a href="applicants.php?job_id=<?= $job['id'] ?>"
                                       class="p-1.5 text-gray-400 hover:text-brand-600 transition-colors" title="View Applicants">
                                        <i class="fa-solid fa-users"></i>
                                    </a>
                                    <!-- Toggle status -->
                                    <?php if ($job['status'] === 'Active'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action"     value="toggle_status">
                                        <input type="hidden" name="job_id"     value="<?= $job['id'] ?>">
                                        <input type="hidden" name="new_status" value="Closed">
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Close Job">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php elseif ($job['status'] === 'Closed'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action"     value="toggle_status">
                                        <input type="hidden" name="job_id"     value="<?= $job['id'] ?>">
                                        <input type="hidden" name="new_status" value="Active">
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-emerald-600 transition-colors" title="Reopen Job">
                                            <i class="fa-solid fa-rotate-right"></i>
                                        </button>
                                    </form>
                                    <?php elseif ($job['status'] === 'Draft'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action"     value="toggle_status">
                                        <input type="hidden" name="job_id"     value="<?= $job['id'] ?>">
                                        <input type="hidden" name="new_status" value="Active">
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-emerald-600 transition-colors" title="Publish Job">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this draft job?')">
                                        <input type="hidden" name="action" value="delete_job">
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Delete Draft">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <span class="text-sm text-gray-500">
                    Showing <?= $total_jobs === 0 ? 0 : $offset + 1 ?> to <?= min($offset + $per_page, $total_jobs) ?> of <?= $total_jobs ?> job<?= $total_jobs !== 1 ? 's' : '' ?>
                </span>
                <div class="flex space-x-2">
                    <?php $qs = http_build_query(array_filter(['search'=>$search,'department'=>$filter_dept,'status'=>$filter_stat])); ?>
                    <a href="?<?= $qs ?>&page=<?= max(1,$page-1) ?>"
                       class="px-3 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm <?= $page<=1?'pointer-events-none opacity-40':'' ?>">Previous</a>
                    <?php for ($p=1; $p<=$total_pages; $p++): ?>
                        <a href="?<?= $qs ?>&page=<?= $p ?>"
                           class="px-3 py-1 rounded-lg border text-sm <?= $p===$page ? 'border-brand-500 bg-brand-50 text-brand-600 font-medium' : 'border-gray-200 text-gray-500 hover:bg-gray-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <a href="?<?= $qs ?>&page=<?= min($total_pages,$page+1) ?>"
                       class="px-3 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm <?= $page>=$total_pages?'pointer-events-none opacity-40':'' ?>">Next</a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ── POST NEW JOB MODAL ─────────────────────────────────────────────────── -->
<div id="postJobModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('postJobModal')"></div>
    <!-- Panel -->
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="flex items-center justify-between px-7 py-5 border-b border-gray-100 bg-gradient-to-r from-brand-600 to-brand-900">
            <div class="flex items-center gap-3 text-white">
                <i class="fa-solid fa-briefcase text-xl"></i>
                <h2 class="text-lg font-semibold">Post New Job</h2>
            </div>
            <button onclick="closeModal('postJobModal')" class="text-white/70 hover:text-white transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <!-- Form -->
        <form method="POST" class="overflow-y-auto flex-1 p-7 space-y-5 custom-scrollbar">
            <input type="hidden" name="action" value="create_job">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Senior Frontend Developer"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Department <span class="text-red-500">*</span></label>
                    <select name="department" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <option value="">Select Department</option>
                        <?php foreach (['Human Resources (HR)','Finance & Accounts','Operations','Sales & Marketing','Information Technology (IT)'] as $d): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Type</label>
                    <select name="job_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <?php foreach (['Full-time','Part-time','Contract','Remote','Internship'] as $jt): ?>
                            <option><?= $jt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Location <span class="text-red-500">*</span></label>
                    <input type="text" name="location" required placeholder="e.g. Remote, Colombo, LK"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Initial Status</label>
                    <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <option value="Active">Active (publish now)</option>
                        <option value="Draft" selected>Draft (save for later)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Description</label>
                    <textarea name="description" rows="4" placeholder="Describe the role, responsibilities, and company culture..."
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm resize-none"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Requirements</label>
                    <textarea name="requirements" rows="3" placeholder="List qualifications, skills, experience required..."
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm resize-none"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeModal('postJobModal')"
                    class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Save Job Posting
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 3px; }
</style>
<script>
function openModal(id) {
    const m = document.getElementById(id);
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const m = document.getElementById(id);
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}
// Auto-open modal if there was a validation error
<?php if ($error_msg): ?>
openModal('postJobModal');
<?php endif; ?>
</script>
</body>
</html>
