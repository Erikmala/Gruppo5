<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valida_csrf()) {
        $errors[] = 'Token CSRF non valido.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $termini_accettati = isset($_POST['terminiecondizioni']) && $_POST['terminiecondizioni'] == '1';
    [$first, $last] = array_pad(preg_split('/\s+/', $full_name, 2, PREG_SPLIT_NO_EMPTY), 2, null);

    // Validazioni base
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    }
    
    // Validazione accettazione termini e condizioni
    if (!$termini_accettati) {
        $errors[] = 'Devi accettare i termini e condizioni per proseguire.';
    }
    
    // Validazione password forte
    if (strlen($password) < 8) {
        $errors[] = 'La password deve avere almeno 8 caratteri.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La password deve contenere almeno una lettera maiuscola.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La password deve contenere almeno una lettera minuscola.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La password deve contenere almeno un numero.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'La password deve contenere almeno un carattere speciale (!@#$%^&*()_+-=[]{}|;:,.<>?).';
    }
    
    if ($password !== $password2) {
        $errors[] = 'Le password non coincidono.';
    }
    if ($full_name === '') {
        $errors[] = 'Inserisci il nome completo.';
    }

    if (!$errors) {
        try {
            // Esiste già?
            $exists = db_run('SELECT id FROM utenti WHERE email = ? LIMIT 1', [$email])->fetch();
            if ($exists) {
                $errors[] = 'Esiste già un utente con questa email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Crea utente
                db()->beginTransaction();
                db_run('INSERT INTO utenti (email, hash_password, nome, cognome, attivo, creato_il) VALUES (?, ?, ?, ?, TRUE, NOW())', [$email, $hash, $first, $last]);

                $userId = (int)db()->lastInsertId();

                // Assegna ruolo di default: utente
                $role = db_run('SELECT id FROM ruoli WHERE nome = ? LIMIT 1', ['utente'])->fetch();
                if ($role) {
                    db_run('INSERT INTO utenti_ruoli (utente_id, ruolo_id) VALUES (?, ?)', [$userId, (int)$role['id']]);
                }

                db()->commit();
                $success = true;
            }
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'prod');
            if (strtolower($appEnv) === 'local') {
                $errors[] = 'Errore durante la registrazione: ' . htmlspecialchars($e->getMessage());
            } else {
                $errors[] = 'Errore durante la registrazione.';
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
  <title>Registrazione - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container-xs">
    <div class="card">
      <h1 class="card-header">Crea il tuo account</h1>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <strong>✅ Registrazione completata con successo.</strong>
          <p style="margin-top: 0.5rem;">
            Ora puoi <a href="/accedi.php" style="color: var(--secondary-dark); font-weight: 700; text-decoration: underline;">effettuare l'accesso</a> al tuo account.
          </p>
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

      <?php if (!$success): ?>
        <form method="post" action="" class="form">
          <?= campo_csrf() ?>
          
          <div class="form-group">
            <label for="full_name">🙎🏻‍♂️ Nome Completo</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="Mario Rossi" required>
          </div>

          <div class="form-group">
            <label for="email">📧 Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="tuo@email.com" required>
          </div>

          <div class="form-group">
            <label for="password">🔒 Password</label>
            <input type="password" id="password" name="password" placeholder="Almeno 8 caratteri" required>
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
            <label for="password2">🔒 Conferma Password</label>
            <input type="password" id="password2" name="password2" placeholder="Ripeti la password" required>
          </div>

          <div class="form-group" style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary, #f9f9f9); border-radius: var(--radius); border-left: 4px solid var(--primary);">
            <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; margin: 0;">
              <input type="checkbox" id="terminiecondizioni" name="terminiecondizioni" value="1" required style="width: auto; flex-shrink: 0;">
              <span>
                ⚖️ Accetto i <a href="/informativa_privacy.php" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Termini e Condizioni</a> 
                e l'<a href="/informativa_privacy.php" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Informativa Privacy</a>
              </span>
            </label>
            <div id="termini-error" style="color: var(--danger); font-size: 0.875rem; margin-top: 0.5rem; display: none;">
              ⚠️ Devi accettare i termini e condizioni per proseguire.
            </div>
          </div>

          <button class="btn btn-primary btn-block btn-lg" type="submit" onclick="return validateForm()">
            🚀 Registrati
          </button>
        </form>

        <p class="text-center mt-3">
          Hai già un account? <a href="/accedi.php" style="color: var(--primary); font-weight: 600;">Accedi</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

<script>
function validateForm() {
    var checkbox = document.getElementById("terminiecondizioni");
    var errorDiv = document.getElementById("termini-error");
    
    if (!checkbox.checked) {
        // Mostra messaggio di errore
        errorDiv.style.display = "block";
        
        // Scroll verso il checkbox per visibilità
        checkbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Impedisce l'invio del form
        return false;
    }
    
    // Nascondi l'errore se il checkbox è selezionato
    errorDiv.style.display = "none";
    return true;
}

// Nascondi errore quando l'utente seleziona il checkbox
document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.getElementById("terminiecondizioni");
    var errorDiv = document.getElementById("termini-error");
    
    if (checkbox && errorDiv) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                errorDiv.style.display = "none";
            }
        });
    }
});
</script>