USE `tech_hub_db`;

-- Aggiorna password admin a: Admin450%
UPDATE utenti 
SET hash_password = '$2y$10$fNWsZAJqbROEOVZIp86oPeMYmGqqSYVHRrXsTQZaVwPdDDjyjEMaC',
    email = 'admin@gmail.com'
WHERE email = 'admin@example.com' OR email = 'admin@gmail.com';

-- Crea utente di test: mario.rossi@gmail.com / MarioRossi15$
INSERT INTO utenti (email, hash_password, nome, cognome)
VALUES ('mario.rossi@gmail.com', '$2y$10$ixOO4x0/04PsJ22UTl0Mu.sGPLNnsE9MHU66iTc7twpV7Y3jW5FpK', 'Mario', 'Rossi')
ON DUPLICATE KEY UPDATE 
    hash_password = '$2y$10$ixOO4x0/04PsJ22UTl0Mu.sGPLNnsE9MHU66iTc7twpV7Y3jW5FpK',
    nome = 'Mario',
    cognome = 'Rossi';

SET @utente_id = (SELECT id FROM utenti WHERE email = 'mario.rossi@gmail.com' LIMIT 1);

-- Assegna ruolo 'utente'
INSERT IGNORE INTO utenti_ruoli (utente_id, ruolo_id)
SELECT @utente_id, r.id FROM ruoli r WHERE r.nome='utente';

-- Aggiungi altri prodotti di test
INSERT INTO prodotti (codice_sku, nome, descrizione, prezzo, valuta, quantita_giacenza, attivo)
VALUES
  ('BOOK-002', 'PHP per Principianti', 'Guida completa al linguaggio PHP moderno.', 24.90, 'EUR', 50, TRUE),
  ('BOOK-003', 'Database Design', 'Progettazione di database relazionali.', 32.50, 'EUR', 30, TRUE),
  ('ELEC-002', 'Tastiera Meccanica', 'Tastiera gaming RGB', 89.90, 'EUR', 25, TRUE),
  ('ELEC-003', 'Webcam HD', 'Webcam 1080p per videochiamate', 45.00, 'EUR', 40, TRUE),
  ('ELEC-004', 'Cuffie Bluetooth', 'Cuffie wireless con cancellazione rumore', 129.90, 'EUR', 15, TRUE)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descrizione=VALUES(descrizione), prezzo=VALUES(prezzo), quantita_giacenza=VALUES(quantita_giacenza);

-- Collega i nuovi prodotti alle categorie
INSERT IGNORE INTO prodotti_categorie (prodotto_id, categoria_id)
SELECT p.id, c.id FROM prodotti p JOIN categorie c ON (
  (p.codice_sku IN ('BOOK-002', 'BOOK-003') AND c.slug='libri') OR
  (p.codice_sku IN ('ELEC-002', 'ELEC-003', 'ELEC-004') AND c.slug='elettronica')
);

-- Categorie
INSERT INTO categorie (nome, slug) VALUES
  ('Smartphone', 'smartphone'),
  ('Accessori', 'accessori'),
  ('Software', 'software'),
  ('Gaming', 'gaming'),
  ('Audio', 'audio')
ON DUPLICATE KEY UPDATE nome=VALUES(nome);

-- Ulteriori prodotti
INSERT INTO prodotti (codice_sku, nome, descrizione, prezzo, valuta, quantita_giacenza, attivo)
VALUES
  ('SMART-001', 'Smartphone Pro 128GB', 'Display OLED 6.1", doppia fotocamera, 5G.', 899.00, 'EUR', 20, TRUE),
  ('SMART-002', 'Smartphone Lite 64GB', 'Schermo 6.4" IPS, ottimo rapporto qualità/prezzo.', 249.99, 'EUR', 0, TRUE), -- esaurito
  ('ACC-001', 'Power Bank 20000mAh', 'Ricarica rapida USB-C PD 22.5W.', 39.90, 'EUR', 120, TRUE),
  ('ACC-002', 'Cavo USB-C 1m', 'Cavo in nylon intrecciato, 60W.', 9.99, 'EUR', 300, TRUE),
  ('SOFT-001', 'Suite Antivirus (1 anno)', 'Licenza digitale per 3 dispositivi.', 29.90, 'EUR', 1000, TRUE),
  ('GAM-001', 'Controller Wireless', 'Compatibile PC/Console via Bluetooth.', 59.90, 'EUR', 45, TRUE),
  ('AUDIO-001', 'Speaker Bluetooth Portatile', 'Autonomia 12h, resistente agli spruzzi.', 49.90, 'EUR', 60, TRUE),
  ('MON-001', 'Monitor 27" 144Hz', 'Pannello IPS, 1ms MPRT, FreeSync.', 279.00, 'EUR', 18, TRUE),
  ('LAP-001', 'Notebook 15" i7 16GB/512GB', 'Ottimo per produttività e studio.', 1099.00, 'EUR', 7, TRUE), -- stock basso
  ('TAB-001', 'Tablet 11" 128GB', 'Schermo 120Hz, penna opzionale.', 399.00, 'EUR', 35, TRUE),
  ('ELEC-005', 'Router Wi-Fi 6', 'Dual-band, MU-MIMO, 4 antenne.', 89.00, 'EUR', 22, TRUE),
  ('ACC-003', 'Adattatore USB-C / HDMI', 'Supporto 4K@60Hz, compatto.', 19.90, 'EUR', 80, TRUE),
  ('DIS-001', 'Prodotto non piu in vendita', 'Esempio di prodotto inattivo, escluso dalla ricerca.', 19.99, 'EUR', 10, FALSE)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descrizione=VALUES(descrizione), prezzo=VALUES(prezzo), quantita_giacenza=VALUES(quantita_giacenza), attivo=VALUES(attivo);

-- Collegamenti prodotto ↔ categorie
INSERT IGNORE INTO prodotti_categorie (prodotto_id, categoria_id)
SELECT p.id, c.id FROM prodotti p JOIN categorie c ON (
  (p.codice_sku IN ('SMART-001','SMART-002') AND c.slug='smartphone') OR
  (p.codice_sku IN ('ACC-001','ACC-002','ACC-003') AND c.slug='accessori') OR
  (p.codice_sku IN ('GAM-001') AND c.slug='accessori') OR
  (p.codice_sku IN ('SOFT-001') AND c.slug='software') OR
  (p.codice_sku IN ('GAM-001') AND c.slug='gaming') OR
  (p.codice_sku IN ('AUDIO-001') AND c.slug='audio') OR
  (p.codice_sku IN ('MON-001','LAP-001','TAB-001','ELEC-005') AND c.slug='elettronica')
);

-- Indirizzi per Mario Rossi
INSERT INTO indirizzi (utente_id, etichetta, nome_completo, indirizzo_riga1, citta, codice_postale, paese)
VALUES
(@utente_id, 'Casa', 'Mario Rossi', 'Via Roma 1', 'Bologna', '40100', 'IT'),
(@utente_id, 'Ufficio', 'Mario Rossi', 'Via Ufficio 2', 'Bologna', '40100', 'IT')
ON DUPLICATE KEY UPDATE etichetta=VALUES(etichetta);

-- Carrello per Mario
INSERT INTO carrelli (utente_id) VALUES (@utente_id)
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), aggiornato_il=NOW();
SET @carrello_id = LAST_INSERT_ID();

-- Aggiungi articoli al carrello
INSERT INTO articoli_carrello (carrello_id, prodotto_id, quantita, prezzo_unitario)
SELECT @carrello_id, p.id, 2, p.prezzo FROM prodotti p WHERE p.codice_sku='BOOK-001'
ON DUPLICATE KEY UPDATE quantita=VALUES(quantita);
INSERT INTO articoli_carrello (carrello_id, prodotto_id, quantita, prezzo_unitario)
SELECT @carrello_id, p.id, 1, p.prezzo FROM prodotti p WHERE p.codice_sku='ELEC-001'
ON DUPLICATE KEY UPDATE quantita=VALUES(quantita);

-- Simula checkout: crea ordine usando gli indirizzi predefiniti
SET @indirizzo_spedizione = (SELECT id FROM indirizzi WHERE utente_id=@utente_id ORDER BY id LIMIT 1);
SET @indirizzo_fatturazione = (SELECT id FROM indirizzi WHERE utente_id=@utente_id ORDER BY id LIMIT 1);

INSERT INTO ordini (utente_id, stato, importo_totale, valuta, indirizzo_spedizione_id, indirizzo_fatturazione_id)
VALUES (@utente_id, 'completato',
        (SELECT IFNULL(SUM(totale_riga),0) FROM articoli_carrello WHERE carrello_id=@carrello_id),
        'EUR', @indirizzo_spedizione, @indirizzo_fatturazione)
ON DUPLICATE KEY UPDATE stato='completato';
SET @ordine_id = LAST_INSERT_ID();

-- Articoli ordine dal carrello
INSERT INTO articoli_ordine (ordine_id, prodotto_id, quantita, prezzo_unitario)
SELECT @ordine_id, ac.prodotto_id, ac.quantita, ac.prezzo_unitario
FROM articoli_carrello ac WHERE ac.carrello_id=@carrello_id
ON DUPLICATE KEY UPDATE quantita=VALUES(quantita);

-- Svuota carrello dopo l'ordine
DELETE FROM articoli_carrello WHERE carrello_id=@carrello_id;

-- Diminuisci giacenza prodotti in base all'ordine
UPDATE prodotti p
JOIN articoli_ordine ao ON ao.prodotto_id = p.id AND ao.ordine_id = @ordine_id
SET p.quantita_giacenza = p.quantita_giacenza - ao.quantita
WHERE p.quantita_giacenza >= ao.quantita;

-- Summary
SELECT '=== TEST DATA LOADED ===' as status;
SELECT 'Admin: admin@gmail.com / Admin450%' as credentials;
SELECT 'User: mario.rossi@gmail.com / MarioRossi15$' as credentials;

SELECT * FROM ordini WHERE id=@ordine_id;
SELECT * FROM articoli_ordine WHERE ordine_id=@ordine_id;
