<?php
// Presupunem că db.php este relativ corect
require '../includes/db.php'; 

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    $email = $_POST['email'];
    $parola = $_POST['parola'];

    // Căutăm utilizatorul după email
    $stmt = $conn->prepare("SELECT * FROM utilizatori WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

       // --- VERIFICĂRI DE SECURITATE ---

        // A. Este BANAT?
        if ($user['e_banat'] == 1) {
            $message = "Contul tău a fost blocat de un administrator.";
        }
        // B. Este VERIFICAT prin Email? (AICI ESTE MODIFICAREA)
        elseif ($user['este_verificat'] == 0) {
            $message = "Te rugăm să accesezi link-ul primit pe email pentru a activa contul!";
        }
        // C. Verificăm PAROLA
        elseif (password_verify($parola, $user['parola'])) {
            // === LOGARE CU SUCCES ===
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id_utilizator'];
            $_SESSION['user_name'] = $user['nume'];
            $_SESSION['user_role'] = $user['rol']; 

            header('Location: ../index.php');
            exit;
        } 
        else {
            // Parolă greșită
            $message = "Email sau parolă incorectă.";
        }
    } else {
        // Utilizatorul nu există
        $message = "Email sau parolă incorectă.";
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Login | PANORAMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            background: url('../ref/login.jpg') no-repeat center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        a { text-decoration: none; color: #000; font-weight: 500;}
        a:hover { color: #555; }

        /* HEADER */
        .header-title {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        /* CONTAINER FORMULAR  */
        .login-container {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border: 1px solid #000;
            padding: 40px;
            box-shadow: 5px 5px 0 0 #000; 
            box-sizing: border-box;
        }

        .login-container h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 30px;
            text-align: center;
            text-transform: uppercase;
        }
        
        /* Mesaje de eroare */
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
        }

        /* FORMULAR */
        .login-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .login-form input[type="email"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #000;
            box-sizing: border-box;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            border-radius: 0;
            background-color: #f9f9f9;
        }

        .login-form button[type="submit"] {
            width: 100%;
            background-color: #000;
            color: #fff;
            padding: 12px;
            border: 1px solid #000;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
            transition: background-color 0.2s, color 0.2s;
            border-radius: 0;
        }
        .login-form button[type="submit"]:hover {
            background-color: #333;
        }

        /* Link înregistrare */
        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: #555;
        }
        
        @media (max-width: 500px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
            .header-title {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    
    <div class="header-title">PANORAMA</div>

    <h2>Autentificare</h2>

    <?php if($message): ?>
        <p class="message-error"><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>
    
    <?php if(isset($_GET['error']) && $_GET['error'] == 'banned'): ?>
        <p class="message-error">Contul tău a fost blocat.</p>
    <?php endif; ?>

    <form method="POST" class="login-form">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>

        <label for="parola">Parolă:</label>
        <input type="password" id="parola" name="parola" required><br>

        <button type="submit">Login</button>
    </form>
    
    <p class="register-link">
        Nu ai cont? <a href="register.php">Înregistrează-te aici</a>
    </p>
</div>

</body>
</html>
