<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';
require __DIR__ . '/../includes/helper_immagini.php';

// Ottieni prodotti con prima immagine e categorie
$products = db_run('SELECT 
                      p.*, 
                      GROUP_CONCAT(c.nome SEPARATOR ", ") as categorie,
                      (
                        SELECT ip.url FROM immagini_prodotti ip 
                        WHERE ip.prodotto_id = p.id 
                        ORDER BY ip.ordine_visualizzazione ASC, ip.id ASC LIMIT 1
                      ) AS url_immagine,
                      (
                        SELECT ip.testo_alternativo FROM immagini_prodotti ip 
                        WHERE ip.prodotto_id = p.id 
                        ORDER BY ip.ordine_visualizzazione ASC, ip.id ASC LIMIT 1
                      ) AS testo_alternativo_immagine
                    FROM prodotti p
                    LEFT JOIN prodotti_categorie pc ON pc.prodotto_id = p.id
                    LEFT JOIN categorie c ON c.id = pc.categoria_id
                    WHERE p.attivo = TRUE
                    GROUP BY p.id
                    ORDER BY p.creato_il DESC')->fetchAll();

// Ottieni categorie per i filtri
$categories = db_run('SELECT slug, nome FROM categorie ORDER BY nome ASC')->fetchAll();

// Ottieni conteggio articoli nel carrello
$cartCount = 0;
if (utente_connesso()) {
    $userId = id_utente_corrente();
    $cart = db_run('SELECT id FROM carrelli WHERE utente_id = ? LIMIT 1', [$userId])->fetch();
    if ($cart) {
        $cartCount = (int)db_run('SELECT SUM(quantita) as totale FROM articoli_carrello WHERE carrello_id = ?', [$cart['id']])->fetch()['totale'];
    }
}

$user = ottieni_utente_connesso();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Catalogo Prodotti - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
</head>
<body>
  <header class="header">
    <div class="header-content">
      <a href="/" class="logo">🛒 Tech Hub</a>
      <nav class="nav">
        <?php if ($user): ?>
          <span class="text-muted">Ciao, <strong><?= htmlspecialchars($user['nome'] ?? 'Utente') ?></strong></span>
          <a href="/carrello.php" class="nav-link">
            Carrello <?php if ($cartCount > 0): ?><span class="cart-badge"><?= $cartCount ?></span><?php endif; ?>
          </a>
          <?php if (ruolo_utente_corrente() === 'admin'): ?>
            <a href="/admin/pannello.php" class="nav-link">Dashboard Admin</a>
          <?php endif; ?>
          <a href="/cambia_password.php" class="nav-link">Cambia password</a>
          <a href="/elimina_account.php" class="nav-link">Elimina account</a>
          <a href="/esci.php" class="btn btn-sm btn-outline">Esci</a>
        <?php else: ?>
          <a href="/accedi.php" class="nav-link">Accedi</a>
          <a href="/registrati.php" class="btn btn-sm btn-primary">Registrati</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <div class="container">
    <h1 class="card-header">
      🛍️ Catalogo Prodotti
    </h1>

    <!-- Barra di ricerca prodotti -->
    <div class="card search-card">
      <form id="search-form" class="search-form-compact" role="search" aria-label="Cerca prodotti">
        <div class="search-row-main">
          <div class="search-input-wrapper">
            <span class="search-icon" aria-hidden="true">🔎</span>
            <input type="text" id="search-input" name="q" placeholder="Cerca per nome, SKU o categoria..." autocomplete="off">
          </div>
          <select id="categoria" name="categoria" title="Categoria">
            <option value="">Tutte le categorie</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="number" step="0.01" min="0" id="prezzo_min" name="prezzo_min" placeholder="Prezzo min" title="Prezzo minimo">
          <input type="number" step="0.01" min="0" id="prezzo_max" name="prezzo_max" placeholder="Prezzo max" title="Prezzo massimo">
          <select id="ordina_per" name="ordina_per" title="Ordina per">
            <option value="recenti">Più recenti</option>
            <option value="prezzo_asc">Prezzo ↑</option>
            <option value="prezzo_desc">Prezzo ↓</option>
            <option value="nome_asc">Nome A-Z</option>
          </select>
        </div>
        <div class="search-row-actions">
          <label class="checkbox-inline">
            <input type="checkbox" id="disponibili" name="disponibili" value="1">
            <span>Solo disponibili</span>
          </label>
          <div class="search-actions-right">
            <button type="submit" class="btn btn-primary btn-sm">Cerca</button>
            <button type="button" id="search-reset" class="btn btn-outline btn-sm">Pulisci</button>
            <span id="search-meta" class="search-meta"></span>
          </div>
        </div>
      </form>
    </div>

    <?php if (empty($products)): ?>
      <div class="card">
        <div class="empty-state">
          <div class="empty-state-icon">📦</div>
          <h2 class="empty-state-title">Nessun prodotto disponibile</h2>
          <p class="empty-state-text">Torna più tardi per vedere i nostri prodotti!</p>
        </div>
      </div>
    <?php else: ?>
      <div id="products-grid" class="products-grid">
        <?php foreach ($products as $product): ?>
          <div class="product-card">
            <div class="product-image">
              <?php 
                $imageUrl = get_product_image_url($product['codice_sku'] ?? $product['sku'], $product['url_immagine'] ?? $product['image_url'] ?? null);
                $imageAlt = get_product_image_alt($product['nome'] ?? $product['name'], $product['testo_alternativo_immagine'] ?? $product['image_alt'] ?? null);
              ?>
              <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($imageAlt) ?>">
            </div>
            <div class="product-body">
              <div class="product-category"><?= htmlspecialchars($product['categorie'] ?? $product['categories'] ?? 'Generale') ?></div>
              <h3 class="product-name"><?= htmlspecialchars($product['nome'] ?? $product['name']) ?></h3>
              <p class="product-desc line-clamp-3"><?= htmlspecialchars($product['descrizione'] ?? $product['description'] ?? '') ?></p>
              <div class="product-footer">
                <div>
                  <div class="product-price"><?= number_format($product['prezzo'] ?? $product['price'], 2, ',', '.') ?> €</div>
                  <div class="product-stock">
                    <?php if ((int)($product['quantita_giacenza'] ?? $product['stock_qty']) > 10): ?>
                      ✔️ Disponibile (<?= (int)($product['quantita_giacenza'] ?? $product['stock_qty']) ?>)
                    <?php elseif ((int)($product['quantita_giacenza'] ?? $product['stock_qty']) > 0): ?>
                      ⚠️ Ultimi <?= (int)($product['quantita_giacenza'] ?? $product['stock_qty']) ?> rimasti
                    <?php else: ?>
                      ❌​ Esaurito
                    <?php endif; ?>
                  </div>
                </div>
                <?php if (utente_connesso() && (int)($product['quantita_giacenza'] ?? $product['stock_qty']) > 0): ?>
                  <form method="post" action="/carrello_aggiungi.php" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_prodotto" value="<?= (int)$product['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-secondary">+ Carrello</button>
                  </form>
                <?php elseif (!utente_connesso()): ?>
                  <a href="/accedi.php" class="btn btn-sm btn-primary">Accedi</a>
                <?php else: ?>
                  <span class="badge badge-danger">Esaurito</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  
  <footer style="background: var(--bg-secondary, #f8f9fa); padding: 2rem 0; margin-top: 4rem; border-top: 1px solid var(--border);">
    <div class="container">
      <div style="text-align: center; color: var(--text-secondary, #6c757d); font-size: 0.9rem;">
        <p>© 2025 Tech Hub</p>
        <p style="margin-top: 0.5rem;">
          <a href="/informativa_privacy.php" style="color: var(--primary); margin: 0 1rem;">Informativa Privacy</a>
          <a href="/richiedi_dati.php" style="color: var(--primary); margin: 0 1rem;">Scarica i tuoi dati</a>
        </p>
      </div>
    </div>
  </footer>
  <script>
    (function() {
      const form = document.getElementById('search-form');
      const input = document.getElementById('search-input');
      const resetBtn = document.getElementById('search-reset');
      const grid = document.getElementById('products-grid');
      const meta = document.getElementById('search-meta');
      const categoria = document.getElementById('categoria');
      const prezzoMin = document.getElementById('prezzo_min');
      const prezzoMax = document.getElementById('prezzo_max');
      const disponibili = document.getElementById('disponibili');
      const ordinaPer = document.getElementById('ordina_per');

      if (!form || !input || !grid) return;

      // Debounce util
      function debounce(fn, wait) {
        let t; return function(...args){ clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
      }

      function setLoading(loading) {
        if (loading) {
          grid.style.opacity = '0.5';
        } else {
          grid.style.opacity = '';
        }
      }

      function updateMeta(q) {
        try {
          const count = grid.querySelectorAll('.product-card').length;
          if (q && q.trim() !== '') {
            meta.textContent = `Risultati: ${count} per "${q.trim()}"`;
          } else {
            meta.textContent = count > 0 ? `Prodotti totali: ${count}` : '';
          }
        } catch (_) { /* no-op */ }
      }

      function buildQuery() {
        const params = new URLSearchParams();
        const q = (input.value || '').trim();
        if (q) params.set('q', q);
        if (categoria && categoria.value) params.set('categoria', categoria.value);
        if (prezzoMin && prezzoMin.value) params.set('prezzo_min', prezzoMin.value);
        if (prezzoMax && prezzoMax.value) params.set('prezzo_max', prezzoMax.value);
        if (disponibili && disponibili.checked) params.set('disponibili', '1');
        if (ordinaPer && ordinaPer.value) params.set('ordina_per', ordinaPer.value);
        return params.toString();
      }

      async function doSearch() {
        setLoading(true);
        try {
          const qs = buildQuery();
          const url = '/ricerca.php' + (qs ? ('?' + qs) : '');
          const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          if (!res.ok) throw new Error('Errore di rete');
          const html = await res.text();
          grid.innerHTML = html;
          updateMeta(input.value);
        } catch (e) {
          grid.innerHTML = `
            <div class="card" style="grid-column: 1/-1;">
              <div class="alert alert-error">
                <strong>Errore</strong>
                <div>Impossibile caricare i risultati. Riprova.</div>
              </div>
            </div>`;
          meta.textContent = '';
        } finally {
          setLoading(false);
        }
      }

      const debounced = debounce(() => doSearch(), 300);

      form.addEventListener('submit', function(ev){ ev.preventDefault(); doSearch(); });
      input.addEventListener('input', function(){ debounced(); });
      categoria && categoria.addEventListener('change', function(){ doSearch(); });
      prezzoMin && prezzoMin.addEventListener('input', function(){ debounced(); });
      prezzoMax && prezzoMax.addEventListener('input', function(){ debounced(); });
      disponibili && disponibili.addEventListener('change', function(){ doSearch(); });
      ordinaPer && ordinaPer.addEventListener('change', function(){ doSearch(); });
      resetBtn.addEventListener('click', function(){ input.value=''; doSearch(''); });

      // Inizializza meta al primo render (server-side)
      updateMeta('');
    })();
  </script>
</body>
</html>

