<?php
/**
 * navbar.php — kept for backward compatibility with pages that still include it.
 * The admin/HR dashboards now use includes/sidebar.php instead.
 * Non-dashboard pages (employee-facing) still use this file.
 */
$current_page = basename($_SERVER['PHP_SELF']);
$prefix = (basename(dirname($_SERVER['PHP_SELF'])) == 'Employee_Resignation') ? '../' : '';

$user_id = $_SESSION['user_id'] ?? 0;
$unread_count = 0;
$unread_notifs = [];
if ($user_id) {
    $notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = '$user_id' AND is_read = 0";
    $notif_res = mysqli_query($conn, $notif_query);
    if ($notif_res) {
        $notif_row = mysqli_fetch_assoc($notif_res);
        $unread_count = $notif_row['total'];
    }
    
    $notif_list_query = "SELECT * FROM notifications WHERE user_id = '$user_id' AND is_read = 0 ORDER BY created_at DESC LIMIT 3";
    $notif_list_res = mysqli_query($conn, $notif_list_query);
    if ($notif_list_res) {
        while ($row = mysqli_fetch_assoc($notif_list_res)) {
            $unread_notifs[] = $row;
        }
    }
}
?>
    <nav class="bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <span class="text-xl font-bold text-slate-800 tracking-tight">LeaveEase</span>
        </div>

        <div class="hidden md:flex items-center gap-8">
            <a href="<?php echo $prefix; ?>dashboard.php"
                class="text-sm font-medium <?php echo $current_page == 'dashboard.php' ? 'text-blue-600 font-semibold' : 'text-slate-500 hover:text-blue-600'; ?> transition-colors">Dashboard</a>
            <a href="<?php echo $prefix; ?>apply_leave.php"
                class="text-sm font-medium <?php echo $current_page == 'apply_leave.php' ? 'text-blue-600 font-semibold' : 'text-slate-500 hover:text-blue-600'; ?> transition-colors">Apply
                Leave</a>
            <a href="<?php echo $prefix; ?>leave-history.php"
                class="text-sm font-medium <?php echo $current_page == 'leave-history.php' ? 'text-blue-600 font-semibold' : 'text-slate-500 hover:text-blue-600'; ?> transition-colors">History</a>
        </div>

        <div class="flex items-center gap-4">
            <!-- Notification Section -->
            <div class="relative group">
                <a href="<?php echo $prefix; ?>notifications.php" class="w-9 h-9 bg-slate-50 hover:bg-slate-100 rounded-full flex items-center justify-center text-slate-500 hover:text-blue-600 transition-colors relative">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <!-- Red Dot Badge -->
                    <?php if ($unread_count > 0): ?>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </a>
                <!-- Dropdown -->
                <div class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <div class="p-3 border-b border-slate-50">
                        <h3 class="text-xs font-bold text-slate-800">Notifications</h3>
                    </div>
                    <div class="p-2 text-xs text-slate-600">
                        <?php if (count($unread_notifs) > 0): ?>
                            <?php foreach ($unread_notifs as $n): ?>
                                <div class="p-2 hover:bg-slate-50 rounded-lg transition-colors border-b border-slate-50 last:border-0">
                                    <p class="font-medium text-slate-800"><?php echo htmlspecialchars($n['message']); ?></p>
                                    <p class="text-[10px] text-slate-400 mt-0.5"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-slate-400 p-2">No new notifications</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-slate-800"><?php echo $_SESSION['user_name'] ?? ''; ?></p>
                <p class="text-[10px] text-slate-400 uppercase"><?php echo $_SESSION['position'] ?? ''; ?></p>
            </div>
            <div class="group relative">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=3b82f6&color=fff"
                    class="w-9 h-9 rounded-full cursor-pointer" alt="Avatar">
                <div
                    class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <div class="p-2 space-y-1">
                        <a href="<?php echo $prefix; ?>profile.php"
                            class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            My Profile
                        </a>
                        <a href="<?php echo $prefix; ?>resign.php"
                            class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Resign
                        </a>
                        <hr class="border-slate-100 my-1">
                        <a href="<?php echo $prefix; ?>logout.php"
                            class="flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>