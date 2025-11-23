<?php
session_start();
require '../includes/db.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role']; 
$user_name = $_SESSION['user_name'];
$message = '';

// Preluarea datelor utilizatorului
$stmt = $conn->prepare("SELECT u.*, 
    (SELECT COUNT(*) FROM articole WHERE id_autor = u.id_utilizator) AS nr_articole, 
    (SELECT SUM(likes) FROM (
        SELECT COUNT(*) AS likes FROM likeuri WHERE id_articol IN (SELECT id_articol FROM articole WHERE id_autor = :uid1) AND id_comentariu IS NULL 
        UNION ALL 
        SELECT COUNT(*) AS likes FROM likeuri WHERE id_comentariu IN (SELECT id_comentariu FROM comentarii WHERE id_utilizator = :uid2)
    ) AS l) AS nr_likeuri_totale 
    FROM utilizatori u WHERE u.id_utilizator = :uid3");
$stmt->execute([':uid1' => $user_id, ':uid2' => $user_id, ':uid3' => $user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Preluarea articolelor recente
$articole_stmt = $conn->prepare("SELECT id_articol, titlu, data_publicare FROM articole WHERE id_autor = :uid ORDER BY data_publicare DESC LIMIT 5");
$articole_stmt->execute([':uid' => $user_id]);
$articole_postate = $articole_stmt->fetchAll(PDO::FETCH_ASSOC);

// Preluarea comentariilor recente 
$comentarii_stmt = $conn->prepare("SELECT c.id_comentariu, c.text, c.data, a.titlu AS titlu_articol, a.id_articol FROM comentarii c JOIN articole a ON c.id_articol = a.id_articol WHERE c.id_utilizator = :uid ORDER BY c.data DESC LIMIT 5");
$comentarii_stmt->execute([':uid' => $user_id]);
$comentarii_postate = $comentarii_stmt->fetchAll(PDO::FETCH_ASSOC);


// Logica de actualizare a profilului 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nume = trim($_POST['nume']);
    $descriere = trim($_POST['descriere']);
    $data_nastere = $_POST['data_nastere'] ?: NULL;
    $gen = $_POST['gen'] ?: NULL;
    $poza_url = $user_data['poza_profil'];

    if (isset($_FILES['poza_profil']) && $_FILES['poza_profil']['error'] === 0) {
        $target_dir = "../uploads/profile/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_extension = pathinfo($_FILES['poza_profil']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid() . '.' . $file_extension;
        if (move_uploaded_file($_FILES['poza_profil']['tmp_name'], $target_dir . $new_file_name)) {
            $poza_url = $new_file_name;
        }
    }
    $upd = $conn->prepare("UPDATE utilizatori SET nume=:n, descriere=:d, data_nastere=:dn, gen=:g, poza_profil=:p WHERE id_utilizator=:uid");
    if ($upd->execute([':n'=>$nume, ':d'=>$descriere, ':dn'=>$data_nastere, ':g'=>$gen, ':p'=>$poza_url, ':uid'=>$user_id])) {
        $_SESSION['user_name'] = $nume;
        header("Location: contul_meu.php?status=success");
        exit;
    }
}
if (isset($_GET['status']) && $_GET['status'] === 'success') $message = "Profil actualizat!";
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | <?= htmlspecialchars($user_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            background: url('../ref/flowers-mini.jpg') no-repeat center;
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* HEADER */
        .classic-header {
            text-align: center;
            padding-top: 40px;
            background: #fff;
            margin-bottom: 40px;
        }
        .header-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 10px;
        }
        .header-title {
            font-family: 'Playfair Display', serif;
            font-size: 5rem;
            font-weight: 700;
            color: #000;
            margin: 0 0 30px 0;
            line-height: 1;
        }
        .nav-strip {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            display: flex;
            justify-content: center;
            align-items: stretch;
            max-width: 100%; 
        }
        .nav-strip a {
            text-decoration: none;
            color: #333;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            text-transform: uppercase;
            padding: 15px 40px;
            border-right: 1px solid #eee; 
            transition: background 0.2s, color 0.2s;
        }
        .nav-strip a:first-child { border-left: 1px solid #eee; }
        .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
        .nav-strip a.logout-link { color: #d9534f; }
        .nav-strip a[style*="font-weight: 700"] { font-weight: 700; color: #000; background-color: #f5f5f5; }


        /* DASHBOARD */
        .dashboard-container {
            max-width: 1000px;
            margin: 0 auto 50px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2.5fr 1fr; 
            gap: 40px;
            flex-grow: 1;
        }

        .card {
            background: #ffffffff;
            border: 1px solid #ddd; 
            padding: 24px;
            margin-bottom: 30px;
            border-radius: 0; 
            box-shadow: none; 
        }
        
        /*  PROFILE CARD - */
        .profile-card { 
            padding: 0; 
            overflow: hidden; 
            border: 1px solid #000; 
        }
        .profile-banner { 
            height: 100px; 
            background: #f7f7f7; 
            width: 100%; 
        }
        .profile-content { 
            padding: 30px; 
            position: relative; 
            margin-top: -80px; 
        }
        
        /* Avatarul și Numele  */
        .profile-header-area {
            display: flex;
            align-items: flex-end; 
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .profile-avatar-wrapper { 
            width: 100px; 
            height: 100px; 
            border-radius: 50%; 
            padding: 4px; 
            background: #fff; 
            flex-shrink: 0;
            margin-right: 20px;
            border: 1px solid #000; 
            box-shadow: 0 0 0 3px #fff; 
        }
        .profile-avatar { 
            width: 100%; 
            height: 100%; 
            border-radius: 50%; 
            object-fit: cover; 
        }
        .profile-name-group {
            flex-grow: 1;
            padding-bottom: 5px;
        }

        .profile-name { 
            font-size: 2.2rem; 
            font-weight: 700; 
            margin: 0; 
            font-family: 'Playfair Display', serif; 
            line-height: 1.1;
        }
        .profile-bio { 
            color: #555; 
            margin-top: 5px; 
            font-size: 1rem; 
        }
        
        .profile-tags { 
            margin-top: 15px; 
            display: flex; 
            gap: 15px; 
            padding-top: 15px;
            border-top: 1px dotted #ccc; 
            align-items: center; 
        }

        .pill { 
            background: #fff; 
            color: #000; 
            padding: 5px 12px; 
            border-radius: 0; 
            font-size: 0.8rem; 
            font-weight: 600; 
            border: 1px solid #000; 
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-edit-toggle { 
            background: #fff; 
            border: 1px solid #000; 
            padding: 8px 16px; 
            border-radius: 0; /* Stil editorial */
            font-size: 0.8rem; 
            cursor: pointer; 
            color: #000; 
            text-transform: uppercase;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s;
        }
        .btn-edit-toggle:hover { background: #f0f0f0; }
        
        /* Articole/Comentarii*/
        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #000; 
            padding-bottom: 5px; 
        }
        .section-title { 
            font-size: 1.3rem; 
            font-weight: 700; 
            font-family: 'Playfair Display', serif; 
            color: #000; 
        }
        .list-row { 
            padding: 12px 0; 
            border-bottom: 1px dotted #ccc; 
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .list-info strong a { 
            color: #000; 
            text-decoration: none; 
            font-family: 'Playfair Display', serif; 
            font-size: 1.1rem; 
            transition: color 0.2s;
        }
        .list-info strong a:hover { color: #444; }
        .list-info span { 
            font-size: 0.85rem; 
            color: #888; 
            display: block;
            margin-top: 3px;
        }
        .status-badge { 
            padding: 3px 8px; 
            border-radius: 0; 
            font-size: 0.75rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            background: #eee; 
            color: #333; 
            border: 1px solid #ddd;
        }

        /* formular edit*/
        .edit-form-card { border: 1px solid #000; } 
        .edit-form-card h3 { 
            font-size: 1.8rem; 
            margin-bottom: 20px; 
            font-family: 'Playfair Display', serif;
            border-bottom: 1px solid #000; 
            padding-bottom: 10px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            font-size: 0.8rem; 
            font-weight: 600; 
            color: #333; 
            margin-bottom: 5px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
        }
        .form-control { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 0; 
            font-family: 'Inter', sans-serif; 
            box-sizing: border-box; 
            font-size: 1rem;
        }
        textarea.form-control { resize: vertical; min-height: 80px; }
        select.form-control { appearance: none; }
        .btn-save { 
            width: 100%; 
            background: #000; 
            color: white; 
            border: none; 
            padding: 12px; 
            border-radius: 0; 
            font-weight: 600; 
            cursor: pointer; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
        }
        .btn-save:hover { background: #333; }
        
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
            .header-title { font-size: 3rem; }
            .nav-strip { flex-direction: column; border-bottom: none; }
            .nav-strip a { border-right: none; border-bottom: 1px solid #eee; padding: 10px; }
            .dashboard-container { grid-template-columns: 1fr; }
            .profile-content { margin-top: -40px; }
            .profile-header-area { flex-direction: column; align-items: center; text-align: center; }
            .profile-avatar-wrapper { margin: 0 0 10px 0; }
            .profile-tags { justify-content: center; flex-wrap: wrap; }
            .btn-edit-toggle { margin-left: 0 !important; margin-top: 15px; }
        }
    </style>
</head>

<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    
    <h1 class="header-title">PANORAMA</h1>
    
    <div class="nav-strip">
        <a href="../index.php">Acasă</a>
        
        <?php if ($role === 'admin' || $role === 'autor'): ?>
            <a href="../articole/articol_add.php">Atelier</a>
        <?php endif; ?>
        
        <?php if ($role === 'cititor'): ?>
            <a href="../cereri/cititor_autor.php">Echipă</a>
        <?php endif; ?>
        
        <a href="../cereri/reguli.php">Reguli</a>
        <a href="contul_meu.php" style="font-weight: 700;">Contul Meu</a>
        
        <?php if ($role === 'admin'): ?>
            <a href="../cereri/cereri-admin.php">Cereri</a>
        <?php endif; ?>
        
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="dashboard-container">

    <div class="left-column">
        
        <?php if($message): ?>
            <div class="card" style="background: #f9f9f9; color: #333; padding: 15px; border-left: 5px solid #000; border-radius: 0;">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card profile-card">
            <div class="profile-banner"></div>
            <div class="profile-content">
                
                <div class="profile-header-area">
                    <div class="profile-avatar-wrapper">
                        <?php $poza = !empty($user_data['poza_profil']) ? '../uploads/profile/'.$user_data['poza_profil'] : 'https://i.postimg.cc/rpLhrY1k/484826919-658875073552295-6683543008384252595-n.jpg'; ?>
                        <img src="<?= $poza; ?>" alt="Avatar" class="profile-avatar">
                    </div>
                    
                    <div class="profile-name-group">
                        <h2 class="profile-name"><?= htmlspecialchars($user_data['nume']); ?></h2>
                        <p class="profile-bio" style="margin-top: 0; font-size: 0.9rem; color: #888; font-family: 'Inter', sans-serif;"></p>
                    </div>
                </div>

                <p class="profile-bio">
                    <?= !empty($user_data['descriere']) ? htmlspecialchars($user_data['descriere']) : 'Nicio descriere publică setată încă. Folosiți formularul din dreapta pentru a adăuga o scurtă prezentare.'; ?>
                </p>
                
                <div class="profile-tags">
                    <span class="pill"><i class="fa-solid fa-user-tag"></i> <?= strtoupper($role); ?></span>
                    <span class="pill"><i class="fa-solid fa-heart" style="color:#dc3545;"></i> <?= $user_data['nr_likeuri_totale'] ?? 0 ?> LIKES</span>
                    <span class="pill"><i class="fa-solid fa-file-lines"></i> <?= $user_data['nr_articole'] ?? 0 ?> ARTICOLE</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <span class="section-title">Articole Recente</span>
            </div>
            <?php if($articole_postate): ?>
                <?php foreach($articole_postate as $art): ?>
                <div class="list-row">
                    <div class="list-info">
                        <strong><a href="../articole/articol.php?id=<?= $art['id_articol'] ?>"><?= htmlspecialchars($art['titlu']) ?></a></strong>
                        <span>Publicat pe <?= date('d M Y', strtotime($art['data_publicare'])); ?></span>
                    </div>
                    <span class="status-badge">PUBLICAT</span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #999;">Nu ai postat articole încă.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-header">
                <span class="section-title">Comentarii Recente</span>
            </div>
            <?php if($comentarii_postate): ?>
                <?php foreach($comentarii_postate as $comm): ?>
                <div class="list-row">
                    <div class="list-info">
                        <span>La articolul: <a href="../articole/articol.php?id=<?= $comm['id_articol'] ?>" style="color: #000; text-decoration: none; font-weight: 600;"><?= htmlspecialchars($comm['titlu_articol']) ?></a></span>
                        <span style="color: #333; font-style: italic; margin-top: 5px;">"<?= htmlspecialchars(substr($comm['text'], 0, 60)) ?>..."</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #999;">Nu ai comentarii postate.</p>
            <?php endif; ?>
        </div>

    </div> 

    <div class="right-column">
        <div class="card edit-form-card" id="edit-area">
            <h3>Editează Profilul</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nume Public</label>
                    <input type="text" name="nume" class="form-control" value="<?= htmlspecialchars($user_data['nume']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Descriere</label>
                    <textarea name="descriere" class="form-control" rows="4"><?= htmlspecialchars($user_data['descriere']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Data Naștere</label>
                    <input type="date" name="data_nastere" class="form-control" value="<?= htmlspecialchars($user_data['data_nastere']) ?>">
                </div>
                <div class="form-group">
                    <label>Gen</label>
                    <select name="gen" class="form-control">
                        <option value="">-</option>
                        <option value="Feminin" <?= $user_data['gen']=='Feminin'?'selected':'' ?>>Feminin</option>
                        <option value="Masculin" <?= $user_data['gen']=='Masculin'?'selected':'' ?>>Masculin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Poză Profil</label>
                    <input type="file" name="poza_profil" class="form-control" style="font-family: 'Inter', sans-serif; font-size: 1rem;">
                </div>
                <button type="submit" name="update_profil" class="btn-save">SALVEAZĂ MODIFICĂRILE</button>
            </form>
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