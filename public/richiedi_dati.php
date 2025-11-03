<?php
/**
 * Richiesta Dati Utente (GDPR - Diritto di Portabilità)
 * Consente all'utente di scaricare i propri dati in formato CSV
 */

require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

richiedi_login();

$idUtente = id_utente_corrente();

// Imposta header per download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dati_utente_tech_hub.csv"');

$output = fopen("php://output", "w");

// Aggiungi BOM UTF-8 per corretta visualizzazione caratteri accentati in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Intestazioni CSV (italiano)
fputcsv($output, ['id', 'nome', 'cognome', 'email', 'attivo', 'creato_il', 'aggiornato_il'], ';');

// Recupera dati utente
$stmt = db_run(
    "SELECT id, nome, cognome, email, attivo, creato_il, aggiornato_il 
     FROM utenti 
     WHERE id = ?", 
    [$idUtente]
);

while ($riga = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Converti booleano attivo in testo
    $riga['attivo'] = $riga['attivo'] ? 'Sì' : 'No';
    fputcsv($output, $riga, ';');
}

fclose($output);
exit;