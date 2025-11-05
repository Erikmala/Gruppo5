
-- Crea database se non esiste (il container lo creerà anche da variabile d'ambiente)
CREATE DATABASE IF NOT EXISTS `tech_hub_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
USE `tech_hub_db`;

-- Tabella: ruoli
CREATE TABLE ruoli (
  id TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(50) NOT NULL UNIQUE, -- 'admin', 'utente'
  descrizione VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella: utenti
CREATE TABLE utenti (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(254) NOT NULL UNIQUE,
  hash_password VARCHAR(255) NOT NULL,
  nome VARCHAR(100) NULL,
  cognome VARCHAR(100) NULL,
  attivo BOOLEAN NOT NULL DEFAULT TRUE,
  ultimo_accesso_il DATETIME NULL,
  conteggio_tentativi_falliti INT UNSIGNED NOT NULL DEFAULT 0,
  bloccato_fino_a DATETIME NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_utenti_attivo ON utenti (attivo);
CREATE INDEX idx_utenti_bloccato_fino_a ON utenti (bloccato_fino_a);

-- Tabella: utenti_ruoli
CREATE TABLE utenti_ruoli (
  utente_id BIGINT UNSIGNED NOT NULL,
  ruolo_id TINYINT UNSIGNED NOT NULL,
  assegnato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (utente_id, ruolo_id),
  CONSTRAINT fk_utenti_ruoli_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
  CONSTRAINT fk_utenti_ruoli_ruolo FOREIGN KEY (ruolo_id) REFERENCES ruoli(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella: tentativi_autenticazione (log sicurezza)
-- Traccia tentativi di login riusciti e falliti
CREATE TABLE tentativi_autenticazione (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  utente_id BIGINT UNSIGNED NULL,
  email_tentata VARCHAR(254) NULL,
  indirizzo_ip VARBINARY(16) NULL, -- memorizza IPv4/IPv6 in formato compatto
  user_agent VARCHAR(512) NULL,
  successo BOOLEAN NOT NULL,
  motivo ENUM('OK','PASSWORD_ERRATA','EMAIL_SCONOSCIUTA','BLOCCATO','TROPPI_TENTATIVI','ALTRO') NOT NULL DEFAULT 'ALTRO',
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tentativi_autenticazione_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_tentativi_autenticazione_utente_data ON tentativi_autenticazione (utente_id, creato_il);
CREATE INDEX idx_tentativi_autenticazione_email_data ON tentativi_autenticazione (email_tentata, creato_il);

-- NUCLEO E-COMMERCE

CREATE TABLE categorie (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE prodotti (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  codice_sku VARCHAR(64) NOT NULL UNIQUE,
  nome VARCHAR(200) NOT NULL,
  descrizione TEXT NULL,
  prezzo DECIMAL(10,2) NOT NULL,
  valuta CHAR(3) NOT NULL DEFAULT 'EUR',
  quantita_giacenza INT NOT NULL DEFAULT 0,
  attivo BOOLEAN NOT NULL DEFAULT TRUE,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE prodotti_categorie (
  prodotto_id BIGINT UNSIGNED NOT NULL,
  categoria_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (prodotto_id, categoria_id),
  CONSTRAINT fk_prodcat_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE,
  CONSTRAINT fk_prodcat_categoria FOREIGN KEY (categoria_id) REFERENCES categorie(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE immagini_prodotti (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  prodotto_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  testo_alternativo VARCHAR(255) NULL,
  ordine_visualizzazione INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_immagini_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carrelli: un carrello per utente
CREATE TABLE carrelli (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  utente_id BIGINT UNSIGNED NOT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_carrello_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
  UNIQUE KEY uq_carrello_utente (utente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE articoli_carrello (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  carrello_id BIGINT UNSIGNED NOT NULL,
  prodotto_id BIGINT UNSIGNED NOT NULL,
  quantita INT NOT NULL CHECK (quantita > 0),
  prezzo_unitario DECIMAL(10,2) NOT NULL,
  totale_riga DECIMAL(10,2) AS (ROUND(quantita * prezzo_unitario, 2)) STORED,
  aggiunto_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_articoli_carrello_carrello FOREIGN KEY (carrello_id) REFERENCES carrelli(id) ON DELETE CASCADE,
  CONSTRAINT fk_articoli_carrello_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_carrello_prodotto (carrello_id, prodotto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE indirizzi (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  utente_id BIGINT UNSIGNED NOT NULL,
  etichetta VARCHAR(100) NULL, -- es. "Casa", "Lavoro"
  nome_completo VARCHAR(200) NOT NULL,
  indirizzo_riga1 VARCHAR(200) NOT NULL,
  indirizzo_riga2 VARCHAR(200) NULL,
  citta VARCHAR(120) NOT NULL,
  regione VARCHAR(120) NULL,
  codice_postale VARCHAR(20) NOT NULL,
  paese CHAR(2) NOT NULL,
  telefono VARCHAR(30) NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_indirizzo_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ordini (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  utente_id BIGINT UNSIGNED NOT NULL,
  stato ENUM('in_attesa','completato') NOT NULL DEFAULT 'in_attesa',
  importo_totale DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valuta CHAR(3) NOT NULL DEFAULT 'EUR',
  indirizzo_spedizione_id BIGINT UNSIGNED NOT NULL,
  indirizzo_fatturazione_id BIGINT UNSIGNED NOT NULL,
  effettuato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ordini_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ordini_indirizzo_spedizione FOREIGN KEY (indirizzo_spedizione_id) REFERENCES indirizzi(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ordini_indirizzo_fatturazione FOREIGN KEY (indirizzo_fatturazione_id) REFERENCES indirizzi(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE articoli_ordine (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ordine_id BIGINT UNSIGNED NOT NULL,
  prodotto_id BIGINT UNSIGNED NOT NULL,
  quantita INT NOT NULL CHECK (quantita > 0),
  prezzo_unitario DECIMAL(10,2) NOT NULL,
  totale_riga DECIMAL(10,2) AS (ROUND(quantita * prezzo_unitario, 2)) STORED,
  CONSTRAINT fk_articoli_ordine_ordine FOREIGN KEY (ordine_id) REFERENCES ordini(id) ON DELETE CASCADE,
  CONSTRAINT fk_articoli_ordine_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_articoli_ordine_ordine ON articoli_ordine (ordine_id);

-- Una vista che mostra gli utenti con blocco attivo
CREATE OR REPLACE VIEW v_utenti_bloccati AS
SELECT id, email, bloccato_fino_a, conteggio_tentativi_falliti
FROM utenti
WHERE bloccato_fino_a IS NOT NULL AND bloccato_fino_a > CURRENT_TIMESTAMP;

-- Dati iniziali di esempio (sicuro SOLO per sviluppo locale)
INSERT INTO ruoli (nome, descrizione) VALUES
  ('admin', 'Amministratore del sistema'),
  ('utente',  'Utente standard')
ON DUPLICATE KEY UPDATE descrizione=VALUES(descrizione);

-- NOTA: Password predefinita per sviluppo: AdminPass123!
-- Hash generato con: password_hash('AdminPass123!', PASSWORD_DEFAULT)
INSERT INTO utenti (email, hash_password, nome, cognome, attivo)
VALUES ('admin@example.com', '$2y$10$Id4OiP/ktMspvNG3VAJyGOjyK5ycvY6g8Zq27eyczpLu3hpurTgA.', 'Admin', 'Sistema', TRUE)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), cognome=VALUES(cognome);

-- Assegna ruolo admin
INSERT IGNORE INTO utenti_ruoli (utente_id, ruolo_id)
SELECT u.id, r.id
FROM utenti u CROSS JOIN ruoli r
WHERE u.email='admin@example.com' AND r.nome='admin';

-- Categorie e prodotti di esempio
INSERT INTO categorie (nome, slug) VALUES
  ('Libri', 'libri'),
  ('Elettronica', 'elettronica')
ON DUPLICATE KEY UPDATE nome=VALUES(nome);

INSERT INTO prodotti (codice_sku, nome, descrizione, prezzo, valuta, quantita_giacenza, attivo)
VALUES
  ('BOOK-001', 'Manuale Sicurezza Web', 'Best practice per sicurezza web.', 29.90, 'EUR', 100, TRUE),
  ('ELEC-001', 'Mouse Ottico', 'Mouse USB 1600 DPI', 14.50, 'EUR', 200, TRUE)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descrizione=VALUES(descrizione), prezzo=VALUES(prezzo), quantita_giacenza=VALUES(quantita_giacenza);

-- Collega prodotti alle categorie
INSERT IGNORE INTO prodotti_categorie (prodotto_id, categoria_id)
SELECT p.id, c.id FROM prodotti p JOIN categorie c ON ( (p.codice_sku='BOOK-001' AND c.slug='libri') OR (p.codice_sku='ELEC-001' AND c.slug='elettronica') );