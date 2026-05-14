<?php
/**
 * auth_check.php
 * Include this at the very top of every protected page.
 * Usage: require_once 'auth_check.php';
 *
 * Optionally define $required_page before including to enforce page-level permissions.
 * Example: $required_page = 'payroll.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. Must be logged in ──────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ── 2. Account must be active ─────────────────────────────────────────────────
if (isset($_SESSION['is_active']) && !$_SESSION['is_active']) {
    session_destroy();
    header('Location: login.php?error=disabled');
    exit;
}

// ── 3. Page-level permission check ───────────────────────────────────────────
// Super Admin bypasses all page-level restrictions
if ($_SESSION['role'] !== 'super_admin' && isset($required_page)) {
    require_once 'db_config.php';
    $stmt = $pdo->prepare(
        "SELECT is_allowed FROM page_permissions WHERE user_id = ? AND page_name = ?"
    );
    $stmt->execute([$_SESSION['user_id'], $required_page]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    // If a record exists and is_allowed = 0, deny access
    if ($perm !== false && (int)$perm['is_allowed'] === 0) {
        http_response_code(403);
        include 'header.php';
        echo '
        <div class="flex-1 flex flex-col items-center justify-center bg-gray-50 p-8">
            <div class="text-center">
                <div class="text-8xl font-black text-gray-200 mb-4">403</div>
                <h2 class="text-2xl font-bold text-gray-700 mb-2">Access Denied</h2>
                <p class="text-gray-500 mb-6">You do not have permission to view this page.<br>Please contact your HR Manager or Super Admin.</p>
                <a href="index.php" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-colors">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
        </body></html>';
        exit;
    }
}

// ── 4. Role-restricted pages ──────────────────────────────────────────────────
// admin_panel.php is strictly Super Admin only
if (isset($admin_only) && $admin_only === true && $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php?error=unauthorized');
    exit;
}

// manager_panel.php is HR Manager + Super Admin
if (isset($manager_only) && $manager_only === true
    && !in_array($_SESSION['role'], ['super_admin', 'hr_manager'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Helper: get role display label
function getRoleLabel(string $role): string {
    return match($role) {
        'super_admin' => 'Super Admin',
        'hr_manager'  => 'HR Manager',
        'hr_officer'  => 'HR Officer',
        default       => ucfirst(str_replace('_', ' ', $role)),
    };
}
?>
