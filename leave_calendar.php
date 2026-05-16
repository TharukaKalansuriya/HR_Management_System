<?php
// Fetch all approved leaves for calendar
$cal_query = "SELECT l.start_date, l.end_date, l.leave_type, l.days, e.first_name, e.last_name, e.department 
              FROM leaves l 
              JOIN employees e ON l.user_id = e.id 
              WHERE l.status IN ('HR_Approved', 'Approved')";

if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'supervisor') {
    $dept = mysqli_real_escape_string($conn, $_SESSION['admin_dept'] ?? '');
    $cal_query .= " AND e.department = '$dept'";
}
$cal_result = mysqli_query($conn, $cal_query);
$calendar_events = [];
while ($row = mysqli_fetch_assoc($cal_result)) {
    $start = new DateTime($row['start_date']);
    $end = (new DateTime($row['end_date']))->modify('+1 day');
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);

    foreach ($period as $dt) {
        $date_str = $dt->format('Y-m-d');
        if (!isset($calendar_events[$date_str])) {
            $calendar_events[$date_str] = [];
        }
        $calendar_events[$date_str][] = [
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'dept' => $row['department'],
            'type' => ucwords(str_replace('_', ' ', $row['leave_type'])),
            'days' => ($row['leave_type'] == 'half_day' && $row['days'] == 1) ? '1/2' : $row['days']
        ];
    }
}
?>

<div class="mb-10">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-slate-800">Leave Calendar Overview</h2>
        <button id="toggleCalendarBtn"
            class="inline-flex items-center gap-2 bg-blue-50 border border-blue-100 hover:bg-blue-600 hover:text-white text-blue-600 px-5 py-2.5 rounded-xl font-bold transition-all shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span id="toggleCalendarText">Open Calendar</span>
        </button>
    </div>

    <div id="calendarContainer"
        class="hidden bg-white rounded-3xl shadow-sm border border-slate-100 p-6 md:p-8 overflow-hidden transition-all duration-300">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Leave Calendar</h2>
                <p class="text-sm text-slate-500 mt-1">Click on a highlighted date to see who is on leave.</p>
            </div>
            <div class="flex items-center gap-4 bg-slate-50 p-1.5 rounded-xl border border-slate-100">
                <button id="prevMonth"
                    class="p-2 text-slate-400 hover:text-blue-600 hover:bg-white rounded-lg transition-all shadow-sm"><svg
                        xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                            clip-rule="evenodd" />
                    </svg></button>
                <h3 id="currentMonthYear"
                    class="text-sm font-bold text-slate-700 min-w-[120px] text-center uppercase tracking-wider"></h3>
                <button id="nextMonth"
                    class="p-2 text-slate-400 hover:text-blue-600 hover:bg-white rounded-lg transition-all shadow-sm"><svg
                        xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                            clip-rule="evenodd" />
                    </svg></button>
            </div>
        </div>

        <div
            class="grid grid-cols-7 gap-2 mb-4 text-center text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">
            <div>Sun</div>
            <div>Mon</div>
            <div>Tue</div>
            <div>Wed</div>
            <div>Thu</div>
            <div>Fri</div>
            <div>Sat</div>
        </div>
        <div id="calendarGrid" class="grid grid-cols-7 gap-1.5 sm:gap-2"></div>
    </div>
</div>

<!-- Modal -->
<div id="leaveModal"
    class="fixed inset-0 bg-slate-900/40 hidden z-[60] flex items-center justify-center backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-3xl shadow-2xl w-[90%] max-w-lg p-6 sm:p-8 transform scale-95 transition-transform duration-300"
        id="leaveModalContent">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800" id="modalDateTitle">Leaves</h3>
                <p class="text-sm text-slate-500 mt-1">Employees on leave for this date</p>
            </div>
            <button id="closeModal"
                class="text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 p-2 rounded-xl transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
        <div id="modalLeaveList" class="space-y-3 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
            <!-- Content injected here -->
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('toggleCalendarBtn');
        const toggleText = document.getElementById('toggleCalendarText');
        const calendarContainer = document.getElementById('calendarContainer');

        toggleBtn.addEventListener('click', function () {
            if (calendarContainer.classList.contains('hidden')) {
                calendarContainer.classList.remove('hidden');
                toggleText.textContent = 'Close Calendar';
                toggleBtn.classList.replace('bg-blue-50', 'bg-slate-100');
                toggleBtn.classList.replace('text-blue-600', 'text-slate-600');
                toggleBtn.classList.replace('border-blue-100', 'border-slate-200');
                toggleBtn.classList.replace('hover:bg-blue-600', 'hover:bg-slate-200');
                toggleBtn.classList.remove('hover:text-white');
            } else {
                calendarContainer.classList.add('hidden');
                toggleText.textContent = 'Open Calendar';
                toggleBtn.classList.replace('bg-slate-100', 'bg-blue-50');
                toggleBtn.classList.replace('text-slate-600', 'text-blue-600');
                toggleBtn.classList.replace('border-slate-200', 'border-blue-100');
                toggleBtn.classList.replace('hover:bg-slate-200', 'hover:bg-blue-600');
                toggleBtn.classList.add('hover:text-white');
            }
        });

        const events = <?php echo json_encode($calendar_events); ?>;
        let currentDate = new Date();

        const grid = document.getElementById('calendarGrid');
        const monthYear = document.getElementById('currentMonthYear');
        const modal = document.getElementById('leaveModal');
        const modalContent = document.getElementById('leaveModalContent');
        const modalDateTitle = document.getElementById('modalDateTitle');
        const modalLeaveList = document.getElementById('modalLeaveList');

        function renderCalendar() {
            grid.innerHTML = '';
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            monthYear.textContent = `${monthNames[month]} ${year}`;

            // Blank cells for previous month
            for (let i = 0; i < firstDay.getDay(); i++) {
                grid.innerHTML += `<div class="aspect-square sm:aspect-auto sm:h-20 bg-slate-50/30 rounded-2xl border border-transparent"></div>`;
            }

            // Days of current month
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let day = 1; day <= lastDay.getDate(); day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayEvents = events[dateStr] || [];

                const isToday = today.getTime() === new Date(year, month, day).getTime();
                const hasEvents = dayEvents.length > 0;

                let cellHTML = `
                <div class="aspect-square sm:aspect-auto sm:h-20 rounded-2xl border transition-all ${hasEvents ? 'cursor-pointer hover:-translate-y-0.5' : ''} flex flex-col items-center justify-center relative group
                    ${isToday ? 'bg-blue-600 border-blue-600 shadow-md shadow-blue-200' : 'bg-white border-slate-100 hover:border-blue-300 hover:shadow-sm'}
                    ${hasEvents && !isToday ? 'bg-blue-50/50' : ''}"
                    ${hasEvents ? `onclick="openLeaveModal('${dateStr}')"` : ''}>
                    
                    <span class="text-sm font-bold ${isToday ? 'text-white' : 'text-slate-700'}">${day}</span>
                    
                    ${hasEvents ? `
                        <div class="absolute bottom-2 sm:bottom-3 flex gap-1 justify-center w-full px-2">
                            ${dayEvents.slice(0, 3).map(() => `<span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full ${isToday ? 'bg-white' : 'bg-blue-500'}"></span>`).join('')}
                            ${dayEvents.length > 3 ? `<span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full ${isToday ? 'bg-white/50' : 'bg-blue-300'}"></span>` : ''}
                        </div>
                    ` : ''}
                    
                    ${hasEvents ? `
                        <div class="hidden sm:block absolute -top-10 bg-slate-800 text-white text-xs px-2 py-1 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                            ${dayEvents.length} on leave
                        </div>
                    ` : ''}
                </div>
            `;
                grid.innerHTML += cellHTML;
            }
        }

        window.openLeaveModal = function (dateStr) {
            const dayEvents = events[dateStr] || [];
            if (dayEvents.length === 0) return;

            // Parse date considering timezone issues (fix off-by-one day)
            const parts = dateStr.split('-');
            const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);

            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            modalDateTitle.textContent = dateObj.toLocaleDateString('en-US', options);

            modalLeaveList.innerHTML = dayEvents.map(e => `
            <div class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <div class="w-10 h-10 shrink-0 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold border-2 border-white shadow-sm">
                    ${e.name.split(' ').map(n => n[0]).join('').substring(0, 2)}
                </div>
                <div class="flex-grow min-w-0">
                    <h4 class="font-bold text-slate-800 text-sm truncate">${e.name}</h4>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">${e.dept}</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="inline-block px-2 py-1 bg-white border border-slate-200 rounded-lg text-[10px] font-bold text-slate-600 uppercase tracking-wider shadow-sm">${e.type}</span>
                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 mt-1">${e.days} Days</p>
                </div>
            </div>
        `).join('');

            modal.classList.remove('hidden');
            // Small delay to allow display:block to apply before animating opacity
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
            }, 10);
        }

        document.getElementById('closeModal').addEventListener('click', () => {
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        });

        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.getElementById('closeModal').click();
            }
        });

        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        renderCalendar();
    });
</script>