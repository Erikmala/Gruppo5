<?php
/**
 * Image Helper - Gestisce le immagini dei prodotti basate su SKU
 */

/**
 * Ottiene l'URL dell'immagine per un prodotto basandosi sul suo SKU
 * Cerca in ordine:
 * 1. Immagine nel DB (immagini_prodotti)
 * 2. File immagine con nome = SKU nelle estensioni: png, jpg, jpeg, webp, svg
 * 3. Placeholder generico
 * 
 * @param string $sku SKU del prodotto
 * @param string|null $urlImmagineDb URL immagine dal database (opzionale)
 * @return string URL dell'immagine da usare
 */
function ottieni_url_immagine_prodotto($sku, $urlImmagineDb = null) {
    // Se c'è un URL nel DB, usalo
    if (!empty($urlImmagineDb)) {
        return $urlImmagineDb;
    }
    
    // Altrimenti cerca file basato su SKU
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (__DIR__ . '/../public'), '/');
    $estensioni = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    
    foreach ($estensioni as $est) {
        $nomeFile = strtoupper($sku) . '.' . $est;
        $percorsoFile = $docRoot . '/assets/img/' . $nomeFile;
        $percorsoUrl = '/assets/img/' . $nomeFile;
        
        if (file_exists($percorsoFile)) {
            return $percorsoUrl;
        }
    }
    
    // Fallback a placeholder
    return '/assets/img/placeholder.svg';
}

/**
 * Ottiene l'alt text per l'immagine di un prodotto
 * 
 * @param string $nomeProdotto Nome del prodotto
 * @param string|null $testoAltDb Alt text dal database (opzionale)
 * @return string Alt text da usare
 */
function ottieni_testo_alternativo_prodotto($nomeProdotto, $testoAltDb = null) {
    if (!empty($testoAltDb)) {
        return $testoAltDb;
    }
    
    return $nomeProdotto . ' - Immagine prodotto';
}

/**
 * Genera il tag img completo per un prodotto
 * 
 * @param array $prodotto Array con i dati del prodotto (deve contenere almeno 'codice_sku' e 'nome')
 * @param string $classe Classe CSS per l'immagine (opzionale)
 * @return string Tag HTML <img>
 */
function tag_immagine_prodotto($prodotto, $classe = '') {
    $url = ottieni_url_immagine_prodotto(
        $prodotto['codice_sku'] ?? $prodotto['sku'] ?? '',
        $prodotto['url_immagine'] ?? $prodotto['image_url'] ?? null
    );
    
    $alt = ottieni_testo_alternativo_prodotto(
        $prodotto['nome'] ?? $prodotto['name'] ?? 'Prodotto',
        $prodotto['testo_alternativo'] ?? $prodotto['image_alt'] ?? null
    );
    
    $attrClasse = $classe ? ' class="' . htmlspecialchars($classe) . '"' : '';
    
    return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '"' . $attrClasse . '>';
}

// Alias per compatibilità temporanea durante testing
function get_product_image_url($sku, $dbImageUrl = null) { return ottieni_url_immagine_prodotto($sku, $dbImageUrl); }
function get_product_image_alt($productName, $dbAltText = null) { return ottieni_testo_alternativo_prodotto($productName, $dbAltText); }
