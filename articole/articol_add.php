<?php
require "../includes/db.php";
$role = $_SESSION['user_role']; 

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'autor')) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$mesaj_succes = '';
$mesaj_eroare = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creare_articol'])) {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }

    $titlu = trim($_POST['titlu']);
    $id_categorie = $_POST['id_categorie'];
    $continut = trim($_POST['continut']);
    $nume_coperta = NULL;

    if (empty($titlu) || empty($id_categorie) || empty($continut)) {
        $mesaj_eroare = "Toate câmpurile obligatorii trebuie completate.";
    } elseif (strlen($continut) < 200) {
        $mesaj_eroare = "Conținutul este prea scurt.";
    } else {
        if (isset($_FILES['coperta']) && $_FILES['coperta']['error'] === UPLOAD_ERR_OK) {
            $nume_fisier = $_FILES['coperta']['name'];
            $extensie = strtolower(pathinfo($nume_fisier, PATHINFO_EXTENSION));
            if (in_array($extensie, ['jpg', 'jpeg', 'png', 'gif'])) {
                $nume_coperta = uniqid() . '.' . $extensie;
                move_uploaded_file($_FILES['coperta']['tmp_name'], '../uploads/' . $nume_coperta);
            }
        }
        if (empty($mesaj_eroare)) {
            try {
                $stmt = $conn->prepare("INSERT INTO articole (titlu, continut, id_categorie, id_autor, data_publicare, coperta_url) VALUES (:t, :c, :cat, :a, NOW(), :img)");
                $stmt->execute([':t'=>$titlu, ':c'=>$continut, ':cat'=>$id_categorie, ':a'=>$user_id, ':img'=>$nume_coperta]);
                $mesaj_succes = "Articolul a fost publicat cu succes!";
            } catch (PDOException $e) { $mesaj_eroare = "Eroare DB."; }
        }
    }
}

$stmt = $conn->query("SELECT id_categorie, denumire FROM categorii ORDER BY denumire");
$categorii = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Creare Articol – Atelier | PANORAMA</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../style.css"> 

<style>
    /* --- Header Clasic --- */
    .classic-header { text-align: center; padding-top: 40px; background: #fff; flex-shrink: 0; } /* Nu se micșorează */
    .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
    .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
    .nav-strip { border-top: 1px solid #000; border-bottom: 1px solid #000; display: flex; justify-content: center; align-items: stretch; max-width: 100%; }
    .nav-strip a { text-decoration: none; color: #333; font-family: 'Inter', sans-serif; font-size: 0.9rem; text-transform: uppercase; padding: 15px 40px; border-right: 1px solid #eee; transition: background 0.2s; }
    .nav-strip a:first-child { border-left: 1px solid #eee; }
    .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
    
    /* --- Main Container care se extinde --- */
    .main-container {
        flex: 1; /* Ocupă tot spațiul liber dintre header și footer */
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 100%; /* Permite lățime maximă */
        padding: 0; /* Eliminăm padding-ul containerului pentru ca imaginile să atingă marginile */
    }

    /* --- Layout Grid --- */
    .atelier-grid {
        display: flex;
        width: 100%;
        height: 100%; /* Umple containerul părinte */
        flex: 1; /* Se extinde vertical */
        align-items: stretch; /* FORȚEAZĂ coloanele să aibă aceeași înălțime */
    }

    /* Coloanele Laterale (Imaginile) */
    .side-col {
        flex: 1; /* Ocupă spațiul rămas lateral */
        position: relative; /* Necesar pentru position:absolute al imaginii */
        background: #f4f4f4; /* Culoare de fallback */
        min-width: 0; /* Previne overflow-ul */
    }

    .deco-img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%; /* Umple complet înălțimea coloanei */
        object-fit: cover; /* Taie imaginea frumos fără deformare */
        filter: grayscale(100%);
        transition: filter 0.5s ease;
    }
    .deco-img:hover { filter: grayscale(0%); }

    /* Coloana Centrală (Formularul) */
    .form-col {
        width: 800px; /* Lățime fixă pentru formular */
        flex-shrink: 0; /* Nu se micșorează sub 800px */
        padding: 40px;
        background: #fff;
        border-left: 1px solid #eee;
        border-right: 1px solid #eee;
    }

    /* --- Formular --- */
    .form-heading { font-family: 'Playfair Display', serif; font-size: 2.5rem; text-align: center; margin-bottom: 10px; color: #000; }
    .form-subheading { text-align: center; font-family: 'Inter', sans-serif; color: #666; margin-bottom: 40px; letter-spacing: 1px; text-transform: uppercase; font-size: 0.8rem; }
    
    .article-form label { display: block; margin-top: 20px; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-family: 'Inter', sans-serif; }
    .article-form input[type="text"], select, textarea, input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 0; font-size: 1rem; font-family: 'Inter', sans-serif; box-sizing: border-box; }
    .article-form textarea { resize: vertical; min-height: 300px; line-height: 1.6; }
    
    .article-form button[type="submit"] { background-color: #000; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-top: 30px; width: 100%; transition: background 0.3s; }
    .article-form button[type="submit"]:hover { background-color: #333; }

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

    /* Responsive */
    @media (max-width: 1200px) {
        .side-col { display: none; } /* Ascunde imaginile pe tablete/mobile */
        .form-col { width: 100%; border: none; }
        .header-title { font-size: 3rem; }
        .nav-strip { flex-direction: column; }
        .nav-strip a { border-right: none; border-bottom: 1px solid #eee; }
    }
</style>
</head>

<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    <h1 class="header-title">PANORAMA</h1>
    <div class="nav-strip">
        <a href="../index.php">Acasa</a>
        <?php if ($role === 'admin' || $role === 'autor'): ?><a href="articol_add.php" style="font-weight: 700;">Atelier</a><?php endif; ?>
        <?php if ($role === 'cititor'): ?><a href="../cereri/cititor_autor.php">Echipă</a><?php endif; ?>
        <a href="../cereri/reguli.php">Reguli</a>
        <a href="../auth/contul_meu.php">Contul meu</a>
        <?php if ($role === 'admin'): ?><a href="../cereri/cereri-admin.php">Cereri & Rapoarte</a><?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <a href="../lista_utilizatori.php">Lista Utilizatori</a>
        <?php endif; ?>
        <?php if ($role === 'cititor' || $role === 'autor'): ?>
            <a href="../contact.php">Contact</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?><a href="../auth/logout.php" style="color: #d9534f;">Logout</a><?php endif; ?>
    </div>
</header>

<div class="main-container">
    <div class="atelier-grid">
        
        <div class="side-col">
            
            <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&q=80&w=800&h=1600" class="deco-img" alt="Decor Stanga">
        </div>

        <div class="form-col">
            <h2 class="form-heading">Atelier de Creație</h2>
            <div class="form-subheading">Scrie următorul tău articol remarcabil</div>
            
            <?php if ($mesaj_succes): ?>
                <div style="color: green; text-align:center; margin-bottom: 20px;"><?= $mesaj_succes ?></div>
            <?php endif; ?>
            <?php if ($mesaj_eroare): ?>
                <div style="color: red; text-align:center; margin-bottom: 20px;"><?= $mesaj_eroare ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="article-form" style="box-shadow: none; padding: 0; border: none;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <label>Titlu Articol</label>
                <input type="text" name="titlu" maxlength="300" required placeholder="Introdu un titlu sugestiv...">

                <label>Categorie</label>
                <select name="id_categorie" required>
                    <option value="">Selectează categoria...</option>
                    <?php foreach ($categorii as $cat): ?>
                        <option value="<?= $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['denumire']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Copertă (Imagine)</label>
                <input type="file" name="coperta" accept="image/*">

                <label>Conținut</label>
                <textarea name="continut" minlength="200" maxlength="10000" required placeholder="Scrie povestea ta aici..."></textarea>

                <button type="submit" name="creare_articol">Publică Articolul</button>
            </form>
        </div>

        <div class="side-col">
            
            <img src="https://images.unsplash.com/photo-1497215728101-856f4ea42174?auto=format&fit=crop&q=80&w=800&h=1600" class="deco-img" alt="Decor Dreapta">
        </div>

    </div>
</div>

<footer class="footer">
    <p>&copy; 2025 PANORAMA Revistă. Toate drepturile rezervate.</p>
    <div class="social-links">
        <a href="#">Facebook</a> | <a href="#">Instagram</a>
    </div>
</footer>

</body>
</html>