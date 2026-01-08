<?php
require '../includes/db.php';

$message = '';
$message_type = ''; // 'success' sau 'error'

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // 1. Căutăm utilizatorul care are acest token și NU este verificat încă
    $stmt = $conn->prepare("SELECT id_utilizator FROM utilizatori WHERE token_verificare = :token AND este_verificat = 0");
    $stmt->execute([':token' => $token]);
    
    if ($stmt->rowCount() > 0) {
        // 2. Activăm contul și ștergem tokenul (ca să nu poată fi refolosit)
        $update = $conn->prepare("UPDATE utilizatori SET este_verificat = 1, token_verificare = NULL WHERE token_verificare = :token");
        
        if ($update->execute([':token' => $token])) {
            $message = "Contul tău a fost activat cu succes! Acum ai acces deplin.";
            $message_type = 'success';
        } else {
            $message = "A apărut o eroare la activarea contului.";
            $message_type = 'error';
        }
    } else {
        // Dacă nu găsim tokenul, înseamnă că e greșit sau contul e deja activat (tokenul a fost șters)
        $message = "Acest link este invalid sau contul a fost deja activat.";
        $message_type = 'error';
    }
} else {
    $message = "Nu a fost furnizat niciun cod de verificare.";
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Verificare Cont | PANORAMA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            /* Folosim aceeași imagine de fundal ca la register, sau poți pune una simplă */
            background: url('../ref/register.jpg') no-repeat center; 
            background-size: cover;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .verify-container {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border: 1px solid #000;
            padding: 40px;
            box-shadow: 5px 5px 0 0 #000; 
            box-sizing: border-box;
            text-align: center;
        }

        .header-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            display: inline-block;
            padding-bottom: 5px;
        }

        p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn-login {
            display: inline-block;
            background-color: #000;
            color: #fff;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid #000;
            transition: background 0.3s;
        }

        .btn-login:hover {
            background-color: #333;
        }

        /* Culori pentru mesaje */
        .text-success { color: #155724; }
        .text-error { color: #721c24; }
        
        /* Iconite simple */
        .icon { font-size: 3rem; margin-bottom: 20px; display: block; }
    </style>
</head>
<body>

<div class="verify-container">
    <div class="header-title">PANORAMA</div>

    <?php if ($message_type == 'success'): ?>
        <span class="icon" style="color: green;">&#10004;</span> <h2 style="margin-top:0;">Activare Reușită!</h2>
        <p class="text-success"><?= htmlspecialchars($message); ?></p>
        
        <a href="login.php" class="btn-login">Mergi la Autentificare</a>

    <?php else: ?>
        <span class="icon" style="color: red;">&#10006;</span> <h2 style="margin-top:0;">Eroare</h2>
        <p class="text-error"><?= htmlspecialchars($message); ?></p>
        
        <a href="login.php" style="color: #000; text-decoration: underline;">Înapoi la Login</a>
    <?php endif; ?>

</div>

</body>
</html>