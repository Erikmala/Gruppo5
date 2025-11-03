-- Migrazione: Correggi vincolo univoco carrelli per permettere più carrelli convertiti per utente
-- Versione idempotente: sicura da eseguire più volte

-- 1) Elimina vecchio unique (se esiste)
SET @exists_old := (
  SELECT 1
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'carrelli'
    AND INDEX_NAME = 'uq_carrello_utente_aperto'
  LIMIT 1
);
SET @sql := IF(@exists_old IS NOT NULL, 'ALTER TABLE carrelli DROP INDEX uq_carrello_utente_aperto', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Aggiungi colonna generata solo se non esiste (1 quando stato='aperto', altrimenti NULL)
SET @col_exists := (
  SELECT 1 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'carrelli'
    AND COLUMN_NAME = 'solo_aperto'
  LIMIT 1
);
SET @sql_col := IF(@col_exists IS NULL,
  'ALTER TABLE carrelli ADD COLUMN solo_aperto TINYINT GENERATED ALWAYS AS (CASE WHEN stato = ''aperto'' THEN 1 ELSE NULL END) STORED',
  'SELECT 1'
);
PREPARE stmtc FROM @sql_col; EXECUTE stmtc; DEALLOCATE PREPARE stmtc;

-- 3) Nuovo unique solo se non esiste: al massimo un carrello APERTO per utente
SET @exists_new := (
  SELECT 1
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'carrelli'
    AND INDEX_NAME = 'uq_carrello_uno_aperto'
  LIMIT 1
);
SET @sql2 := IF(@exists_new IS NULL,
  'ALTER TABLE carrelli ADD UNIQUE KEY uq_carrello_uno_aperto (utente_id, solo_aperto)',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- 4) Verifica opzionale
SHOW COLUMNS FROM carrelli LIKE 'solo_aperto';
SHOW INDEX FROM carrelli WHERE Key_name IN ('uq_carrello_utente_aperto','uq_carrello_uno_aperto');
