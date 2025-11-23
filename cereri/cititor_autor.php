<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'] ?? 'Utilizator';

// Dacă utilizatorul nu e cititor, îl redirecționăm
if ($role !== 'cititor') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

// Procesare submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accept_rules = isset($_POST['accept_rules']);
    $justification = trim($_POST['justification']);

    if ($accept_rules && $justification !== '') {
        // Verifică dacă există deja o cerere pending
        $check_stmt = $conn->prepare("SELECT id_cerere FROM cereri_autor WHERE id_utilizator = :user AND (status = 'pending' OR status = 'accepted')");
        $check_stmt->execute([':user' => $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Ai deja o cerere în așteptare sau acceptată. Te rugăm să aștepți decizia adminului.";
        } else {
            $stmt = $conn->prepare("INSERT INTO cereri_autor (id_utilizator, text_justificare) VALUES (:user, :just)");
            if ($stmt->execute([':user'=>$user_id, ':just'=>$justification])) {
                 $success = "Cererea ta a fost trimisă către admin. Îți mulțumim!";
            } else {
                 $error = "A apărut o eroare la trimiterea cererii.";
            }
        }
    } else {
        $error = "Trebuie să bifezi acceptarea regulilor și să scrii o justificare validă.";
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Alătură-te echipei | PANORAMA</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Inter', sans-serif;
        color: #1A1D1F;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        background: url('../ref/echipa.jpg') no-repeat center;
    }
    a { text-decoration: none; color: #000; }
    a:hover { color: #555; }
    
    /*  HEADER  */
    .classic-header { text-align: center; padding-top: 40px; background: #fff; margin-bottom: 40px; }
    .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
    .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
    .nav-strip { border-top: 1px solid #000; border-bottom: 1px solid #000; display: flex; justify-content: center; align-items: stretch; max-width: 100%; }
    .nav-strip a { text-decoration: none; color: #333; font-family: 'Inter', sans-serif; font-size: 0.9rem; text-transform: uppercase; padding: 15px 40px; border-right: 1px solid #eee; transition: background 0.2s; }
    .nav-strip a:first-child { border-left: 1px solid #eee; }
    .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
    .nav-strip a.logout-link { color: #d9534f; }
    /* Accent pe linkul curent */
    .nav-strip a[href*="cititor_autor.php"] { font-weight: 700; color: #000; background-color: #f5f5f5; }

    /* FORM  */
    .main-content {
        flex-grow: 1;
        display: flex;
        justify-content: center;
        padding: 20px;
    }

    .form-container {
        width: 100%;
        max-width: 650px;
        background: #fff;
        border: 1px solid #000;
        padding: 40px;
        margin-bottom: 50px;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    
    /* Titlu secțiune */
    .form-container h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0;
        margin-bottom: 10px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    
    .intro-text {
        font-size: 1rem;
        color: #555;
        margin-bottom: 30px;
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

    /* Reguli */
    .form-container h3 {
        font-family: 'Inter', sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 30px;
        margin-bottom: 15px;
        color: #000;
    }
    .form-container ul {
        list-style: disc;
        margin-left: 20px;
        margin-bottom: 20px;
        padding-left: 0;
        font-size: 0.95rem;
    }
    .form-container ul li {
        margin-bottom: 8px;
    }

    /* Checkbox & Justificare */
    .form-container label {
        display: block;
        margin-top: 15px;
        margin-bottom: 10px;
        font-size: 0.95rem;
        font-weight: 500;
    }
    .form-container input[type="checkbox"] {
        margin-right: 10px;
        transform: scale(1.1);
    }

    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #000;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
        font-size: 0.95rem;
        resize: vertical;
        border-radius: 0;
    }
    
    /* Buton */
    .form-container button[type="submit"] {
        width: 100%;
        background-color: #000;
        color: #fff;
        padding: 12px;
        border: 1px solid #000;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 25px;
        transition: background-color 0.2s, color 0.2s;
        border-radius: 0;
    }
    .form-container button[type="submit"]:hover {
        background-color: #333;
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

    /* Responsive Header (preluat de la index.php) */
    @media (max-width: 768px) {
        .header-title { font-size: 3rem; }
        .nav-strip { flex-direction: column; }
        .nav-strip a { border-right: none; border-bottom: 1px solid #eee; padding: 10px; }
        .form-container { padding: 20px; }
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
            <a href="../articole/articol_add.php">Atelier</a>
        <?php endif; ?>

        <?php if ($role === 'cititor'): ?>
            <a href="cititor_autor.php">Echipă</a>
        <?php endif; ?>

        <a href="reguli.php">Reguli</a>
        <a href="../auth/contul_meu.php">Contul meu</a>

        <?php if ($role === 'admin'): ?>
            <a href="cereri-admin.php">Cereri</a>
        <?php endif; ?>
        
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="main-content">
    <div class="form-container">
        <h1>Alătură-te Echipei PANORAMA</h1>
        
        <p class="intro-text">
            Bine ai venit, <?= htmlspecialchars($user_name); ?>! Dacă ești pasionat(ă) de scris și dorești să contribui cu articole, trimite-ne o cerere.
        </p>

        <?php if(!empty($success)): ?>
            <div class="message-box success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="message-box error"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <h3>Reguli Esențiale de Autor:</h3>
            <ul>
                <li>Respectă<a href="reguli.php"> standardele de calitate și etică editorială</a>  ale revistei!</li>
                <li>Fii original și evită plagiatul! Citează sursele acolo unde este necesar!</li>
            </ul>
            
            <label>
                <input type="checkbox" name="accept_rules" required> 
                Am citit și sunt de acord cu regulile menționate mai sus.
            </label>

            <label for="justification">De ce îți dorești să devii autor? (Justificare necesară)</label>
            <textarea name="justification" id="justification" rows="5" required placeholder="Explică pe scurt ce tip de conținut dorești să scrii și de ce ești potrivit pentru echipa noastră."></textarea>

            <button type="submit">Trimite cererea</button>
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