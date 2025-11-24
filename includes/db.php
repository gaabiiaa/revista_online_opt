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
    // Afișează erorile ca excepții PDO (recomandat pentru debug și producție)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    // Returnează datele ca array-uri asociative
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Dezactivează emularea preparării interogărilor
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Înlocuim die cu un mesaj de eroare mai explicit.
    die("Eroare de conexiune la baza de date InfinityFree: " . $e->getMessage());
}
// Conexiunea ($conn) este gata de utilizare în restul aplicației.
?>
