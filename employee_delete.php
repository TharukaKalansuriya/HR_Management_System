<?php
require_once 'auth_check.php';
$required_page = 'employees.php';
require_once 'db_config.php';

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: employees.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        die("Database error during deletion: " . $e->getMessage());
    }
} else {
    header("Location: employees.php");
    exit;
}
?>
