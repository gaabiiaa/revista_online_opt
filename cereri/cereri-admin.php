<?php
session_start();
require '../includes/db.php';
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['user_role'] ?? 'cititor'; 
$user_name = $_SESSION['user_name'] ?? 'Admin';

// Verifică rolul (doar Admin are acces)
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Procesare aprobare/respingere
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id_cerere = (int)$_GET['id'];

    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE cereri_autor SET status='accepted' WHERE id_cerere=:id");
        $stmt->execute([':id'=>$id_cerere]);

        // Schimbăm rolul utilizatorului
        $stmt2 = $conn->prepare("UPDATE utilizatori u JOIN cereri_autor c ON u.id_utilizator=c.id_utilizator SET u.rol='autor' WHERE c.id_cerere=:id");
        $stmt2->execute([':id'=>$id_cerere]);
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE cereri_autor SET status='rejected' WHERE id_cerere=:id");
        $stmt->execute([':id'=>$id_cerere]);
    }
    header('Location: cereri-admin.php');
    exit;
}

// Preluare cereri
$cereri = $conn->query("SELECT c.*, u.nume FROM cereri_autor c JOIN utilizatori u ON c.id_utilizator=u.id_utilizator ORDER BY c.data_cerere DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Administrare Cereri | PANORAMA</title>
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
        background-color: #f8f8f8; 
    }
    a { text-decoration: none; color: #000; }
    a:hover { color: #555; }

    /* HEADER   */
    .classic-header { text-align: center; padding-top: 40px; background: #fff; margin-bottom: 40px; }
    .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
    .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
    .nav-strip { border-top: 1px solid #000; border-bottom: 1px solid #000; display: flex; justify-content: center; align-items: stretch; max-width: 100%; }
    .nav-strip a { text-decoration: none; color: #333; font-family: 'Inter', sans-serif; font-size: 0.9rem; text-transform: uppercase; padding: 15px 40px; border-right: 1px solid #eee; transition: background 0.2s; }
    .nav-strip a:first-child { border-left: 1px solid #eee; }
    .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
    .nav-strip a.logout-link { color: #d9534f; }
    .nav-strip a[href*="cereri-admin.php"] { font-weight: 700; color: #000; background-color: #f5f5f5; }

    /* MAIN WRAPPER */
    .main-wrapper {
        flex-grow: 1;
        padding: 0 20px 50px 20px;
        position: relative;
        background: url('https://i.postimg.cc/76L7jg1C/pexels-mccutcheon-1191710.jpg') no-repeat center;;
        background-size: 100%; 
        background-attachment: scroll;  
        z-index: 1;
    }
    
    /* Conținutul vizibil */
    .content-area {
        max-width: 1100px;
        margin: 0 auto;
        padding: 40px;
        background: #fff; 
        border: 1px solid #000;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 10;
        border-radius: 0;
    }

    .content-area h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        font-weight: 700;
        color: #000;
        margin-top: 0;
        margin-bottom: 30px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .admin-table th, .admin-table td {
        border: 1px solid #ddd;
        padding: 12px 15px;
        text-align: left;
        font-size: 0.9rem;
    }
    .admin-table th {
        background-color: #000;
        color: #fff;
        font-family: 'Inter', sans-serif;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-color: #000;
    }
    .admin-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .admin-table td {
        vertical-align: top;
        line-height: 1.4;
    }

    /* Coloana Status */
    .status-pending { color: #f39c12; font-weight: 600; }
    .status-accepted { color: #27ae60; font-weight: 600; }
    .status-rejected { color: #e74c3c; font-weight: 600; }
    
    /* Coloana Acțiuni */
    .action-link {
        display: inline-block;
        margin-right: 10px;
        padding: 5px 10px;
        font-weight: 500;
        border-radius: 0;
        text-transform: uppercase;
        font-size: 0.8rem;
    }
    .action-accept { background-color: #2ecc71; color: white; }
    .action-reject { background-color: #e74c3c; color: white; }
    .action-accept:hover { background-color: #27ae60; }
    .action-reject:hover { background-color: #c0392b; }

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
         .nav-strip { flex-direction: column; }
         .nav-strip a { border-right: none; border-bottom: 1px solid #eee; padding: 10px; }
         .content-area { padding: 20px; margin: 20px 10px; }
         .admin-table th, .admin-table td { padding: 8px; font-size: 0.8rem; }
    }
</style>
</head>
<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    <h1 class="header-title">PANORAMA</h1>
    
    <div class="nav-strip">
        <a href="../index.php">Acasă</a>
        <a href="../articole/articol_add.php">Atelier</a>
        <a href="reguli.php">Reguli</a>
        <a href="../auth/contul_meu.php">Contul meu</a>
        <a href="cereri-admin.php">Cereri</a>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="main-wrapper">
    <div class="content-area">
        <h1>Administrare Cereri Autor</h1>
        
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Utilizator</th>
                    <th>Justificare</th>
                    <th>Data cererii</th>
                    <th>Status</th>
                    <th>Acțiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cereri as $cerere): ?>
                <tr>
                    <td><?= htmlspecialchars($cerere['nume']) ?></td>
                    <td><?= nl2br(htmlspecialchars($cerere['text_justificare'])) ?></td>
                    <td><?= date('d M Y', strtotime($cerere['data_cerere'])) ?></td>
                    <td class="status-<?= htmlspecialchars($cerere['status']) ?>">
                        <?= htmlspecialchars(ucfirst($cerere['status'])) ?>
                    </td>
                    <td>
                        <?php if($cerere['status'] === 'pending'): ?>
                            <a href="?action=accept&id=<?= $cerere['id_cerere'] ?>" class="action-link action-accept" onclick="return confirm('Ești sigur că vrei să accepți această cerere?')">
                                <i class="fa fa-check"></i> Acceptă
                            </a>
                            <a href="?action=reject&id=<?= $cerere['id_cerere'] ?>" class="action-link action-reject" onclick="return confirm('Ești sigur că vrei să respingi această cerere?')">
                                <i class="fa fa-times"></i> Respinge
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="footer">
    <p>&copy; 2025 PANORAMA Revistă. Toate drepturile rezervate. Admin: <?= htmlspecialchars($user_name); ?></p>
</footer>

</body>
</html>