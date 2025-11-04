<?php

// Rileva HTTPS
function e_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    $urlApp = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
    if ($urlApp !== '' && str_starts_with(strtolower($urlApp), 'https://')) return true;
    return false;
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function avvia_sessione(): void {
    // Rafforza il comportamento delle sessioni PHP
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax'); // protegge la maggior parte dei CSRF su GET

    // Imposta flag secure solo quando HTTPS è rilevato (in dev su http deve essere 0)
    ini_set('session.cookie_secure', e_https() ? '1' : '0');

    // Durata ragionevole (regola secondo necessità)
    $durata = 60 * 60 * 2; // 2 ore
    ini_set('session.gc_maxlifetime', (string)$durata);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Crea chiavi base se mancanti
    $_SESSION['iniziata'] = $_SESSION['iniziata'] ?? time();
    $_SESSION['hash_ip']   = $_SESSION['hash_ip'] ?? hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/**
 * Rigenera ID sessione al login o cambio privilegio
 */
function rigenera_sessione(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['rigenerata_il'] = time();
    }
}

function utente_connesso(): bool {
    return !empty($_SESSION['id_utente']);
}

function id_utente_corrente(): ?int {
    return utente_connesso() ? (int)$_SESSION['id_utente'] : null;
}

function ruolo_utente_corrente(): ?string {
    return $_SESSION['ruolo'] ?? null; // 'utente' | 'admin'
}

function richiedi_login(): void {
    if (!utente_connesso()) {
        header('Location: /accedi.php');
        exit;
    }
}

function richiedi_admin(): void {
    if (!utente_connesso() || (ruolo_utente_corrente() !== 'admin')) {
        http_response_code(403);
        echo 'Accesso negato';
        exit;
    }
}

function token_csrf(): string {
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

function campo_csrf(): string {
    $token = token_csrf();
    return '<input type="hidden" name="token_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function valida_csrf(): bool {
    $token = $_POST['token_csrf'] ?? '';
    return is_string($token) && $token !== '' && hash_equals($_SESSION['token_csrf'] ?? '', $token);
}

// Avvia automaticamente le sessioni per tutte le richieste che includono questo file
avvia_sessione();
