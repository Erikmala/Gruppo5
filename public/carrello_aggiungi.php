<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

richiedi_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!valida_csrf()) {
    die('Token CSRF non valido.');
}

$productId = (int)($_POST['id_prodotto'] ?? $_POST['product_id'] ?? 0);
$quantity = (int)($_POST['quantita'] ?? $_POST['quantity'] ?? 1);

if ($productId <= 0 || $quantity <= 0) {
    header('Location: /');
    exit;
}

try {
    // Verifica che il prodotto esista e abbia giacenza
    $product = db_run('SELECT id, nome, prezzo, quantita_giacenza FROM prodotti WHERE id = ? AND attivo = TRUE LIMIT 1', [$productId])->fetch();
    
    if (!$product) {
        header('Location: /?error=product_not_found');
        exit;
    }

    if ((int)$product['quantita_giacenza'] < $quantity) {
        header('Location: /?error=insufficient_stock');
        exit;
    }

    $userId = id_utente_corrente();

    // Ottieni o crea carrello per utente
    $cart = db_run('SELECT id FROM carrelli WHERE utente_id = ? LIMIT 1', [$userId])->fetch();
    
    if (!$cart) {
        db_run('INSERT INTO carrelli (utente_id, creato_il) VALUES (?, NOW())', [$userId]);
        $cartId = (int)db()->lastInsertId();
    } else {
        $cartId = (int)$cart['id'];
    }

    // Verifica se l'articolo è già nel carrello
    $existingItem = db_run('SELECT id, quantita FROM articoli_carrello WHERE carrello_id = ? AND prodotto_id = ? LIMIT 1', [$cartId, $productId])->fetch();

    if ($existingItem) {
        // Aggiorna quantità
        $newQty = (int)$existingItem['quantita'] + $quantity;
        db_run('UPDATE articoli_carrello SET quantita = ? WHERE id = ?', [$newQty, (int)$existingItem['id']]);
    } else {
        // Inserisci nuovo articolo
        db_run('INSERT INTO articoli_carrello (carrello_id, prodotto_id, quantita, prezzo_unitario, aggiunto_il) VALUES (?, ?, ?, ?, NOW())', 
               [$cartId, $productId, $quantity, $product['prezzo']]);
    }
    
    header('Location: /carrello.php?added=1');
    exit;} catch (Throwable $e) {
    header('Location: /?error=cart_error');
    exit;
}
