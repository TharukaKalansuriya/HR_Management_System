<?php
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: attendance.php');
    exit;
}

$action        = $_POST['action'] ?? 'mark';
$employee_id   = (int)($_POST['employee_id'] ?? 0);
$att_date      = $_POST['attendance_date'] ?? date('Y-m-d');
$check_in      = !empty($_POST['check_in'])  ? $_POST['check_in']  : null;
$check_out     = !empty($_POST['check_out']) ? $_POST['check_out'] : null;
$status        = $_POST['status']   ?? 'Present';
$global_shift  = !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : null;
$ot_rule       = $_POST['ot_rule'] ?? 'standard';
$notes         = trim($_POST['notes'] ?? '');
$marked_by     = 'Admin';

// Fetch all shifts map for target hour evaluation
$shifts_map = [];
try {
    $sh_rows = $pdo->query("SELECT * FROM work_shifts")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sh_rows as $sr) {
        $in_t = strtotime($sr['start_time']);
        $out_t = strtotime($sr['end_time']);
        $target_h = 8.0;
        if ($out_t > $in_t) {
            $target_h = round(($out_t - $in_t) / 3600, 2);
        } else {
            // overnight
            $target_h = round((($out_t + 86400) - $in_t) / 3600, 2);
        }
        $shifts_map[$sr['id']] = $target_h;
    }
} catch (PDOException $e) {
    // defaults keep intact
}

/**
 * Compute Work Hours and Overtime based on check in/out and shift target hours
 */
function computeAttendanceHours($ci, $co, $stat, $shift_id, $rule, $s_map) {
    if (empty($ci) || empty($co) || $stat === 'Absent' || $stat === 'On Leave') {
        return ['wh' => null, 'ot' => 0.00];
    }
    
    $in_time = strtotime($ci);
    $out_time = strtotime($co);
    $diff_secs = $out_time - $in_time;
    if ($diff_secs < 0) {
        $diff_secs += 86400; // Passed midnight
    }
    
    $wh = round($diff_secs / 3600, 2);
    $target_h = isset($s_map[$shift_id]) ? $s_map[$shift_id] : 8.00;
    
    $ot = 0.00;
    if ($rule === 'full_credit' || $stat === 'Holiday' || $stat === 'Weekend') {
        $ot = $wh;
    } else {
        if ($wh > $target_h) {
            $ot = round($wh - $target_h, 2);
        }
    }
    
    return ['wh' => $wh, 'ot' => $ot];
}

// Calculate for single record edit/add
$shift_id = $global_shift;
$res = computeAttendanceHours($check_in, $check_out, $status, $shift_id, $ot_rule, $shifts_map);
$work_hours = $res['wh'];
$overtime = $res['ot'];

try {
    if ($action === 'mark') {
        // Bulk mark for a date
        $employees = $_POST['employees'] ?? [];
        $statuses  = $_POST['statuses']  ?? [];
        $shift_ids = $_POST['shift_ids'] ?? [];

        foreach ($employees as $idx => $emp_id) {
            $emp_id   = (int)$emp_id;
            $emp_stat = $statuses[$idx] ?? 'Present';
            $ci = !empty($_POST['check_ins'][$idx])  ? $_POST['check_ins'][$idx]  : null;
            $co = !empty($_POST['check_outs'][$idx]) ? $_POST['check_outs'][$idx] : null;
            
            // Resolve row shift ID
            $row_shift = !empty($shift_ids[$idx]) ? (int)$shift_ids[$idx] : $global_shift;
            if ($row_shift === 0) $row_shift = null;
            
            $computed = computeAttendanceHours($ci, $co, $emp_stat, $row_shift, $ot_rule, $shifts_map);
            $wh = $computed['wh'];
            $ot = $computed['ot'];
            
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, shift_id, work_hours, overtime_hours, notes, marked_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), shift_id=VALUES(shift_id), work_hours=VALUES(work_hours), overtime_hours=VALUES(overtime_hours), notes=VALUES(notes), marked_by=VALUES(marked_by), updated_at=NOW()");
            $stmt->execute([$emp_id, $att_date, $ci, $co, $emp_stat, $row_shift, $wh, $ot, $notes, $marked_by]);
        }
        header('Location: attendance.php?msg=marked&date=' . urlencode($att_date));

    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE attendance SET employee_id=?, attendance_date=?, check_in=?, check_out=?, status=?, shift_id=?, work_hours=?, overtime_hours=?, notes=?, marked_by=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$employee_id, $att_date, $check_in, $check_out, $status, $shift_id, $work_hours, $overtime, $notes, $marked_by, $id]);
        header('Location: attendance.php?msg=updated&date=' . urlencode($att_date));

    } elseif ($action === 'add_single') {
        $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, shift_id, work_hours, overtime_hours, notes, marked_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), shift_id=VALUES(shift_id), work_hours=VALUES(work_hours), overtime_hours=VALUES(overtime_hours), notes=VALUES(notes), marked_by=VALUES(marked_by), updated_at=NOW()");
        $stmt->execute([$employee_id, $att_date, $check_in, $check_out, $status, $shift_id, $work_hours, $overtime, $notes, $marked_by]);
        header('Location: attendance.php?msg=added&date=' . urlencode($att_date));
    }

} catch (PDOException $e) {
    header('Location: attendance.php?error=' . urlencode($e->getMessage()));
}
exit;
