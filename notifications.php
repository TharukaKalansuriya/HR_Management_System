<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    die;
}

include 'includes/dbconnection.php';
include 'includes/header.php';
include 'includes/navbar.php';

// Mark all notifications as read for this user
$user_id = $_SESSION['user_id'];
$update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = '$user_id'";
mysqli_query($conn, $update_query);

// Fetch notifications for this user
$notif_query = "SELECT * FROM notifications WHERE user_id = '$user_id' ORDER BY created_at DESC";
$notif_result = mysqli_query($conn, $notif_query);
?>

<main class="flex-grow p-6 md:p-10 bg-slate-50">
    <div class="max-w-4xl mx-auto">
        <!-- Header Section -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="dashboard.php" class="text-sm font-semibold text-slate-400 hover:text-blue-600 transition-colors flex items-center gap-1 mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="text-3xl font-bold text-slate-800">Notifications</h1>
                <p class="text-slate-500 mt-1">Stay updated with your leave and resignation requests.</p>
            </div>
            <button class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                Mark all as read
            </button>
        </div>

        <!-- General Notifications -->
        <?php if (mysqli_num_rows($notif_result) > 0): ?>
            <div class="mb-8">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Recent Notifications</h2>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="divide-y divide-slate-100">
                        <?php while ($n = mysqli_fetch_assoc($notif_result)): ?>
                            <div class="p-6 hover:bg-slate-50/50 transition-colors flex gap-4 items-start relative">
                                <span class="absolute top-6 right-6 text-xs text-slate-400 font-medium">
                                    <?php echo date('M d, Y', strtotime($n['created_at'])); ?>
                                </span>
                                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Update</h3>
                                    <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($n['message']); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Active Notice Periods -->
        <?php
        $notice_query = "SELECT * FROM notice_periods WHERE employee_id = '$user_id' ORDER BY created_at DESC";
        $notice_result = mysqli_query($conn, $notice_query);
        if (mysqli_num_rows($notice_result) > 0):
        ?>
            <div class="mb-8">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Notice Periods</h2>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="divide-y divide-slate-100">
                        <?php while ($n = mysqli_fetch_assoc($notice_result)): ?>
                            <div class="p-6 hover:bg-slate-50/50 transition-colors flex gap-4 items-start relative">
                                <span class="absolute top-6 right-6 text-xs text-slate-400 font-medium">
                                    <?php echo date('M d, Y', strtotime($n['created_at'])); ?>
                                </span>
                                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Notice</h3>
                                     <?php if ($n['start_date'] && $n['end_date']): 
                                         $end = strtotime($n['end_date']);
                                         $now = strtotime(date('Y-m-d'));
                                         $diff = $end - $now;
                                         $days_left = ceil($diff / (60 * 60 * 24));
                                         if ($days_left < 0) $days_left = 0;
                                     ?>
                                         <p class="text-xs text-slate-500 mt-1">
                                             Valid from <span class="font-semibold text-slate-700"><?php echo date('M d, Y', strtotime($n['start_date'])); ?></span> to <span class="font-semibold text-slate-700"><?php echo date('M d, Y', strtotime($n['end_date'])); ?></span>.
                                             <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600"><?php echo $days_left; ?> days left</span>
                                         </p>
                                     <?php endif; ?>
                                    <?php if (!empty($n['reason'])): ?>
                                        <p class="text-xs text-slate-400 mt-1 italic">Reason: "<?php echo htmlspecialchars($n['reason']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Approved Terminations -->
        <?php
        $term_query = "SELECT * FROM terminations WHERE employee_id = '$user_id' AND status = 'Approved' ORDER BY created_at DESC";
        $term_result = mysqli_query($conn, $term_query);
        if (mysqli_num_rows($term_result) > 0):
        ?>
            <div class="mb-8">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Terminations</h2>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="divide-y divide-slate-100">
                        <?php while ($t = mysqli_fetch_assoc($term_result)): ?>
                            <div class="p-6 hover:bg-slate-50/50 transition-colors flex gap-4 items-start relative">
                                <span class="absolute top-6 right-6 text-xs text-slate-400 font-medium">
                                    <?php echo date('M d, Y', strtotime($t['created_at'])); ?>
                                </span>
                                <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Termination Notice</h3>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Your termination has been approved. 
                                        Type: <span class="font-semibold text-slate-700"><?php echo $t['type']; ?></span>.
                                        Effective Date: <span class="font-semibold text-slate-700"><?php echo date('M d, Y', strtotime($t['termination_date'])); ?></span>.
                                    </p>
                                    <?php if (!empty($t['reason'])): ?>
                                        <p class="text-xs text-slate-400 mt-1 italic">Reason: "<?php echo htmlspecialchars($t['reason']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
