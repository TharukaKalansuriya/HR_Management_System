<?php
/**
 * navbar.php — kept for backward compatibility with pages that still include it.
 * The admin/HR dashboards now use includes/sidebar.php instead.
 * Non-dashboard pages (employee-facing) still use this file.
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Only show top-nav for employee-facing pages (no sidebar)
if (!isset($_SESSION['admin_id'])):
?>
<nav class="bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between sticky top-0 z-50">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <span class="text-xl font-bold text-slate-800 tracking-tight">LeaveEase</span>
    </div>

    <div class="hidden md:flex items-center gap-8">
        <a href="dashboard.php"    class="text-sm font-medium <?php echo $current_page=='dashboard.php'    ? 'text-blue-600 font-semibold' : 'text-slate-500 hover:text-blue-600'; ?> transition-colors">Dashboard</a>
        <a href="apply_leave.php"  class="text-sm font-medium <?php echo $current_page=='apply_leave.php'  ? 'text-blue-600 font-semibold' : 'text-slate-500 hover:text-blue-600'; ?> transition-colors">Apply Leave</a>
        <a href="leave-history.php" class="text-sm font-medium <?php echo $current_page=='leave-history.php' ? 'text-blue-600 font-semibold' : 'text-slate-500 hover:text-blue-600'; ?> transition-colors">History</a>
    </div>

    <div class="flex items-center gap-3">
        <div class="text-right hidden sm:block">
            <p class="text-xs font-bold text-slate-800"><?php echo $_SESSION['user_name'] ?? ''; ?></p>
            <p class="text-[10px] text-slate-400 uppercase"><?php echo $_SESSION['position'] ?? ''; ?></p>
        </div>
        <div class="group relative">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=3b82f6&color=fff"
                 class="w-9 h-9 rounded-full cursor-pointer" alt="Avatar">
            <div class="absolute right-0 mt-2 w-40 bg-white rounded-xl shadow-xl border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <div class="p-2">
                    <a href="logout.php" class="flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
