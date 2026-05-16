<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeaveEase | Modern Leave Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .gradient-bg { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float {
            0%   { transform: translateY(0px); }
            50%  { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }

        /* Sidebar scrollbar styling */
        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-track { background: transparent; }
        aside::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        /* Admin layout: sidebar sticks while page scrolls normally */
        body.admin-layout { overflow-x: hidden; }
        body.admin-layout > aside {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
        }
    </style>
</head>
<?php 
// Determine layout type: Admin/HR get flex-row with sidebar, Employees get standard flex-col
$is_admin_layout = isset($_SESSION['admin_id']);
?>
<body class="bg-slate-100 text-slate-900 min-h-screen flex <?php echo $is_admin_layout ? 'flex-row admin-layout' : 'flex-col'; ?>">
