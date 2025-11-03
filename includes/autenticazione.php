<?php
/**
 * Helper di autenticazione
 * Centralizza la logica di login, blocco account, registrazione tentativi
 */

require_once __DIR__ . '/connessione_db.php';
require_once __DIR__ . '/sessione.php';

// Configurazione
define('MAX_TENTATIVI_LOGIN', 5);
define('DURATA_BLOCCO_MINUTI', 15);

/**
 * Registra tentativi di autenticazione (successo o fallimento)
 */
function registra_tentativo_autenticazione(
    ?int $idUtente,
    string $emailTentata,
    bool $successo,
    string $motivo = 'ALTRO'
): void {
    $ipCompatto = @inet_pton($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

    db_run(
        'INSERT INTO tentativi_autenticazione (utente_id, email_tentata, indirizzo_ip, user_agent, successo, motivo)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$idUtente, $emailTentata, $ipCompatto, $userAgent, $successo ? 1 : 0, $motivo]
    );
}

/**
 * Verifica se l'account utente è attualmente bloccato
 * Ritorna array: ['bloccato' => bool, 'minuti_rimanenti' => int]
 */
function account_bloccato(int $idUtente): array {
    $riga = db_run(
        'SELECT bloccato_fino_a FROM utenti WHERE id = ? LIMIT 1',
        [$idUtente]
    )->fetch();

    if (!$riga || !$riga['bloccato_fino_a']) {
        return ['bloccato' => false, 'minuti_rimanenti' => 0];
    }

    $bloccatoFino = strtotime($riga['bloccato_fino_a']);
    $adesso = time();
    
    if ($bloccatoFino > $adesso) {
        $minutiRimanenti = (int)ceil(($bloccatoFino - $adesso) / 60);
        return ['bloccato' => true, 'minuti_rimanenti' => $minutiRimanenti];
    }

    // Blocco scaduto, resetta contatori
    db_run(
        'UPDATE utenti SET bloccato_fino_a = NULL, conteggio_tentativi_falliti = 0 WHERE id = ?',
        [$idUtente]
    );
    return ['bloccato' => false, 'minuti_rimanenti' => 0];
}

/**
 * Incrementa il contatore dei tentativi falliti e blocca l'account se si raggiunge la soglia
 * Ritorna array con 'bloccato' (bool) e 'tentativi_rimanenti' (int)
 */
function gestisci_login_fallito(int $idUtente): array {
    $utente = db_run(
        'SELECT conteggio_tentativi_falliti FROM utenti WHERE id = ? LIMIT 1',
        [$idUtente]
    )->fetch();

    if (!$utente) return ['bloccato' => false, 'tentativi_rimanenti' => MAX_TENTATIVI_LOGIN];

    $nuovoConteggio = (int)$utente['conteggio_tentativi_falliti'] + 1;
    $tentativiRimanenti = MAX_TENTATIVI_LOGIN - $nuovoConteggio;

    if ($nuovoConteggio >= MAX_TENTATIVI_LOGIN) {
        // Blocca account
        $bloccatoFino = date('Y-m-d H:i:s', time() + (DURATA_BLOCCO_MINUTI * 60));
        db_run(
            'UPDATE utenti SET conteggio_tentativi_falliti = ?, bloccato_fino_a = ? WHERE id = ?',
            [$nuovoConteggio, $bloccatoFino, $idUtente]
        );
        return ['bloccato' => true, 'tentativi_rimanenti' => 0];
    } else {
        // Incrementa solo il contatore
        db_run(
            'UPDATE utenti SET conteggio_tentativi_falliti = ? WHERE id = ?',
            [$nuovoConteggio, $idUtente]
        );
        return ['bloccato' => false, 'tentativi_rimanenti' => $tentativiRimanenti];
    }
}

/**
 * Resetta il contatore dei tentativi falliti dopo un login riuscito
 */
function resetta_tentativi_falliti(int $idUtente): void {
    db_run(
        'UPDATE utenti SET conteggio_tentativi_falliti = 0, bloccato_fino_a = NULL, ultimo_accesso_il = NOW() WHERE id = ?',
        [$idUtente]
    );
}

/**
 * Tenta login con email e password
 * Ritorna array: ['successo' => bool, 'errore' => string|null, 'id_utente' => int|null, 'ruolo' => string|null]
 */
function tentativo_login(string $email, string $password): array {
    $email = trim($email);

    if ($email === '' || $password === '') {
        return ['successo' => false, 'errore' => 'Email e password sono obbligatori.'];
    }

    // Trova utente
    $utente = db_run(
        'SELECT id, email, hash_password, attivo FROM utenti WHERE email = ? LIMIT 1',
        [$email]
    )->fetch();

    // Email sconosciuta
    if (!$utente) {
        registra_tentativo_autenticazione(null, $email, false, 'EMAIL_SCONOSCIUTA');
        return ['successo' => false, 'errore' => 'Credenziali non valide.'];
    }

    $idUtente = (int)$utente['id'];

    // Verifica se l'account è attivo
    if (!$utente['attivo']) {
        registra_tentativo_autenticazione($idUtente, $email, false, 'ACCOUNT_DISABILITATO');
        return ['successo' => false, 'errore' => '⛔ Account disabilitato. Contatta l\'amministratore per maggiori informazioni.'];
    }

    // Verifica se l'account è bloccato
    $statoBlocco = account_bloccato($idUtente);
    if ($statoBlocco['bloccato']) {
        registra_tentativo_autenticazione($idUtente, $email, false, 'BLOCCATO');
        $minuti = $statoBlocco['minuti_rimanenti'];
        return ['successo' => false, 'errore' => "🔒 Account bloccato per troppi tentativi falliti. Riprova tra $minuti minut" . ($minuti == 1 ? 'o' : 'i') . "."];
    }

    // Verifica password
    if (!password_verify($password, $utente['hash_password'])) {
        $risultatoFallimento = gestisci_login_fallito($idUtente);
        registra_tentativo_autenticazione($idUtente, $email, false, 'PASSWORD_ERRATA');
        
        if ($risultatoFallimento['bloccato']) {
            return ['successo' => false, 'errore' => '🔒 Account bloccato! Hai superato il numero massimo di tentativi (' . MAX_TENTATIVI_LOGIN . '). Riprova tra ' . DURATA_BLOCCO_MINUTI . ' minuti.'];
        } else {
            $rimanenti = $risultatoFallimento['tentativi_rimanenti'];
            return ['successo' => false, 'errore' => "❌ Credenziali non valide. Tentativi rimasti: $rimanenti"];
        }
    }

    // Successo! Ottieni ruolo utente
    $ruolo = db_run(
        'SELECT r.nome FROM ruoli r
         JOIN utenti_ruoli ur ON ur.ruolo_id = r.id
         WHERE ur.utente_id = ?
         ORDER BY r.id ASC LIMIT 1',
        [$idUtente]
    )->fetch();

    $nomeRuolo = $ruolo['nome'] ?? 'utente';

    // Resetta tentativi falliti
    resetta_tentativi_falliti($idUtente);
    registra_tentativo_autenticazione($idUtente, $email, true, 'OK');

    return [
        'successo' => true,
        'errore' => null,
        'id_utente' => $idUtente,
        'ruolo' => $nomeRuolo
    ];
}

/**
 * Ottieni i dati dell'utente attualmente connesso
 */
function ottieni_utente_connesso(): ?array {
    if (!utente_connesso()) {
        return null;
    }

    $idUtente = id_utente_corrente();
    $utente = db_run(
        'SELECT id, email, nome, cognome, creato_il FROM utenti WHERE id = ? LIMIT 1',
        [$idUtente]
    )->fetch();

    return $utente ?: null;
}

// Alias per compatibilità temporanea durante testing
function get_logged_user(): ?array { return ottieni_utente_connesso(); }
function attempt_login(string $email, string $password): array { return tentativo_login($email, $password); }
