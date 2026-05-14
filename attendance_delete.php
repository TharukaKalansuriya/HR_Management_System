<?php
require_once 'db_config.php';
$id = (int)($_GET['id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: attendance.php?msg=deleted&date=' . urlencode($date));
exit;
