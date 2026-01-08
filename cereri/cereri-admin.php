<?php
require '../includes/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['user_role'] ?? 'cititor'; 
$user_name = $_SESSION['user_name'] ?? 'Admin';

// Verifică rolul (doar Admin are acces)
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// ==========================================
// 1. PROCESARE ACȚIUNI (CERERI & RAPOARTE)
// ==========================================

if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id_target = (int)$_GET['id'];
    $redirect_tab = 'cereri'; 

    // --- LOGICA PENTRU CERERI AUTOR ---
    if ($action === 'accept_cerere') {
        $stmt = $conn->prepare("UPDATE cereri_autor SET status='accepted' WHERE id_cerere=:id");
        $stmt->execute([':id'=>$id_target]);
        
        $stmt2 = $conn->prepare("UPDATE utilizatori u JOIN cereri_autor c ON u.id_utilizator=c.id_utilizator SET u.rol='autor' WHERE c.id_cerere=:id");
        $stmt2->execute([':id'=>$id_target]);
        $redirect_tab = 'cereri';

    } elseif ($action === 'reject_cerere') {
        $stmt = $conn->prepare("UPDATE cereri_autor SET status='rejected' WHERE id_cerere=:id");
        $stmt->execute([':id'=>$id_target]);
        $redirect_tab = 'cereri';
    
    // --- LOGICA PENTRU RAPOARTE (ADAPTATĂ LA TABELUL TĂU) ---
    } elseif ($action === 'dismiss_report') {
        // Ștergem doar raportul
        $stmt = $conn->prepare("DELETE FROM raportari WHERE id_raport = :id");
        $stmt->execute([':id'=>$id_target]);
        $redirect_tab = 'rapoarte';

    } elseif ($action === 'delete_content') {
        // Aflăm ce trebuie șters (Articol sau Comentariu)
        $infoStmt = $conn->prepare("SELECT tip_continut, id_obiect FROM raportari WHERE id_raport = :id");
        $infoStmt->execute([':id'=>$id_target]);
        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if ($info) {
            if ($info['tip_continut'] == 'articol') {
                // Ștergem articolul
                $conn->prepare("DELETE FROM articole WHERE id_articol = :idObiect")->execute([':idObiect'=>$info['id_obiect']]);
            } elseif ($info['tip_continut'] == 'comentariu') {
                // Ștergem comentariul
                $conn->prepare("DELETE FROM comentarii WHERE id_comentariu = :idObiect")->execute([':idObiect'=>$info['id_obiect']]);
            }
            // Ștergem și raportul
            $conn->prepare("DELETE FROM raportari WHERE id_raport = :id")->execute([':id'=>$id_target]);
        }
        $redirect_tab = 'rapoarte';
    }

    header('Location: cereri-admin.php?tab=' . $redirect_tab);
    exit;
}

// ==========================================
// 2. PRELUARE DATE DIN BAZA DE DATE
// ==========================================

// A. CERERI
$cereri = $conn->query("SELECT c.*, u.nume FROM cereri_autor c JOIN utilizatori u ON c.id_utilizator=u.id_utilizator ORDER BY c.data_cerere DESC")->fetchAll(PDO::FETCH_ASSOC);

// B. RAPOARTE (SQL CORECTAT PENTRU TABELUL TĂU)
// Folosim id_obiect și tip_continut pentru a face JOIN condiționat
$sql_rapoarte = "
    SELECT 
        r.id_raport, 
        r.motiv, 
        r.data, 
        r.tip_continut, 
        r.id_obiect,
        u.nume as nume_reclamant,
        a.titlu as titlu_articol,
        c.text as text_comentariu
    FROM raportari r
    LEFT JOIN utilizatori u ON r.id_utilizator = u.id_utilizator
    -- Facem join cu articole DOAR dacă tipul este 'articol'
    LEFT JOIN articole a ON r.id_obiect = a.id_articol AND r.tip_continut = 'articol'
    -- Facem join cu comentarii DOAR dacă tipul este 'comentariu'
    LEFT JOIN comentarii c ON r.id_obiect = c.id_comentariu AND r.tip_continut = 'comentariu'
    ORDER BY r.data DESC
";
$rapoarte = $conn->query($sql_rapoarte)->fetchAll(PDO::FETCH_ASSOC);

$active_tab = $_GET['tab'] ?? 'cereri';
?>

<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard | PANORAMA</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

<style>
    /* ... (Stilurile rămân aceleași ca înainte, sunt bune) ... */
    body { font-family: 'Inter', sans-serif; color: #1A1D1F; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column; background-color: #f8f8f8; }
    a { text-decoration: none; color: #000; }
    .classic-header { text-align: center; padding-top: 40px; background: #fff; margin-bottom: 40px; }
    .header-subtitle { font-family: 'Inter', sans-serif; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: #333; margin-bottom: 10px; }
    .header-title { font-family: 'Playfair Display', serif; font-size: 5rem; font-weight: 700; color: #000; margin: 0 0 30px 0; line-height: 1; }
    .nav-strip { border-top: 1px solid #000; border-bottom: 1px solid #000; display: flex; justify-content: center; align-items: stretch; max-width: 100%; }
    .nav-strip a { text-decoration: none; color: #333; font-family: 'Inter', sans-serif; font-size: 0.9rem; text-transform: uppercase; padding: 15px 40px; border-right: 1px solid #eee; transition: background 0.2s; }
    .nav-strip a:first-child { border-left: 1px solid #eee; }
    .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
    .nav-strip a.logout-link { color: #d9534f; }
    .nav-strip a[href*="cereri-admin.php"] { font-weight: 700; color: #000; background-color: #f5f5f5; }
    .main-wrapper { flex-grow: 1; padding: 0 20px 50px 20px; background: url('https://i.postimg.cc/76L7jg1C/pexels-mccutcheon-1191710.jpg') no-repeat center; background-size: cover; background-attachment: fixed; }
    .content-area { max-width: 1100px; margin: 0 auto; padding: 40px; background: #fff; border: 2px solid #000; box-shadow: 10px 10px 0px #000; position: relative; }
    .tabs-container { display: flex; border-bottom: 2px solid #000; margin-bottom: 30px; }
    .tab-btn { flex: 1; padding: 20px; text-align: center; font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; text-transform: uppercase; background: #fff; border: none; cursor: pointer; transition: all 0.3s; color: #aaa; border-bottom: 4px solid transparent; }
    .tab-btn:hover { color: #000; background-color: #f9f9f9; }
    .tab-btn.active { color: #000; border-bottom: 4px solid #000; }
    .panel { display: none; animation: fadeIn 0.4s ease; }
    .panel.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .admin-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .admin-table th, .admin-table td { border: 1px solid #000; padding: 12px 15px; text-align: left; font-size: 0.9rem; }
    .admin-table th { background-color: #000; color: #fff; text-transform: uppercase; letter-spacing: 1px; font-weight: 400; font-family: 'Inter', sans-serif;}
    .admin-table tr:nth-child(even) { background-color: #f2f2f2; }
    .status-pending { color: #f39c12; font-weight: 700; }
    .status-accepted { color: #27ae60; font-weight: 700; }
    .status-rejected { color: #e74c3c; font-weight: 700; }
    .action-btn { display: inline-block; padding: 6px 12px; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; color: #fff; margin-right: 5px; border: 1px solid #000; cursor: pointer; transition: transform 0.2s; }
    .btn-green { background-color: #000; color: #fff; }
    .btn-green:hover { background-color: #27ae60; border-color: #27ae60; }
    .btn-red { background-color: #fff; color: #000; }
    .btn-red:hover { background-color: #e74c3c; color: #fff; border-color: #e74c3c; }
    .content-preview { font-style: italic; color: #555; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; }
    .badge-type { color: #000000ff; padding: 2px 6px; font-size: 10px; text-transform: uppercase; }
    .footer { text-align: center; padding: 20px; background: #fff; border-top: 1px solid #000; margin-top: auto;}
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
        <a href="cereri-admin.php">Cereri & Rapoarte</a>
        <?php if ($role === 'admin'): ?>
            <a href="../lista_utilizatori.php">Lista Utilizatori</a>
        <?php endif; ?>
        <?php if ($role === 'cititor' || $role === 'autor'): ?>
            <a href="../contact.php">Contact</a>
        <?php endif; ?>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>
</header>

<div class="main-wrapper">
    <div class="content-area">
        
        <div class="tabs-container">
            <button class="tab-btn <?= $active_tab == 'cereri' ? 'active' : '' ?>" onclick="switchTab('cereri')">
                CERERI AUTOR
            </button>
            <button class="tab-btn <?= $active_tab == 'rapoarte' ? 'active' : '' ?>" onclick="switchTab('rapoarte')">
                RAPOARTE & RECLAMAȚII
            </button>
        </div>

        <div id="panel-cereri" class="panel <?= $active_tab == 'cereri' ? 'active' : '' ?>">
            <table class="admin-table">
                <thead>
                    <tr><th>Utilizator</th><th>Justificare</th><th>Data</th><th>Status</th><th>Acțiuni</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($cereri)): ?>
                        <tr><td colspan="5" style="text-align:center;">Nu există cereri noi.</td></tr>
                    <?php else: ?>
                        <?php foreach($cereri as $cerere): ?>
                        <tr>
                            <td><?= htmlspecialchars($cerere['nume']) ?></td>
                            <td><?= nl2br(htmlspecialchars($cerere['text_justificare'])) ?></td>
                            <td><?= date('d.m.Y', strtotime($cerere['data_cerere'])) ?></td>
                            <td class="status-<?= htmlspecialchars($cerere['status']) ?>"><?= htmlspecialchars(ucfirst($cerere['status'])) ?></td>
                            <td>
                                <?php if($cerere['status'] === 'pending'): ?>
                                    <a href="?action=accept_cerere&id=<?= $cerere['id_cerere'] ?>" class="action-btn btn-green" onclick="return confirm('Accepți?')"><i class="fa fa-check"></i> ACCEPTĂ</a>
                                    <a href="?action=reject_cerere&id=<?= $cerere['id_cerere'] ?>" class="action-btn btn-red" onclick="return confirm('Respingi?')"><i class="fa fa-times"></i> RESPINGE</a>
                                <?php else: ?>
                                    <span style="color:#aaa;">Finalizat</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="panel-rapoarte" class="panel <?= $active_tab == 'rapoarte' ? 'active' : '' ?>">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Tip</th>
                        <th>Reclamat de</th>
                        <th>Motiv</th>
                        <th>Conținut Reclamat</th>
                        <th>Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($rapoarte)): ?>
                        <tr><td colspan="5" style="text-align:center;">Nu există rapoarte active.</td></tr>
                    <?php else: ?>
                        <?php foreach($rapoarte as $rap): ?>
                        <tr>
                            <td style="text-align: center;">
                                <span class="badge-type"><?= strtoupper(htmlspecialchars($rap['tip_continut'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($rap['nume_reclamant'] ?? 'Anonim') ?></td>
                            <td style="color: #d9534f; font-weight:600;"><?= strtoupper(htmlspecialchars($rap['motiv'])) ?></td>
                            <td>
                                <span class="content-preview">
                                    <?php 
                                        // Afișăm titlul dacă e articol, textul dacă e comentariu
                                        if ($rap['tip_continut'] == 'articol') {
                                            echo htmlspecialchars($rap['titlu_articol'] ?? 'Articol șters');
                                        } elseif ($rap['tip_continut'] == 'comentariu') {
                                            echo '"' . htmlspecialchars($rap['text_comentariu'] ?? 'Comentariu șters') . '"';
                                        }
                                    ?>
                                </span>
                                <br>
                                <small style="color:#888;">Data: <?= date('d.m.Y H:i', strtotime($rap['data'])) ?></small>
                            </td>
                            <td>
                                <a href="?action=delete_content&id=<?= $rap['id_raport'] ?>" class="action-btn btn-red" onclick="return confirm('ATENȚIE! Vei șterge definitiv acest conținut (<?= $rap['tip_continut'] ?>). Ești sigur?')">
                                    <i class="fa fa-trash"></i> Șterge Conținut
                                </a>

                                <a href="?action=dismiss_report&id=<?= $rap['id_raport'] ?>" class="action-btn btn-green" onclick="return confirm('Ignori acest raport?')">
                                    <i class="fa fa-eye-slash"></i> Ignoră
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
<style>
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
</style>
<footer class="footer">
    <p>&copy; 2025 PANORAMA Revistă. Toate drepturile rezervate.</p>
    <div class="social-links">
        <a href="#">Facebook</a> | <a href="#">Instagram</a></a>
    </div>
</footer>

<script>
    function switchTab(tabName) {
        document.getElementById('panel-cereri').classList.remove('active');
        document.getElementById('panel-rapoarte').classList.remove('active');
        const buttons = document.querySelectorAll('.tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        document.getElementById('panel-' + tabName).classList.add('active');
        if(tabName === 'cereri') { buttons[0].classList.add('active'); } else { buttons[1].classList.add('active'); }
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }
</script>

</body>
</html>