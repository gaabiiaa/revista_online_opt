<?php
require 'includes/db.php';

// === IMPORTĂM PHPMAILER ===
// Ajustează calea dacă folderul 'includes' este în altă parte
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. SECURITATE
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 2. PROCESARE BANARE (CU PHPMAILER)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user_id'])) {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    $id_ban = (int)$_POST['ban_user_id'];
    $email_ban = $_POST['email_user'];
    
    // Update în bază
    $stmt = $conn->prepare("UPDATE utilizatori SET e_banat = 1 WHERE id_utilizator = :id");
    $stmt->execute([':id' => $id_ban]);

    // --- TRIMITERE EMAIL CU PHPMAILER ---
    $mail = new PHPMailer(true);

    try {
        // Setări Server
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';       // Serverul SMTP al Gmail
        $mail->SMTPAuth   = true;
        $mail->Username   = 'panorama.revista.online@gmail.com';  
        $mail->Password   = 'lyoo trvd dnkj azvk';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Destinatari
        $mail->setFrom('panorama.revista.online@gmail.com', 'Admin Panorama'); // De la cine
        $mail->addAddress($email_ban);                           // Către cine

        // Conținut
        $mail->isHTML(true);
        $mail->Subject = 'Notificare: Cont Blocat - PANORAMA';
        $mail->Body    = '
            <div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #000;">
                <h2 style="color: #d9534f;">Contul tău a fost blocat</h2>
                <p>Salut,</p>
                <p>Te informăm că accesul tău la platforma <strong>PANORAMA</strong> a fost restricționat permanent din cauza încălcării termenilor și condițiilor.</p>
                <p>Dacă consideri că este o greșeală, ne poți contacta.</p>
                <br>
                <p>Cu respect,<br>Echipa Panorama</p>
            </div>
        ';
        $mail->AltBody = 'Salut. Contul tau a fost blocat permanent pe platforma Panorama.';

        $mail->send();
        // Redirect succes cu mail trimis
        header("Location: lista_utilizatori.php?msg=banned_sent");
    } catch (Exception $e) {
        header("Location: lista_utilizatori.php?msg=banned_error");
    }
    exit;
}

// 3. PROCESARE DEBANARE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user_id'])) {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    $id_unban = (int)$_POST['unban_user_id'];
    $conn->prepare("UPDATE utilizatori SET e_banat = 0 WHERE id_utilizator = :id")->execute([':id' => $id_unban]);
    header("Location: lista_utilizatori.php?msg=unbanned");
    exit;
}

// 4. FILTRARE ȘI SORTARE (Codul reparat anterior)
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'nume';

switch($sort) {
    case 'email': $orderby = "email ASC"; break;
    case 'data': $orderby = "data_inregistrare DESC"; break;
    case 'rol': $orderby = "rol ASC"; break;
    default: $orderby = "nume ASC";
}

$sql = "SELECT * FROM utilizatori WHERE (nume LIKE :s1 OR email LIKE :s2) ORDER BY $orderby";
$stmt = $conn->prepare($sql);
$stmt->execute([':s1' => "%$search%", ':s2' => "%$search%"]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
// LOGICA DE EXPORT (EXCEL)
// LOGICA DE EXPORT (EXCEL - VARIANTA TABEL HTML)
if (isset($_GET['export']) && $_GET['export'] == 'xls') {
    // 1. Curățăm buffer-ul
    if (ob_get_length()) ob_end_clean();
    
    // 2. Header-e pentru Excel
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=lista_utilizatori.xls");
    
    // 3. IMPORTANT: Meta tag pentru Diacritice (UTF-8)
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    
    // 4. Deschidem un Tabel HTML (Excel îl va transforma în grilă)
    echo '<table border="1">';
    
    // 5. Antetul Tabelului
    echo '<tr>
            <th style="background-color:#000; color:#fff;">ID</th>
            <th style="background-color:#000; color:#fff;">Nume</th>
            <th style="background-color:#000; color:#fff;">Email</th>
            <th style="background-color:#000; color:#fff;">Rol</th>
            <th style="background-color:#000; color:#fff;">Status</th>
          </tr>';
    
    // 6. Datele
    $stmtExport = $conn->query("SELECT * FROM utilizatori");
    
    while($row = $stmtExport->fetch(PDO::FETCH_ASSOC)) {
        // Calculăm Statusul
        if ($row['e_banat'] == 1) {
            $status_text = "BANAT";
            $bg_color = "#ffe6e6"; // Roșu deschis pentru banați
        }  elseif ($row['este_verificat' == 0]) {
            $status_text = "Inactiv";
            $bg_color = "#ffffff";
        } else {
            $status_text = "Activ";
            $bg_color = "#ffffff";
        }

        // Scriem rândul
        echo "<tr style='background-color: {$bg_color};'>";
        echo "<td>{$row['id_utilizator']}</td>";
        echo "<td>" . htmlspecialchars($row['nume']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>{$row['rol']}</td>";
        echo "<td>{$status_text}</td>";
        echo "</tr>";
    }
    
    echo '</table>';
    exit; // Oprim execuția
}
// DATE PENTRU GRAFIC
// Numărăm câți useri sunt pentru fiecare rol
$stats_stmt = $conn->query("SELECT rol, COUNT(*) as nr FROM utilizatori GROUP BY rol");
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Rezultat: ['admin'=>1, 'cititor'=>5]

// Pregătim datele pentru Javascript (JSON)
$roles_json = json_encode(array_keys($stats));
$counts_json = json_encode(array_values($stats));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Lista Utilizatori | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        /* STIL PANORAMA (Brutalist) */
        body { font-family: 'Inter', sans-serif; background: #f8f8f8; padding: 40px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 3rem; margin-bottom: 30px; text-transform: uppercase; border-bottom: 4px solid #000; padding-bottom: 10px; }
        
        .filter-bar { display: flex; gap: 15px; margin-bottom: 30px; background: #fff; padding: 20px; border: 2px solid #000; box-shadow: 6px 6px 0px #000; }
        .filter-bar input, .filter-bar select { padding: 10px; border: 1px solid #000; font-family: 'Inter', sans-serif; }
        .filter-bar button { background: #000; color: #fff; border: none; padding: 10px 20px; font-weight: bold; cursor: pointer; text-transform: uppercase; }
        .filter-bar button:hover { background: #333; }
        
        .user-table { width: 100%; border-collapse: collapse; background: #fff; border: 2px solid #000; }
        .user-table th, .user-table td { border: 1px solid #000; padding: 15px; text-align: left; }
        .user-table th { background: #000; color: #fff; text-transform: uppercase; font-size: 0.9rem; }
        
        .badge { padding: 4px 8px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; border: 1px solid #000; }
        .badge-admin { background: #e0e0e0; }
        .badge-autor { background: #d4edda; }
        .badge-cititor { background: #fff; }
        .badge-banned { background: #ff0000; color: #fff; border-color: #ff0000; }

        .btn-ban { background: #fff; color: #d9534f; border: 1px solid #d9534f; padding: 5px 10px; cursor: pointer; font-weight: bold; text-transform: uppercase; font-size: 0.8rem; }
        .btn-ban:hover { background: #d9534f; color: #fff; }
        
        .btn-unban { background: #fff; color: #28a745; border: 1px solid #28a745; padding: 5px 10px; cursor: pointer; font-weight: bold; text-transform: uppercase; font-size: 0.8rem; }
        .btn-unban:hover { background: #28a745; color: #fff; }
        
        .back-link { display: inline-block; margin-bottom: 20px; color: #000; font-weight: bold; text-decoration: none; text-transform: uppercase; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <a href="../index.php" class="back-link">← Înapoi la Site</a>
    
    <h1 class="page-title">Administrare Utilizatori</h1>
    <div style="display: flex; gap: 30px; margin-bottom: 30px; align-items: flex-start;">
    
    <div style="background: #fff; padding: 20px; border: 2px solid #000; box-shadow: 4px 4px 0 #000;">
        <h3>Exportă Date</h3>
        <p>Descarcă lista completă de utilizatori.</p>
        <a href="lista_utilizatori.php?export=xls" style="background: green; color: #fff; padding: 10px 15px; text-decoration: none; font-weight: bold;">
            <i class="fa fa-file-excel"></i> DESCARCĂ EXCEL
        </a>
    </div>

    <div style="background: #fff; padding: 20px; border: 2px solid #000; box-shadow: 4px 4px 0 #000; flex-grow: 1;">
        <h3>Statistică Utilizatori</h3>
        <div style="width: 300px; margin: 0 auto;">
            <canvas id="userChart"></canvas>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('userChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie', // Tipul graficului: Pie (Plăcintă)
        data: {
            labels: <?= $roles_json ?>, // ['admin', 'cititor', 'autor']
            datasets: [{
                data: <?= $counts_json ?>, // [1, 15, 3]
                backgroundColor: ['#d9534f', '#5bc0de', '#5cb85c'], // Culori
                borderWidth: 2,
                borderColor: '#000'
            }]
        },
        options: {
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
</script>
    <form method="GET" class="filter-bar">
        <input type="text" name="q" placeholder="Caută nume sau email..." value="<?= htmlspecialchars($search) ?>" style="flex-grow: 1;">
        <select name="sort" onchange="this.form.submit()">
            <option value="nume" <?= $sort=='nume'?'selected':'' ?>>Sortează după Nume</option>
            <option value="email" <?= $sort=='email'?'selected':'' ?>>Sortează după Email</option>
            <option value="rol" <?= $sort=='rol'?'selected':'' ?>>După Rol</option>
        </select>
        <button type="submit">CAUTĂ</button>
        <?php if($search): ?>
            <a href="lista_utilizatori.php" style="padding: 10px; color: #000;">Reset</a>
        <?php endif; ?>
    </form>

    <table class="user-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nume</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Status</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr style="<?= $user['e_banat'] ? 'background-color: #ffe6e6;' : '' ?>">
                <td>#<?= $user['id_utilizator'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($user['nume']) ?></strong>
                    <br><small style="color: #666;">
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><span class="badge badge-<?= $user['rol'] ?>"><?= strtoupper($user['rol']) ?></span></td>
                <td>
                    <?php if($user['e_banat']): ?>
                        <span class="badge badge-banned">BANAT</span>
                    <?php elseif($user['este_verificat'] == 0): ?>
                    	<span style="color:grey; font-weight:bold;">Inactiv</span>
                    <?php else: ?>
                        <span style="color:green; font-weight:bold;">Activ</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($user['rol'] !== 'admin'): ?>
                        <?php if($user['e_banat'] == 0): ?>
                            <form method="POST" onsubmit="return confirm('Sigur dorești să banezi definitiv acest utilizator?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="ban_user_id" value="<?= $user['id_utilizator'] ?>">
                                <input type="hidden" name="email_user" value="<?= $user['email'] ?>">
                                <button type="submit" class="btn-ban"><i class="fa fa-ban"></i> BANEAZĂ</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Debanezi acest utilizator?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="unban_user_id" value="<?= $user['id_utilizator'] ?>">
                                <button type="submit" class="btn-unban"><i class="fa fa-unlock"></i> DEBANEAZĂ</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <small style="color:#aaa;">Protejat</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'banned_sent') {
        alert("Utilizator banat! Email trimis cu succes.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    if (urlParams.get('msg') === 'banned_error') {
        alert("Utilizator banat, dar NU am putut trimite emailul (verifică setările PHPMailer).");
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>

</body>
</html>