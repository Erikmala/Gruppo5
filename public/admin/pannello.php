<?php
require __DIR__ . '/../../includes/sessione.php';
require __DIR__ . '/../../includes/connessione_db.php';
require __DIR__ . '/../../includes/autenticazione.php';

require_admin();

$user = get_logged_user();

// Helpers
function fmt_currency(float $v, string $cur = 'EUR'): string {
  $num = number_format($v, 2, ',', '.');
  return ($cur === 'EUR' ? $num . ' €' : $cur . ' ' . $num);
}
function fmt_date(string $dt): string {
  return date('Y-m-d H:i:s', strtotime($dt));
}
function reason_label(string $reason): string {
  $map = [
    'PASSWORD_ERRATA' => 'Password errata',
    'WRONG_PASSWORD' => 'Password errata',
    'EMAIL_SCONOSCIUTA' => 'Email sconosciuta',
    'UNKNOWN_EMAIL'  => 'Email sconosciuta',
    'BLOCCATO' => 'Account bloccato',
    'LOCKED'         => 'Account bloccato',
    'TROPPI_TENTATIVI' => 'Troppi tentativi',
    'TOO_MANY_ATTEMPTS' => 'Troppi tentativi',
    'OK'             => 'OK',
    'ALTRO'          => 'Altro',
    'OTHER'          => 'Altro',
  ];
  return $map[$reason] ?? $reason;
}

// Range filtro (viste amministrative)
$range = $_GET['range'] ?? '7d'; // 24h | 7d | 30d | all
switch ($range) {
  case '24h': $whereRange = 'AND ta.creato_il >= DATE_SUB(NOW(), INTERVAL 1 DAY)'; $ordersRange = 'AND o.effettuato_il >= DATE_SUB(NOW(), INTERVAL 1 DAY)'; break;
  case '30d': $whereRange = 'AND ta.creato_il >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; $ordersRange = 'AND o.effettuato_il >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; break;
  case 'all': $whereRange = ''; $ordersRange = ''; break;
  case '7d':
  default:    $whereRange = 'AND ta.creato_il >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; $ordersRange = 'AND o.effettuato_il >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

// Statistics
$stats = [
  'users'    => (int)db_run('SELECT COUNT(*) as cnt FROM utenti')->fetch()['cnt'],
  'orders'   => (int)db_run('SELECT COUNT(*) as cnt FROM ordini')->fetch()['cnt'],
  'products' => (int)db_run('SELECT COUNT(*) as cnt FROM prodotti')->fetch()['cnt'],
  'revenue'  => (float)(db_run('SELECT COALESCE(SUM(importo_totale),0) as totale FROM ordini WHERE stato = "completato"')->fetch()['totale'] ?? 0),
];

// Recent failed login attempts
// Failed attempts (with IP decoded) limited by range
$failedAttempts = db_run(
  'SELECT email_tentata, INET6_NTOA(indirizzo_ip) AS ip, user_agent, motivo, creato_il 
   FROM tentativi_autenticazione ta
   WHERE ta.successo = 0 ' . $whereRange . '
   ORDER BY ta.creato_il DESC 
   LIMIT 10'
)->fetchAll();

$failedCount = (int)db_run(
  'SELECT COUNT(*) AS cnt FROM tentativi_autenticazione ta WHERE ta.successo = 0 ' . $whereRange
)->fetch()['cnt'];

// Locked accounts
$lockedAccounts = db_run(
    'SELECT * FROM v_utenti_bloccati ORDER BY bloccato_fino_a DESC'
)->fetchAll();

// Recent orders
$recentOrders = db_run(
  'SELECT o.*, u.email as email_utente 
   FROM ordini o
   JOIN utenti u ON u.id = o.utente_id
   WHERE 1=1 ' . $ordersRange . '
   ORDER BY o.effettuato_il DESC
   LIMIT 10'
)->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Admin - Tech Hub</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <header class="header">
    <div class="header-content">
      <a href="/admin/pannello.php" class="logo" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent;">🔒 Admin Dashboard</a>
      <nav class="nav">
        <span class="text-muted">Admin: <strong><?= htmlspecialchars($user['nome'] ?? $user['first_name'] ?? 'Admin') ?></strong></span>
        <a href="/" class="nav-link">Vai al Sito</a>
        <a href="/cambia_password.php" class="nav-link">Cambia password</a>
        <a href="/esci.php" class="btn btn-sm btn-danger">Esci</a>
      </nav>
    </div>
  </header>

  <div class="container">
    <h1 class="card-header">📊 Dashboard Amministrativa</h1>
    <div class="card" style="margin-bottom: 1.5rem;">
      <form method="get" class="form" style="display:flex; gap: 1rem; align-items:center; flex-wrap: wrap;">
        <label for="range" style="font-weight:600;">Intervallo dati:</label>
        <select id="range" name="range">
          <option value="24h" <?= $range==='24h'?'selected':'' ?>>Ultime 24 ore</option>
          <option value="7d" <?= $range==='7d'?'selected':'' ?>>Ultimi 7 giorni</option>
          <option value="30d" <?= $range==='30d'?'selected':'' ?>>Ultimi 30 giorni</option>
          <option value="all" <?= $range==='all'?'selected':'' ?>>Tutto</option>
        </select>
        <button class="btn btn-secondary" type="submit">Aggiorna</button>
      </form>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">👥 Utenti Totali</div>
        <div class="stat-value"><?= $stats['users'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">📦 Ordini Totali</div>
        <div class="stat-value"><?= $stats['orders'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">🏷️ Prodotti</div>
        <div class="stat-value"><?= $stats['products'] ?></div>
      </div>
      <div class="stat-card revenue">
        <div class="stat-label">💰 Revenue Totale</div>
        <div class="stat-value"><?= fmt_currency((float)$stats['revenue']) ?></div>
      </div>
    </div>

    <?php if (!empty($lockedAccounts)): ?>
      <div class="card">
        <h2 class="card-header" style="font-size: 1.5rem; color: var(--danger);">⚠️ Account Bloccati</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Email</th>
                <th>Tentativi Falliti</th>
                <th>Bloccato Fino a</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lockedAccounts as $acc): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($acc['email']) ?></strong></td>
                  <td><span class="badge badge-danger"><?= (int)($acc['conteggio_tentativi_falliti'] ?? $acc['failed_login_count'] ?? 0) ?> tentativi</span></td>
                  <td><?= htmlspecialchars($acc['bloccato_fino_a'] ?? $acc['locked_until'] ?? '') ?></td>
                  <td>
                    <form method="post" action="/admin/sblocca_utente.php" style="display: inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$acc['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-secondary">🔓 Sblocca</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2 class="card-header" style="font-size: 1.5rem;">🚫 Tentativi di Login Falliti (<?= htmlspecialchars($range) ?>) — <?= (int)$failedCount ?> totali</h2>
      <?php if (empty($failedAttempts)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">✅</div>
          <p class="empty-state-text">Nessun tentativo fallito recente. Tutto tranquillo!</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Email</th>
                <th>IP</th>
                <th>Motivo</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($failedAttempts as $attempt): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($attempt['email_attempted']) ?></strong></td>
                  <td><code><?= htmlspecialchars($attempt['ip'] ?? 'N/D') ?></code></td>
                  <td>
                    <?php
                      $label = reason_label($attempt['reason'] ?? 'OTHER');
                      $reasonColor = match($attempt['reason'] ?? 'OTHER') {
                        'WRONG_PASSWORD' => 'danger',
                        'UNKNOWN_EMAIL' => 'warning',
                        'LOCKED', 'TOO_MANY_ATTEMPTS' => 'danger',
                        default => 'info'
                      };
                    ?>
                    <span class="badge badge-<?= $reasonColor ?>"><?= htmlspecialchars($label) ?></span>
                  </td>
                  <td><?= fmt_date($attempt['creato_il'] ?? $attempt['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="card-header" style="font-size: 1.5rem;">🛍️ Ordini Recenti (<?= htmlspecialchars($range) ?>)</h2>
      <?php if (empty($recentOrders)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📦</div>
          <p class="empty-state-text">Nessun ordine presente.</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Utente</th>
                <th>Totale</th>
                <th>Stato</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $order): ?>
                <tr>
                  <td><strong>#<?= (int)$order['id'] ?></strong></td>
                  <td><?= htmlspecialchars($order['user_email']) ?></td>
                  <td><strong style="color: var(--secondary);"><?= fmt_currency((float)$order['total_amount']) ?></strong></td>
                  <td>
                    <?php
                    $statusConfig = match($order['status']) {
                      'completed' => ['class' => 'success', 'icon' => '✅'],
                      'pending' => ['class' => 'warning', 'icon' => '⏳'],
                      default => ['class' => 'info', 'icon' => '❓']
                    };
                    ?>
                    <span class="badge badge-<?= $statusConfig['class'] ?>">
                      <?= $statusConfig['icon'] ?> <?= ucfirst($order['status']) ?>
                    </span>
                  </td>
                  <td><?= fmt_date($order['placed_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
