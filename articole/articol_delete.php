<?php
require '../includes/db.php'; 

// 1. Verificare Autentificare și ID Articol
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    // Redirecționează dacă nu e logat sau lipsește ID-ul
    header('Location: ../index.php'); 
    exit;
}

$articol_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$redirect_location = '../index.php'; // Locația implicită de redirecționare după operație

try {
    // 2. Preia datele articolului pentru a verifica autorizarea și URL-ul imaginii
    $stmt = $conn->prepare("SELECT id_autor, coperta_url FROM articole WHERE id_articol = :id");
    $stmt->execute([':id' => $articol_id]);
    $articol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$articol) {
        // Articolul nu există, dar continuăm spre redirect
        header('Location: ' . $redirect_location . '?status=error&msg=Articolul nu a fost găsit.');
        exit;
    }

    $is_author = ($articol['id_autor'] == $user_id);
    $is_admin = ($user_role === 'admin');

    // 3. Verificare Permisiuni (Autor sau Admin)
    if (!$is_author && !$is_admin) {
        header('Location: ' . $redirect_location . '?status=error&msg=Nu ai permisiunea de a șterge acest articol.');
        exit;
    }

    // --- Ștergere Articol ---

    // 4. Șterge imaginea de copertă asociată (opțional, dar recomandat)
    if (!empty($articol['coperta_url'])) {
        $upload_dir = '../uploads/';
        $file_path = $upload_dir . $articol['coperta_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // 5. Șterge articolul (și toate înregistrările conexe: comentarii, like-uri, etc., dacă ai setat FOREIGN KEY cu ON DELETE CASCADE. Dacă nu, trebuie să le ștergi manual.)
    
    // Asumăm că tabelul 'articole' are chei străine setate cu ON DELETE CASCADE pentru 'comentarii' și 'likeuri'
    // Dacă nu ai CASCADE, adaugă instrucțiuni DELETE separate pentru likeuri și comentarii:
    // $conn->prepare("DELETE FROM likeuri WHERE id_articol = :id")->execute([':id' => $articol_id]);
    // $conn->prepare("DELETE FROM comentarii WHERE id_articol = :id")->execute([':id' => $articol_id]);


    $delete_stmt = $conn->prepare("DELETE FROM articole WHERE id_articol = :id");
    $delete_stmt->execute([':id' => $articol_id]);

    // 6. Redirecționare cu mesaj de succes
    header('Location: ' . $redirect_location . '?status=success&msg=Articolul a fost șters cu succes.');
    exit;

} catch (PDOException $e) {
    // În caz de eroare de bază de date
    header('Location: ' . $redirect_location . '?status=error&msg=Eroare la ștergerea articolului.');
    // În mediul de dezvoltare, poți folosi: die("Eroare PDO: " . $e->getMessage());
    exit;
}
?>