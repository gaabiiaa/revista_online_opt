<?php
require 'includes/db.php';

// 1. Securitate: Utilizatorul trebuie să fie logat
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VERIFICARE SECURITY CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Eroare de securitate: Token CSRF invalid! Cerere respinsă.");
        
    }
    // Preluăm datele
    $user_id = $_SESSION['user_id'];
    $tip_continut = $_POST['tip_continut']; // Va fi 'articol' sau 'comentariu'
    $id_obiect = (int)$_POST['id_obiect'];
    $motiv = $_POST['motiv'];
    
    // Preluăm URL-ul paginii de unde s-a făcut raportarea (index.php sau articol.php)
    // Dacă nu există, folosim HTTP_REFERER ca rezervă
    $redirect_url = $_POST['return_url'] ?? $_SERVER['HTTP_REFERER'];

    // Validare simplă
    if (!empty($motiv) && !empty($id_obiect) && in_array($tip_continut, ['articol', 'comentariu'])) {
        try {
            // Inserăm în baza de date (structura ta polimorfă)
            $stmt = $conn->prepare("INSERT INTO raportari (id_utilizator, tip_continut, id_obiect, motiv, data) VALUES (:uid, :tip, :id, :motiv, NOW())");
            
            $stmt->execute([
                ':uid'   => $user_id,
                ':tip'   => $tip_continut,
                ':id'    => $id_obiect,
                ':motiv' => $motiv
            ]);

            // --- REDIRECȚIONARE ---
            // Adăugăm parametrul msg=raport_success la URL-ul existent
            if (strpos($redirect_url, '?') !== false) {
                // Dacă URL-ul are deja parametri (ex: articol.php?id=5)
                $redirect_url .= "&msg=raport_success";
            } else {
                // Dacă URL-ul e curat (ex: index.php)
                $redirect_url .= "?msg=raport_success";
            }

            header("Location: " . $redirect_url);
            exit;

        } catch (PDOException $e) {
            // Loghează eroarea dacă e nevoie
            header("Location: " . $redirect_url . "&error=db_error");
            exit;
        }
    }
}

// Dacă accesarea e directă, trimite la index
header("Location: ../index.php");
exit;
?>