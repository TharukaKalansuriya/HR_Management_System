<?php
require_once 'auth_check.php';
$required_page = 'interviews.php';
require_once 'db_config.php';

$success_msg = '';
$error_msg   = '';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'schedule_interview') {
        $applicant_id     = (int)($_POST['applicant_id'] ?? 0);
        $job_id           = (int)($_POST['job_id'] ?? 0);
        $interview_type   = $_POST['interview_type']   ?? 'Initial Screening';
        $interview_mode   = $_POST['interview_mode']   ?? 'Video';
        $scheduled_date   = $_POST['scheduled_date']   ?? '';
        $start_time       = $_POST['start_time']       ?? '';
        $end_time         = $_POST['end_time']         ?? '';
        $interviewer_name = trim($_POST['interviewer_name'] ?? '');
        $location_or_link = trim($_POST['location_or_link'] ?? '');
        $notes            = trim($_POST['notes']       ?? '');
        $created_by       = $_SESSION['user_id'];

        if ($applicant_id && $job_id && $scheduled_date && $start_time && $end_time) {
            $stmt = $pdo->prepare("INSERT INTO interviews
                (applicant_id, job_id, interview_type, interview_mode, scheduled_date,
                 start_time, end_time, interviewer_name, location_or_link, notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$applicant_id,$job_id,$interview_type,$interview_mode,
                            $scheduled_date,$start_time,$end_time,
                            $interviewer_name,$location_or_link,$notes,$created_by]);

            // Update applicant stage to Interviewing
            $pdo->prepare("UPDATE applicants SET stage='Interviewing' WHERE id=? AND stage NOT IN ('Offered','Hired','Rejected')")
                ->execute([$applicant_id]);

            $success_msg = "Interview scheduled successfully.";
        } else {
            $error_msg = "Applicant, Job, Date, Start Time and End Time are required.";
        }
    }

    if ($_POST['action'] === 'update_status') {
        $id  = (int)($_POST['interview_id'] ?? 0);
        $st  = $_POST['new_status'] ?? 'Completed';
        $pdo->prepare("UPDATE interviews SET status=? WHERE id=?")->execute([$st,$id]);
        header("Location: interviews.php?msg=updated"); exit;
    }
}

// ── GET: fetch interviews grouped by date ────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$filter_date = trim($_GET['filter_date'] ?? '');
$filter_stat = trim($_GET['status'] ?? '');

$where  = ["1=1"];
$params = [];
if ($search) {
    $where[] = "(a.full_name LIKE ? OR i.interviewer_name LIKE ? OR jp.title LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter_date) { $where[] = "i.scheduled_date = ?"; $params[] = $filter_date; }
if ($filter_stat) { $where[] = "i.status = ?";         $params[] = $filter_stat; }

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT i.*, a.full_name AS candidate_name, jp.title AS job_title
    FROM interviews i
    JOIN applicants a  ON a.id  = i.applicant_id
    JOIN job_postings jp ON jp.id = i.job_id
    WHERE $where_sql
    ORDER BY i.scheduled_date ASC, i.start_time ASC
");
$stmt->execute($params);
$all_interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by date
$grouped = [];
foreach ($all_interviews as $iv) {
    $grouped[$iv['scheduled_date']][] = $iv;
}

// For modal dropdowns
$active_applicants = $pdo->query("
    SELECT a.id, a.full_name, jp.id AS job_id, jp.title AS job_title
    FROM applicants a
    JOIN job_postings jp ON jp.id = a.job_id
    WHERE a.stage NOT IN ('Hired','Rejected')
    ORDER BY a.full_name
")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') $success_msg = "Interview status updated.";

$page_title = "Interview Schedule";
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
                <h1 class="text-2xl font-bold text-gray-800">Interview Schedule</h1>
                <p class="text-sm text-gray-500 mt-1">Manage upcoming interviews and track their progress.</p>
            </div>
            <button onclick="openModal('scheduleModal')" id="btn-schedule-interview"
                class="mt-4 md:mt-0 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-calendar-plus"></i> Schedule Interview
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-1 mb-8 bg-gray-200/50 p-1 rounded-xl w-max">
            <a href="recruitment.php"  class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Overview</a>
            <a href="job_postings.php" class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Job Postings</a>
            <a href="applicants.php"   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Applicants</a>
            <a href="interviews.php"   class="px-4 py-2 text-sm font-medium rounded-lg bg-white text-brand-700 shadow-sm transition-all">Interviews</a>
        </div>

        <!-- Filters -->
        <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-8 flex flex-col md:flex-row gap-4 items-center">
            <div class="flex flex-1 w-full relative">
                <i class="fa-solid fa-search absolute left-4 top-3 text-gray-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search by candidate, interviewer or job..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
            </div>
            <div class="flex gap-3 flex-wrap w-full md:w-auto">
                <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>"
                    class="border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                <select name="status" class="border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                    <option value="">All Statuses</option>
                    <?php foreach (['Scheduled','Completed','Cancelled','Rescheduled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_stat===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-xl text-sm font-medium hover:bg-brand-700">Filter</button>
                <?php if ($search||$filter_date||$filter_stat): ?>
                    <a href="interviews.php" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Interviews List -->
        <?php if (empty($grouped)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
            <i class="fa-solid fa-calendar-xmark text-5xl text-gray-200 mb-4"></i>
            <p class="font-medium text-gray-500">No interviews found</p>
            <p class="text-xs text-gray-400 mt-1">Schedule your first interview using the button above.</p>
        </div>
        <?php else: foreach ($grouped as $date => $ivs): ?>

        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3 mt-6 first:mt-0">
            <?php
            $ts = strtotime($date);
            $today    = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            if ($date === $today)         echo "Today, " . date('M d', $ts);
            elseif ($date === $tomorrow)  echo "Tomorrow, " . date('M d', $ts);
            else                          echo date('l, M d, Y', $ts);
            ?>
        </h3>

        <div class="space-y-3 mb-2">
        <?php foreach ($ivs as $iv):
            $modeIcon  = match($iv['interview_mode']) { 'Video'=>'fa-video','Phone'=>'fa-phone', default=>'fa-building' };
            $modeColor = match($iv['interview_mode']) { 'Video'=>'bg-brand-50 text-brand-600','Phone'=>'bg-emerald-50 text-emerald-600', default=>'bg-indigo-50 text-indigo-600' };
            $statusBadge = match($iv['status']) {
                'Scheduled'   => 'bg-blue-100 text-blue-700',
                'Completed'   => 'bg-emerald-100 text-emerald-700',
                'Cancelled'   => 'bg-red-100 text-red-700',
                'Rescheduled' => 'bg-amber-100 text-amber-700',
                default       => 'bg-gray-100 text-gray-600'
            };
        ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-brand-200 transition-colors">
            <!-- Type & Job -->
            <div class="flex items-center gap-4 md:w-1/4">
                <div class="w-12 h-12 rounded-xl <?= $modeColor ?> flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid <?= $modeIcon ?> text-xl"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($iv['interview_type']) ?></h4>
                    <p class="text-xs text-brand-600 font-medium mt-0.5"><?= htmlspecialchars($iv['job_title']) ?></p>
                </div>
            </div>
            <!-- Candidate & Interviewer -->
            <div class="flex items-center gap-6 md:w-2/5">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Candidate</p>
                    <div class="flex items-center gap-2">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($iv['candidate_name']) ?>&background=dbeafe&color=1e3a8a&size=32" class="w-7 h-7 rounded-full">
                        <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($iv['candidate_name']) ?></span>
                    </div>
                </div>
                <?php if ($iv['interviewer_name']): ?>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Interviewer</p>
                    <div class="flex items-center gap-2">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($iv['interviewer_name']) ?>&background=ede9fe&color=5b21b6&size=32" class="w-7 h-7 rounded-full">
                        <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($iv['interviewer_name']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Time & Actions -->
            <div class="flex flex-col md:items-end gap-2 md:w-1/3">
                <div class="flex items-center gap-2 text-gray-600">
                    <i class="fa-regular fa-clock text-brand-500 text-sm"></i>
                    <span class="text-sm font-medium">
                        <?= date('g:i A', strtotime($iv['start_time'])) ?> – <?= date('g:i A', strtotime($iv['end_time'])) ?>
                    </span>
                </div>
                <?php if ($iv['location_or_link']): ?>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($iv['location_or_link']) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusBadge ?>">
                        <?= $iv['status'] ?>
                    </span>
                    <?php if ($iv['status'] === 'Scheduled'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action"       value="update_status">
                        <input type="hidden" name="interview_id" value="<?= $iv['id'] ?>">
                        <input type="hidden" name="new_status"   value="Completed">
                        <button type="submit" class="px-3 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-medium transition-colors">
                            ✓ Complete
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action"       value="update_status">
                        <input type="hidden" name="interview_id" value="<?= $iv['id'] ?>">
                        <input type="hidden" name="new_status"   value="Cancelled">
                        <button type="submit" class="px-3 py-1 border border-gray-200 text-gray-600 hover:bg-gray-50 rounded-lg text-xs font-medium transition-colors">
                            Cancel
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php endforeach; endif; ?>
    </div>
</main>

<!-- ── SCHEDULE INTERVIEW MODAL ───────────────────────────────────────────── -->
<div id="scheduleModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('scheduleModal')"></div>
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-7 py-5 border-b border-gray-100 bg-gradient-to-r from-indigo-600 to-brand-900">
            <div class="flex items-center gap-3 text-white">
                <i class="fa-solid fa-calendar-plus text-xl"></i>
                <h2 class="text-lg font-semibold">Schedule Interview</h2>
            </div>
            <button onclick="closeModal('scheduleModal')" class="text-white/70 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST" class="overflow-y-auto flex-1 p-7 space-y-5 custom-scrollbar">
            <input type="hidden" name="action" value="schedule_interview">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Applicant <span class="text-red-500">*</span></label>
                    <select name="applicant_id" id="modal-applicant" required onchange="fillJobFromApplicant(this)"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <option value="">Select Applicant</option>
                        <?php foreach ($active_applicants as $ap): ?>
                            <option value="<?= $ap['id'] ?>" data-job="<?= $ap['job_id'] ?>">
                                <?= htmlspecialchars($ap['full_name']) ?> — <?= htmlspecialchars($ap['job_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="job_id" id="modal-job-id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Interview Type</label>
                    <select name="interview_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <?php foreach (['Initial Screening','Technical','Cultural Fit','HR Round','Final Round'] as $t): ?>
                            <option><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mode</label>
                    <select name="interview_mode" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm bg-white">
                        <option>Video</option><option>In-Person</option><option>Phone</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Start Time <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">End Time <span class="text-red-500">*</span></label>
                        <input type="time" name="end_time" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Interviewer Name</label>
                    <input type="text" name="interviewer_name" placeholder="e.g. Sarah Smith"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Location / Meeting Link</label>
                    <input type="text" name="location_or_link" placeholder="Zoom link or Room number"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Any special instructions or topics to cover..."
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-sm resize-none"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeModal('scheduleModal')"
                    class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium">Cancel</button>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check"></i> Confirm Schedule
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
function fillJobFromApplicant(sel){
    const opt=sel.options[sel.selectedIndex];
    document.getElementById('modal-job-id').value=opt.dataset.job||'';
}
<?php if ($error_msg): ?>openModal('scheduleModal');<?php endif; ?>
// Pre-fill applicant if passed via URL
const urlAp = new URLSearchParams(window.location.search).get('applicant_id');
if (urlAp) {
    const sel = document.getElementById('modal-applicant');
    if(sel){ sel.value=urlAp; fillJobFromApplicant(sel); openModal('scheduleModal'); }
}
</script>
</body>
</html>
