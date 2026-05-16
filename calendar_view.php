<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    die;
}
include 'includes/dbconnection.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 
?>

<div class="flex flex-col flex-1 min-w-0" id="main-content">
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Leave Calendar</h1>
            <p class="text-xs text-slate-400 font-medium">Visual overview of approved leave schedules</p>
        </div>
        <span class="text-xs font-semibold text-slate-400"><?php echo date('l, d M Y'); ?></span>
    </header>

    <main class="flex-1 p-6 lg:p-8 bg-slate-100">
        <div class="max-w-full">
            <?php include 'leave_calendar.php'; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // Ensure the calendar is visible by default on this dedicated page
    document.addEventListener('DOMContentLoaded', function() {
        const calendarContainer = document.getElementById('calendarContainer');
        const toggleBtn = document.getElementById('toggleCalendarBtn');
        const toggleText = document.getElementById('toggleCalendarText');
        
        if (calendarContainer && calendarContainer.classList.contains('hidden')) {
            calendarContainer.classList.remove('hidden');
            if (toggleText) toggleText.textContent = 'Close Calendar';
            if (toggleBtn) {
                toggleBtn.classList.replace('bg-blue-50', 'bg-slate-100');
                toggleBtn.classList.replace('text-blue-600', 'text-slate-600');
                toggleBtn.classList.replace('border-blue-100', 'border-slate-200');
                toggleBtn.classList.replace('hover:bg-blue-600', 'hover:bg-slate-200');
                toggleBtn.classList.remove('hover:text-white');
            }
        }
    });
</script>
