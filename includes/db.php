<?php
$host = '127.0.0.1';
$db   = 'revista_online';       // numele bazei de date
$user = 'root';           // user MySQL
$pass = '';               // parola MySQL
$charset = 'utf8mb4';

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
?>
