<?php
require '../includes/db.php'; 

// 1. Verificare Autentificare și ID Articol
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ../index.php'); // Redirecționează dacă nu e logat sau lipsește ID-ul
    exit;
}

$articol_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$message = '';

// 2. Încărcare date articol și Verificare Autorizare
$stmt = $conn->prepare("SELECT a.*, u.nume AS autor_nume, c.denumire AS categorie_denumire 
                        FROM articole a
                        JOIN utilizatori u ON a.id_autor = u.id_utilizator
                        JOIN categorii c ON a.id_categorie = c.id_categorie
                        WHERE id_articol = :id");
$stmt->execute([':id' => $articol_id]);
$articol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$articol) {
    // Articolul nu a fost găsit, redirecționăm sau afișăm eroare
    header('Location: ../index.php'); 
    exit("Articolul nu a fost găsit.");
}

// Restricție: Doar autorul articolului sau un admin poate edita
if ($articol['id_autor'] != $user_id && $user_role != 'admin') {
    header('Location: ../index.php');
    exit("Nu ai permisiunea să editezi acest articol.");
}

// 3. Procesare POST (Salvare Modificări)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid!");
    }
    // Titlul NU este editabil, nu îl preluăm din POST
    $continut = trim($_POST['continut']);
    $id_categorie = (int)$_POST['id_categorie'];
    
    // Verificăm dacă s-a încărcat o imagine nouă
    $coperta_url = $articol['coperta_url']; // Păstrăm URL-ul existent implicit
    if (isset($_FILES['coperta']) && $_FILES['coperta']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $file_name = uniqid() . '_' . basename($_FILES['coperta']['name']);
        $target_file = $upload_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validare tip fișier
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['coperta']['tmp_name'], $target_file)) {
                // Șterge vechea imagine dacă există și nu e cea implicită
                if (!empty($articol['coperta_url']) && file_exists($upload_dir . $articol['coperta_url'])) {
                    unlink($upload_dir . $articol['coperta_url']);
                }
                $coperta_url = $file_name;
            } else {
                $message .= "Eroare la încărcarea noii imagini. ";
            }
        } else {
            $message .= "Doar fișiere JPG, JPEG, PNG & GIF sunt permise. ";
        }
    }


    if (empty($continut) || empty($id_categorie)) {
        $message .= "Conținutul și categoria sunt obligatorii.";
    } else {
        $update_stmt = $conn->prepare("
            UPDATE articole 
            SET continut = :continut, 
                id_categorie = :id_categorie, 
                coperta_url = :coperta_url,
                data_modificare = NOW() 
            WHERE id_articol = :id
        ");
        
        $update_success = $update_stmt->execute([
            ':continut' => $continut,
            ':id_categorie' => $id_categorie,
            ':coperta_url' => $coperta_url,
            ':id' => $articol_id
        ]);

        if ($update_success) {
            $message = "Articolul a fost actualizat cu succes!";
            // Reîncărcăm datele proaspete în formular
            $articol['continut'] = $continut;
            $articol['id_categorie'] = $id_categorie;
            $articol['coperta_url'] = $coperta_url;
        } else {
            $message .= "Eroare la actualizarea articolului.";
        }
    }
}

// Preluare categorii pentru dropdown
$categorii_stmt = $conn->query("SELECT * FROM categorii ORDER BY denumire ASC");
$categorii = $categorii_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Editează Articol | PANORAMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- RESET & BASE --- */
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f8f8f8;
        }
        a { text-decoration: none; color: #000; }
        a:hover { color: #555; }
        
        /* =========================================
            HEADER CLASIC (De preluat de la index.php)
            ========================================= */
        .classic-header { text-align: center; padding-top: 40px; background: #fff; margin-bottom: 40px; }
        .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
        .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
        .nav-strip { border-top: 1px solid #000; border-bottom: 1px solid #000; display: flex; justify-content: center; align-items: stretch; max-width: 100%; }
        .nav-strip a { text-decoration: none; color: #333; font-family: 'Inter', sans-serif; font-size: 0.9rem; text-transform: uppercase; padding: 15px 40px; border-right: 1px solid #eee; transition: background 0.2s; }
        .nav-strip a:first-child { border-left: 1px solid #eee; }
        .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
        .nav-strip a.logout-link { color: #d9534f; }
        .nav-strip a[href*="articol_add.php"] { font-weight: 700; color: #000; background-color: #f5f5f5; } /* Accent pe Atelier */

        /* =========================================
            MAIN CONTENT & FORM CONTAINER
            ========================================= */
        .main-content {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            padding: 50px 20px;
        }

        .form-container {
            width: 100%;
            max-width: 800px;
            background: #fff;
            border: 1px solid #000;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05); /* Umbră subtilă */
        }
        
        /* Titlu secțiune */
        .form-container h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        /* Mesaje de stare */
        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid;
            text-align: center;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Input Groups */
        .input-group {
            margin-bottom: 25px;
        }
        .input-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .input-group input[type="text"],
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #000;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            border-radius: 0;
            background-color: #f9f9f9;
        }
        .input-group textarea {
            min-height: 300px; /* Suficient spațiu pentru conținut */
            resize: vertical;
        }
        .input-group input[type="file"] {
            border: 1px solid #ccc;
            padding: 8px;
            background-color: #fff;
        }
        
        /* Titlu needitabil */
        .uneditable-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; /* Puțin mai mic decât H1, dar vizibil */
            font-weight: 700;
            margin-top: 10px;
            margin-bottom: 25px;
            color: #333;
            background-color: #e9e9e9; /* Fundal gri deschis pentru a indica non-editabil */
            padding: 10px;
            border: 1px solid #ccc;
            display: block; /* Ocupă lățimea completă */
        }

        /* Current cover image preview */
        .current-cover {
            margin-top: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .current-cover img {
            max-width: 200px;
            height: auto;
            border: 1px solid #ddd;
            padding: 5px;
        }
        .current-cover p {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        /* Buton submit */
        .btn-submit {
            width: 100%;
            background-color: #000;
            color: #fff;
            padding: 12px;
            border: 1px solid #000;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 30px;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0;
        }
        .btn-submit:hover {
            background-color: #333;
        }

        /* Link înapoi */
        .back-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
        }
        .back-link a {
            font-weight: 600;
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

        /* Responsive */
        @media (max-width: 768px) {
            .classic-header .header-title { font-size: 3rem; }
            .classic-header .nav-strip { flex-direction: column; }
            .classic-header .nav-strip a { border-right: none; border-bottom: 1px solid #eee; padding: 10px; }
            .form-container { padding: 20px; }
            .form-container h1 { font-size: 2rem; }
            .uneditable-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    <h1 class="header-title">PANORAMA</h1>
    
    <div class="nav-strip">
        <a href="../index.php">Acasa</a>

        <?php 
        $user_role_nav = $_SESSION['user_role'] ?? ''; // Folosim o variabilă locală pentru rol în nav
        if ($user_role_nav === 'admin' || $user_role_nav === 'autor'): ?>
            <a href="articol_add.php" style="font-weight: 700;">Atelier</a>
        <?php endif; ?>

        <?php if ($user_role_nav === 'cititor'): ?>
            <a href="../cereri/cititor_autor.php">Echipă</a>
        <?php endif; ?>

        <a href="../cereri/reguli.php">Reguli</a>
        <a href="../auth/contul_meu.php">Contul meu</a>

        <?php if ($user_role_nav === 'admin'): ?>
            <a href="../cereri/cereri-admin.php">Cereri</a>
        <?php endif; ?>
        
        <?php if ($user_role_nav === 'cititor' || $user_role_nav === 'autor'): ?>
            <a href="../contact.php">Contact</a>
        <?php endif; ?>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="main-content">
    <div class="form-container">
        <h1>Editează Articol</h1>

        <?php if (!empty($message)): ?>
            <div class="message-box <?= (strpos($message, 'succes') !== false) ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div class="input-group">
                <label>Titlu Articol (nu poate fi editat):</label>
                <span class="uneditable-title"><?= htmlspecialchars($articol['titlu']); ?></span>
            </div>

            <div class="input-group">
                <label for="id_categorie">Categorie:</label>
                <select id="id_categorie" name="id_categorie" required>
                    <?php foreach ($categorii as $cat): ?>
                        <option value="<?= $cat['id_categorie']; ?>" 
                                <?= ($cat['id_categorie'] == $articol['id_categorie']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['denumire']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label for="continut">Conținut Articol:</label>
                <textarea id="continut" name="continut" required><?= htmlspecialchars($articol['continut']); ?></textarea>
            </div>

            <div class="input-group">
                <label for="coperta">Imagine Copertă (opțional - lasă gol pentru a o păstra pe cea existentă):</label>
                <input type="file" id="coperta" name="coperta" accept="image/jpeg, image/png, image/gif">
                <?php 
                $current_image_path = !empty($articol['coperta_url']) ? '../uploads/' . htmlspecialchars($articol['coperta_url']) : '';
                if (!empty($current_image_path) && file_exists($current_image_path)): 
                ?>
                    <div class="current-cover">
                        <p>Imaginea curentă:</p>
                        <img src="<?= $current_image_path; ?>" alt="Coperta curentă">
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-submit">Salvează Modificările</button>
            
            <div class="back-link">
                <a href="../articole/articol.php?id=<?= $articol_id; ?>">← Înapoi la articol</a>
            </div>
        </form>
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