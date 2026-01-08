<?php
// Datele de conexiune preluate din exemplul InfinityFree:
$host = 'sql301.infinityfree.com';      // DB_SERVER
$db   = 'if0_40493982_db_panorama';     // DB_NAME
$user = 'if0_40493982';                 // DB_USERNAME
$pass = 'ohmammamiaG69';                // DB_PASSWORD
$charset = 'utf8mb4';

// ATENȚIE: InfinityFree folosește portul implicit MySQL (3306), nu este necesar de specificat
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // afișează erorile
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Conexiune esuata: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generăm un token CSRF dacă nu există
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
