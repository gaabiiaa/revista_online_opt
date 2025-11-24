<?php
// Citeste variabila de mediu setata de Render/Fly.io
$dbUrl = getenv('DATABASE_URL');

if (!$dbUrl) {
    die("Eroare fatala: Variabila DATABASE_URL nu este setata.");
}

// Extrage componentele URL-ului
$url = parse_url($dbUrl);

// Conexiunea la baza de date
$host = $url['host'];
$dbname = ltrim($url['path'], '/');
$user = $url['user'];
$password = $url['pass'];
$port = isset($url['port']) ? $url['port'] : '5432';
$scheme = $url['scheme']; // 'pgsql' sau 'mysql'

try {
    // ATENTIE: Modifica 'pgsql' in 'mysql' daca ai ales MariaDB/MySQL pe Render
    $conn = new PDO("$scheme:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Eroare de conexiune la bază de date. Verificati log-urile.");
}
?>
