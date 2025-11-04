<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';
require __DIR__ . '/../includes/helper_immagini.php';

richiedi_login();

$userId = id_utente_corrente();
$user = ottieni_utente_connesso();

// Ottieni carrello dell'utente
$cart = db_run('SELECT id FROM carrelli WHERE utente_id = ? LIMIT 1', [$userId])->fetch();

$cartItems = [];
$total = 0;

if ($cart) {
    $cartId = (int)$cart['id'];
    $cartItems = db_run(
        'SELECT ac.*, p.nome as nome_prodotto, p.quantita_giacenza, p.codice_sku
         FROM articoli_carrello ac
         JOIN prodotti p ON p.id = ac.prodotto_id
         WHERE ac.carrello_id = ?
         ORDER BY ac.aggiunto_il DESC',
        [$cartId]
    )->fetchAll();

    foreach ($cartItems as $item) {
        $total += (float)$item['totale_riga'];
    }
}

$showAdded = isset($_GET['added']);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Carrello - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header class="header">
    <div class="header-content">
  <a href="/" class="logo">🛒 Tech Hub</a>
      <nav class="nav">
        <span class="text-muted">Ciao, <strong><?= htmlspecialchars($user['nome'] ?? 'Utente') ?></strong></span>
        <a href="/" class="nav-link">Catalogo</a>
        <a href="/carrello.php" class="nav-link">Carrello</a>
        <a href="/esci.php" class="btn btn-sm btn-outline">Esci</a>
      </nav>
    </div>
  </header>

  <div class="container-sm">
    <h1 class="card-header">🛒 Il tuo Carrello</h1>

    <?php if ($showAdded): ?>
      <div class="alert alert-success">✅ Prodotto aggiunto al carrello con successo!</div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
      <div class="card">
        <div class="empty-state">
          <div class="empty-state-icon">🛒</div>
          <h2 class="empty-state-title">Il carrello è vuoto</h2>
          <p class="empty-state-text">Aggiungi prodotti dal catalogo per iniziare!</p>
          <a href="/" class="btn btn-primary btn-lg">Vai al Catalogo</a>
        </div>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Immagine</th>
              <th>Prodotto</th>
              <th>Prezzo Unitario</th>
              <th>Quantità</th>
              <th>Totale</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cartItems as $item): ?>
              <tr>
                <td>
                  <img src="<?= htmlspecialchars(ottieni_url_immagine_prodotto($item['codice_sku'] ?? $item['sku'])) ?>" 
                       alt="<?= htmlspecialchars($item['nome_prodotto'] ?? $item['product_name']) ?>" 
                       style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                </td>
                <td><strong><?= htmlspecialchars($item['nome_prodotto'] ?? $item['product_name']) ?></strong></td>
                <td><?= number_format($item['prezzo_unitario'] ?? $item['unit_price'], 2, ',', '.') ?> €</td>
                <td>
                  <form method="post" action="/carrello_aggiorna.php" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="id_articolo" value="<?= (int)$item['id'] ?>">
                    <input type="number" name="quantita" value="<?= (int)($item['quantita'] ?? $item['quantity']) ?>" min="1" max="<?= (int)($item['quantita_giacenza'] ?? $item['stock_qty']) ?>" class="qty-input" style="width: 70px; padding: 0.5rem; border: 2px solid var(--gray-light); border-radius: var(--radius); text-align: center;">
                    <button type="submit" class="btn btn-sm btn-primary">✓</button>
                  </form>
                </td>
                <td><strong style="color: var(--secondary); font-size: 1.125rem;"><?= number_format($item['totale_riga'] ?? $item['line_total'], 2, ',', '.') ?> €</strong></td>
                <td>
                  <form method="post" action="/carrello_rimuovi.php" style="display: inline;">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="id_articolo" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">🗑️ Rimuovi</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card mt-3">
        <div class="flex-between" style="font-size: 1.75rem; font-weight: 800;">
          <span>Totale:</span>
          <span style="color: var(--secondary);"><?= number_format($total, 2, ',', '.') ?> €</span>
        </div>
      </div>

      <div class="flex-between mt-3">
        <a href="/" class="btn btn-outline btn-lg">← Continua lo shopping</a>
        <a href="/checkout.php" class="btn btn-secondary btn-lg">Procedi al Checkout →</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
