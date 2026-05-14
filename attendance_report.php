<?php
require_once 'db_config.php';
$page_title = "Attendance Report";
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_emp   = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;
$year  = substr($filter_month,0,4);
$mon   = substr($filter_month,5,2);
$days_in_month = cal_days_in_month(CAL_GREGORIAN,(int)$mon,(int)$year);
$employees_all = $pdo->query("SELECT id,first_name,last_name,department,position FROM employees WHERE status='Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
$sql="SELECT a.*,e.first_name,e.last_name,e.department FROM attendance a JOIN employees e ON a.employee_id=e.id WHERE YEAR(a.attendance_date)=? AND MONTH(a.attendance_date)=?";
$params=[$year,$mon];
if($filter_emp>0){$sql.=" AND a.employee_id=?";$params[]=$filter_emp;}
$stmt=$pdo->prepare($sql);$stmt->execute($params);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$map=[];
foreach($rows as $r){$map[$r['employee_id']][$r['attendance_date']]=$r;}
include 'header.php';
include 'sidebar.php';
?>
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
<?php include 'topbar.php'; ?>
<div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
  <div>
    <h1 class="text-2xl font-bold text-gray-800">Attendance Report</h1>
    <p class="text-sm text-gray-500 mt-1"><?php echo date('F Y', strtotime($filter_month.'-01')); ?></p>
  </div>
  <div class="flex gap-3">
    <a href="attendance.php" class="flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium py-2 px-5 rounded-xl shadow-sm transition-all"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <button onclick="window.print()" class="flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-5 rounded-xl shadow-sm transition-all"><i class="fa-solid fa-print"></i> Print</button>
  </div>
</div>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
  <form method="GET" class="flex flex-wrap gap-4 items-end">
    <div><label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Month</label>
    <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></div>
    <div><label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Employee</label>
    <select name="emp_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
      <option value="0">All Employees</option>
      <?php foreach($employees_all as $e): ?>
      <option value="<?php echo $e['id']; ?>" <?php echo $filter_emp==$e['id']?'selected':''; ?>><?php echo htmlspecialchars($e['first_name'].' '.$e['last_name']); ?></option>
      <?php endforeach; ?>
    </select></div>
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-5 rounded-lg text-sm">Filter</button>
  </form>
</div>
<?php
$emps_to_show = $filter_emp > 0 ? array_filter($employees_all, fn($e)=>$e['id']==$filter_emp) : $employees_all;
foreach($emps_to_show as $emp):
  $emp_records = $map[$emp['id']] ?? [];
  $present=0;$absent=0;$late=0;$leave=0;$total_hrs=0;
  foreach($emp_records as $dr){
    if($dr['status']==='Present') $present++;
    elseif($dr['status']==='Absent') $absent++;
    elseif($dr['status']==='Late') $late++;
    elseif($dr['status']==='On Leave') $leave++;
    $total_hrs += (float)($dr['work_hours']??0);
  }
  $initials=strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
  <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3 bg-gray-50/50">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold border border-brand-200"><?php echo $initials; ?></div>
      <div><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></p><p class="text-xs text-gray-400"><?php echo htmlspecialchars($emp['department']); ?></p></div>
    </div>
    <div class="flex gap-6 text-xs">
      <div class="text-center"><p class="text-emerald-600 font-bold text-lg"><?php echo $present; ?></p><p class="text-gray-400">Present</p></div>
      <div class="text-center"><p class="text-red-500 font-bold text-lg"><?php echo $absent; ?></p><p class="text-gray-400">Absent</p></div>
      <div class="text-center"><p class="text-amber-500 font-bold text-lg"><?php echo $late; ?></p><p class="text-gray-400">Late</p></div>
      <div class="text-center"><p class="text-purple-500 font-bold text-lg"><?php echo $leave; ?></p><p class="text-gray-400">Leave</p></div>
      <div class="text-center"><p class="text-blue-600 font-bold text-lg"><?php echo number_format($total_hrs,1); ?></p><p class="text-gray-400">Hrs</p></div>
    </div>
  </div>
  <div class="p-4">
    <div class="flex flex-wrap gap-1">
      <?php for($d=1;$d<=$days_in_month;$d++):
        $date_str=sprintf('%s-%02d-%02d',$year,$mon,$d);
        $rec=$emp_records[$date_str]??null;
        $dow=date('N',strtotime($date_str));
        if($rec){$s=$rec['status'];$bg=['Present'=>'bg-emerald-500','Absent'=>'bg-red-500','Late'=>'bg-amber-400','Half Day'=>'bg-orange-400','On Leave'=>'bg-purple-400','Holiday'=>'bg-blue-400','Weekend'=>'bg-gray-300'][$s]??'bg-gray-200';}
        elseif($dow>=6){$bg='bg-gray-100';}
        else{$bg='bg-red-100';}
        $tt=$rec?$rec['status']:'N/A';
      ?>
      <div class="relative group w-8 h-8 rounded-lg <?php echo $bg; ?> flex items-center justify-center text-xs font-bold <?php echo $rec&&in_array($rec['status'],['Present','Absent','Late'])?'text-white':'text-gray-600'; ?> cursor-default">
        <?php echo $d; ?>
        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10"><?php echo date('d M',strtotime($date_str)).': '.$tt; ?></div>
      </div>
      <?php endfor; ?>
    </div>
    <div class="flex flex-wrap gap-4 mt-3 text-xs text-gray-500">
      <span><span class="inline-block w-3 h-3 rounded bg-emerald-500 mr-1"></span>Present</span>
      <span><span class="inline-block w-3 h-3 rounded bg-red-500 mr-1"></span>Absent</span>
      <span><span class="inline-block w-3 h-3 rounded bg-amber-400 mr-1"></span>Late</span>
      <span><span class="inline-block w-3 h-3 rounded bg-purple-400 mr-1"></span>On Leave</span>
      <span><span class="inline-block w-3 h-3 rounded bg-gray-300 mr-1"></span>Weekend</span>
      <span><span class="inline-block w-3 h-3 rounded bg-red-100 mr-1"></span>Not Marked</span>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div></main></body></html>
