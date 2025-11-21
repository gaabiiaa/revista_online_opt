<?php
session_start();
require 'db.php';

$stmt = $conn->query("SELECT * FROM utilizatori ORDER BY id_utilizator ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Lista utilizatorilor</h1>

<table border="1" cellpadding="8">
    <tr>
        <th>ID</th>
        <th>Nume</th>
        <th>Email</th>
        <th>Rol</th>
    </tr>

    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id_utilizator'] ?></td>
            <td><?= htmlspecialchars($u['nume']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= $u['rol'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>

