<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'super_admin';
$is_hr    = isset($_SESSION['admin_id']) && ($_SESSION['admin_role'] === 'hr_manager' || $_SESSION['admin_role'] === 'hr_officer');
$is_supervisor = isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'supervisor';

$dash_link = 'hr_dashboard.php';
if ($is_admin) $dash_link = 'admin_dashboard.php';
if ($is_supervisor) $dash_link = 'supervisor_dashboard.php';

$history_link = $is_admin ? 'admin_leave_history.php' : 'hr_leave_history.php';
if ($is_supervisor) $history_link = 'sup_leave_history.php';

$role_label = strtoupper(str_replace('_', ' ', $_SESSION['admin_role'] ?? 'HR'));
if ($is_admin) $role_label = 'Super Admin';
$user_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'User';

// Nav items: [label, href, icon_path, show_for_admin, show_for_hr, show_for_supervisor]
$nav_items = [
    ['Dashboard', $dash_link, 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', true, true, true],
    ['HR Home', 'http://localhost/hr/index.php', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', ($is_admin && $current_page == 'admin_dashboard.php'), false, false],
    ['Add Supervisor', 'add_supervisor.php', 'M12 6v6m0 0v6m0-6h6m-6 0H6', ($is_admin && $current_page == 'admin_dashboard.php'), false, false],
    ['Leave Calendar', 'calendar_view.php', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', true, true, true],
    ['Leave Allocation', 'leave_allocation.php', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', true, true, false],
    ['Manage Employees', 'manage_employees.php', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', true, true, true],
    ['Leave History', $history_link, 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', true, true, true],
    ['Manage Roles', 'manage_roles.php', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', true, false, false],
];
?>

<!-- SIDEBAR -->
<aside id="sidebar" class="w-64 min-h-screen bg-blue-900 flex flex-col shrink-0 transition-all duration-300">

    <!-- Brand -->
    <div class="flex items-center gap-3 px-6 py-5 border-b border-blue-800/50">
        <div class="w-9 h-9 bg-blue-500 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <div class="flex flex-col">
            <span class="text-lg font-bold text-white tracking-tight leading-tight">LeaveEase</span>
            <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400/80">ETrack Biz</span>
        </div>
    </div>



    <!-- Navigation -->
    <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
        <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-blue-400/60 px-3 mb-3">Main Menu</p>
        <?php foreach ($nav_items as [$label, $href, $icon, $for_admin, $for_hr, $for_supervisor]):
            if ($is_admin && !$for_admin)
                continue;
            if ($is_hr && !$for_hr)
                continue;
            if ($is_supervisor && !$for_supervisor)
                continue;

            $active = ($current_page === $href || $current_page === basename($href));
            ?>
            <a href="<?php echo $href; ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group
                  <?php echo $active
                      ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30'
                      : 'text-blue-100/70 hover:text-white hover:bg-blue-800'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 shrink-0 <?php echo $active ? 'text-white' : 'text-blue-400/70 group-hover:text-blue-300'; ?>"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $icon; ?>" />
                </svg>
                <span><?php echo $label; ?></span>
                <?php if ($active): ?>
                    <span class="ml-auto w-1.5 h-1.5 rounded-full bg-white"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- User Profile & Logout -->
    <div class="px-3 py-4 border-t border-blue-800/50 mt-auto">
        <div class="flex items-center gap-3 px-3 mb-4">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=3b82f6&color=fff&size=80"
                class="w-10 h-10 rounded-xl object-cover ring-2 ring-blue-500/30" alt="Avatar">
            <div class="overflow-hidden">
                <p class="text-[11px] font-bold text-white uppercase tracking-widest truncate">
                    <?php echo isset($_SESSION['db_role']) ? htmlspecialchars($_SESSION['db_role']) : $role_label; ?>
                </p>
                <?php if (!empty($_SESSION['admin_dept'])): ?>
                    <p class="text-[9px] font-bold text-blue-400 uppercase tracking-[0.15em] truncate mt-0.5">
                        <?php echo htmlspecialchars($_SESSION['admin_dept']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <a href="logout.php"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-blue-100/70 hover:text-white hover:bg-red-600/20 hover:text-red-400 transition-all group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-400/70 group-hover:text-red-400"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Logout
        </a>
    </div>
</aside>