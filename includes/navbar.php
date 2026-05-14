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
        <?php if (isset($_SESSION['admin_id'])): ?>
            <?php $dash_link = ($_SESSION['admin_role'] == 'super_admin') ? "admin_dashboard.php" : "hr_dashboard.php"; ?>
            <a href="<?php echo $dash_link; ?>" class="text-sm font-semibold text-blue-600">Dashboard</a>
            <a href="leave_allocation.php" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">Leave Allocation</a>
            <a href="manage_roles.php" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">Manage Roles</a>
            <?php $history_link = ($_SESSION['admin_role'] == 'super_admin') ? "admin_leave_history.php" : "hr_leave_history.php"; ?>
            <a href="<?php echo $history_link; ?>" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">Leave History</a>
            <a href="manage_employees.php" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">Manage Employee</a>
        <?php else: ?>
            <a href="dashboard.php" class="text-sm font-semibold text-blue-600">Dashboard</a>
            <a href="apply_leave.php" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">Apply Leave</a>
            <a href="leave-history.php" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">History</a>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-4">
        <button class="relative p-2 text-slate-400 hover:text-blue-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
        </button>
        <div class="flex items-center gap-3 pl-4 border-l border-slate-100">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-slate-800"><?php echo isset($_SESSION['admin_id']) ? $_SESSION['admin_name'] : $_SESSION['user_name']; ?></p>
                <p class="text-[10px] text-slate-400 font-medium uppercase"><?php echo isset($_SESSION['admin_id']) ? $_SESSION['admin_role'] : $_SESSION['position']; ?></p>
            </div>
            <div class="group relative">
                <div class="w-10 h-10 rounded-full bg-blue-50 border-2 border-blue-50 flex items-center justify-center overflow-hidden cursor-pointer">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode(isset($_SESSION['admin_id']) ? $_SESSION['admin_name'] : $_SESSION['user_name']); ?>&background=3b82f6&color=fff" alt="Profile">
                </div>
                <!-- Simple Dropdown -->
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
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
    </div>
</nav>
