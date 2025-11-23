<?php
session_start();
require '../includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nume = $_POST['nume'];
    $email = $_POST['email'];
    $parola = $_POST['parola'];
    $parola_conf = $_POST['parola_conf'];

    // Verificare parole
    if ($parola !== $parola_conf) {
        $message = "Parolele nu coincid!";
    } else {
        // Hash parola
        $hash = password_hash($parola, PASSWORD_DEFAULT);

        // Verificare email existent
        $stmt = $conn->prepare("SELECT * FROM utilizatori WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Acest email este deja folosit!";
        } else {
            // Inserare utilizator
            $insert = $conn->prepare("
                INSERT INTO utilizatori (nume, email, parola, rol)
                VALUES (:nume, :email, :parola, 'cititor')
            ");
            $insert->bindParam(':nume', $nume);
            $insert->bindParam(':email', $email);
            $insert->bindParam(':parola', $hash);

            if ($insert->execute()) {
                $message = "Cont creat cu succes! Te poți loga acum.";
                // Redirecționare către pagina de login
                header("Location: login.php");
                exit;
            } else {
                $message = "Eroare la crearea contului.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Înregistrare | PANORAMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            background: url('../ref/register.jpg') no-repeat center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        a { text-decoration: none; color: #000; font-weight: 500;}
        a:hover { color: #555; }

        /* HEADER  */
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

        /* FORMULAR */
        .register-container {
            width: 100%;
            max-width: 450px;
            background: #fff;
            border: 1px solid #000;
            padding: 40px;
            box-shadow: 5px 5px 0 0 #000; 
            box-sizing: border-box;
        }

        .register-container h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 30px;
            text-align: center;
            text-transform: uppercase;
        }
        
        /* Mesaje de stare */
        .message-error, .message-success {
            padding: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
            border: 1px solid;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .register-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .register-form input[type="text"],
        .register-form input[type="email"],
        .register-form input[type="password"] {
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
        /* Ajustare margini pentru ultimele inputuri (fără <br><br> în etichete) */
        .register-form br { display: none; }
        .register-form input:last-of-type { margin-bottom: 30px; }


        .register-form button[type="submit"] {
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
        .register-form button[type="submit"]:hover {
            background-color: #333;
        }

        /* Link login */
        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: #555;
        }
        
        @media (max-width: 500px) {
            .register-container {
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

<div class="register-container">
    
    <div class="header-title">PANORAMA</div>

    <h2>Înregistrare</h2>

    <?php if($message): ?>
        <p class="<?= (strpos($message, 'succes') !== false) ? 'message-success' : 'message-error'; ?>">
            <?= htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST" class="register-form">
        <label for="nume">Nume:</label>
        <input type="text" id="nume" name="nume" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="parola">Parolă:</label>
        <input type="password" id="parola" name="parola" required>

        <label for="parola_conf">Confirmă parolă:</label>
        <input type="password" id="parola_conf" name="parola_conf" required>

        <button type="submit">Înregistrează-te</button>
    </form>
    
    <p class="login-link">
        Ai deja cont? <a href="login.php">Loghează-te aici</a>
    </p>
</div>

</body>
</html>