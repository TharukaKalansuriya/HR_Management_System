<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<!-- Sidebar -->
<aside class="w-64 bg-brand-900 text-white flex flex-col h-full shadow-2xl relative z-20">
    <div class="h-16 flex items-center px-6 border-b border-brand-600/30">
        <i class="fa-solid fa-layer-group text-2xl mr-3 text-brand-100"></i>
        <span class="text-xl font-bold tracking-wider">HRMS <span class="text-brand-100 font-light">by ETrack Biz</span></span>
    </div>

    <div class="flex-1 overflow-y-auto py-4 custom-scrollbar">
        <div class="px-6 mb-2 text-xs font-semibold text-brand-100/50 uppercase tracking-wider">Main</div>
        <nav class="space-y-1">
            <a href="index.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo $current_page == 'index.php' ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-house w-6 text-center mr-3"></i> Dashboard
            </a>
        </nav>

        <div class="px-6 mb-2 mt-6 text-xs font-semibold text-brand-100/50 uppercase tracking-wider">Modules</div>
        <nav class="space-y-1">
            <a href="employees.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo (strpos($current_page, 'employee') !== false) ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-users w-6 text-center mr-3"></i> Employee Management
            </a>
            <a href="attendance.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo (strpos($current_page, 'attendance') !== false) ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-clock w-6 text-center mr-3"></i> Attendance
            </a>
            <a href="#" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium text-gray-300">
                <i class="fa-solid fa-calendar-alt w-6 text-center mr-3"></i> Leave Management
            </a>
            <a href="payroll.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo (strpos($current_page, 'payroll') !== false || strpos($current_page, 'salary') !== false) ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-money-check-dollar w-6 text-center mr-3"></i> Payroll
            </a>
            <a href="recruitment.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo (in_array($current_page, ['recruitment.php', 'job_postings.php', 'applicants.php', 'interviews.php'])) ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-briefcase w-6 text-center mr-3"></i> Recruitment
            </a>
            <a href="documents.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo ($current_page == 'documents.php') ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-folder-open w-6 text-center mr-3"></i> Documents
            </a>
            <a href="reports.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo (strpos($current_page, 'report') !== false) ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-chart-line w-6 text-center mr-3"></i> Reports & Analysis
            </a>
        </nav>

        <div class="px-6 mb-2 mt-6 text-xs font-semibold text-brand-100/50 uppercase tracking-wider">System</div>
        <nav class="space-y-1">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
            <a href="admin_panel.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo $current_page === 'admin_panel.php' ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-user-shield w-6 text-center mr-3"></i> Admin Panel
            </a>
            <?php endif; ?>
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'hr_manager'])): ?>
            <a href="manager_panel.php" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium <?php echo $current_page === 'manager_panel.php' ? 'active' : 'text-gray-300'; ?>">
                <i class="fa-solid fa-user-gear w-6 text-center mr-3"></i> Manager Panel
            </a>
            <?php endif; ?>
            <a href="#" class="sidebar-item flex items-center px-6 py-3 text-sm font-medium text-gray-300">
                <i class="fa-solid fa-gear w-6 text-center mr-3"></i> Settings
            </a>
        </nav>
    </div>

    <div class="p-4 border-t border-brand-600/30">
        <div class="flex items-center">
            <?php
            $displayName = $_SESSION['user_name'] ?? 'User';
            $displayRole = isset($_SESSION['role']) ? getRoleLabel($_SESSION['role']) : '';
            ?>
            <img class="w-10 h-10 rounded-full border-2 border-brand-500"
                 src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=eff6ff&color=1e3a8a"
                 alt="User Avatar">
            <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium truncate"><?= htmlspecialchars($displayName) ?></p>
                <p class="text-xs text-brand-100/60 truncate"><?= htmlspecialchars($displayRole) ?></p>
            </div>
            <a href="logout.php" title="Logout" class="ml-2 text-brand-100/50 hover:text-white transition-colors duration-200 flex-shrink-0">
                <i class="fa-solid fa-right-from-bracket text-sm"></i>
            </a>
        </div>
    </div>
</aside>
