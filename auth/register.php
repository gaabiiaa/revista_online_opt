<?php
require '../includes/db.php';

// --- INCLUDERE PHPMAILER ---
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    $nume = trim($_POST['nume']);
    $email = trim($_POST['email']);
    $parola = $_POST['parola'];
    $parola_conf = $_POST['parola_conf'];
    
    if (strlen($parola) < 5) {
        $message = "Parola trebuie să aibă minim 5 caractere!";
    } 
    elseif (!preg_match('/[A-Z]/', $parola)) {
        $message = "Parola trebuie să conțină cel puțin o literă mare (A-Z)!";
    }
    elseif (!preg_match('/[0-9]/', $parola)) {
        $message = "Parola trebuie să conțină cel puțin o cifră (0-9)!";
    }
    elseif (!preg_match('/[\W_]/', $parola)) { 
        // [\W_] înseamnă orice caracter care nu e literă sau cifră (ex: !, @, #, $, etc.)
        $message = "Parola trebuie să conțină cel puțin un caracter special (ex: !@#$%)!";
    }
    elseif ($parola !== $parola_conf) {
        $message = "Parolele nu coincid!";
    } 
    else {
    // --- 1. VERIFICARE RECAPTCHA ---
    $recaptcha_secret = '6LeZvDIsAAAAAEVS85PCwc-IngjVnOufE0kPNKLa'; // Cheia Secretă
    $recaptcha_response = $_POST['g-recaptcha-response'];
    
    // Verificăm la Google
    $verify_url = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}";
    $captcha_check = json_decode(file_get_contents($verify_url));

    if (empty($recaptcha_response) || !$captcha_check->success) {
        $message = "Te rugăm să bifezi căsuța 'Nu sunt robot'!";
    } elseif ($parola !== $parola_conf) {
        $message = "Parolele nu coincid!";
    } else {
        // --- 2. VERIFICARE EMAIL EXISTENT ---
        $stmt = $conn->prepare("SELECT id_utilizator FROM utilizatori WHERE email = :email");
        $stmt->execute([':email' => $email]);

        if ($stmt->rowCount() > 0) {
            $message = "Acest email este deja folosit!";
        } else {
            // --- 3. INSERARE UTILIZATOR (NEVERIFICAT) ---
            $hash = password_hash($parola, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32)); // Generăm token unic

            $insert = $conn->prepare("
                INSERT INTO utilizatori (nume, email, parola, rol, token_verificare, este_verificat)
                VALUES (:nume, :email, :parola, 'cititor', :token, 0)
            ");
            
            if ($insert->execute([':nume' => $nume, ':email' => $email, ':parola' => $hash, ':token' => $token])) {
                
                // --- 4. TRIMITERE EMAIL CU PHPMAILER ---
                $mail = new PHPMailer(true);
                try {
                    // Setări Server
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    
                    // --- DATELE TALE AICI ---
                    $mail->Username   = 'panorama.revista.online@gmail.com'; // <--- PUNE ADRESA DE EMAIL AICI
                    $mail->Password   = 'lyoo trvd dnkj azvk';      // <--- Parola ta de aplicație (deja pusă)
                    
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Expeditor și Destinatar
                    $mail->setFrom($mail->Username, 'Revista Online'); // Se trimite de la adresa ta
                    $mail->addAddress($email, $nume);

                    // Generare Link Activare (Detectează automat dacă ești pe localhost sau online)
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $domain = $_SERVER['HTTP_HOST'];
                    // Ajustează calea dacă e nevoie (ex: scoate /revista dacă ești pe root la hosting)
                    $path = "/opt_PHP/revista/auth/verify.php"; 
                    if ($domain != 'localhost' && $domain != '127.0.0.1') {
                        $path = "/auth/verify.php"; // Pe hosting probabil e direct în root
                    }
                    
                    $link = "$protocol://$domain$path?token=" . $token;

                    // Conținut Email
                    $mail->isHTML(true);
                    $mail->Subject = 'Activeaza contul - PANORAMA';
                    $mail->Body    = "
                        <h2>Salut, $nume!</h2>
                        <p>Îți mulțumim că te-ai înregistrat pe Panorama.</p>
                        <p>Te rugăm să dai click pe link-ul de mai jos pentru a activa contul:</p>
                        <p><a href='$link' style='padding:10px 20px; background-color:black; color:white; text-decoration:none;'>Activează Contul</a></p>
                        <br><p>Dacă nu ai solicitat acest cont, ignoră acest email.</p>
                    ";
                    
                    $mail->send();
                    $message = "Cont creat cu succes! Verifică-ți emailul pentru a activa contul.";
                    
                    // NU mai facem redirect automat, ca să vadă mesajul
                    
                } catch (Exception $e) {
                    $message = "Cont creat, dar emailul nu s-a putut trimite. Eroare: {$mail->ErrorInfo}";
                }

            } else {
                $message = "Eroare la crearea contului în baza de date.";
            }
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
    
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            background: url('../ref/register.jpg') no-repeat center;
            background-size: cover; /* Asigură că imaginea acoperă tot ecranul */
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
            background: rgba(255, 255, 255, 0.8); /* Fundal mic pentru lizibilitate */
            padding-left: 10px; padding-right: 10px;
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
        
        /* Stil pentru containerul Captcha */
        .captcha-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }

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
            /* Facem captcha responsive daca e nevoie */
            .g-recaptcha {
                transform: scale(0.77);
                -webkit-transform: scale(0.77);
                transform-origin: 0 0;
                -webkit-transform-origin: 0 0;
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
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
        <label for="nume">Nume:</label>
        <input type="text" id="nume" name="nume" required value="<?= isset($_POST['nume']) ? htmlspecialchars($_POST['nume']) : '' ?>">

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">

        <label for="parola">Parolă:</label>
        <input type="password" id="parola" name="parola" required>

        <label for="parola_conf">Confirmă parolă:</label>
        <input type="password" id="parola_conf" name="parola_conf" required>

        <div class="captcha-container">
            <div class="g-recaptcha" data-sitekey="6LeZvDIsAAAAAHffoSteG7_KjOLSR4uvaCwD52n8"></div>
        </div>

        <button type="submit">Înregistrează-te</button>
    </form>
    
    <p class="login-link">
        Ai deja cont? <a href="login.php">Loghează-te aici</a>
    </p>
</div>

</body>
</html>
