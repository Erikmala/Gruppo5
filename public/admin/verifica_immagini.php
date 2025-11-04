<?php

require __DIR__ . '/../../includes/sessione.php';
require __DIR__ . '/../../includes/connessione_db.php';
require __DIR__ . '/../../includes/autenticazione.php';
require __DIR__ . '/../../includes/helper_immagini.php';

// Solo per admin
richiedi_login();
if (ruolo_utente_corrente() !== 'admin') {
    die('Accesso negato. Solo amministratori.');
}

// Get all products
$products = db_run('SELECT id, codice_sku, nome as nome_prodotto, attivo FROM prodotti ORDER BY codice_sku')->fetchAll();

$stats = [
    'with_images' => 0,
    'without_images' => 0,
    'inactive' => 0
];

$issues = [];
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifica Immagini - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .status-icon { font-size: 1.5rem; }
    .img-preview { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid var(--gray-light); }
    .product-row { display: grid; grid-template-columns: 100px 150px 1fr 300px 100px; gap: 1rem; align-items: center; padding: 1rem; border-bottom: 1px solid var(--gray-light); }
    .product-row:hover { background: var(--light); }
  </style>
</head>
<body>
  <header class="header">
    <div class="header-content">
      <a href="/" class="logo">🛒 Tech Hub</a>
      <nav class="nav">
        <a href="/admin/pannello.php" class="nav-link">Dashboard</a>
        <a href="/" class="nav-link">Catalogo</a>
        <a href="/esci.php" class="btn btn-sm btn-outline">Esci</a>
      </nav>
    </div>
  </header>

  <div class="container">
    <h1 class="card-header">🖼️ Verifica Immagini Prodotti</h1>

    <div class="card">
      <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">📊 Statistiche</h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <?php
        foreach ($products as $product) {
            if (!$product['attivo']) {
                $stats['inactive']++;
                continue;
            }
            
            $imageUrl = ottieni_url_immagine_prodotto($product['codice_sku']);
            $hasImage = !str_contains($imageUrl, 'placeholder');
            
            if ($hasImage) {
                $stats['with_images']++;
            } else {
                $stats['without_images']++;
                $issues[] = $product['codice_sku'];
            }
        }
        ?>
        <div style="background: var(--light); padding: 1.5rem; border-radius: var(--radius); text-align: center;">
          <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
          <div style="font-size: 2rem; font-weight: 800; color: var(--success);"><?= $stats['with_images'] ?></div>
          <div style="color: var(--gray);">Con immagine</div>
        </div>
        <div style="background: var(--light); padding: 1.5rem; border-radius: var(--radius); text-align: center;">
          <div style="font-size: 2rem; margin-bottom: 0.5rem;">❌</div>
          <div style="font-size: 2rem; font-weight: 800; color: var(--danger);"><?= $stats['without_images'] ?></div>
          <div style="color: var(--gray);">Senza immagine</div>
        </div>
        <div style="background: var(--light); padding: 1.5rem; border-radius: var(--radius); text-align: center;">
          <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏸️</div>
          <div style="font-size: 2rem; font-weight: 800; color: var(--gray);"><?= $stats['inactive'] ?></div>
          <div style="color: var(--gray);">Inattivi</div>
        </div>
      </div>
    </div>

    <?php if (!empty($issues)): ?>
      <div class="alert alert-warning mt-3">
        <strong>⚠️ Immagini mancanti per:</strong>
        <div style="margin-top: 0.5rem; font-family: monospace;">
          <?= implode(', ', $issues) ?>
        </div>
        <p style="margin-top: 1rem; margin-bottom: 0;">
          Carica le immagini nella cartella <code>public/assets/img/</code> con nome = SKU<br>
          Esempio: <code>BOOK-001.png</code>, <code>ELEC-002.jpg</code>
        </p>
      </div>
    <?php endif; ?>

    <div class="card mt-3">
      <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">📦 Dettaglio Prodotti</h2>
      
      <div style="display: grid; grid-template-columns: 100px 150px 1fr 300px 100px; gap: 1rem; padding: 0.5rem 1rem; font-weight: 700; background: var(--light); border-radius: var(--radius);">
        <div>Status</div>
        <div>SKU</div>
        <div>Nome Prodotto</div>
        <div>Immagine</div>
        <div>Preview</div>
      </div>

      <?php foreach ($products as $product): ?>
        <?php
          if (!$product['attivo']) continue;
          
          $imageUrl = ottieni_url_immagine_prodotto($product['codice_sku']);
          $hasImage = !str_contains($imageUrl, 'placeholder');
        ?>
        <div class="product-row">
          <div class="status-icon"><?= $hasImage ? '✅' : '❌' ?></div>
          <div><code><?= htmlspecialchars($product['codice_sku']) ?></code></div>
          <div><?= htmlspecialchars($product['nome_prodotto']) ?></div>
          <div style="font-size: 0.875rem; color: var(--gray); word-break: break-all;">
            <?= htmlspecialchars($imageUrl) ?>
          </div>
          <div>
            <img src="<?= htmlspecialchars($imageUrl) ?>" 
                 alt="<?= htmlspecialchars($product['nome_prodotto']) ?>" 
                 class="img-preview">
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card mt-3">
      <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">📁 File nella cartella assets/img</h2>
      <?php
        $imgDir = __DIR__ . '/../public/assets/img/';
        if (!is_dir($imgDir)) {
            echo '<p class="text-muted">Cartella non trovata</p>';
        } else {
            $files = array_filter(scandir($imgDir), function($f) use ($imgDir) {
                return is_file($imgDir . $f) && $f[0] !== '.';
            });
            
            if (empty($files)) {
                echo '<p class="text-muted">Nessun file trovato</p>';
            } else {
                echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">';
                foreach ($files as $file) {
                    $filePath = '/assets/img/' . $file;
                    echo '<div style="border: 2px solid var(--gray-light); padding: 1rem; border-radius: var(--radius); text-align: center;">';
                    echo '<img src="' . htmlspecialchars($filePath) . '" style="width: 100%; max-height: 150px; object-fit: contain; margin-bottom: 0.5rem;">';
                    echo '<div style="font-size: 0.875rem; word-break: break-all;"><code>' . htmlspecialchars($file) . '</code></div>';
                    echo '</div>';
                }
                echo '</div>';
            }
        }
      ?>
    </div>
  </div>
</body>
</html>
