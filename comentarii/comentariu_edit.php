<?php
require '../includes/db.php';

// Verificare autentificare
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die("ID comentariu lipsă.");
}

$id_comentariu = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Preluăm comentariul pentru a verifica drepturile și a prelua conținutul vechi
$stmt = $conn->prepare("SELECT * FROM comentarii WHERE id_comentariu = :id");
$stmt->execute([':id' => $id_comentariu]);
$comentariu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comentariu) {
    die("Comentariul nu există.");
}

// VERIFICARE PERMISIUNI: Doar autorul poate edita
if ($comentariu['id_utilizator'] != $user_id) {
    die("Nu ai permisiunea de a edita acest comentariu.");
}

// Procesare Formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    $continut_nou = trim($_POST['continut']);

    if (!empty($continut_nou)) {
        $updateStmt = $conn->prepare("UPDATE comentarii SET continut = :continut, editat = 1 WHERE id_comentariu = :id");
        $updateStmt->execute([
            ':continut' => $continut_nou,
            ':id' => $id_comentariu
        ]);

        // Redirect înapoi la articol
        header("Location: ../articole/articol.php?id=" . $comentariu['id_articol']);
        exit;
    } else {
        $error = "Comentariul nu poate fi gol.";
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Editare Comentariu</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <div class="container">
        <h2>Editează Comentariul</h2>
        <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <textarea name="continut" rows="5" cols="50" required><?= htmlspecialchars($comentariu['continut']); ?></textarea>
            <br><br>
            <button type="submit">Salvează Modificările</button>
            <a href="../articole/articol.php?id=<?= $comentariu['id_articol']; ?>">Anulează</a>
        </form>
    </div>
</body>
</html>