<?php
/**
 * Helper per la gestione delle immagini dei prodotti
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

function ottieni_testo_alternativo_prodotto($nomeProdotto, $testoAltDb = null) {
    if (!empty($testoAltDb)) {
        return $testoAltDb;
    }
    
    return $nomeProdotto . ' - Immagine prodotto';
}

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