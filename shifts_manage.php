<?php
require_once 'auth_check.php';
$required_page = 'attendance.php';
require_once 'db_config.php';

$page_title = "Manage Work Shifts";
$error = '';
$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $shift_name = trim($_POST['shift_name'] ?? '');
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        
        if (!empty($shift_name) && !empty($start_time) && !empty($end_time)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO work_shifts (shift_name, start_time, end_time) VALUES (?, ?, ?)");
                $stmt->execute([$shift_name, $start_time, $end_time]);
                header("Location: shifts_manage.php?msg=added");
                exit;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all shift parameters.";
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $shift_name = trim($_POST['shift_name'] ?? '');
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        
        if ($id > 0 && !empty($shift_name) && !empty($start_time) && !empty($end_time)) {
            try {
                $stmt = $pdo->prepare("UPDATE work_shifts SET shift_name=?, start_time=?, end_time=? WHERE id=?");
                $stmt->execute([$shift_name, $start_time, $end_time, $id]);
                header("Location: shifts_manage.php?msg=updated");
                exit;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Invalid parameters provided for update.";
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            // Nullify assigned_shift_id in employees first to keep constraint clean
            $pdo->prepare("UPDATE employees SET assigned_shift_id = NULL WHERE assigned_shift_id = ?")->execute([$id]);
            // Nullify shift_id in attendance records
            $pdo->prepare("UPDATE attendance SET shift_id = NULL WHERE shift_id = ?")->execute([$id]);
            
            $stmt = $pdo->prepare("DELETE FROM work_shifts WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: shifts_manage.php?msg=deleted");
            exit;
        } catch (PDOException $e) {
            $error = "Cannot delete shift: " . $e->getMessage();
        }
    }
}

// Fetch all shifts
$shifts = $pdo->query("SELECT * FROM work_shifts ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
        <div class="max-w-5xl mx-auto">
            
            <!-- Navigation Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center">
                    <a href="attendance.php" class="text-gray-500 hover:text-brand-600 mr-3 transition-colors">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Work Shifts Management</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Create and configure structured daily schedule patterns</p>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="mb-6 p-4 rounded-xl border-l-4 <?php echo $_GET['msg'] == 'deleted' ? 'bg-red-50 border-red-500 text-red-700' : 'bg-emerald-50 border-emerald-500 text-emerald-700'; ?>">
                    <p class="font-semibold">
                        <?php 
                        if ($_GET['msg'] == 'added') echo "New work shift template registered successfully.";
                        if ($_GET['msg'] == 'updated') echo "Work shift parameters updated successfully.";
                        if ($_GET['msg'] == 'deleted') echo "Work shift template deleted securely.";
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 rounded-xl border-l-4 bg-red-50 border-red-500 text-red-700">
                    <p class="font-semibold"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column: Add New Shift Form -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-max">
                    <div class="flex items-center mb-4 pb-3 border-b border-gray-50 text-brand-700 font-bold text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-clock mr-2 text-base"></i> Add New Shift
                    </div>
                    <form action="shifts_manage.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Shift Name</label>
                            <input type="text" name="shift_name" required placeholder="e.g. Night Shift, Early Bird" class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Start Time</label>
                            <input type="time" name="start_time" required value="08:00" class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">End Time</label>
                            <input type="time" name="end_time" required value="17:00" class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none transition-all">
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2 px-4 rounded-xl shadow-sm transition-colors text-sm">
                                Create Shift Template
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right Columns: List of Shifts -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="font-bold text-gray-800 text-sm">Configured Work Shifts</h3>
                            <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full font-bold"><?php echo count($shifts); ?> Templates</span>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <?php if (count($shifts) > 0): ?>
                                <?php foreach ($shifts as $sh): ?>
                                    <?php 
                                    // Calculate expected duration
                                    $in = strtotime($sh['start_time']);
                                    $out = strtotime($sh['end_time']);
                                    $hrs = 0;
                                    if ($out > $in) {
                                        $hrs = round(($out - $in) / 3600, 1);
                                    } else {
                                        // Night shift passing midnight
                                        $hrs = round((($out + 86400) - $in) / 3600, 1);
                                    }
                                    ?>
                                    <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between hover:bg-gray-50/50 transition-colors gap-4">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($sh['shift_name']); ?></h4>
                                                <span class="text-xs bg-brand-50 text-brand-700 font-mono px-2 py-0.5 rounded-md border border-brand-100 font-bold">
                                                    <?php echo $hrs; ?>h duration
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-4 mt-1.5 text-xs text-gray-500 font-medium font-mono">
                                                <span><i class="fa-regular fa-circle-play text-emerald-500 mr-1 text-xs"></i> Start: <?php echo date('h:i A', strtotime($sh['start_time'])); ?></span>
                                                <span><i class="fa-regular fa-circle-stop text-amber-500 mr-1 text-xs"></i> End: <?php echo date('h:i A', strtotime($sh['end_time'])); ?></span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 self-end sm:self-center">
                                            <button onclick="openShiftEdit(<?php echo htmlspecialchars(json_encode($sh)); ?>)" class="text-blue-600 hover:bg-blue-50 px-3 py-1.5 rounded-lg text-xs font-semibold border border-blue-200 transition-colors flex items-center">
                                                <i class="fa-solid fa-pen mr-1.5 text-2xs"></i> Edit
                                            </button>
                                            <a href="shifts_manage.php?action=delete&id=<?php echo $sh['id']; ?>" onclick="return confirm('Delete this shift template? Employees assigned to this shift will default back to General schedule.');" class="text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-200 transition-colors flex items-center">
                                                <i class="fa-solid fa-trash-can mr-1.5 text-2xs"></i> Remove
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-gray-400">
                                    <p class="font-medium text-sm">No work shifts defined yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</main>

<!-- Edit Shift Modal -->
<div id="editShiftModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-brand-900 px-6 py-4 flex items-center justify-between">
            <h3 class="text-base font-bold text-white">Edit Shift Parameters</h3>
            <button onclick="document.getElementById('editShiftModal').classList.add('hidden')" class="text-brand-100/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="shifts_manage.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_shift_id">
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Shift Name</label>
                <input type="text" name="shift_name" id="edit_shift_name" required class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Start Time</label>
                <input type="time" name="start_time" id="edit_start_time" required class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">End Time</label>
                <input type="time" name="end_time" id="edit_end_time" required class="w-full rounded-lg border border-gray-300 py-2 px-3 text-gray-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm outline-none">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editShiftModal').classList.add('hidden')" class="border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium py-2 px-4 rounded-xl text-xs">Cancel</button>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2 px-5 rounded-xl text-xs shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openShiftEdit(sh) {
    document.getElementById('edit_shift_id').value = sh.id;
    document.getElementById('edit_shift_name').value = sh.shift_name;
    document.getElementById('edit_start_time').value = sh.start_time;
    document.getElementById('edit_end_time').value = sh.end_time;
    document.getElementById('editShiftModal').classList.remove('hidden');
}
</script>
</body>
</html>
