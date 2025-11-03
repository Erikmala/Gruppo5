-- Migrazione: Sistema immagini basato su SKU
-- Data: 2025-10-28
-- Descrizione: Aggiorna il sistema di gestione immagini per usare SKU come riferimento

USE `tech_hub_db`;

-- Rimuovi le vecchie immagini SVG placeholder dal database
-- Le immagini saranno ora caricate automaticamente dalla cartella assets/img
-- usando il nome del file = SKU del prodotto

DELETE FROM immagini_prodotti WHERE url LIKE '%/assets/img/%.svg';

-- Nota: Il sistema ora funziona così:
-- 1. Ogni prodotto ha uno SKU univoco (es: BOOK-001, ELEC-001)
-- 2. Le immagini sono salvate in public/assets/img/ con nome = SKU (es: BOOK-001.png)
-- 3. Il codice PHP cerca automaticamente l'immagine corrispondente allo SKU
-- 4. La tabella immagini_prodotti può essere usata per override specifici o immagini multiple
-- 5. Se non trova nulla, usa un placeholder generico

-- Opzionale: Aggiungi un commento alla tabella per documentare il nuovo sistema
ALTER TABLE immagini_prodotti 
COMMENT = 'Immagini prodotto. Sistema principale: file con nome=SKU in assets/img/. Questa tabella per override/immagini aggiuntive.';

-- Verifica che tutti i prodotti abbiano uno SKU valido
SELECT 
    p.id,
    p.codice_sku,
    p.nome,
    CASE 
        WHEN p.codice_sku IS NULL OR p.codice_sku = '' THEN 'SKU MANCANTE!'
        ELSE 'OK'
    END as stato
FROM prodotti p
ORDER BY p.id;
