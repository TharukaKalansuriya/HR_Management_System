<?php
require_once 'auth_check.php';
$required_page = 'recruitment.php';
require_once 'db_config.php';

// ── Live stats ────────────────────────────────────────────────────────────────
$active_jobs   = (int)$pdo->query("SELECT COUNT(*) FROM job_postings WHERE status='Active'")->fetchColumn();
$total_apps    = (int)$pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$today         = date('Y-m-d');
$interviews_today = (int)$pdo->query("SELECT COUNT(*) FROM interviews WHERE scheduled_date='$today' AND status='Scheduled'")->fetchColumn();
$hired_month   = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE stage='Hired' AND MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW())")->fetchColumn();

// ── Recent job postings (5) ───────────────────────────────────────────────────
$recent_jobs = $pdo->query("
    SELECT jp.*, (SELECT COUNT(*) FROM applicants a WHERE a.job_id=jp.id) AS applicant_count
    FROM job_postings jp
    ORDER BY jp.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Recent applicants (5) ─────────────────────────────────────────────────────
$recent_apps = $pdo->query("
    SELECT a.*, jp.title AS job_title
    FROM applicants a
    JOIN job_postings jp ON jp.id = a.job_id
    ORDER BY a.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Upcoming interviews (next 7 days) ─────────────────────────────────────────
$upcoming = $pdo->query("
    SELECT i.*, a.full_name AS candidate_name, jp.title AS job_title
    FROM interviews i
    JOIN applicants a   ON a.id  = i.applicant_id
    JOIN job_postings jp ON jp.id = i.job_id
    WHERE i.scheduled_date >= '$today' AND i.status='Scheduled'
    ORDER BY i.scheduled_date ASC, i.start_time ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ── Pipeline breakdown ────────────────────────────────────────────────────────
$pipeline = $pdo->query("SELECT stage, COUNT(*) AS cnt FROM applicants GROUP BY stage")->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = "Recruitment Dashboard";
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Recruitment Overview</h1>
                <p class="text-sm text-gray-500 mt-1">Manage job postings, applicants, and interviews.</p>
            </div>
            <div class="mt-4 md:mt-0 flex gap-3">
                <a href="job_postings.php" id="btn-post-job"
                   class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Post New Job
                </a>
                <a href="interviews.php" id="btn-schedule-interview"
                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-calendar-plus"></i> Schedule Interview
                </a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-1 mb-8 bg-gray-200/50 p-1 rounded-xl w-max">
            <a href="recruitment.php"  class="px-4 py-2 text-sm font-medium rounded-lg bg-white text-brand-700 shadow-sm transition-all">Overview</a>
            <a href="job_postings.php" class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Job Postings</a>
            <a href="applicants.php"   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Applicants</a>
            <a href="interviews.php"   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:text-brand-600 hover:bg-white/60 transition-all">Interviews</a>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php
            $stats = [
                ['label'=>'Active Jobs',       'value'=>$active_jobs,       'icon'=>'fa-briefcase',    'bg'=>'bg-blue-50',    'color'=>'text-blue-600'],
                ['label'=>'Total Applicants',  'value'=>$total_apps,        'icon'=>'fa-users',        'bg'=>'bg-indigo-50',  'color'=>'text-indigo-600'],
                ['label'=>'Interviews Today',  'value'=>$interviews_today,  'icon'=>'fa-calendar-check','bg'=>'bg-amber-50',  'color'=>'text-amber-600'],
                ['label'=>'Hired This Month',  'value'=>$hired_month,       'icon'=>'fa-user-check',   'bg'=>'bg-emerald-50', 'color'=>'text-emerald-600'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center gap-4">
                <div class="p-4 rounded-xl <?= $s['bg'] ?> <?= $s['color'] ?> flex-shrink-0">
                    <i class="fa-solid <?= $s['icon'] ?> text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500"><?= $s['label'] ?></p>
                    <p class="text-2xl font-bold text-gray-800"><?= $s['value'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Upcoming Interviews Banner (if any today) -->
        <?php if ($interviews_today > 0): ?>
        <div class="mb-8 px-5 py-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center gap-3">
            <div class="p-2.5 bg-amber-100 rounded-xl text-amber-600">
                <i class="fa-solid fa-bell text-lg"></i>
            </div>
            <div>
                <p class="font-semibold text-amber-800">
                    <?= $interviews_today ?> interview<?= $interviews_today>1?'s':'' ?> scheduled for today
                </p>
                <p class="text-xs text-amber-600 mt-0.5">Check the Interviews tab for details and to mark them complete.</p>
            </div>
            <a href="interviews.php?filter_date=<?= $today ?>" class="ml-auto px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition-colors flex-shrink-0">
                View Today's Schedule
            </a>
        </div>
        <?php endif; ?>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- Upcoming Interviews (2/3 width) -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days text-brand-600"></i>
                        <h2 class="text-base font-semibold text-gray-800">Upcoming Interviews</h2>
                    </div>
                    <a href="interviews.php" class="text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors">View All →</a>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($upcoming)): ?>
                    <div class="px-6 py-12 text-center text-gray-400">
                        <i class="fa-regular fa-calendar text-4xl mb-3 block opacity-40"></i>
                        <p class="text-sm">No upcoming interviews scheduled</p>
                        <a href="interviews.php" class="inline-block mt-3 text-xs text-brand-600 hover:underline">Schedule one now →</a>
                    </div>
                    <?php else: foreach ($upcoming as $iv):
                        $modeIcon  = match($iv['interview_mode']) { 'Video'=>'fa-video','Phone'=>'fa-phone', default=>'fa-building' };
                        $modeColor = match($iv['interview_mode']) { 'Video'=>'text-brand-500','Phone'=>'text-emerald-500', default=>'text-indigo-500' };
                        $isToday   = ($iv['scheduled_date'] === $today);
                    ?>
                    <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/60 transition-colors">
                        <!-- Date badge -->
                        <div class="flex-shrink-0 w-12 text-center <?= $isToday?'bg-brand-600 text-white':'bg-gray-100 text-gray-600' ?> rounded-xl p-2">
                            <p class="text-xs font-medium"><?= date('M', strtotime($iv['scheduled_date'])) ?></p>
                            <p class="text-lg font-bold leading-none"><?= date('d', strtotime($iv['scheduled_date'])) ?></p>
                        </div>
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid <?= $modeIcon ?> text-xs <?= $modeColor ?>"></i>
                                <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($iv['interview_type']) ?></p>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="font-medium text-gray-700"><?= htmlspecialchars($iv['candidate_name']) ?></span>
                                &nbsp;·&nbsp; <?= htmlspecialchars($iv['job_title']) ?>
                            </p>
                        </div>
                        <!-- Time -->
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-medium text-gray-700"><?= date('g:i A', strtotime($iv['start_time'])) ?></p>
                            <p class="text-xs text-gray-400">– <?= date('g:i A', strtotime($iv['end_time'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Pipeline Breakdown (1/3 width) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-filter text-brand-600"></i>
                        <h2 class="text-base font-semibold text-gray-800">Pipeline</h2>
                    </div>
                    <a href="applicants.php" class="text-xs text-brand-600 hover:text-brand-800 font-medium">View All →</a>
                </div>
                <div class="p-6 space-y-4">
                    <?php
                    $pipeline_defs = [
                        'New'         => ['bg'=>'bg-gray-400',    'label'=>'New Applications'],
                        'Screening'   => ['bg'=>'bg-blue-500',    'label'=>'Screening'],
                        'Interviewing'=> ['bg'=>'bg-purple-500',  'label'=>'Interviewing'],
                        'Offered'     => ['bg'=>'bg-amber-500',   'label'=>'Offered'],
                        'Hired'       => ['bg'=>'bg-emerald-500', 'label'=>'Hired'],
                        'Rejected'    => ['bg'=>'bg-red-400',     'label'=>'Rejected'],
                    ];
                    $max = max(array_values($pipeline) ?: [1]);
                    foreach ($pipeline_defs as $stage => $def):
                        $cnt = $pipeline[$stage] ?? 0;
                        $pct = $max > 0 ? round(($cnt/$max)*100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-xs font-medium text-gray-600"><?= $def['label'] ?></span>
                            <span class="text-xs font-bold text-gray-800"><?= $cnt ?></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="<?= $def['bg'] ?> h-2 rounded-full transition-all duration-500" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Bottom Grid: Recent Jobs + Recent Applicants -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Recent Jobs -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-base font-semibold text-gray-800">Recent Job Postings</h2>
                    <a href="job_postings.php" class="text-xs text-brand-600 hover:text-brand-800 font-medium">View All →</a>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($recent_jobs)): ?>
                    <div class="px-6 py-10 text-center text-gray-400 text-sm">No jobs posted yet.</div>
                    <?php else: foreach ($recent_jobs as $job):
                        $sBadge = match($job['status']) {
                            'Active'=>'bg-emerald-100 text-emerald-700',
                            'Draft' =>'bg-amber-100 text-amber-700',
                            default =>'bg-gray-100 text-gray-500'
                        };
                    ?>
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/60 transition-colors">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800 text-sm truncate"><?= htmlspecialchars($job['title']) ?></p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                <?= htmlspecialchars($job['department']) ?> &nbsp;·&nbsp;
                                <?= htmlspecialchars($job['job_type']) ?>
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-4">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= $sBadge ?>"><?= $job['status'] ?></span>
                            <p class="text-xs text-gray-400 mt-1"><?= $job['applicant_count'] ?> applicant<?= $job['applicant_count']!=1?'s':'' ?></p>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Recent Applicants -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-base font-semibold text-gray-800">Recent Applicants</h2>
                    <a href="applicants.php" class="text-xs text-brand-600 hover:text-brand-800 font-medium">View All →</a>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php
                    $app_colors = [
                        'New'         =>'bg-gray-100 text-gray-700',
                        'Screening'   =>'bg-blue-100 text-blue-700',
                        'Interviewing'=>'bg-purple-100 text-purple-700',
                        'Offered'     =>'bg-amber-100 text-amber-700',
                        'Hired'       =>'bg-emerald-100 text-emerald-700',
                        'Rejected'    =>'bg-red-100 text-red-700',
                    ];
                    if (empty($recent_apps)):
                    ?>
                    <div class="px-6 py-10 text-center text-gray-400 text-sm">No applicants yet.</div>
                    <?php else: foreach ($recent_apps as $ap): ?>
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/60 transition-colors">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <img class="w-9 h-9 rounded-full border border-gray-100 flex-shrink-0"
                                 src="https://ui-avatars.com/api/?name=<?= urlencode($ap['full_name']) ?>&background=dbeafe&color=1e3a8a&size=36" alt="">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($ap['full_name']) ?></p>
                                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($ap['job_title']) ?></p>
                            </div>
                        </div>
                        <span class="ml-3 flex-shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $app_colors[$ap['stage']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= $ap['stage'] ?>
                        </span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<style>
.custom-scrollbar::-webkit-scrollbar{width:6px}
.custom-scrollbar::-webkit-scrollbar-track{background:transparent}
.custom-scrollbar::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:3px}
.stat-card{transition:transform .25s ease,box-shadow .25s ease}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 10px 25px -5px rgba(0,0,0,.1)}
</style>
</body>
</html>
