<?php
require_once 'auth_check.php';
$required_page = 'index.php';
require_once 'db_config.php';
$page_title = "Dashboard Overview";
include 'header.php';
include 'sidebar.php';
?>
<!-- Main Content -->
<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <!-- Main section -->
    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Stat 1 -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-blue-50 text-blue-600 mr-4">
                    <i class="fa-solid fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Employees</p>
                    <p class="text-2xl font-bold text-gray-800">248</p>
                </div>
            </div>
            <!-- Stat 2 -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-orange-50 text-orange-600 mr-4">
                    <i class="fa-solid fa-user-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">On Leave Today</p>
                    <p class="text-2xl font-bold text-gray-800">12</p>
                </div>
            </div>
            <!-- Stat 3 -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 mr-4">
                    <i class="fa-solid fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Open Vacancies</p>
                    <p class="text-2xl font-bold text-gray-800">8</p>
                </div>
            </div>
            <!-- Stat 4 -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center">
                <div class="p-4 rounded-xl bg-purple-50 text-purple-600 mr-4">
                    <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Payroll Run</p>
                    <p class="text-2xl font-bold text-gray-800">5 Days</p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white/50 backdrop-blur-sm">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Leave Requests</h2>
                        <a href="#" class="text-sm text-brand-600 hover:text-brand-800 font-medium transition-colors">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50/50">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Employee</th>
                                    <th class="px-6 py-4 font-medium">Leave Type</th>
                                    <th class="px-6 py-4 font-medium">Duration</th>
                                    <th class="px-6 py-4 font-medium">Status</th>
                                    <th class="px-6 py-4 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <img class="w-8 h-8 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name=Sarah+Smith&background=random" alt="Avatar">
                                            <div>
                                                <p class="font-medium text-gray-800">Sarah Smith</p>
                                                <p class="text-xs text-gray-500">UX Designer</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">Annual Leave</td>
                                    <td class="px-6 py-4 text-gray-600">Oct 12 - Oct 15</td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-emerald-500 hover:text-emerald-700 mr-2 transition-colors"><i class="fa-solid fa-check"></i></button>
                                        <button class="text-red-500 hover:text-red-700 transition-colors"><i class="fa-solid fa-xmark"></i></button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <img class="w-8 h-8 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name=James+Wilson&background=random" alt="Avatar">
                                            <div>
                                                <p class="font-medium text-gray-800">James Wilson</p>
                                                <p class="text-xs text-gray-500">Frontend Dev</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">Sick Leave</td>
                                    <td class="px-6 py-4 text-gray-600">Today</td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Approved</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    </td>
                                </tr>
                                 <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <img class="w-8 h-8 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name=Michael+Brown&background=random" alt="Avatar">
                                            <div>
                                                <p class="font-medium text-gray-800">Michael Brown</p>
                                                <p class="text-xs text-gray-500">HR Manager</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">Casual Leave</td>
                                    <td class="px-6 py-4 text-gray-600">Oct 20</td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-emerald-500 hover:text-emerald-700 mr-2 transition-colors"><i class="fa-solid fa-check"></i></button>
                                        <button class="text-red-500 hover:text-red-700 transition-colors"><i class="fa-solid fa-xmark"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-8">
                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="employees.php" class="flex flex-col items-center justify-center p-4 bg-brand-50 rounded-xl hover:bg-brand-100 transition-colors group">
                            <i class="fa-solid fa-user-plus text-brand-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="text-xs font-medium text-brand-800">Employees</span>
                        </a>
                        <button class="flex flex-col items-center justify-center p-4 bg-emerald-50 rounded-xl hover:bg-emerald-100 transition-colors group">
                            <i class="fa-solid fa-file-invoice-dollar text-emerald-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="text-xs font-medium text-emerald-800">Run Payroll</span>
                        </button>
                        <a href="job_postings.php" class="flex flex-col items-center justify-center p-4 bg-purple-50 rounded-xl hover:bg-purple-100 transition-colors group">
                            <i class="fa-solid fa-bullhorn text-purple-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="text-xs font-medium text-purple-800">Post Job</span>
                        </a>
                        <button class="flex flex-col items-center justify-center p-4 bg-orange-50 rounded-xl hover:bg-orange-100 transition-colors group">
                            <i class="fa-solid fa-chart-pie text-orange-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="text-xs font-medium text-orange-800">Reports</span>
                        </button>
                    </div>
                </div>

                <!-- Upcoming Birthdays / Events -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Upcoming Events</h2>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="bg-red-50 text-red-500 rounded-lg p-2 mr-3 flex-shrink-0">
                                <i class="fa-solid fa-cake-candles"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Alex's Birthday</p>
                                <p class="text-xs text-gray-500">Tomorrow at Office</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-blue-50 text-blue-500 rounded-lg p-2 mr-3 flex-shrink-0">
                                <i class="fa-solid fa-users-viewfinder"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Quarterly Review Meeting</p>
                                <p class="text-xs text-gray-500">Oct 15, 10:00 AM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
