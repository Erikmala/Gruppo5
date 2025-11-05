<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/helper_immagini.php';

header('Content-Type: text/html; charset=utf-8');

// Parametri input (tutti opzionali)
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$categoria   = isset($_GET['categoria']) ? trim((string)$_GET['categoria']) : '';
$prezzoMin   = isset($_GET['prezzo_min']) ? (string)$_GET['prezzo_min'] : '';
$prezzoMax   = isset($_GET['prezzo_max']) ? (string)$_GET['prezzo_max'] : '';
$disponibili = isset($_GET['disponibili']) ? (string)$_GET['disponibili'] : '';
$ordinaPer   = isset($_GET['ordina_per']) ? (string)$_GET['ordina_per'] : 'recenti';

// Sanitizzazione leggera
if (mb_strlen($q) > 100) { $q = mb_substr($q, 0, 100); }
if ($categoria !== '' && !preg_match('/^[a-z0-9\-]+$/i', $categoria)) { $categoria = ''; }

$prezzoMinVal = null;
$prezzoMaxVal = null;
if ($prezzoMin !== '' && is_numeric(str_replace(',', '.', $prezzoMin))) {
    $prezzoMinVal = (float)str_replace(',', '.', $prezzoMin);
}
if ($prezzoMax !== '' && is_numeric(str_replace(',', '.', $prezzoMax))) {
    $prezzoMaxVal = (float)str_replace(',', '.', $prezzoMax);
}
$soloDisponibili = ($disponibili === '1' || strtolower($disponibili) === 'true');

// Costruzione query con filtri
$joins = ' LEFT JOIN prodotti_categorie pc ON pc.prodotto_id = p.id
           LEFT JOIN categorie c ON c.id = pc.categoria_id ';
$where = ' WHERE p.attivo = TRUE ';
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where .= ' AND (p.nome LIKE ? OR p.descrizione LIKE ? OR p.codice_sku LIKE ? OR c.nome LIKE ?) ';
    array_push($params, $like, $like, $like, $like);
}

if ($categoria !== '') {
    $where .= ' AND EXISTS (
                    SELECT 1 FROM prodotti_categorie pc2
                    JOIN categorie c2 ON c2.id = pc2.categoria_id
                    WHERE pc2.prodotto_id = p.id AND c2.slug = ?
               ) ';
    $params[] = $categoria;
}

if ($prezzoMinVal !== null) {
    $where .= ' AND p.prezzo >= ? ';
    $params[] = $prezzoMinVal;
}
if ($prezzoMaxVal !== null) {
    $where .= ' AND p.prezzo <= ? ';
    $params[] = $prezzoMaxVal;
}

if ($soloDisponibili) {
    $where .= ' AND p.quantita_giacenza > 0 ';
}

// Ordinamento
switch ($ordinaPer) {
    case 'prezzo_asc':
        $orderBy = ' ORDER BY p.prezzo ASC, p.id DESC ';
        break;
    case 'prezzo_desc':
        $orderBy = ' ORDER BY p.prezzo DESC, p.id DESC ';
        break;
    case 'nome_asc':
        $orderBy = ' ORDER BY p.nome ASC, p.id DESC ';
        break;
    case 'recenti':
    default:
        $orderBy = ' ORDER BY p.creato_il DESC ';
        break;
}

$sql = 'SELECT 
            p.*, 
            GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ", ") as categorie,
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
        FROM prodotti p ' 
        . $joins 
        . $where .
        ' GROUP BY p.id ' 
        . $orderBy;

$products = db_run($sql, $params)->fetchAll();

if (!$products || count($products) === 0) {
    echo '<div class="card" style="grid-column: 1 / -1;">'
       . '<div class="empty-state">'
       . '<div class="empty-state-icon">🔍</div>'
       . '<h2 class="empty-state-title">Nessun risultato</h2>'
       . '<p class="empty-state-text">Non abbiamo trovato prodotti per la tua ricerca.</p>'
       . '</div>'
       . '</div>';
    exit;
}

foreach ($products as $product) {
    $imageUrl = ottieni_url_immagine_prodotto($product['codice_sku'] ?? $product['sku'], $product['url_immagine'] ?? $product['image_url'] ?? null);
    $imageAlt = ottieni_testo_alternativo_prodotto($product['nome'] ?? $product['name'], $product['testo_alternativo_immagine'] ?? $product['image_alt'] ?? null);
    $categorie = htmlspecialchars($product['categorie'] ?? $product['categories'] ?? 'Generale');
    $nome = htmlspecialchars($product['nome'] ?? $product['name']);
    $desc = htmlspecialchars($product['descrizione'] ?? $product['description'] ?? '');
    $prezzo = number_format($product['prezzo'] ?? $product['price'], 2, ',', '.');
    $stock = (int)($product['quantita_giacenza'] ?? $product['stock_qty']);
    $id = (int)$product['id'];

    echo '<div class="product-card">';
    echo '  <div class="product-image">';
    echo '    <img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($imageAlt) . '">';
    echo '  </div>';
    echo '  <div class="product-body">';
    echo '    <div class="product-category">' . $categorie . '</div>';
    echo '    <h3 class="product-name">' . $nome . '</h3>';
    echo '    <p class="product-desc line-clamp-3">' . $desc . '</p>';
    echo '    <div class="product-footer">';
    echo '      <div>';
    echo '        <div class="product-price">' . $prezzo . ' €</div>';
    echo '        <div class="product-stock">';
    if ($stock > 10) {
        echo '✔️ Disponibile (' . $stock . ')';
    } elseif ($stock > 0) {
        echo '⚠️ Ultimi ' . $stock . ' rimasti';
    } else {
        echo '❌​ Esaurito';
    }
    echo '        </div>';
    echo '      </div>';

    if (utente_connesso() && $stock > 0) {
        echo '      <form method="post" action="/carrello_aggiungi.php" style="margin: 0;">';
        echo campo_csrf();
        echo '        <input type="hidden" name="id_prodotto" value="' . $id . '">';
        echo '        <button type="submit" class="btn btn-sm btn-secondary">+ Carrello</button>';
        echo '      </form>';
    } elseif (!utente_connesso()) {
        echo '      <a href="/accedi.php" class="btn btn-sm btn-primary">Accedi</a>';
    } else {
        echo '      <span class="badge badge-danger">Esaurito</span>';
    }

    echo '    </div>'; // product-footer
    echo '  </div>'; // product-body
    echo '</div>'; // product-card
}
