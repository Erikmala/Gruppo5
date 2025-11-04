<?php


(function () {
    $percorsoEnv = __DIR__ . '/../.env';
    if (!is_readable($percorsoEnv)) {
        return; // nessun file .env, salta
    }
    $righe = file($percorsoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($righe as $riga) {
        $riga = trim($riga);
        if ($riga === '' || $riga[0] === '#') continue;
        $parti = explode('=', $riga, 2);
        if (count($parti) !== 2) continue;
        [$chiave, $valore] = $parti;
        $chiave = trim($chiave);
        $valore = trim($valore);
        // Rimuovi virgolette opzionali
        if ((str_starts_with($valore, '"') && str_ends_with($valore, '"')) ||
            (str_starts_with($valore, "'") && str_ends_with($valore, "'"))) {
            $valore = substr($valore, 1, -1);
        }
        putenv("{$chiave}={$valore}");
        $_ENV[$chiave] = $valore;
        $_SERVER[$chiave] = $valore;
    }
})();

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

// --- Leggi configurazione dall'ambiente (con valori predefiniti sensati per sviluppo locale) ---
$DB_HOST   = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT   = getenv('DB_PORT') ?: '3306';
$DB_NAME   = getenv('DB_NAME') ?: 'tech_hub_db';
$DB_USER   = getenv('DB_USER') ?: 'tech_hub_user';
$DB_PASS   = getenv('DB_PASS') ?: 'admin123';
$DB_CHSET  = getenv('DB_CHARSET') ?: 'utf8mb4';
$DB_SSL_CA = getenv('DB_SSL_CA') ?: null; // percorso al bundle CA se necessario

// --- Costruisci DSN ---
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $DB_HOST, $DB_PORT, $DB_NAME, $DB_CHSET);

// --- Opzioni PDO ---
$PDO_OPTIONS = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lancia eccezioni
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // array associativi
    PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement nativi
    PDO::ATTR_PERSISTENT         => false,                  // nessuna connessione persistente per default
];

if ($DB_SSL_CA) {
    // Se SSL è configurato (es. MySQL cloud), abilitalo
    $PDO_OPTIONS[PDO::MYSQL_ATTR_SSL_CA] = $DB_SSL_CA;
}

// --- Crea un'istanza PDO singleton ---
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $dsn, $DB_USER, $DB_PASS, $PDO_OPTIONS;

    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $PDO_OPTIONS);
        // Assicura SQL mode e timezone (opzionale ma buona pratica)
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_safe_updates = 0");
        return $pdo;
    } catch (PDOException $e) {
        // In produzione, evita di mostrare le credenziali; registra in modo sicuro invece
        http_response_code(500);
        die('Connessione al database fallita.');
    }
}

function db_run(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    foreach ($params as $i => $val) {
        // Bind parametri posizionali 1-indexed
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->execute();
    return $stmt;
}

?>
