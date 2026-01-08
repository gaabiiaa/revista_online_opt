<?php
session_start();
require 'includes/db.php';
// Include PHPMailer 
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nume = $_POST['nume'];
    $email = $_POST['email'];
    $subiect = $_POST['subiect'];
    $mesaj = $_POST['mesaj'];

    $mail = new PHPMailer(true);
    try {
        // Setări Server (Alea pe care le-ai folosit la Login)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'panorama.revista.online@gmail.com'; 
        $mail->Password   = 'lyoo trvd dnkj azvk'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Trimite DE LA utilizator CĂTRE Admin (adică tot către tine)
        $mail->setFrom($mail->Username, 'Formular Contact'); 
        $mail->addReplyTo($email, $nume); // Ca să poți da reply direct utilizatorului
        $mail->addAddress('panorama.revista.online@gmail.com'); // Adminul primește mailul

        $mail->isHTML(true);
        $mail->Subject = "Contact: $subiect";
        $mail->Body    = "<h3>Mesaj nou de la $nume ($email)</h3><p>$mesaj</p>";

        $mail->send();
        $msg = "<p style='color:green'>Mesajul a fost trimis!</p>";
    } catch (Exception $e) {
        $msg = "<p style='color:red'>Eroare: {$mail->ErrorInfo}</p>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Contact | Panorama</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; padding: 40px; background: #f4f4f4; }
        .contact-box { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border: 1px solid #000; box-shadow: 5px 5px 0 #000; }
        input, textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; }
        button { background: #000; color: #fff; padding: 10px 20px; border: none; cursor: pointer; text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>
    <div class="contact-box">
        <h1>Contactează-ne</h1>
        <?= $msg ?>
        <form method="POST">
            <label>Nume:</label>
            <input type="text" name="nume" required>
            <label>Email:</label>
            <input type="email" name="email" required>
            <label>Subiect:</label>
            <input type="text" name="subiect" required>
            <label>Mesaj:</label>
            <textarea name="mesaj" rows="5" required></textarea>
            <button type="submit">Trimite</button>
        </form>
        <br>
        <a href="index.php">&larr; Înapoi la site</a>
    </div>
</body>
</html>