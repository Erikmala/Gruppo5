-- Migrazione: Semplifica stati carrello e ordini
-- Data: 2025-10-29
-- Descrizione: Rimuove stati eccessivi e semplifica la struttura

-- ============================================================
-- 1. RIMUOVI TABELLE INUTILIZZATE
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `richieste_dati`;
DROP TABLE IF EXISTS `consensi_privacy`;
DROP TABLE IF EXISTS `verifiche_email`;
DROP TABLE IF EXISTS `reset_password`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 2. SEMPLIFICA TABELLA CARRELLI
-- ============================================================

-- Prima rimuovi FK
ALTER TABLE `carrelli` 
  DROP FOREIGN KEY `fk_carrello_utente`;

-- Rimuovi colonne e indici
ALTER TABLE `carrelli` 
  DROP INDEX `uq_carrello_uno_aperto`,
  DROP INDEX `idx_carrelli_sessione_aperta`,
  DROP COLUMN `id_sessione`,
  DROP COLUMN `stato`,
  DROP COLUMN `solo_aperto`;

-- Modifica colonna utente_id a NOT NULL
ALTER TABLE `carrelli`
  MODIFY COLUMN `utente_id` BIGINT UNSIGNED NOT NULL;

-- Ricrea FK con CASCADE e aggiungi UNIQUE
ALTER TABLE `carrelli`
  ADD CONSTRAINT `fk_carrello_utente` 
    FOREIGN KEY (`utente_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
  ADD UNIQUE KEY `uq_carrello_utente` (`utente_id`);

-- ============================================================
-- 3. SEMPLIFICA STATI ORDINI
-- ============================================================

-- Prima converti gli ordini esistenti con vecchi stati
-- Assicurati che tutti gli stati incompatibili siano aggiornati
UPDATE `ordini` 
SET `stato` = CASE 
  WHEN `stato` IN ('pagato', 'spedito', 'consegnato') THEN 'completato'
  WHEN `stato` IN ('annullato', 'rimborsato') THEN 'in_attesa'
  ELSE `stato`
END
WHERE `stato` NOT IN ('in_attesa', 'completato');

-- Ora modifica l'enum per avere solo 2 stati
ALTER TABLE `ordini` 
  MODIFY COLUMN `stato` ENUM('in_attesa','completato') NOT NULL DEFAULT 'in_attesa';

-- ============================================================
-- FINE MIGRAZIONE
-- ============================================================

-- Verifica risultati
SELECT 'Migrazione completata!' as stato;
SELECT COUNT(*) as totale_carrelli FROM carrelli;
SELECT COUNT(*) as totale_ordini, stato, COUNT(*) as conteggio 
FROM ordini 
GROUP BY stato;
