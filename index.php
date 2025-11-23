<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role']; 

// Sortare articole
$sort = $_GET['sort'] ?? 'data';
switch($sort){
    case 'like':
        $order_by = "like_cnt DESC";
        break;
    case 'categorie':
        $order_by = "c.denumire ASC";
        break;
    default:
        $order_by = "a.data_publicare DESC";
}

// Gestionare POST (like + comentariu)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Like articol
    if (isset($_POST['like_articol'])) {
        $id_articol = $_POST['id_articol'];
        $check = $conn->prepare("SELECT * FROM likeuri WHERE id_utilizator = :user AND id_articol = :articol AND id_comentariu IS NULL");
        $check->execute([':user'=>$user_id, ':articol'=>$id_articol]);
        if ($check->rowCount() == 0) {
            $stmt = $conn->prepare("INSERT INTO likeuri (id_utilizator, id_articol) VALUES (:user, :articol)");
            $stmt->execute([':user'=>$user_id, ':articol'=>$id_articol]);
        } else {
            // Permitem userului să dea UNLIKE
            $stmt = $conn->prepare("DELETE FROM likeuri WHERE id_utilizator = :user AND id_articol = :articol AND id_comentariu IS NULL");
            $stmt->execute([':user'=>$user_id, ':articol'=>$id_articol]);
        }
    }
    // Adaugare comentariu
    if (isset($_POST['add_comentariu'])) {
        $id_articol = $_POST['id_articol'];
        $text = trim($_POST['text_comentariu']);
        if ($text !== '') {
            $stmt = $conn->prepare("INSERT INTO comentarii (text, id_utilizator, id_articol) VALUES (:text, :user, :articol)");
            $stmt->execute([':text'=>$text, ':user'=>$user_id, ':articol'=>$id_articol]);
        }
    }
    header("Location: index.php?sort=$sort");
    exit;
}

// Preluare articole
$articole_stmt = $conn->prepare("
    SELECT a.*, u.nume AS autor, c.denumire AS categorie,
            (SELECT COUNT(*) FROM likeuri l WHERE l.id_articol = a.id_articol AND l.id_comentariu IS NULL) AS like_cnt
    FROM articole a
    JOIN utilizatori u ON a.id_autor = u.id_utilizator
    JOIN categorii c ON a.id_categorie = c.id_categorie
    ORDER BY $order_by
");
$articole_stmt->execute();
$articole = $articole_stmt->fetchAll(PDO::FETCH_ASSOC);

// Verifică dacă userul a dat like pentru fiecare articol (pentru a afișa iconița plină)
$user_likes = [];
if (!empty($articole)) {
    $articol_ids = array_column($articole, 'id_articol');
    $placeholders = implode(',', array_fill(0, count($articol_ids), '?'));
    $check_likes_stmt = $conn->prepare("SELECT id_articol FROM likeuri WHERE id_utilizator = ? AND id_articol IN ($placeholders) AND id_comentariu IS NULL");
    $check_likes_stmt->execute(array_merge([$user_id], $articol_ids));
    $user_likes = array_column($check_likes_stmt->fetchAll(PDO::FETCH_ASSOC), 'id_articol');
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Revista Online - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            background-color: #f8f8f8;
        }
        a { text-decoration: none; color: #000; }
        a:hover { color: #555; }
        
        /* HEADER  */
        .classic-header { text-align: center; padding-top: 40px; background: #fff; margin-bottom: 40px; }
        .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
        .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
        .nav-strip { border-top: 1px solid #000; border-bottom: 1px solid #000; display: flex; justify-content: center; align-items: stretch; max-width: 100%; }
        .nav-strip a { text-decoration: none; color: #333; font-family: 'Inter', sans-serif; font-size: 0.9rem; text-transform: uppercase; padding: 15px 40px; border-right: 1px solid #eee; transition: background 0.2s; }
        .nav-strip a:first-child { border-left: 1px solid #eee; }
        .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
        .nav-strip a.logout-link { color: #d9534f; }
        .nav-strip a[style*="font-weight: 700"] { font-weight: 700; color: #000; background-color: #f5f5f5; }

        /* LAYOUT */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 3fr 1fr; 
            gap: 40px;
            padding: 0 20px;
        }

        .main-content {
            padding-bottom: 50px;
        }

        .sidebar {
            padding-top: 20px; 
        }
        
        /* Sortare */
        .sort-options {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #000; 
            padding-bottom: 15px;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .sort-options select {
            border: 1px solid #000;
            padding: 5px 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            margin-left: 10px;
            border-radius: 0;
            appearance: none;
            background: #fff;
        }

        /* SIDEBAR STYLING  */
    
    .sidebar {
        padding-top: 20px;
        position: sticky; 
        top: 40px; 
        align-self: flex-start; 
        height: fit-content; 
    }

    .sidebar-section h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 15px;
        border-bottom: 1px solid #000; 
        padding-bottom: 5px;
    }
    .category-list {
        list-style: none;
        padding: 0;
    }
    .category-list li {
        margin-bottom: 8px;
    }
    .category-list li a {
        display: block;
        padding: 5px 0;
        font-size: 0.95rem;
        transition: color 0.2s, padding-left 0.2s;
        text-transform: uppercase; 
        letter-spacing: 0.5px;
    }
    .category-list li a:hover {
        padding-left: 5px;
        color: #000;
        font-weight: 600; 
    }
        
    /* =========================================
        POST CARD (Articol)
        ========================================= */
    .post-card {
        display: flex;
        background: #fff;
        border: 1px solid #000; 
        margin-bottom: 40px;
        box-shadow: none;
        border-radius: 0;
        min-height: 250px; 
    }

    .post-image {
        width: 30%; 
        height: auto;
        object-fit: cover;
        flex-shrink: 0;
        border-right: 1px solid #000;
    }

    .post-info {
        flex-grow: 1;
        padding: 25px 30px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .post-meta {
        font-size: 0.75rem;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }
    .post-category {
        font-weight: 600;
        color: #000;
    }

    .post-info h2 {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0 0 15px 0;
        line-height: 1.15;
    }

    .post-excerpt {
        font-size: 1rem;
        color: #333;
        line-height: 1.5;
        margin-bottom: 20px;
    }

    .read-more, .edit-link { /* Adăugat .edit-link aici */
        display: inline-block;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #000;
        padding-bottom: 5px;
        border-bottom: 2px solid #000;
        transition: color 0.2s;
        margin-right: 20px; /* Spațiu între Citește și Editează */
    }
    .read-more:hover, .edit-link:hover {
        color: #555;
        border-bottom-color: #555;
    }
    .edit-link {
        color: #008080; /* Culoare diferită pentru Editare */
        border-bottom-color: #008080;
    }
        
        /* --- POST ACTIONS (Like/Comentariu) --- */
        .post-actions {
            border-top: 1px dotted #ccc;
            padding-top: 15px;
        }
        
        .like-btn {
            background: none;
            border: none;
            color: #888; 
            cursor: pointer;
            padding: 5px;
            font-size: 1.1rem;
            transition: color 0.2s;
        }
        .like-btn.liked {
            color: #dc3545; /* Roșu pentru like dat */
        }
        .like-btn:hover {
            color: #dc3545;
        }

        .comment-form input[type="text"] {
            border: 1px solid #000; 
            border-radius: 0;
            padding: 8px;
            font-size: 0.9rem;
        }
        .comment-form button {
            background: #000;
            color: #fff;
            border: none;
            padding: 8px 15px;
            font-size: 0.9rem;
            cursor: pointer;
            text-transform: uppercase;
            margin-left: 5px;
            border-radius: 0;
        }
        /* Stil pentru link-ul de Ștergere */
    .delete-link {
        display: inline-block;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #d9534f; /* Roșu pentru Delete */
        padding-bottom: 5px;
        border-bottom: 2px solid #d9534f;
        transition: color 0.2s;
        margin-right: 20px;
        cursor: pointer;
    }
    .delete-link:hover {
        color: #cc0000;
        border-bottom-color: #cc0000;
    }

        /* --- FOOTER --- */
        .footer { 
            margin-top: auto;
            padding: 20px;
            text-align: center;
            background: #fff;
            border-top: 1px solid #000;
            flex-shrink: 0;
            font-size: 0.85rem;
            color: #555;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-container { 
                grid-template-columns: 1fr; 
                gap: 20px;
            }
            .post-card {
                flex-direction: column;
                min-height: auto;
            }
            .post-image {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid #000;
            }
            .post-info h2 { font-size: 1.8rem; }
            .post-info { padding: 20px; }
            .sidebar { padding-top: 0; position: static; } /* Sidebar-ul nu mai este sticky pe mobil */
        }
        @media (max-width: 768px) {
             .header-title { font-size: 3rem; }
             .nav-strip { flex-direction: column; }
             .nav-strip a { border-right: none; border-bottom: 1px solid #eee; padding: 10px; }
        }
    </style>
</head>

<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    <h1 class="header-title">PANORAMA</h1>
    
    <div class="nav-strip">
        <a href="index.php" style="font-weight: 700;">Acasa</a>

        <?php if ($role === 'admin' || $role === 'autor'): ?>
            <a href="articole/articol_add.php">Atelier</a>
        <?php endif; ?>

        <?php if ($role === 'cititor'): ?>
            <a href="cereri/cititor_autor.php">Echipă</a>
        <?php endif; ?>

        <a href="cereri/reguli.php">Reguli</a>
        <a href="auth/contul_meu.php">Contul meu</a>

        <?php if ($role === 'admin'): ?>
            <a href="cereri/cereri-admin.php">Cereri</a>
        <?php endif; ?>
        
        <a href="auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="main-container">
    
    <main class="main-content">
        <form method="GET" class="sort-options">
            Sortează după:
            <select name="sort" onchange="this.form.submit()">
                <option value="data" <?= $sort=='data'?'selected':'' ?>>Data</option>
                <option value="like" <?= $sort=='like'?'selected':'' ?>>Like-uri</option>  
            </select>
        </form>

<?php foreach($articole as $articol): ?>
    <article class="post-card">
        
        <?php
        $image_url = !empty($articol['coperta_url']) 
                     ? 'uploads/' . htmlspecialchars($articol['coperta_url'])
                     : 'https://i.postimg.cc/YScK03DF/17150506-an-unconscious-mind-is-a-great-healer.jpg'; 
        $has_liked = in_array($articol['id_articol'], $user_likes);
        // Preluăm ID-ul autorului articolului din query (a.id_autor)
        $is_author = ($articol['id_autor'] == $user_id); 
        $is_admin = ($role === 'admin');
        $can_edit = $is_author;
        $can_delete= $is_author || $is_admin;
        ?>
        
        <img src="<?= $image_url ?>" 
            alt="Copertă articol: <?= htmlspecialchars($articol['titlu']); ?>" 
            class="post-image">

        <div class="post-info">
            <div>
                <div class="post-meta">
                    <span class="post-category"><?= htmlspecialchars($articol['categorie']); ?></span> | 
                    <span><?= date('M d, Y', strtotime($articol['data_publicare'])); ?> | DE <?= htmlspecialchars(strtoupper($articol['autor'])); ?></span>
                </div>
                
                <h2><a href="articole/articol.php?id=<?= $articol['id_articol']; ?>"><?= htmlspecialchars($articol['titlu']); ?></a></h2> 
                
                <p class="post-excerpt"><?= nl2br(htmlspecialchars(substr($articol['continut'], 0, 180))) . (strlen($articol['continut']) > 180 ? '...' : ''); ?></p>
            </div>

            <div class="post-actions">
                <a href="articole/articol.php?id=<?= $articol['id_articol']; ?>" class="read-more">
                    Citește Articolul Integral →
                </a>
                
                <?php if ($can_edit) : ?>
                    <a href="articole/articol_edit.php?id=<?= $articol['id_articol']; ?>" class="edit-link">
                        <i class="fas fa-edit"></i> Editează
                    </a>
                    <?php endif; ?>
                <?php if ($can_delete) : ?>
                    <a href="articole/articol_delete.php?id=<?= $articol['id_articol']; ?>" 
                       class="delete-link" 
                       onclick="return confirm('Ești sigur că dorești să ștergi acest articol? Acțiunea este ireversibilă.');">
                        <i class="fas fa-trash-alt"></i> Șterge
                    </a>
                <?php endif; ?>
                <div style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between;">
                    </div>
                <div style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <form method="POST" style="display:flex; align-items: center; margin-right: 15px;">
                        <input type="hidden" name="id_articol" value="<?= $articol['id_articol']; ?>">
                        <button type="submit" name="like_articol" class="like-btn <?= $has_liked ? 'liked' : ''; ?>"> 
                        <i class="<?= $has_liked ? 'fas fa-heart' : 'far fa-heart'; ?>"></i> 
                        </button>
                        <span style="font-size: 0.9rem; color: #333; font-weight: 500;"><?= $articol['like_cnt']; ?> Like-uri</span>
                    </form>
                    
                    <form method="POST" style="display: flex; flex-grow: 1;" class="comment-form">
                        <input type="hidden" name="id_articol" value="<?= $articol['id_articol']; ?>">
                        <input type="text" name="text_comentariu" placeholder="Comentează..." style="width: 100%;" required>
                        <button type="submit" name="add_comentariu">Trimite</button>
                    </form>
                </div>
            </div>

        </div> 
    </article> 
<?php endforeach; ?>
    </main>
     <aside class="sidebar">
        <section class="sidebar-section">
        <h3>Categorii</h3>
        <ul class="category-list">
            <li><a href="#tehnologie">Tehnologie</a></li>
            <li><a href="#cultura">Cultură</a></li>
            <li><a href="#sanatate">Sănătate</a></li>
            <li><a href="#sport">Sport</a></li>
            <li><a href="#politica">Politică</a></li>
            <li><a href="#calatorii">Călătorii</a></li>
            <li><a href="#gastronomie">Gastronomie</a></li>
            <li><a href="#educatie">Educație</a></li>
            <li><a href="#artesimuzica">Arte și Muzică</a></li>
            <li><a href="#business">Business</a></li>
        </ul>
        </section>
    </aside>
</div> 

<footer class="footer">
    <p>&copy; 2025 PANORAMA Revistă. Toate drepturile rezervate.</p>
    <div class="social-links">
        <a href="#">Facebook</a> | <a href="#">Instagram</a>
    </div>
</footer>

</body>
</html>
