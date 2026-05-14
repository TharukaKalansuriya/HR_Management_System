<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php'; 
include 'includes/navbar.php'; 

$message = "";
$message_type = ""; // 'success' or 'error'

// Helper for calculation
function getUsedDays($conn, $user_id, $type) {
    $q = "SELECT SUM(days) as total FROM leaves WHERE user_id = '$user_id' AND leave_type = '$type' AND status = 'Approved'";
    $res = mysqli_query($conn, $q);
    $data = mysqli_fetch_assoc($res);
    return $data['total'] ? $data['total'] : 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date_post = !empty($_POST['end_date']) ? $_POST['end_date'] : $_POST['start_date'];
    $end_date = mysqli_real_escape_string($conn, $end_date_post);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    // Calculate requested days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    // Fetch user's role/position
    $user_q = "SELECT position FROM employees WHERE id = '$user_id'";
    $user_res = mysqli_query($conn, $user_q);
    $user_role = mysqli_fetch_assoc($user_res)['position'];

    // Fetch allocations from DB with improved matching
    $norm_user_pos = strtolower(str_replace([' ', '_', '-'], '', $user_role));
    $db_alloc = null;
    $all_alloc_res = mysqli_query($conn, "SELECT * FROM leave_allocations");
    while ($a = mysqli_fetch_assoc($all_alloc_res)) {
        $norm_role = strtolower(str_replace([' ', '_', '-'], '', $a['role_name']));
        if ($norm_role === $norm_user_pos) {
            $db_alloc = $a;
            break;
        }
    }
    
    // Fallback for common abbreviations
    if (!$db_alloc && $norm_user_pos == 'traineese') {
        mysqli_data_seek($all_alloc_res, 0);
        while ($a = mysqli_fetch_assoc($all_alloc_res)) {
            if (strtolower(str_replace([' ', '_', '-'], '', $a['role_name'])) === 'trineesoftwareengineer') {
                $db_alloc = $a;
                break;
            }
        }
    }

    // Shared Pool Logic for Remaining Balance Check
    $annual_used = getUsedDays($conn, $user_id, 'annual');
    $sick_used = getUsedDays($conn, $user_id, 'sick');
    $casual_used = getUsedDays($conn, $user_id, 'casual');
    $half_day_used = getUsedDays($conn, $user_id, 'half_day');

    $TOTAL_ANNUAL = $db_alloc['annual_limit'] ?? 14;
    $TOTAL_CASUAL = $db_alloc['casual_limit'] ?? 7;
    $TOTAL_HALF_DAY = $db_alloc['half_day_limit'] ?? 10;

    $annual_rem = $TOTAL_ANNUAL - ($annual_used + ($half_day_used / 2.0));
    $casual_rem = $TOTAL_CASUAL - ($casual_used + $sick_used);
    $half_day_rem = $TOTAL_HALF_DAY - $half_day_used;

    $remaining = 0;
    if ($leave_type == 'annual') $remaining = $annual_rem;
    elseif ($leave_type == 'casual') $remaining = $casual_rem;
    elseif ($leave_type == 'sick') $remaining = $casual_rem; // Shared with Casual
    elseif ($leave_type == 'half_day') $remaining = $half_day_rem;
    elseif ($leave_type == 'unpaid') $remaining = 999;



    if ($end < $start) {
        $message = "End date cannot be earlier than start date!";
        $message_type = "error";
    } elseif ($leave_type == 'annual' && $days > $annual_rem) {
        $message = "Insufficient Annual Leave balance!";
        $message_type = "error";
    } elseif ($leave_type == 'half_day' && ($days / 2.0) > $annual_rem) {
        $message = "Insufficient Annual Leave balance for this half-day request!";
        $message_type = "error";
    } elseif ($days > $remaining && $leave_type !== 'unpaid') {
        $message = "Insufficient leave balance! You only have " . floor($remaining) . " days left for " . ucfirst($leave_type) . " leave.";
        $message_type = "error";
    } else {
        $document_path = "";
        
        // Handle file upload
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $target_dir = "uploads/";
            $file_extension = pathinfo($_FILES["document"]["name"], PATHINFO_EXTENSION);
            $new_filename = "leave_" . time() . "_" . $user_id . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
                $document_path = $target_file;
            }
        }

        $query = "INSERT INTO leaves (user_id, leave_type, start_date, end_date, days, reason, document_path) 
                  VALUES ('$user_id', '$leave_type', '$start_date', '$end_date', '$days', '$reason', '$document_path')";
        
        if (mysqli_query($conn, $query)) {
            $message = "Leave application submitted successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}
?>

<main class="flex-grow p-6 md:p-10 bg-slate-50">
    <div class="max-w-3xl mx-auto">
        <div class="mb-8">
            <a href="dashboard.php" class="inline-flex items-center text-sm font-semibold text-slate-400 hover:text-blue-600 transition-colors mb-4 gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
                Back to Dashboard
            </a>
            <h1 class="text-3xl font-bold text-slate-800">Apply for Leave</h1>
            <p class="text-slate-500 mt-1">Please fill in the details for your leave request.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div id="status-alert" class="mb-6 p-4 rounded-2xl flex items-center gap-3 transition-all duration-500 <?php echo $message_type == 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?> animate-float">
                <?php if ($message_type == 'success'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                <?php endif; ?>
                <span class="font-bold text-sm"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <form action="apply_leave.php" method="POST" enctype="multipart/form-data" class="p-8 md:p-10 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700" for="leave_type">Leave Type</label>
                        <select id="leave_type" name="leave_type" required 
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none appearance-none cursor-pointer">
                            <option value="">Select a type</option>
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="casual">Casual Leave</option>
                            <option value="half_day">Half Day Leave</option>
                            <option value="unpaid">Unpaid Leave</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700" for="days">Expected Duration (Days)</label>
                        <input type="number" id="days" name="days" readonly 
                            class="w-full px-4 py-3 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 cursor-not-allowed outline-none"
                            placeholder="Calculated automatically">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700" for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required 
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700" for="end_date">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date" 
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700" for="reason">Reason for Leave</label>
                    <textarea id="reason" name="reason" rows="4" required 
                        class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none resize-none"
                        placeholder="Please provide a brief explanation..."></textarea>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Supporting Documents (Optional)</label>
                    <div class="relative border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center hover:border-blue-400 transition-all cursor-pointer group bg-slate-50/50">
                        <input type="file" id="document" name="document" accept=".pdf,.png,.jpg,.jpeg"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                        
                        <!-- Preview Container -->
                        <div id="preview-container" class="hidden mb-4">
                            <div class="relative inline-block">
                                <img id="image-preview" src="#" alt="Preview" class="max-h-32 rounded-xl shadow-md border-4 border-white mx-auto hidden">
                                <div id="file-icon-preview" class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div id="upload-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300 group-hover:text-blue-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-sm font-medium text-slate-500">Drag and drop or <span class="text-blue-600 font-bold">browse files</span></p>
                            <p class="text-xs text-slate-400 mt-1" id="file-name">PDF, PNG, JPG up to 10MB</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex flex-col sm:flex-row gap-4">
                    <button type="submit" 
                        class="flex-grow bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-blue-100 transition-all transform hover:-translate-y-1 active:scale-95">
                        Submit Application
                    </button>
                    <button type="reset" 
                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-4 px-8 rounded-xl transition-all">
                        Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    // Automatic day calculation script
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const daysInput = document.getElementById('days');
    const leaveTypeInput = document.getElementById('leave_type');

    function calculateDays() {
        if (!startDateInput.value) {
            daysInput.value = '';
            return;
        }

        const start = new Date(startDateInput.value);
        const end = endDateInput.value ? new Date(endDateInput.value) : start;

        if (end >= start) {
            const diffTime = Math.abs(end - start);
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (leaveTypeInput && leaveTypeInput.value === 'half_day') {
                diffDays = diffDays * 0.5;
            }
            
            daysInput.value = diffDays;
        } else {
            daysInput.value = 0;
        }
    }

    startDateInput.addEventListener('change', calculateDays);
    endDateInput.addEventListener('change', calculateDays);
    if (leaveTypeInput) {
        leaveTypeInput.addEventListener('change', calculateDays);
    }

    // Show filename and preview after selection
    document.getElementById('document').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const fileNameElement = document.getElementById('file-name');
        const previewContainer = document.getElementById('preview-container');
        const imagePreview = document.getElementById('image-preview');
        const fileIconPreview = document.getElementById('file-icon-preview');
        const uploadPlaceholder = document.getElementById('upload-placeholder');

        if (file) {
            fileNameElement.textContent = file.name;
            fileNameElement.classList.add('text-blue-600', 'font-bold');
            
            previewContainer.classList.remove('hidden');
            uploadPlaceholder.classList.add('opacity-50');

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('hidden');
                    fileIconPreview.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            } else {
                imagePreview.classList.add('hidden');
                fileIconPreview.classList.remove('hidden');
            }
        } else {
            fileNameElement.textContent = 'PDF, PNG, JPG up to 10MB';
            fileNameElement.classList.remove('text-blue-600', 'font-bold');
            previewContainer.classList.add('hidden');
            uploadPlaceholder.classList.remove('opacity-50');
        }
    });

    // Auto-hide alert after 4 seconds
    const statusAlert = document.getElementById('status-alert');
    if (statusAlert) {
        setTimeout(() => {
            statusAlert.style.opacity = '0';
            statusAlert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                statusAlert.style.display = 'none';
            }, 500);
        }, 4000);
    }
</script>
