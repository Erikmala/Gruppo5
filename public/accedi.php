<?php
require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valida_csrf()) {
        $errors[] = 'Token CSRF non valido.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$errors) {
        $risultato = tentativo_login($email, $password);

        if ($risultato['successo']) {
            $_SESSION['id_utente'] = $risultato['id_utente'];
            $_SESSION['ruolo']    = $risultato['ruolo'];
            rigenera_sessione();

            // Reindirizza in base al ruolo
            if ($risultato['ruolo'] === 'admin') {
                header('Location: /admin/pannello.php');
            } else {
                header('Location: /');
            }
            exit;
        } else {
            $errors[] = $risultato['errore'];
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accedi - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container-xs">
    <div class="card">
      <h1 class="card-header">🔐 Accedi al tuo account</h1>

      <?php if ($errors): ?>
        <div class="alert alert-error">
          <strong>⚠️ Errori:</strong>
          <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="" class="form">
        <?= campo_csrf() ?>
        
        <div class="form-group">
          <label for="email">📧 Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="tuo@email.com" required>
        </div>

        <div class="form-group">
          <label for="password">🔒 Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button class="btn btn-primary btn-block btn-lg" type="submit">
          ➜ Entra
        </button>
      </form>

      <p class="text-center mt-3">
        Non hai un account? <a href="/registrati.php" style="color: var(--primary); font-weight: 600;">Registrati ora</a>
      </p>
    </div>
  </div>
</body>
</html>
