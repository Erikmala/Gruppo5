<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

require_login();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $errors[] = 'Token CSRF non valido.';
    }

    $current = $_POST['current_password'] ?? '';
    $password = $_POST['new_password'] ?? '';
    $password2 = $_POST['new_password2'] ?? '';

    // Basic checks
    if ($current === '' || $password === '' || $password2 === '') {
        $errors[] = 'Compila tutti i campi.';
    }

    // Strong password policy (same as register.php)
    if (strlen($password) < 8) {
        $errors[] = 'La nuova password deve avere almeno 8 caratteri.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La nuova password deve contenere almeno una lettera maiuscola.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La nuova password deve contenere almeno una lettera minuscola.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La nuova password deve contenere almeno un numero.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'La nuova password deve contenere almeno un carattere speciale (!@#$%^&*...).';
    }

    if ($password !== $password2) {
        $errors[] = 'Le nuove password non coincidono.';
    }

    if (!$errors) {
        try {
            $userId = current_user_id();
            $user = db_run('SELECT id, hash_password FROM utenti WHERE id = ? LIMIT 1', [$userId])->fetch();
            if (!$user) {
                $errors[] = 'Utente non trovato.';
            } else {
                // Verify current password
                if (!password_verify($current, $user['password_hash'])) {
                    $errors[] = 'La password attuale non è corretta.';
                }

                // Ensure new password differs from current
                if (password_verify($password, $user['password_hash'])) {
                    $errors[] = 'La nuova password deve essere diversa da quella attuale.';
                }

                if (!$errors) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    db_run('UPDATE utenti SET hash_password = ?, aggiornato_il = NOW() WHERE id = ?', [$hash, $userId]);
                    $success = true;
                }
            }
        } catch (Throwable $e) {
            $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'prod');
            if (strtolower($appEnv) === 'local') {
                $errors[] = 'Errore durante l\'aggiornamento della password: ' . htmlspecialchars($e->getMessage());
            } else {
                $errors[] = 'Errore durante l\'aggiornamento della password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cambia Password - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
</head>
<body>
  <div class="container-xs">
    <div class="card">
      <h1 class="card-header">🔑 Cambia Password</h1>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <strong>✅ Password aggiornata!</strong>
          <div class="mt-1">La tua password è stata cambiata con successo.</div>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-error">
          <strong>⚠️ Correggi gli errori:</strong>
          <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="" class="form">
        <?= csrf_field() ?>

        <div class="form-group">
          <label for="current_password">🔒 Password Attuale</label>
          <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
          <label for="new_password">🆕 Nuova Password</label>
          <input type="password" id="new_password" name="new_password" placeholder="Almeno 8 caratteri" required>
          <div class="password-requirements">
            <strong>Requisiti password:</strong>
            • Almeno 8 caratteri<br>
            • Almeno 1 lettera maiuscola (A-Z)<br>
            • Almeno 1 lettera minuscola (a-z)<br>
            • Almeno 1 numero (0-9)<br>
            • Almeno 1 carattere speciale (!@#$%^&*...)
          </div>
        </div>

        <div class="form-group">
          <label for="new_password2">🔁 Conferma Nuova Password</label>
          <input type="password" id="new_password2" name="new_password2" required>
        </div>

        <div class="flex gap-2 mt-2">
          <button type="submit" class="btn btn-primary">Salva nuova password</button>
          <a href="/" class="btn btn-outline">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
