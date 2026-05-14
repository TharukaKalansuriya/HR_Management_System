<?php
require_once 'auth_check.php';
require_once 'db_config.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$month  = (int)($_GET['month'] ?? date('m'));
$year   = (int)($_GET['year'] ?? date('Y'));

if (!$id) {
    header("Location: payroll.php?month=$month&year=$year");
    exit;
}

try {
    if ($action === 'mark_paid') {
        $stmt = $pdo->prepare("UPDATE payroll_runs SET status = 'Paid' WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: payroll.php?msg=paid&month=$month&year=$year");
        exit;
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM payroll_runs WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: payroll.php?msg=deleted&month=$month&year=$year");
        exit;
    }
} catch (PDOException $e) {
    // redirect safety
}

header("Location: payroll.php?month=$month&year=$year");
exit;
