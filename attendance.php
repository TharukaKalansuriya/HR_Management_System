<?php
require_once 'auth_check.php';
$required_page = 'attendance.php';
require_once 'db_config.php';

// Handle Day Type setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_day_type') {
    $d_date = $_POST['holiday_date'] ?? '';
    $d_type = $_POST['day_type'] ?? 'Regular Weekday';
    $d_name = trim($_POST['holiday_name'] ?? '');
    if (!empty($d_date)) {
        if ($d_type === 'Regular Weekday') {
            $pdo->prepare("DELETE FROM public_holidays WHERE holiday_date=?")->execute([$d_date]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO public_holidays (holiday_date, holiday_name, day_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE holiday_name=VALUES(holiday_name), day_type=VALUES(day_type)");
            $stmt->execute([$d_date, $d_name ?: $d_type, $d_type]);
        }
        header("Location: attendance.php?date=" . urlencode($d_date) . "&msg=updated");
        exit;
    }
}

$page_title = "Attendance Management";

// Filters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_emp = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;
$view = $_GET['view'] ?? 'daily';

// Determine Day Type for the selected view date
$day_type_info = $pdo->prepare("SELECT * FROM public_holidays WHERE holiday_date=?");
$day_type_info->execute([$filter_date]);
$current_holiday = $day_type_info->fetch(PDO::FETCH_ASSOC);

$default_day_type = 'Regular Weekday';
$default_holiday_name = '';
if ($current_holiday) {
    $default_day_type = $current_holiday['day_type'];
    $default_holiday_name = $current_holiday['holiday_name'];
} else {
    $dow = date('w', strtotime($filter_date));
    if ($dow == 0 || $dow == 6) { // Sunday or Saturday
        $default_day_type = 'Weekend';
        $default_holiday_name = 'Standard Weekend';
    }
}

// Fetch employees with assigned shifts
$employees = $pdo->query("SELECT id, first_name, last_name, department, position, assigned_shift_id FROM employees WHERE status='Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch shifts
$shifts = $pdo->query("SELECT * FROM work_shifts ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);


// Fetch attendance records
if ($view === 'monthly') {
    $year = substr($filter_month, 0, 4);
    $mon  = substr($filter_month, 5, 2);
    $sql  = "SELECT a.*, e.first_name, e.last_name, e.department, e.position, s.shift_name
             FROM attendance a
             JOIN employees e ON a.employee_id = e.id
             LEFT JOIN work_shifts s ON a.shift_id = s.id
             WHERE YEAR(a.attendance_date)=? AND MONTH(a.attendance_date)=?";
    $params = [$year, $mon];
    if ($filter_emp > 0) { $sql .= " AND a.employee_id=?"; $params[] = $filter_emp; }
    $sql .= " ORDER BY a.attendance_date DESC, e.first_name";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
} else {
    $sql = "SELECT a.*, e.first_name, e.last_name, e.department, e.position, s.shift_name
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN work_shifts s ON a.shift_id = s.id
            WHERE a.attendance_date = ?";
    $params = [$filter_date];
    if ($filter_emp > 0) { $sql .= " AND a.employee_id=?"; $params[] = $filter_emp; }
    $sql .= " ORDER BY e.first_name";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
}
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats for selected date
$stats = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'on_leave'=>0,'total'=>count($employees)];
$day_stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE attendance_date=? GROUP BY status");
$day_stmt->execute([$filter_date]);
foreach($day_stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = strtolower(str_replace(' ','_',$r['status']));
    if(isset($stats[$k])) $stats[$k] = (int)$r['cnt'];
}

// Build attendance map for daily view
$att_map = [];
foreach ($records as $r) { $att_map[$r['employee_id']] = $r; }

include 'header.php';
include 'sidebar.php';
?>
<!-- MAIN CONTENT -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">

<?php if(isset($_GET['msg'])): $msg=$_GET['msg']; ?>
<div class="mb-6 p-4 rounded-xl border-l-4 <?php echo $msg=='deleted'?'bg-red-50 border-red-500 text-red-700':($msg=='updated'?'bg-blue-50 border-blue-500 text-blue-700':'bg-emerald-50 border-emerald-500 text-emerald-700'); ?>">
  <p class="font-semibold"><?php echo $msg=='marked'?'Attendance Marked!':($msg=='updated'?'Record Updated!':($msg=='deleted'?'Record Deleted!':'Success!')); ?></p>
</div>
<?php endif; ?>
<?php if(isset($_GET['error'])): ?>
<div class="mb-6 p-4 rounded-xl border-l-4 bg-red-50 border-red-500 text-red-700">
  <p class="font-semibold">Error: <?php echo htmlspecialchars($_GET['error']); ?></p>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
  <div>
    <h1 class="text-2xl font-bold text-gray-800">Attendance Management</h1>
    <p class="text-sm text-gray-500 mt-1">Track, mark and manage employee attendance</p>
  </div>
  <div class="flex flex-wrap gap-2 sm:gap-3">
    <a href="shifts_manage.php" class="flex items-center gap-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold py-2 px-4 rounded-xl shadow-2xs transition-all text-sm border border-indigo-100">
      <i class="fa-solid fa-clock font-bold"></i> Manage Shifts
    </a>
    <button onclick="document.getElementById('markModal').classList.remove('hidden')" class="flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm transition-all text-sm">
      <i class="fa-solid fa-pen-to-square"></i> Mark Attendance
    </button>
    <a href="attendance_report.php?month=<?php echo htmlspecialchars($filter_month); ?>" class="flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-xl shadow-sm transition-all text-sm">
      <i class="fa-solid fa-file-export"></i> Report
    </a>
  </div>
</div>

<?php if ($view === 'daily'): ?>
<!-- Day Type Configurator Banner -->
<div class="bg-gradient-to-r from-white via-gray-50 to-white rounded-2xl shadow-2xs border border-gray-200/80 p-4 mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
  <div class="flex items-center gap-3">
    <div class="p-2.5 rounded-xl <?php echo $default_day_type == 'Public Holiday' ? 'bg-blue-100 text-blue-700' : ($default_day_type == 'Weekend' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'); ?>">
      <i class="fa-solid <?php echo $default_day_type == 'Public Holiday' ? 'fa-umbrella-beach' : ($default_day_type == 'Weekend' ? 'fa-couch' : 'fa-briefcase'); ?> text-lg"></i>
    </div>
    <div>
      <div class="flex items-center gap-2">
        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">View Date Type</span>
        <span class="text-xs px-2 py-0.5 rounded-md font-bold <?php echo $default_day_type == 'Public Holiday' ? 'bg-blue-50 text-blue-700 border border-blue-200' : ($default_day_type == 'Weekend' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'); ?>">
          <?php echo htmlspecialchars($default_day_type); ?>
        </span>
      </div>
      <p class="text-sm font-bold text-gray-800 mt-0.5">
        <?php echo htmlspecialchars($default_holiday_name ?: 'Standard Regular Working Day'); ?>
      </p>
    </div>
  </div>
  <form action="attendance.php" method="POST" class="flex flex-wrap items-center gap-2">
    <input type="hidden" name="action" value="set_day_type">
    <input type="hidden" name="holiday_date" value="<?php echo htmlspecialchars($filter_date); ?>">
    
    <select name="day_type" class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700 font-medium outline-none bg-white">
      <option value="Regular Weekday" <?php echo $default_day_type == 'Regular Weekday' ? 'selected' : ''; ?>>Regular Weekday</option>
      <option value="Weekend" <?php echo $default_day_type == 'Weekend' ? 'selected' : ''; ?>>Weekend</option>
      <option value="Public Holiday" <?php echo $default_day_type == 'Public Holiday' ? 'selected' : ''; ?>>Public Holiday</option>
    </select>
    
    <input type="text" name="holiday_name" placeholder="Label (e.g. May Day)" value="<?php echo htmlspecialchars($default_holiday_name === 'Standard Weekend' ? '' : $default_holiday_name); ?>" class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-800 placeholder-gray-400 outline-none w-32">
    
    <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold py-1.5 px-3 rounded-lg transition-colors">
      Apply Settings
    </button>
  </form>
</div>
<?php endif; ?>


<!-- Stats Row -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
  <?php
  $statDefs=[
    ['label'=>'Total Employees','key'=>'total','color'=>'blue','icon'=>'fa-users'],
    ['label'=>'Present','key'=>'present','color'=>'emerald','icon'=>'fa-circle-check'],
    ['label'=>'Absent','key'=>'absent','color'=>'red','icon'=>'fa-circle-xmark'],
    ['label'=>'Late','key'=>'late','color'=>'amber','icon'=>'fa-clock'],
    ['label'=>'On Leave','key'=>'on_leave','color'=>'purple','icon'=>'fa-calendar-minus'],
  ];
  foreach($statDefs as $s): ?>
  <div class="stat-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
    <div class="p-3 rounded-xl bg-<?php echo $s['color']; ?>-50 text-<?php echo $s['color']; ?>-600">
      <i class="fa-solid <?php echo $s['icon']; ?> text-lg"></i>
    </div>
    <div>
      <p class="text-xs font-medium text-gray-500"><?php echo $s['label']; ?></p>
      <p class="text-2xl font-bold text-gray-800"><?php echo $stats[$s['key']]; ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<!-- Filter Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
  <form method="GET" class="flex flex-wrap gap-4 items-end">
    <div>
      <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">View Mode</label>
      <select name="view" onchange="this.form.submit()" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="daily" <?php echo $view=='daily'?'selected':''; ?>>Daily View</option>
        <option value="monthly" <?php echo $view=='monthly'?'selected':''; ?>>Monthly View</option>
      </select>
    </div>
    <?php if($view==='daily'): ?>
    <div>
      <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Date</label>
      <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <?php else: ?>
    <div>
      <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Month</label>
      <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
    <?php endif; ?>
    <div>
      <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Employee</label>
      <select name="emp_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="0">All Employees</option>
        <?php foreach($employees as $e): ?>
        <option value="<?php echo $e['id']; ?>" <?php echo $filter_emp==$e['id']?'selected':''; ?>>
          <?php echo htmlspecialchars($e['first_name'].' '.$e['last_name']); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-5 rounded-lg text-sm transition-colors">
      <i class="fa-solid fa-filter mr-1"></i> Filter
    </button>
    <a href="attendance.php" class="border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium py-2 px-4 rounded-lg text-sm transition-colors">Reset</a>
  </form>
</div>
<!-- Attendance Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
    <h2 class="text-base font-semibold text-gray-800">
      <?php if($view==='daily'): ?>
        Attendance for <?php echo date('l, d F Y', strtotime($filter_date)); ?>
      <?php else: ?>
        Monthly Records — <?php echo date('F Y', strtotime($filter_month.'-01')); ?>
      <?php endif; ?>
    </h2>
    <span class="text-sm text-gray-400"><?php echo count($records); ?> records</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
      <thead class="text-xs text-gray-500 uppercase bg-gray-50/70">
        <tr>
          <?php if($view==='monthly'): ?><th class="px-5 py-4 font-medium">Date</th><?php endif; ?>
          <th class="px-5 py-4 font-medium">Employee</th>
          <th class="px-5 py-4 font-medium">Department</th>
          <th class="px-5 py-4 font-medium">Shift</th>
          <th class="px-5 py-4 font-medium">Check In</th>
          <th class="px-5 py-4 font-medium">Check Out</th>
          <th class="px-5 py-4 font-medium">Hours</th>
          <th class="px-5 py-4 font-medium">Status</th>
          <th class="px-5 py-4 font-medium text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php
        $statusColors=[
          'Present'=>'bg-emerald-100 text-emerald-700',
          'Absent'=>'bg-red-100 text-red-700',
          'Late'=>'bg-amber-100 text-amber-700',
          'Half Day'=>'bg-orange-100 text-orange-700',
          'On Leave'=>'bg-purple-100 text-purple-700',
          'Holiday'=>'bg-blue-100 text-blue-700',
          'Weekend'=>'bg-gray-100 text-gray-600',
        ];
        ?>
        <?php if(count($records)>0): foreach($records as $r):
          $initials=strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1));
          $sc=$statusColors[$r['status']]??'bg-gray-100 text-gray-600';
        ?>
        <tr class="hover:bg-gray-50/50 transition-colors">
          <?php if($view==='monthly'): ?>
          <td class="px-5 py-4 text-gray-600 whitespace-nowrap"><?php echo date('d M Y', strtotime($r['attendance_date'])); ?></td>
          <?php endif; ?>
          <td class="px-5 py-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-xs border border-brand-200 flex-shrink-0"><?php echo $initials; ?></div>
              <div>
                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></p>
                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($r['position']); ?></p>
              </div>
            </div>
          </td>
          <td class="px-5 py-4 text-gray-600"><?php echo htmlspecialchars($r['department']); ?></td>
          <td class="px-5 py-4 text-gray-500 text-xs"><?php echo htmlspecialchars($r['shift_name']??'—'); ?></td>
          <td class="px-5 py-4 text-gray-700 font-mono"><?php echo $r['check_in']?date('h:i A',strtotime($r['check_in'])):'<span class="text-gray-300">—</span>'; ?></td>
          <td class="px-5 py-4 text-gray-700 font-mono"><?php echo $r['check_out']?date('h:i A',strtotime($r['check_out'])):'<span class="text-gray-300">—</span>'; ?></td>
          <td class="px-5 py-4">
            <?php if($r['work_hours']): ?>
              <span class="font-medium text-gray-700"><?php echo $r['work_hours']; ?>h</span>
              <?php if($r['overtime_hours']>0): ?><span class="ml-1 text-xs text-emerald-600">+<?php echo $r['overtime_hours']; ?> OT</span><?php endif; ?>
            <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
          </td>
          <td class="px-5 py-4"><span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $sc; ?>"><?php echo $r['status']; ?></span></td>
          <td class="px-5 py-4 text-right">
            <div class="flex items-center justify-end gap-2">
              <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($r)); ?>)" class="text-blue-500 hover:bg-blue-50 p-2 rounded-lg transition-colors border border-transparent hover:border-blue-200" title="Edit"><i class="fa-solid fa-pen text-xs"></i></button>
              <a href="attendance_delete.php?id=<?php echo $r['id']; ?>&date=<?php echo urlencode($filter_date); ?>" onclick="return confirm('Delete this record?')" class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors border border-transparent hover:border-red-200" title="Delete"><i class="fa-solid fa-trash-can text-xs"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9" class="px-6 py-16 text-center text-gray-400">
          <div class="flex flex-col items-center"><i class="fa-solid fa-clipboard-list text-5xl text-gray-200 mb-4"></i><p class="text-lg font-medium text-gray-500">No attendance records found</p><p class="text-sm mt-1">Use "Mark Attendance" to record for this date.</p></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- end p-8 -->
</main>

<!-- ============ MARK ATTENDANCE MODAL ============ -->
<div id="markModal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm overflow-y-auto py-8">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 overflow-hidden">
    <div class="bg-brand-900 px-6 py-5 flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold text-white">Mark Bulk Attendance</h3>
        <p class="text-brand-100/70 text-sm mt-0.5">Mark attendance for all active employees</p>
      </div>
      <button onclick="document.getElementById('markModal').classList.add('hidden')" class="text-brand-100/70 hover:text-white transition-colors text-xl"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="attendance_mark.php" method="POST">
      <input type="hidden" name="action" value="mark">
      <div class="p-6 border-b border-gray-100 flex flex-wrap gap-4 items-end bg-gray-50/50">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Date</label>
          <input type="date" name="attendance_date" value="<?php echo $filter_date; ?>" required class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Default Shift</label>
          <select name="shift_id" onchange="applyGlobalShift(this)" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
            <option value="">— No Global Shift —</option>
            <?php foreach($shifts as $sh): ?>
            <option value="<?php echo $sh['id']; ?>" data-in="<?php echo substr($sh['start_time'],0,5); ?>" data-out="<?php echo substr($sh['end_time'],0,5); ?>"><?php echo htmlspecialchars($sh['shift_name']); ?> (<?php echo date('h:i A',strtotime($sh['start_time'])); ?>–<?php echo date('h:i A',strtotime($sh['end_time'])); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Overtime Rule</label>
          <select name="ot_rule" id="modal_ot_rule" onchange="recalcAllOT()" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white font-medium">
            <option value="standard" <?php echo $default_day_type === 'Regular Weekday' ? 'selected' : ''; ?>>Standard OT (Exceeding Shift Duration)</option>
            <option value="full_credit" <?php echo $default_day_type !== 'Regular Weekday' ? 'selected' : ''; ?>>Full OT Credit (All Hours as Overtime)</option>
          </select>
        </div>
        <div class="flex gap-2.5 ml-auto">
          <button type="button" onclick="markAll('Present')" class="text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-3 py-2 rounded-lg font-bold transition-colors">✓ All Present</button>
          <button type="button" onclick="markAll('Absent')" class="text-xs bg-red-100 text-red-700 hover:bg-red-200 px-3 py-2 rounded-lg font-bold transition-colors">✗ All Absent</button>
        </div>
      </div>
      <div class="overflow-y-auto max-h-96">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500 uppercase font-semibold">
            <tr>
              <th class="px-5 py-3 text-left">Employee</th>
              <th class="px-4 py-3">Assigned Shift</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Check In</th>
              <th class="px-4 py-3">Check Out</th>
              <th class="px-4 py-3 text-right">Computed</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach($employees as $i=>$e):
              $existing=$att_map[$e['id']]??null;
              $initials=strtoupper(substr($e['first_name'],0,1).substr($e['last_name'],0,1));
              
              // Map default status dynamically based on Day Type configuration
              $mapped_status = 'Present';
              if ($default_day_type === 'Public Holiday') $mapped_status = 'Holiday';
              if ($default_day_type === 'Weekend') $mapped_status = 'Weekend';
              
              // Target shift
              $target_shift_id = $existing ? $existing['shift_id'] : $e['assigned_shift_id'];
              
              // Default check in / out times based on target shift start/end if available
              $def_in = '09:00'; $def_out = '17:00'; $def_hrs = 8;
              foreach ($shifts as $sh) {
                  if ($sh['id'] == $target_shift_id) {
                      $def_in = substr($sh['start_time'], 0, 5);
                      $def_out = substr($sh['end_time'], 0, 5);
                      $in_t = strtotime($sh['start_time']); $out_t = strtotime($sh['end_time']);
                      $def_hrs = $out_t > $in_t ? round(($out_t - $in_t)/3600, 1) : 8;
                      break;
                  }
              }
              $ci_val = $existing ? substr($existing['check_in'] ?? '', 0, 5) : $def_in;
              $co_val = $existing ? substr($existing['check_out'] ?? '', 0, 5) : $def_out;
              if (empty($ci_val)) $ci_val = $def_in;
              if (empty($co_val)) $co_val = $def_out;
            ?>
            <tr class="hover:bg-gray-50/50 att-row" data-shift-hrs="<?php echo $def_hrs; ?>">
              <input type="hidden" name="employees[]" value="<?php echo $e['id']; ?>">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-xs border border-brand-200 flex-shrink-0"><?php echo $initials; ?></div>
                  <div>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($e['first_name'].' '.$e['last_name']); ?></p>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($e['department']); ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <select name="shift_ids[]" onchange="updateRowShift(this)" class="row-shift border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500 w-32 bg-white">
                  <option value="" data-hrs="8">— General (8h) —</option>
                  <?php foreach($shifts as $sh): 
                      $in_t = strtotime($sh['start_time']); $out_t = strtotime($sh['end_time']);
                      $sh_hrs = $out_t > $in_t ? round(($out_t - $in_t)/3600, 1) : 8;
                  ?>
                  <option value="<?php echo $sh['id']; ?>" data-hrs="<?php echo $sh_hrs; ?>" data-in="<?php echo substr($sh['start_time'],0,5); ?>" data-out="<?php echo substr($sh['end_time'],0,5); ?>" <?php echo $target_shift_id == $sh['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sh['shift_name']); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="px-4 py-3">
                <select name="statuses[]" onchange="calcRowOT(this.closest('tr'))" class="att-status border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500 w-24 bg-white">
                  <?php foreach(['Present','Absent','Late','Half Day','On Leave','Holiday','Weekend'] as $st): ?>
                  <option value="<?php echo $st; ?>" <?php echo ($existing && $existing['status']==$st) || (!$existing && $mapped_status==$st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="px-4 py-3"><input type="time" name="check_ins[]" onchange="calcRowOT(this.closest('tr'))" value="<?php echo $ci_val; ?>" class="row-in border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white"></td>
              <td class="px-4 py-3"><input type="time" name="check_outs[]" onchange="calcRowOT(this.closest('tr'))" value="<?php echo $co_val; ?>" class="row-out border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white"></td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <span class="row-hrs font-bold text-gray-800 text-xs block">--</span>
                <span class="row-ot text-2xs text-emerald-600 font-bold block"></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="px-6 py-4 flex justify-end gap-3 bg-gray-50 border-t border-gray-100">
        <button type="button" onclick="document.getElementById('markModal').classList.add('hidden')" class="border border-gray-200 text-gray-600 hover:bg-gray-100 font-medium py-2 px-5 rounded-xl text-sm transition-colors">Cancel</button>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-6 rounded-xl text-sm transition-colors shadow-sm"><i class="fa-solid fa-check mr-2"></i>Save Attendance</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ EDIT ATTENDANCE MODAL ============ -->
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
    <div class="bg-blue-700 px-6 py-5 flex items-center justify-between rounded-t-2xl">
      <h3 class="text-lg font-bold text-white">Edit Attendance Record</h3>
      <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-blue-100/70 hover:text-white text-xl"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="attendance_mark.php" method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <input type="hidden" name="employee_id" id="edit_emp_id">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Employee</label>
        <p id="edit_emp_name" class="text-sm font-medium text-gray-800 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200"></p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Date</label>
        <input type="date" name="attendance_date" id="edit_date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Check In</label>
          <input type="time" name="check_in" id="edit_check_in" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Check Out</label>
          <input type="time" name="check_out" id="edit_check_out" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Status</label>
          <select name="status" id="edit_status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <?php foreach(['Present','Absent','Late','Half Day','On Leave','Holiday','Weekend'] as $st): ?>
            <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Shift</label>
          <select name="shift_id" id="edit_shift" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">— None —</option>
            <?php foreach($shifts as $sh): ?>
            <option value="<?php echo $sh['id']; ?>"><?php echo htmlspecialchars($sh['shift_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Notes</label>
        <textarea name="notes" id="edit_notes" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Optional notes..."></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="border border-gray-200 text-gray-600 hover:bg-gray-100 font-medium py-2 px-5 rounded-xl text-sm">Cancel</button>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-xl text-sm shadow-sm"><i class="fa-solid fa-floppy-disk mr-2"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(r) {
  document.getElementById('edit_id').value = r.id;
  document.getElementById('edit_emp_id').value = r.employee_id;
  document.getElementById('edit_emp_name').textContent = r.first_name + ' ' + r.last_name;
  document.getElementById('edit_date').value = r.attendance_date;
  document.getElementById('edit_check_in').value = r.check_in || '';
  document.getElementById('edit_check_out').value = r.check_out || '';
  document.getElementById('edit_status').value = r.status;
  document.getElementById('edit_shift').value = r.shift_id || '';
  document.getElementById('edit_notes').value = r.notes || '';
  document.getElementById('editModal').classList.remove('hidden');
}

function markAll(status) {
  document.querySelectorAll('.att-status').forEach(function(sel){ 
      sel.value = status; 
      calcRowOT(sel.closest('tr'));
  });
}

function applyGlobalShift(sel) {
  const opt = sel.options[sel.selectedIndex];
  const gShiftId = sel.value;
  const gIn = opt ? opt.getAttribute('data-in') : '';
  const gOut = opt ? opt.getAttribute('data-out') : '';
  
  document.querySelectorAll('.att-row').forEach(row => {
      const shiftSel = row.querySelector('.row-shift');
      // If employee shift is not explicitly customized, follow global
      if (shiftSel && (!shiftSel.dataset.userEdited || shiftSel.dataset.userEdited !== 'true')) {
          shiftSel.value = gShiftId;
          updateRowShift(shiftSel, true);
      }
  });
}

function updateRowShift(sel, isFromGlobal = false) {
  if (!isFromGlobal) {
      sel.dataset.userEdited = 'true';
  }
  const opt = sel.options[sel.selectedIndex];
  const row = sel.closest('tr');
  if (opt) {
      const hrs = opt.getAttribute('data-hrs') || '8';
      const inTime = opt.getAttribute('data-in');
      const outTime = opt.getAttribute('data-out');
      row.dataset.shiftHrs = hrs;
      
      const inInput = row.querySelector('.row-in');
      const outInput = row.querySelector('.row-out');
      if (inInput && inTime) inInput.value = inTime;
      if (outInput && outTime) outInput.value = outTime;
  }
  calcRowOT(row);
}

function calcRowOT(row) {
  const inInput = row.querySelector('.row-in');
  const outInput = row.querySelector('.row-out');
  const statusSel = row.querySelector('.att-status');
  const hrsSpan = row.querySelector('.row-hrs');
  const otSpan = row.querySelector('.row-ot');
  const otRule = document.getElementById('modal_ot_rule') ? document.getElementById('modal_ot_rule').value : 'standard';
  
  if (!inInput || !outInput || !hrsSpan || !otSpan) return;
  
  const statusVal = statusSel ? statusSel.value : 'Present';
  if (statusVal === 'Absent' || statusVal === 'On Leave') {
      hrsSpan.textContent = '0.0h';
      otSpan.textContent = '';
      return;
  }
  
  const inVal = inInput.value;
  const outVal = outInput.value;
  if (!inVal || !outVal) {
      hrsSpan.textContent = '--';
      otSpan.textContent = '';
      return;
  }
  
  const [inH, inM] = inVal.split(':').map(Number);
  const [outH, outM] = outVal.split(':').map(Number);
  let totalMins = (outH * 60 + outM) - (inH * 60 + inM);
  if (totalMins < 0) {
      totalMins += 24 * 60; // night shift overnight
  }
  
  const totalHrs = Math.round((totalMins / 60) * 100) / 100;
  hrsSpan.textContent = totalHrs.toFixed(1) + 'h';
  
  const shiftTargetHrs = parseFloat(row.dataset.shiftHrs || '8');
  let otHrs = 0;
  
  if (otRule === 'full_credit' || statusVal === 'Holiday' || statusVal === 'Weekend') {
      otHrs = totalHrs;
  } else {
      if (totalHrs > shiftTargetHrs) {
          otHrs = Math.round((totalHrs - shiftTargetHrs) * 100) / 100;
      }
  }
  
  if (otHrs > 0) {
      otSpan.textContent = '+' + otHrs.toFixed(1) + 'h OT';
  } else {
      otSpan.textContent = '';
  }
}

function recalcAllOT() {
  document.querySelectorAll('.att-row').forEach(row => calcRowOT(row));
}

// Initial compute on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  recalcAllOT();
});
</script>
</body>
</html>
