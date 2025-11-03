<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    header('Location: /cart.php');
    exit;
}

$itemId = (int)($_POST['id_articolo'] ?? $_POST['item_id'] ?? 0);
$quantity = (int)($_POST['quantita'] ?? $_POST['quantity'] ?? 1);

if ($itemId <= 0 || $quantity <= 0) {
    header('Location: /cart.php');
    exit;
}

try {
    $userId = current_user_id();
    
    // Verifica che l'articolo appartenga al carrello dell'utente e controlla giacenza
    $item = db_run(
        'SELECT ac.id, ac.prodotto_id, p.quantita_giacenza
         FROM articoli_carrello ac
         JOIN carrelli c ON c.id = ac.carrello_id
         JOIN prodotti p ON p.id = ac.prodotto_id
         WHERE ac.id = ? AND c.utente_id = ?
         LIMIT 1',
        [$itemId, $userId]
    )->fetch();

    if ($item && $quantity <= (int)$item['quantita_giacenza']) {
        db_run('UPDATE articoli_carrello SET quantita = ? WHERE id = ?', [$quantity, $itemId]);
    }

    header('Location: /cart.php');
    exit;

} catch (Throwable $e) {
    header('Location: /cart.php?error=1');
    exit;
}
