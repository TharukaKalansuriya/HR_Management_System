<header class="h-16 glass-card border-b border-gray-200 flex items-center justify-between px-8 z-10 shrink-0">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">
            <?php
                if(isset($page_title)) {
                    echo htmlspecialchars($page_title);
                } else {
                    echo "Dashboard Overview";
                }
            ?>
        </h1>
    </div>
    <div class="flex items-center space-x-4">
        <div class="relative">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-500 transition-colors duration-200 focus:outline-none">
                <i class="fa-regular fa-bell"></i>
                <span class="absolute top-1 right-1 transform translate-x-1/4 -translate-y-1/4 bg-red-500 text-white border-2 border-white rounded-full w-4 h-4 flex items-center justify-center text-[10px] font-bold">3</span>
            </button>
        </div>
        <button class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 transition-colors duration-200 focus:outline-none">
            <span class="text-sm font-medium text-gray-600">Today, <?php echo date('M d, Y'); ?></span>
        </button>
        <?php if (isset($_SESSION['role'])): ?>
        <span class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
            <?php echo match($_SESSION['role']) {
                'super_admin' => 'bg-purple-100 text-purple-700',
                'hr_manager'  => 'bg-blue-100 text-blue-700',
                default       => 'bg-gray-100 text-gray-600'
            }; ?>">
            <i class="fa-solid <?php echo match($_SESSION['role']) {
                'super_admin' => 'fa-user-shield',
                'hr_manager'  => 'fa-user-tie',
                default       => 'fa-user'
            }; ?>"></i>
            <?php echo htmlspecialchars(getRoleLabel($_SESSION['role'])); ?>
        </span>
        <?php endif; ?>
    </div>
</header>
