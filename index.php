<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Variabila de sortare
$sort = $_GET['sort'] ?? 'data'; // default sortare dupa data

// Construim query-ul cu sortare
switch($sort) {
    case 'like':
        $order_by = "nr_likeuri DESC";
        break;
    case 'categorie':
        $order_by = "c.denumire ASC";
        break;
    default:
        $order_by = "a.data_publicare DESC";
        break;
}

// Preluam articolele cu JOIN la utilizatori si categorii + numar like-uri
$stmt = $conn->prepare("
    SELECT a.*, u.nume AS autor, c.denumire AS categorie,
           (SELECT COUNT(*) FROM likeuri l WHERE l.id_articol = a.id_articol) AS nr_likeuri
    FROM articole a
    JOIN utilizatori u ON a.id_autor = u.id_utilizator
    JOIN categorii c ON a.id_categorie = c.id_categorie
    ORDER BY $order_by
");
$stmt->execute();
$articole = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Revista Online</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Bine ai venit, <?= htmlspecialchars($_SESSION['user_name']); ?>!</h1>
    <p>Rol: <?= htmlspecialchars($role); ?></p>

    <nav>
        <ul>
            <li><a href="auth/logout.php">Logout</a></li>
        </ul>
    </nav>

<?php if(isset($_SESSION['user_id'])): ?>
    <p><a href="articole/articol_add.php" class="button">Adaugă articol nou</a></p>
<?php endif; ?>

    <h2>Articole</h2>

    <form method="GET">
        Sortează după:
        <select name="sort" onchange="this.form.submit()">
            <option value="data" <?= $sort=='data'?'selected':'' ?>>Data publicării</option>
            <option value="like" <?= $sort=='like'?'selected':'' ?>>Număr like-uri</option>
            <option value="categorie" <?= $sort=='categorie'?'selected':'' ?>>Categorie</option>
        </select>
    </form>

    <hr>

    <?php foreach($articole as $articol): ?>
    <div class="article">
        <h3><?= htmlspecialchars($articol['titlu']); ?></h3>
        <p><em>Autor: <?= htmlspecialchars($articol['autor']); ?> | Categoria: <?= htmlspecialchars($articol['categorie']); ?> | Data: <?= $articol['data_publicare']; ?> | Like-uri: <?= $articol['likes_count']; ?></em></p>
        <p><?= nl2br(htmlspecialchars($articol['continut'])); ?></p>

        <!-- Formular like pentru articol -->
        <form method="POST" action="likeuri/like_add.php">
            <input type="hidden" name="id_articol" value="<?= $articol['id_articol']; ?>">
            <button type="submit">Like</button>
        </form>

        <!-- Formular comentariu inline -->
        <form method="POST" action="comentarii/comentariu_add.php">
            <input type="hidden" name="id_articol" value="<?= $articol['id_articol']; ?>">
            <textarea name="text_comentariu" rows="2" placeholder="Scrie un comentariu..." required></textarea>
            <button type="submit">Trimite</button>
        </form>

        <!-- Afișare comentarii -->
        <div class="comments">
            <?php
            $stmt = $conn->prepare("SELECT c.*, u.nume, 
                        (SELECT COUNT(*) FROM likeuri l WHERE l.id_articol = a.id_articol) AS likes_count
                        FROM comentarii c 
                        JOIN utilizatori u ON c.id_utilizator = u.id_utilizator 
                        WHERE c.id_articol = :id 
                        ORDER BY c.data ASC");
            $stmt->execute([':id' => $articol['id_articol']]);
            $comentarii = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php foreach($comentarii as $com): ?>
                <p><strong><?= htmlspecialchars($com['nume']); ?>:</strong> <?= nl2br(htmlspecialchars($com['text'])); ?> <em>(<?= $com['data']; ?>)</em></p>
                
                <!-- Formular like pentru comentariu -->
                <form method="POST" action="likeuri/like_add.php">
                    <input type="hidden" name="id_comentariu" value="<?= $com['id_comentariu']; ?>">
                    <button type="submit">Like</button>
                </form>
            <?php endforeach; ?>
        </div>
        <hr>
    </div>
<?php endforeach; ?>



</body>
</html>
