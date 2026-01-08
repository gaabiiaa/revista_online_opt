<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ../index.php');
    exit;
}

$id_comentariu = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'cititor';

// Preluăm comentariul pentru a vedea cine l-a scris și la ce articol aparține
$stmt = $conn->prepare("SELECT id_utilizator, id_articol FROM comentarii WHERE id_comentariu = :id");
$stmt->execute([':id' => $id_comentariu]);
$comentariu = $stmt->fetch(PDO::FETCH_ASSOC);

if ($comentariu) {
    // VERIFICARE PERMISIUNI: Autorul SAU Adminul
    if ($user_id == $comentariu['id_utilizator'] || $user_role == 'admin') {
        
        $delStmt = $conn->prepare("DELETE FROM comentarii WHERE id_comentariu = :id");
        $delStmt->execute([':id' => $id_comentariu]);
        
        // Redirect înapoi la articol
        header("Location: ../articole/articol.php?id=" . $comentariu['id_articol']);
        exit;
    } else {
        die("Nu ai permisiunea de a șterge acest comentariu.");
    }
} else {
    die("Comentariul nu există.");
}
?>