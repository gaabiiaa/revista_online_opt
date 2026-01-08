<?php
require '../includes/db.php'; 

// 1. Securitate: Verificăm dacă e logat și dacă vine prin POST
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
    }
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$parola_introduse = $_POST['parola'] ?? '';

// 2. Preluăm datele utilizatorului (Hash parola + Nume poză)
$stmt = $conn->prepare("SELECT parola, poza_profil FROM utilizatori WHERE id_utilizator = :uid");
$stmt->execute([':uid' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificare suplimentară dacă userul mai există
if (!$user) {
    header('Location: ../auth/logout.php');
    exit;
}

// 3. Verificăm Parola introdusă în modal
if (password_verify($parola_introduse, $user['parola'])) {
    
    // --- PAROLA ESTE CORECTĂ ---

    // A. Ștergem fișierul fizic (poza) de pe server
    // (MySQL șterge doar textul din bază, nu și fișierul jpg/png, deci facem noi curat)
    if (!empty($user['poza_profil'])) {
        $cale_poza = "../uploads/profile/" . $user['poza_profil'];
        if (file_exists($cale_poza)) {
            unlink($cale_poza); 
        }
    }

    try {
        // B. Ștergem utilizatorul din baza de date
        // Datorită CASCADE, se vor șterge automat și articolele, comentariile și like-urile lui.
        $delete_stmt = $conn->prepare("DELETE FROM utilizatori WHERE id_utilizator = :uid");
        $delete_stmt->execute([':uid' => $user_id]);

        // C. Distrugem sesiunea (Logout forțat)
        session_unset();
        session_destroy();

        // D. Redirecționăm la pagina principală
        header('Location: ../index.php?msg=cont_sters');
        exit;

    } catch (PDOException $e) {
        // AFISĂM EROAREA PE ECRAN CA SĂ VEDEM CAUZA
        echo "<div style='background:white; color:red; padding:20px; border:2px solid red;'>";
        echo "<h1>Eroare SQL:</h1>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
        die(); // Oprim scriptul aici
    }

} else {
    // --- PAROLA ESTE GREȘITĂ ---
    // Îl trimitem înapoi cu mesaj de eroare
    header('Location: contul_meu.php?error=pass_incorrect');
    exit;
}
?>