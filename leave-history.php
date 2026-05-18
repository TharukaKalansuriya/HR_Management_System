<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_set_cookie_params(2592000, '/');
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    die;
}
include 'includes/dbconnection.php';
include 'includes/header.php'; 
include 'includes/navbar.php'; 

$user_id = $_SESSION['user_id'];

// Get filter from URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if ($status_filter !== 'all') {
    $safe_filter = mysqli_real_escape_string($conn, $status_filter);
    $query = "SELECT * FROM leaves WHERE user_id = '$user_id' AND status = '$safe_filter'";
} else {
    // By default, history shows finalized leaves, but for HR Managers we include HR_Approved (which is their Pending state)
    if (stripos($_SESSION['position'] ?? '', 'Supervisor') !== false) {
        $query = "SELECT * FROM leaves WHERE user_id = '$user_id' AND (status = 'Recommended' OR status = 'HR_Approved' OR status = 'Approved' OR status = 'Rejected')";
    } elseif (stripos($_SESSION['position'] ?? '', 'HR Manager') !== false) {
        $query = "SELECT * FROM leaves WHERE user_id = '$user_id' AND (status = 'Approved' OR status = 'Rejected' OR status = 'HR_Approved')";
    } else {
        $query = "SELECT * FROM leaves WHERE user_id = '$user_id' AND (status = 'Approved' OR status = 'Rejected')";
    }
}
$query .= " ORDER BY created_at DESC";

$result = mysqli_query($conn, $query);

// Helper function for leave type display
function getLeaveTypeDisplay($type) {
    $types = [
        'annual' => ['AL', 'Annual Leave', 'bg-blue-50 text-blue-600'],
        'sick' => ['SL', 'Sick Leave', 'bg-emerald-50 text-emerald-600'],
        'casual' => ['CL', 'Casual Leave', 'bg-purple-50 text-purple-600'],
        'half_day' => ['HD', 'Half Day', 'bg-amber-50 text-amber-600'],
        'unpaid' => ['UL', 'Unpaid Leave', 'bg-red-50 text-red-600']
    ];
    return $types[strtolower($type)] ?? ['L', 'Leave', 'bg-slate-50 text-slate-600'];
}

// Helper function for status display
function getStatusDisplay($status) {
    $status_lower = strtolower($status);
    $user_position = $_SESSION['position'] ?? '';
    
    if (stripos($user_position, 'Supervisor') !== false) {
        if ($status_lower == 'recommended') {
            return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200">
                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-2"></span>
                        Pending
                    </span>';
        } elseif ($status_lower == 'hr_approved') {
            return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-700 border border-blue-200">
                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                        HR Approved
                    </span>';
        }
    }

    if ($status_lower == 'approved') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                    Approved
                </span>';
    } elseif ($status_lower == 'hr_approved') {
        if (stripos($user_position, 'HR Manager') !== false) {
            return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200">
                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-2"></span>
                        Pending
                    </span>';
        }
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-700 border border-blue-200">
                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                    HR Approved
                </span>';
    } elseif ($status_lower == 'rejected') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700 border border-red-200">
                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-2"></span>
                    Rejected
                </span>';
    } else {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200">
                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-2"></span>
                    Pending
                </span>';
    }
}
?>

<main class="flex-grow p-6 md:p-10 bg-slate-50">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Leave History</h1>
                <p class="text-slate-500 mt-1">Track and manage all your past and upcoming leave applications.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text" id="tableSearch" placeholder="Search leaves..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full md:w-64 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>



        <!-- History Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto overflow-y-auto max-h-[600px]">
                <table class="w-full text-left border-collapse relative" id="leaveTable">
                    <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-sm shadow-sm">
                            <tr>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Type</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Applied Date</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Document</th>
                                <th class="px-8 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): 
                                    $typeInfo = getLeaveTypeDisplay($row['leave_type']);
                                ?>
                                <tr class="hover:bg-slate-50/30 transition-colors">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 <?php echo $typeInfo[2]; ?> rounded-xl flex items-center justify-center font-bold text-xs"><?php echo $typeInfo[0]; ?></div>
                                            <div>
                                                <div class="font-semibold text-slate-800"><?php echo $typeInfo[1]; ?></div>
                                                <div class="text-xs text-slate-400 max-w-[200px] truncate" title="<?php echo $row['reason']; ?>"><?php echo $row['reason']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="text-sm text-slate-600 font-medium"><?php echo date('M d', strtotime($row['start_date'])) . ' - ' . date('M d', strtotime($row['end_date'])); ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><?php echo ($row['leave_type'] === 'half_day') ? ($row['days'] == 1 ? '1/2' : ($row['days'] * 0.5)) : $row['days']; ?> Days</div>
                                    </td>
                                    <td class="px-8 py-6 text-sm text-slate-600"><?php echo date('M d, Y', strtotime($row['created_at'] ?? $row['start_date'])); ?></td>
                                    <td class="px-8 py-6">
                                            <?php if (!empty($row['document_path'])): ?>
                                            <a href="<?php echo $row['document_path']; ?>" target="_blank" class="text-slate-400 hover:text-blue-600 transition-colors p-2 hover:bg-blue-50 rounded-lg inline-block" title="View Document">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                </svg>
                                            </a>
                                            <?php else: ?>
                                                <span class="text-slate-300 font-bold ml-4">-</span>
                                            <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php echo getStatusDisplay($row['status']); ?>
                                    </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr id="no-data-row">
                                <td colspan="5" class="px-8 py-10 text-center text-slate-400 font-medium">No leave history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    // Enhanced table search
    document.getElementById('tableSearch').addEventListener('input', function() {
        const value = this.value.toLowerCase().trim();
        const searchTerms = value.split(/\s+/).filter(term => term.length > 0);
        const rows = document.querySelectorAll('#leaveTable tbody tr:not(#no-data-row):not(#no-matching-row)');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            // Check if all search terms are present in the row
            const isMatch = searchTerms.every(term => text.includes(term));
            
            row.style.display = isMatch ? '' : 'none';
            if (isMatch) visibleCount++;
        });

        // Dynamic "No matching results" feedback
        let noMatchRow = document.getElementById('no-matching-row');
        const noDataRow = document.getElementById('no-data-row');

        if (visibleCount === 0 && searchTerms.length > 0) {
            if (!noMatchRow) {
                noMatchRow = document.createElement('tr');
                noMatchRow.id = 'no-matching-row';
                noMatchRow.innerHTML = `<td colspan="5" class="px-8 py-10 text-center text-slate-400 font-medium italic">No leaves matching your search...</td>`;
                document.querySelector('#leaveTable tbody').appendChild(noMatchRow);
            }
            noMatchRow.style.display = '';
            if (noDataRow) noDataRow.style.display = 'none';
        } else {
            if (noMatchRow) noMatchRow.style.display = 'none';
            if (noDataRow && visibleCount === 0) noDataRow.style.display = '';
        }
    });
</script>
