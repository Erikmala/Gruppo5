<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';
require __DIR__ . '/../includes/helper_immagini.php';

richiedi_login();

$userId = id_utente_corrente();
$user = ottieni_utente_connesso();

// Get user's open cart
$cart = db_run('SELECT id FROM carrelli WHERE utente_id = ? LIMIT 1', [$userId])->fetch();

if (!$cart) {
    header('Location: /carrello.php');
    exit;
}

$cartId = (int)$cart['id'];

// Get cart items
    $cartItems = db_run(
    'SELECT ac.*, p.nome as nome_prodotto, p.quantita_giacenza, p.codice_sku
     FROM articoli_carrello ac
     JOIN prodotti p ON p.id = ac.prodotto_id
     WHERE ac.carrello_id = ?
     ORDER BY ac.aggiunto_il DESC',
    [$cartId]
)->fetchAll();

if (empty($cartItems)) {
    header('Location: /carrello.php');
    exit;
}

$total = 0;
foreach ($cartItems as $item) {
    $total += (float)$item['line_total'];
}

// Get user addresses
$addresses = db_run('SELECT * FROM indirizzi WHERE utente_id = ? ORDER BY creato_il DESC', [$userId])->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valida_csrf()) {
        $errors[] = 'Token CSRF non valido.';
    }

    $addressId = (int)($_POST['address_id'] ?? 0);
    
    // If "new address" selected, create it
    if (isset($_POST['create_address'])) {
        $fullName = trim($_POST['full_name'] ?? '');
        $line1 = trim($_POST['line1'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'IT');

        if ($fullName && $line1 && $city && $postalCode) {
            try {
                db_run(
                    'INSERT INTO indirizzi (utente_id, etichetta, nome_completo, indirizzo_riga1, citta, codice_postale, paese, creato_il) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [$userId, 'Indirizzo Checkout', $fullName, $line1, $city, $postalCode, $country]
                );
                $addressId = (int)db()->lastInsertId();
            } catch (Throwable $e) {
                $errors[] = 'Errore nella creazione dell\'indirizzo.';
            }
        } else {
            $errors[] = 'Compila tutti i campi dell\'indirizzo.';
        }
    }

    if (!$errors && $addressId > 0) {
        // Verify address belongs to user
        $address = db_run('SELECT id FROM indirizzi WHERE id = ? AND utente_id = ? LIMIT 1', [$addressId, $userId])->fetch();
        
        if (!$address) {
            $errors[] = 'Indirizzo non valido.';
        }
    } else if (!$errors) {
        $errors[] = 'Seleziona un indirizzo di spedizione.';
    }

    // Process order
    if (!$errors) {
        try {
            db()->beginTransaction();

            // Create order
            db_run(
                'INSERT INTO ordini (utente_id, stato, importo_totale, valuta, indirizzo_spedizione_id, indirizzo_fatturazione_id, effettuato_il) 
                 VALUES (?, "in_attesa", ?, "EUR", ?, ?, NOW())',
                [$userId, $total, $addressId, $addressId]
            );
            $orderId = (int)db()->lastInsertId();

            // Copy cart items to order items
            foreach ($cartItems as $item) {
                db_run(
                    'INSERT INTO articoli_ordine (ordine_id, prodotto_id, quantita, prezzo_unitario) 
                     VALUES (?, ?, ?, ?)',
                    [$orderId, (int)($item['id_prodotto'] ?? $item['product_id']), (int)($item['quantita'] ?? $item['quantity']), ($item['prezzo_unitario'] ?? $item['unit_price'])]
                );

                // Decrease stock
                db_run(
                    'UPDATE prodotti SET quantita_giacenza = quantita_giacenza - ? WHERE id = ?',
                    [(int)($item['quantita'] ?? $item['quantity']), (int)($item['id_prodotto'] ?? $item['product_id'])]
                );
            }

            // Clear cart items after order
            db_run('DELETE FROM articoli_carrello WHERE carrello_id = ?', [$cartId]);

            db()->commit();
            $success = true;

    } catch (Throwable $e) {
      if (db()->inTransaction()) db()->rollBack();
      $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'prod');
      if (strtolower($appEnv) === 'local') {
        $errors[] = 'Errore durante l\'elaborazione dell\'ordine: ' . htmlspecialchars($e->getMessage());
      } else {
        $errors[] = 'Errore durante l\'elaborazione dell\'ordine.';
      }
    }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header class="header">
    <div class="header-content">
  <a href="/" class="logo">🛒 Tech Hub</a>
      <nav class="nav">
        <span class="text-muted">Ciao, <strong><?= htmlspecialchars($user['nome'] ?? $user['first_name'] ?? 'Utente') ?></strong></span>
      </nav>
    </div>
  </header>

  <div class="container-sm">
    <h1 class="card-header">💳 Checkout</h1>

    <?php if ($success): ?>
      <div class="card">
        <div class="alert alert-success">
          <div style="text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
            <h2 style="margin-bottom: 0.5rem;">Ordine completato con successo!</h2>
            <p style="margin-bottom: 1.5rem;">Grazie per il tuo acquisto. Riceverai una conferma via email.</p>
            <a href="/" class="btn btn-primary btn-lg">← Torna al Catalogo</a>
          </div>
        </div>
      </div>
    <?php else: ?>

      <?php if ($errors): ?>
        <div class="alert alert-error">
          <strong>⚠️ Errori:</strong>
          <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="card">
        <h2 class="card-header" style="font-size: 1.25rem;">📦 Riepilogo Ordine</h2>
        <?php foreach ($cartItems as $item): ?>
          <div class="flex-between" style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-light);">
            <span><strong><?= htmlspecialchars($item['product_name']) ?></strong> × <?= (int)$item['quantity'] ?></span>
            <span style="font-weight: 600;"><?= number_format($item['line_total'], 2, ',', '.') ?> €</span>
          </div>
        <?php endforeach; ?>
        <div class="flex-between" style="padding-top: 1rem; margin-top: 1rem; border-top: 2px solid var(--dark); font-size: 1.5rem; font-weight: 800;">
          <span>Totale:</span>
          <span style="color: var(--secondary);"><?= number_format($total, 2, ',', '.') ?> €</span>
        </div>
      </div>

      <div class="card mt-3">
        <h2 class="card-header" style="font-size: 1.25rem;">📍 Indirizzo di Spedizione</h2>
        
        <form method="post" action="" class="form">
          <?= campo_csrf() ?>
          
          <?php if (!empty($addresses)): ?>
            <div style="display: grid; gap: 1rem;">
              <?php foreach ($addresses as $addr): ?>
                <label style="border: 2px solid var(--gray-light); padding: 1rem; border-radius: var(--radius); cursor: pointer; transition: var(--transition); display: flex; align-items: start; gap: 1rem;">
                  <input type="radio" name="address_id" value="<?= (int)$addr['id'] ?>" required style="margin-top: 0.25rem;">
                  <div>
                    <div style="font-weight: 700; margin-bottom: 0.25rem;"><?= htmlspecialchars($addr['full_name']) ?></div>
                    <div style="color: var(--gray); font-size: 0.875rem;">
                      <?= htmlspecialchars($addr['line1']) ?><br>
                      <?= htmlspecialchars($addr['city']) ?> <?= htmlspecialchars($addr['postal_code']) ?>, <?= htmlspecialchars($addr['country']) ?>
                    </div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
            
            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">
          <?php endif; ?>

          <div style="background: var(--light); padding: 1.5rem; border-radius: var(--radius); border: 2px dashed var(--gray-light);">
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; margin-bottom: 1rem; cursor: pointer;">
              <input type="checkbox" name="create_address" value="1" id="new_address_toggle" style="width: auto;">
              <span style="font-weight: 700;">➕ Usa un nuovo indirizzo</span>
            </label>
            
            <div id="new_address_fields" style="display: none;">
              <div class="form-group">
                <label for="full_name">Nome Completo</label>
                <input type="text" id="full_name" name="full_name" placeholder="Mario Rossi">
              </div>
              <div class="form-group">
                <label for="line1">Indirizzo</label>
                <input type="text" id="line1" name="line1" placeholder="Via Roma 123">
              </div>
              <div class="form-group">
                <label for="city">Città</label>
                <input type="text" id="city" name="city" placeholder="Bologna">
              </div>
              <div class="form-group">
                <label for="postal_code">CAP</label>
                <input type="text" id="postal_code" name="postal_code" placeholder="40100">
              </div>
              <div class="form-group">
                <label for="country">Nazione</label>
                <select id="country" name="country">
                  <option value="IT">Italia</option>
                  <option value="FR">Francia</option>
                  <option value="DE">Germania</option>
                  <option value="ES">Spagna</option>
                </select>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-secondary btn-block btn-lg mt-3">
            🎉 Conferma Ordine
          </button>
        </form>
      </div>

    <?php endif; ?>
  </div>

  <script>
    document.getElementById('new_address_toggle')?.addEventListener('change', function() {
      document.getElementById('new_address_fields').style.display = this.checked ? 'block' : 'none';
    });
  </script>
</body>
</html>
