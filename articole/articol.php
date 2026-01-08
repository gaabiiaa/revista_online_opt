<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// --- EDITARE/ACTUALIZARE COMENTARIU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_comentariu'])) {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    $id_com_edit = (int)$_POST['id_comentariu'];
    $text_nou = trim($_POST['text_comentariu']);
    $articol_curent_id = (int)$_GET['id'];

    // Verificăm dacă utilizatorul este autorul comentariului
    $stmtCheck = $conn->prepare("SELECT id_utilizator FROM comentarii WHERE id_comentariu = :id");
    $stmtCheck->execute([':id' => $id_com_edit]);
    $com_data = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($com_data && $com_data['id_utilizator'] == $_SESSION['user_id']) {
        if (!empty($text_nou)) {
            $stmtUpd = $conn->prepare("UPDATE comentarii SET text = :text, editat = 1 WHERE id_comentariu = :id");
            $stmtUpd->execute([
                ':text' => $text_nou,
                ':id' => $id_com_edit
            ]);
        }
    }
    // Reîncărcăm pagina pentru a ieși din modul editare și a vedea schimbarea
    header("Location: articol.php?id=" . $articol_curent_id);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

$id_articol = $_GET['id'] ?? die('Eroare: ID articol lipsa.');
$id_articol = (int)$id_articol;

if ($id_articol <= 0) die('Eroare: ID articol invalid.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_comentariu'])) {
        $text = trim($_POST['text_comentariu']);
        if ($text !== '') {
            $stmt = $conn->prepare("INSERT INTO comentarii (text, id_utilizator, id_articol) 
            VALUES (:text, :user, :articol)");
            $stmt->execute([':text'=>$text, ':user'=>$user_id, ':articol'=>$id_articol]);
        }
    }

    if (isset($_POST['like_articol'])) {
        $check = $conn->prepare("SELECT * FROM likeuri 
            WHERE id_utilizator=:user AND id_articol=:articol AND id_comentariu IS NULL");
        $check->execute([':user'=>$user_id, ':articol'=>$id_articol]);

        if ($check->rowCount() == 0) {
            $stmt = $conn->prepare("INSERT INTO likeuri (id_utilizator, id_articol) 
                VALUES (:user, :articol)");
            $stmt->execute([':user'=>$user_id, ':articol'=>$id_articol]);
        }
    }

    if (isset($_POST['like_comentariu'])) {
        $cid = $_POST['id_comentariu'];

        $check = $conn->prepare("SELECT * FROM likeuri 
            WHERE id_utilizator=:user AND id_comentariu=:cid");
        $check->execute([':user'=>$user_id, ':cid'=>$cid]);

        if ($check->rowCount() == 0) {
            $stmt = $conn->prepare("INSERT INTO likeuri (id_utilizator, id_articol, id_comentariu) 
                VALUES (:user, :articol, :cid)");
            $stmt->execute([':user'=>$user_id, ':articol'=>$id_articol, ':cid'=>$cid]);
        }
    }

    header("Location: articol.php?id=$id_articol");
    exit;
}

$stmt = $conn->prepare("
    SELECT a.*, u.nume AS autor, c.denumire AS categorie,
           (SELECT COUNT(*) FROM likeuri l 
            WHERE l.id_articol = a.id_articol AND l.id_comentariu IS NULL) AS like_cnt
    FROM articole a
    JOIN utilizatori u ON a.id_autor = u.id_utilizator
    JOIN categorii c ON a.id_categorie = c.id_categorie
    WHERE a.id_articol = :id
");
$stmt->execute([':id'=>$id_articol]);
$articol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$articol) die('Articolul nu a fost găsit.');

$comments_stmt = $conn->prepare("
    SELECT c.*, u.nume AS nume_utilizator,
           (SELECT COUNT(*) FROM likeuri l WHERE l.id_comentariu = c.id_comentariu) AS likes_count
    FROM comentarii c
    JOIN utilizatori u ON c.id_utilizator = u.id_utilizator
    WHERE c.id_articol = :id
    ORDER BY c.data ASC
");
$comments_stmt->execute([':id'=>$id_articol]);
$comentarii = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($articol['titlu']); ?> | Revista Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* Aici introduceți BLOCUL CSS actualizat de mai sus */
        /* GENERAL LAYOUT COMPACT */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        .main-container {
            flex: 1;
        }

        /* --- HEADER (Preluat din articole_add.php pentru consistență) --- */
        .classic-header { text-align: center; padding-top: 40px; background: #fff; flex-shrink: 0; }
        .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
        .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
        
        .nav-strip { 
            border-top: 1px solid #000; 
            border-bottom: 1px solid #000; 
            display: flex; 
            justify-content: center; 
            align-items: stretch; 
        }
        .nav-strip a { 
            text-decoration: none; 
            color: #333; 
            font-family: 'Inter', sans-serif; 
            font-size: 0.9rem; 
            text-transform: uppercase; 
            padding: 15px 40px; 
            border-right: 1px solid #eee; 
            transition: background 0.2s; 
        }
        .nav-strip a:first-child { 
            border-left: 1px solid #eee; 
        }
        .nav-strip a:hover { 
            background-color: #f9f9f9; 
            color: #000; 
        }
        .nav-strip a.logout-link {
            color: #d9534f;
        }
        @media (max-width: 992px) {
            .header-title { font-size: 3rem; }
            .nav-strip { flex-direction: column; }
            .nav-strip a { border-right: none; border-bottom: 1px solid #eee; }
            .nav-strip a:first-child { border-left: none; }
        }

        /* --- LAYOUT ARTICOL (CONCENTRAT) --- */
        .main-content {
            max-width: 750px; 
            margin: 0 auto;
            padding: 50px 20px; 
            background: #fff;
        }

        /* --- TITLU ȘI META --- */
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem; 
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .post-meta {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 40px;
        }
        .post-meta strong {
            font-weight: 500;
            color: #000;
        }
        .post-meta .category {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000; 
        }

        /* --- IMAGINE COPERTA (Full-width Style) --- */
        .cover-image-wrapper {
            /* Reglează marginile pentru a se extinde în afara .main-content pe desktop */
            margin: 0 -20px 40px -20px; 
            max-width: none; 
        }
        .article-cover-img {
            width: 100%;
            height: auto;
            max-height: 500px;
            object-fit: cover;
            border-radius: 0; 
        }

        /* --- CONȚINUT --- */
        .article-content {
            line-height: 1.7; 
            font-size: 1.15rem; 
            margin-bottom: 50px;
            font-family: 'Inter', sans-serif;
        }
        .article-content p {
            margin-bottom: 1.5em; 
        }
        .article-content img {
            display: none; 
        }


        /* --- SECȚIUNE COMENTARII --- */
        .comments-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ccc; 
        }

        .comment-form textarea {
            width: 100%;
            resize: vertical;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 0;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }
        .comment-form button {
            background: #000 !important; 
            color: white !important;
            padding: 10px 20px !important;
            border-radius: 0 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .comment-box {
            padding: 20px;
            background: #f9f9f9;
            border-left: 5px solid #000; 
            margin-bottom: 20px;
            border-radius: 0;
        }

        .comment-box strong {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            display: block;
            margin-bottom: 5px;
        }

        .comment-box p {
            font-family: 'Inter', sans-serif;
            margin: 10px 0;
        }

        .comment-meta {
            font-size: 0.75rem;
            color: #999;
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
        .footer .social-links a {
            color: #000;
            text-decoration: none;
            margin: 0 5px;
            font-weight: 600;
        }
        .footer .social-links a:hover {
            text-decoration: underline;
        }

    </style>
</head>

<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    <h1 class="header-title">PANORAMA</h1>
    
    <div class="nav-strip">
        <a href="../index.php">Acasa</a>
        
        <?php if ($role === 'admin' || $role === 'autor'): ?>
            <a href="articol_add.php">Atelier</a>
        <?php endif; ?>

        <?php if ($role === 'cititor'): ?>
            <a href="cereri/cititor_autor.php">Echipă</a>
        <?php endif; ?>
        
        <a href="../cereri/reguli.php">Reguli</a>
        <a href="../auth/contul_meu.php">Contul meu</a>
        
        <?php if ($role === 'admin'): ?>
            <a href="/cereri/cereri-admin.php">Cereri & Rapoarte</a>
        <?php endif; ?>
        
        <?php if ($role === 'admin'): ?>
            <a href="../lista_utilizatori.php">Lista Utilizatori</a>
        <?php endif; ?>
        <?php if ($role === 'cititor' || $role === 'autor'): ?>
            <a href="../contact.php">Contact</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../auth/logout.php" class="logout-link">Logout</a>
        <?php endif; ?>
    </div>
</header>


<div class="main-container">
    <main class="main-content">

        <h1><?= htmlspecialchars($articol['titlu']); ?></h1>

        <p class="post-meta">
            Publicat de <strong><?= htmlspecialchars($articol['autor']); ?></strong>
            în <span class="category"><?= htmlspecialchars($articol['categorie']); ?></span>
            | Data publicare: <?= date('d M Y', strtotime($articol['data_publicare']));?>| Editat: <?= date('d M Y', strtotime($articol['data_modificare'])); ?>
             <form method="POST" style="display:inline;"> 
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <button type="submit" name="like_articol" style="border:none; background:none; cursor:pointer; font-size:0.9rem; color:#dc3545; padding:0;">
                    <i class="fa-solid fa-heart"></i>
                </button>
            </form> Like-uri: <?= $articol['like_cnt']; ?>
        </p>
        
        <?php 
        $image_url = !empty($articol['coperta_url']) 
                        ? '../uploads/' . htmlspecialchars($articol['coperta_url']) 
                        : 'https://i.postimg.cc/YScK03DF/17150506-an-unconscious-mind-is-a-great-healer.jpg'; // Imagine de fallback largă
        ?>
        <div class="cover-image-wrapper">
            <img src="<?= $image_url ?>" alt="Copertă articol" class="article-cover-img">
        </div>

        <div class="article-content">
            <?= nl2br(htmlspecialchars($articol['continut'])); ?>
        </div>

        <section class="comments-section">
            <h2>Comentarii (<?= count($comentarii) ?>)</h2>

            <form method="POST" class="comment-form" style="margin-bottom: 25px;">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <textarea name="text_comentariu" rows="3" placeholder="Alătură-te discuției..." required></textarea>
                <button type="submit" name="add_comentariu">
                    Trimite Comentariul
                </button>
            </form>

           <?php foreach ($comentarii as $com): ?>
    <?php 
        // Verificări permisiuni
        $is_author = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $com['id_utilizator']);
        $is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin');
        
        // Verificăm dacă acest comentariu este cel selectat pentru editare
        $este_in_editare = (isset($_GET['edit_id']) && $_GET['edit_id'] == $com['id_comentariu'] && $is_author);
    ?>

    <div class="comment-box" id="com-<?= $com['id_comentariu']; ?>">
        <strong><?= htmlspecialchars($com['nume_utilizator']); ?></strong>

        <?php if ($este_in_editare): ?>
            <form method="POST" style="margin-top: 10px;">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id_comentariu" value="<?= $com['id_comentariu']; ?>">
                
                <textarea name="text_comentariu" rows="3" style="width: 100%; padding: 5px;"><?= htmlspecialchars($com['text']); ?></textarea>
                
                <div style="margin-top: 5px;">
                    <button type="submit" name="update_comentariu" class="btn-mic">Salvează</button>
                    <a href="articol.php?id=<?= $_GET['id']; ?>#com-<?= $com['id_comentariu']; ?>" class= "btn-mic-red">Anulează</a>
                </div>
            </form>

        <?php else: ?>
            <p>
                <?= nl2br(htmlspecialchars($com['text'])); ?>
                <?php if (!empty($com['editat']) && $com['editat'] == 1): ?>
                    <small style="color: #888; font-style: italic; font-size: 0.8em;"> (editat)</small>
                <?php endif; ?>
            </p>

            <div class="comment-meta">
                <?= date('d M Y, H:i', strtotime($com['data'])); ?> | Like-uri: <?= $com['likes_count']; ?>
                
                <form method="POST" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id_comentariu" value="<?= $com['id_comentariu']; ?>">
                    <button type="submit" name="like_comentariu" style="border:none; background:none; cursor:pointer; color:#dc3545;">
                        <i class="fa-solid fa-heart"></i>
                    </button>
                </form>

                <span class="comment-actions" style="margin-left: 15px; font-size: 0.9em;">
                    <?php if ($is_author): ?>
                        <a href="articol.php?id=<?= $_GET['id']; ?>&edit_id=<?= $com['id_comentariu']; ?>#com-<?= $com['id_comentariu']; ?>" 
                           class="btn-mic">
                            <i class="fa-solid fa-pen"></i> Editează
                        </a>
                    <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $com['id_utilizator'] && $_SESSION['user_role'] !== 'admin'): ?>
    <button onclick="deschideRaportare('comentariu', <?= $com['id_comentariu']; ?>)" 
            class="btn-mic">
        <i class="fa-solid fa-flag"></i> RAPORTEAZĂ
    </button>
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
    /* Importăm fonturile */
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;700&display=swap');

    /* === STILURILE TALE ORIGINALE (NESCHIMBATE) === */
    .btn-mic {
        background-color: #fff;
        color: #000;                /* Text negru */
        border: 2px solid #000;     /* Chenar negru */
        padding: 6px 12px;
        font-family: 'Roboto', sans-serif;
        font-weight: 700;           /* Text îngroșat */
        font-size: 11px;            /* Dimensiune discretă */
        text-transform: uppercase;  /* MAJUSCULE */
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;                   /* Spațiu între iconiță și text */
        box-shadow: 3px 3px 0px #000; /* Umbra solidă mică */
        transition: all 0.2s ease;
    }

    .btn-mic:hover {
        background-color: #000;
        color: #fff;
        box-shadow: 1px 1px 0px #000; 
        transform: translate(2px, 2px); 
    }

    .btn-mic-red {
        background-color: #fff;
        color: #ff0000ff;           /* Text roșu */
        padding: 6px 12px;
        font-family: 'Roboto', sans-serif;
        font-weight: 700; 
        font-size: 11px; 
        text-transform: uppercase; 
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px; 
        transition: all 0.2s ease;
    }

    /* === MODAL & ELEMENTE AUXILIARE === */
    /* === STIL MODAL PANORAMA (Exact ca la articole) === */
.modal-overlay {
    position: fixed; top: 0; left: 0; 
    width: 100%; height: 100%;
    background-color: rgba(255, 255, 255, 0.9); /* Albicios, semi-transparent */
    backdrop-filter: grayscale(100%);
    z-index: 9998;
}

.modal-panorama {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background-color: #fff;
    border: 3px solid #000;         /* Chenar negru gros */
    box-shadow: 15px 15px 0px #000; /* Umbră solidă decalat */
    width: 90%;
    max-width: 420px;
    padding: 40px;
    z-index: 9999;
    text-align: center;
    box-sizing: border-box;
}

.modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 700;
    text-transform: uppercase;
    margin: 0 0 15px 0;
    color: #000;
    letter-spacing: 1px;
    line-height: 1.1;
}

.modal-subtitle {
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
    color: #444;
    margin-bottom: 30px;
}

/* Butonul X de închidere */
.close-btn {
    position: absolute;
    top: 15px; right: 20px;
    font-size: 30px;
    font-weight: bold;
    color: #000;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #555; }

/* === RADIO BUTTONS PĂTRATE (Stil Checkbox) === */
.radio-group {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 30px;
    text-align: left;
    padding-left: 10px;
}

.radio-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-family: 'Roboto', sans-serif;
    font-size: 15px;
    font-weight: 500;
    position: relative;
    padding-left: 35px; /* Spațiu pentru pătrățel */
    color: #000;
    user-select: none;
}

/* Ascundem input-ul standard */
.radio-label input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0; width: 0;
}

/* Creăm pătrățelul custom */
.checkmark {
    position: absolute;
    top: 0; left: 0;
    height: 20px; width: 20px;
    background-color: #fff;
    border: 2px solid #000; /* Contur negru */
    border-radius: 0;       /* Colțuri drepte */
}

/* Când e selectat - apare interiorul negru */
.radio-label input:checked ~ .checkmark {
    background-color: #000; /* Se umple cu negru la selectare */
}

/* Butonul de trimitere */
.submit-btn {
    width: 100%;
    background-color: #000;
    color: #fff;
    padding: 16px;
    font-family: 'Roboto', sans-serif;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
    letter-spacing: 1px;
}

.submit-btn:hover {
    background-color: #333;
}
</style>

<script>
    function deschideRaportare(tip, id) {
        // Populăm câmpurile ascunse
        document.getElementById('raport_tip').value = tip;
        document.getElementById('raport_id').value = id;
        
        // Afișăm modalul
        document.getElementById('modalRaportare').style.display = 'block';
        document.getElementById('overlayRaport').style.display = 'block';
    }

    function inchideRaportare() {
        document.getElementById('modalRaportare').style.display = 'none';
        document.getElementById('overlayRaport').style.display = 'none';
    }
    
    // Închide la click pe fundal
    window.onclick = function(event) {
        if (event.target == document.getElementById('overlayRaport')) {
            inchideRaportare();
        }
    }
</script>
                    <?php if ($is_author || $is_admin): ?>
    <a href="../comentarii/comentariu_delete.php?id=<?= $com['id_comentariu']; ?>" 
       class="btn-mic"
       onclick="return confirm('Sigur vrei să ștergi acest comentariu?');">
        <i class="fa-solid fa-trash"></i> ȘTERGE
    </a>
<?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

        </section>

    </main>
</div>

<footer class="footer">
    <p>&copy; 2025 PANORAMA Revistă. Toate drepturile rezervate.</p>
    <div class="social-links">
        <a href="#">Facebook</a> | <a href="#">Instagram</a>
    </div>
</footer>

</body>
</html>
