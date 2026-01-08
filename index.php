<?php
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
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
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
<?php
// ... (codul de Login și POST rămâne mai sus) ...

// =========================================================
// LOGICA DE SORTARE ȘI FILTRARE ARTICOLE
// =========================================================

// 1. Preluăm parametrii din URL
$sort = $_GET['sort'] ?? 'data';            // ex: index.php?sort=like
$categorie_selectata = $_GET['categ'] ?? null; // ex: index.php?categ=Sport
$sql_params = [];

// 2. Stabilim regula de sortare (ORDER BY)
switch($sort){
    case 'like':
        // Sortăm după coloana calculată 'like_cnt'
        $order_by = "like_cnt DESC";
        break;
    case 'categorie':
        // Sortăm alfabetic după numele categoriei
        $order_by = "c.denumire ASC";
        break;
    default:
        // Implicit: cele mai noi primele
        $order_by = "a.data_publicare DESC";
}

// 3. Construim interogarea SQL (SELECT + JOIN)
// Observație: Calculăm like-urile direct aici (subquery) pentru a putea sorta după ele
$sql = "SELECT a.*, u.nume AS autor, c.denumire AS categorie,
               (SELECT COUNT(*) FROM likeuri l WHERE l.id_articol = a.id_articol AND l.id_comentariu IS NULL) AS like_cnt
        FROM articole a
        JOIN utilizatori u ON a.id_autor = u.id_utilizator
        JOIN categorii c ON a.id_categorie = c.id_categorie";

// 4. Adăugăm Filtrarea după Categorie (WHERE)
// Doar dacă utilizatorul a dat click pe o categorie în Sidebar
if ($categorie_selectata) {
    $sql .= " WHERE c.denumire = :categ";
    $sql_params[':categ'] = $categorie_selectata;
}

// 5. Adăugăm Sortarea (ORDER BY)
$sql .= " ORDER BY $order_by";

// 6. Executăm interogarea finală
$articole_stmt = $conn->prepare($sql);
$articole_stmt->execute($sql_params);
$articole = $articole_stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Verificăm Like-urile utilizatorului curent (pentru inima roșie)
$user_likes = [];
if (!empty($articole)) {
    $articol_ids = array_column($articole, 'id_articol');
    $placeholders = implode(',', array_fill(0, count($articol_ids), '?'));
    
    // Căutăm like-urile doar pentru articolele pe care le afișăm
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
        .report-link {
        display: inline-block;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #3fde45ff; /* Roșu pentru Delete */
        padding-bottom: 5px;
        border-bottom: 2px solid #3fde45ff;
        transition: color 0.2s;
        margin-right: 20px;
        cursor: pointer;
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
            <a href="cereri/cereri-admin.php">Cereri & Rapoarte</a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../lista_utilizatori.php">Lista Utilizatori</a>
        <?php endif; ?>

        <?php if ($role === 'cititor' || $role === 'autor'): ?>
            <a href="../contact.php">Contact</a>
        <?php endif; ?>
        
        <a href="auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="main-container">
    
    <main class="main-content">
        <form method="GET" class="sort-options">
            Sortează după:
            <select name="sort" onchange="this.form.submit()">
                <option value="data" <?= $sort=='data'?'selected':'' ?>>DATA</option>
                <option value="like" <?= $sort=='like'?'selected':'' ?>>LIKE-uri</option>  
            </select>
        </form>
<style>
    /* Stiluri pentru Widget-ul RSS */
    .rss-container {
        background: #fff;
        border: 2px solid #000;
        box-shadow: 5px 5px 0px #000; /* Umbra specifică site-ului tău */
        font-family: 'Inter', sans-serif;
        max-width: 100%; /* Se adaptează la containerul părinte */
        overflow: hidden; /* Ca să nu iasă conținutul */
    }

    .rss-header {
        background-color: #000;
        color: #fff;
        padding: 15px;
        margin: 0;
        font-family: 'Playfair Display', serif;
        font-size: 1.2rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .rss-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .rss-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s ease;
    }

    .rss-item:last-child {
        border-bottom: none;
    }

    .rss-item:hover {
        background-color: #f9f9f9;
    }

    .rss-link {
        text-decoration: none;
        color: #1A1D1F;
        font-weight: 600;
        font-size: 1rem;
        line-height: 1.4;
        display: block;
        margin-bottom: 5px;
        transition: color 0.2s;
    }

    .rss-link:hover {
        color: #d9534f; /* Roșu accent la hover */
        text-decoration: underline;
    }

    .rss-date {
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .rss-error {
        padding: 15px;
        color: #d9534f;
        font-style: italic;
    }
</style>

<div class="rss-container" style="margin-bottom: 40px;">
    <h3 class="rss-header">
         Știri Externe (Digi24)
    </h3>
    
    <ul class="rss-list">
        <?php
        $rss_url = "https://www.digi24.ro/rss"; 
        
        // Folosim @ pentru a ascunde erorile PHP native dacă site-ul e picat
        $rss = @simplexml_load_file($rss_url);

        if($rss) {
            $limit = 3;
            $count = 0;
            foreach ($rss->channel->item as $item) {
                if ($count >= $limit) break;
                
                // Formatare dată
                $date = date('d.m.Y | H:i', strtotime($item->pubDate));
                
                echo "<li class='rss-item'>";
                echo "<a href='{$item->link}' target='_blank' class='rss-link'>{$item->title}</a>";
                echo "<div class='rss-date'><i class='far fa-clock'></i> {$date}</div>";
                echo "</li>";
                
                $count++;
            }
        } else {
            echo "<li class='rss-error'>Nu s-au putut încărca știrile momentan.</li>";
        }
        ?>
    </ul>
</div>
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
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $articol['id_autor'] && $_SESSION['user_role'] !== 'admin'): ?>
    <div style="margin: 20px 0;">
        <button onclick="deschideRaportare('articol', <?= $articol['id_articol']; ?>)" class="btn-raporteaza-mic">
            <i class="fa-solid fa-flag"></i> Raportează Articolul
        </button>
    </div>
<?php endif; ?>

<div id="modalRaportare" class="modal-panorama" style="display:none;">
    <span class="close-btn" onclick="inchideRaportare()">&times;</span>
    
    <h2 class="modal-title">RAPORTEAZĂ CONȚINUT</h2>
    <div class="modal-subtitle">Selectează motivul raportării:</div>

    <form action="../raporteaza.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="tip_continut" id="raport_tip" value="">
        <input type="hidden" name="id_obiect" id="raport_id" value="">
        <input type="hidden" name="return_url" value="<?= $_SERVER['REQUEST_URI']; ?>">

        <div class="radio-group">
            <label class="radio-label">
                <input type="radio" name="motiv" value="Spam sau Reclamă" required>
                <span class="checkmark"></span> Spam sau Reclamă
            </label>
            <label class="radio-label">
                <input type="radio" name="motiv" value="Limbaj vulgar / Jigniri">
                <span class="checkmark"></span> Limbaj vulgar / Jigniri
            </label>
            <label class="radio-label">
                <input type="radio" name="motiv" value="Informații false">
                <span class="checkmark"></span> Informații false
            </label>
            <label class="radio-label">
                <input type="radio" name="motiv" value="Instigare la ură">
                <span class="checkmark"></span> Instigare la ură
            </label>
        </div>

        <button type="submit" class="submit-btn">TRIMITE RAPORTUL</button>
    </form>
</div>

<div id="overlayRaport" class="modal-overlay" onclick="inchideRaportare()" style="display:none;"></div>
<style>
    /* === STILURI BUTON RAPORTARE === */
.btn-raporteaza-mic {
    background-color: #fff;
    color: #000;                
    border: 2px solid #000;     /* Chenar negru gros */
    padding: 8px 16px;
    font-family: 'Roboto', sans-serif;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 4px 4px 0px #000; /* Umbră solidă (Hard Shadow) */
    transition: all 0.2s ease;
}

.btn-raporteaza-mic:hover {
    background-color: #000;
    color: #fff;
    box-shadow: 2px 2px 0px #000; /* Efect de apăsare */
    transform: translate(2px, 2px);
}

/* === STILURI MODAL PANORAMA === */
.modal-overlay {
    position: fixed; 
    top: 0; left: 0; 
    width: 100%; height: 100%;
    background-color: rgba(255, 255, 255, 0.8); /* Albicios transparent */
    backdrop-filter: grayscale(100%);
    z-index: 9998;
}

.modal-panorama {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background-color: #fff;
    border: 2px solid #000;         /* Chenar negru */
    box-shadow: 12px 12px 0px #000; /* Umbră masivă */
    width: 90%;
    max-width: 450px;
    padding: 40px 30px;
    z-index: 9999;
    box-sizing: border-box;
    text-align: center;
}

.close-btn {
    position: absolute;
    top: 10px; right: 15px;
    font-size: 32px;
    font-weight: bold;
    color: #000;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #555; }

.modal-title {
    font-family: 'Playfair Display', serif; /* Font stil ziar */
    font-size: 26px;
    text-transform: uppercase;
    margin: 0 0 10px 0;
    color: #000;
    letter-spacing: 1px;
}

.modal-subtitle {
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
    color: #555;
    margin-bottom: 25px;
    font-weight: 500;
}

/* Radio Buttons Customizate (Pătrate) */
.radio-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 30px;
    text-align: left;
}

.radio-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    position: relative;
    padding-left: 35px;
    color: #000;
}

.radio-label input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}

/* Pătratul Checkbox */
.checkmark {
    position: absolute;
    top: 0; left: 0;
    height: 20px; width: 20px;
    background-color: #fff;
    border: 2px solid #000;
    border-radius: 0; /* Colțuri drepte */
}

/* Punctul interior când e selectat */
.radio-label input:checked ~ .checkmark:after {
    content: "";
    position: absolute;
    display: block;
    left: 3px; top: 3px;
    width: 10px; height: 10px;
    background: #000;
}

/* Buton Trimite */
.submit-btn {
    width: 100%;
    background-color: #000;
    color: #fff;
    padding: 14px;
    font-family: 'Roboto', sans-serif;
    font-weight: 700;
    text-transform: uppercase;
    border: none;
    cursor: pointer;
    transition: background 0.3s;
}

.submit-btn:hover {
    background-color: #333;
}
</style>

<script>
    function deschideRaportare(tip, id) {
        document.getElementById('raport_tip').value = tip;
        document.getElementById('raport_id').value = id;
        document.getElementById('modalRaportare').style.display = 'block';
        document.getElementById('overlayRaport').style.display = 'block';
    }

    function inchideRaportare() {
        document.getElementById('modalRaportare').style.display = 'none';
        document.getElementById('overlayRaport').style.display = 'none';
    }
</script>
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
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="id_articol" value="<?= $articol['id_articol']; ?>">
                        <button type="submit" name="like_articol" class="like-btn <?= $has_liked ? 'liked' : ''; ?>"> 
                        <i class="<?= $has_liked ? 'fas fa-heart' : 'far fa-heart'; ?>"></i> 
                        </button>
                        <span style="font-size: 0.9rem; color: #333; font-weight: 500;"><?= $articol['like_cnt']; ?> Like-uri</span>
                    </form>
                    
                    <form method="POST" style="display: flex; flex-grow: 1;" class="comment-form">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
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
            <li><a href="index.php" style="font-weight: bold;">Toate Categoriile</a></li>
            
            <li><a href="index.php?categ=Tehnologie">Tehnologie</a></li>
            <li><a href="index.php?categ=Cultura">Cultura</a></li>
            <li><a href="index.php?categ=Sanatate">Sanatate</a></li>
            <li><a href="index.php?categ=Sport">Sport</a></li>
            <li><a href="index.php?categ=Politica">Politica</a></li>
            <li><a href="index.php?categ=Calatorii">Calatorii</a></li>
            <li><a href="index.php?categ=Gastronomie">Gastronomie</a></li>
            <li><a href="index.php?categ=Educatie">Educatie</a></li>
            <li><a href="index.php?categ=Arte si Muzica">Arte și Muzica</a></li>
            <li><a href="index.php?categ=Business">Business</a></li>
            <li><a href="index.php?categ=Noutati">Noutati</a></li>
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