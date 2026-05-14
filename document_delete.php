<?php
require_once 'auth_check.php';
$required_page = 'documents.php';
require_once 'db_config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // Fetch file path first
        $stmt = $pdo->prepare("SELECT file_path FROM employee_documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($doc) {
            // Delete physical file
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM employee_documents WHERE id = ?");
            $stmt->execute([$id]);
            
            header("Location: documents.php?msg=" . urlencode("Document deleted successfully."));
            exit;
        }
    } catch (PDOException $e) {
        die("Error deleting document: " . $e->getMessage());
    }
}

header("Location: documents.php");
exit;
