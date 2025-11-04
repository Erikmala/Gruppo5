<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

richiedi_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !valida_csrf()) {
    header('Location: /carrello.php');
    exit;
}

$itemId = (int)($_POST['id_articolo'] ?? $_POST['item_id'] ?? 0);

if ($itemId <= 0) {
    header('Location: /carrello.php');
    exit;
}

try {
    $userId = id_utente_corrente();
    
    // Verifica che l'articolo appartenga al carrello dell'utente
    $item = db_run(
        'SELECT ac.id FROM articoli_carrello ac
         JOIN carrelli c ON c.id = ac.carrello_id
         WHERE ac.id = ? AND c.utente_id = ?
         LIMIT 1',
        [$itemId, $userId]
    )->fetch();

    if ($item) {
        db_run('DELETE FROM articoli_carrello WHERE id = ?', [$itemId]);
    }

    header('Location: /carrello.php');
    exit;

} catch (Throwable $e) {
    header('Location: /carrello.php?error=1');
    exit;
}
