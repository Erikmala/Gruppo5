<?php
/**
 * Eliminazione Account Utente (GDPR - Diritto alla Cancellazione)
 * Consente all'utente di eliminare permanentemente il proprio account
 */

require __DIR__ . '/../includes/sessione.php';
require __DIR__ . '/../includes/connessione_db.php';
require __DIR__ . '/../includes/autenticazione.php';

richiedi_login();

$idUtente = id_utente_corrente();
$errore = null;
$successo = false;

// Verifica se è stata confermata la cancellazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma_cancellazione'])) {
    if (!valida_csrf()) {
        $errore = 'Token CSRF non valido.';
    } else {
        try {
            db()->beginTransaction();
            
            // Elimina dati correlati all'utente (rispetta vincoli foreign key)
            db_run('DELETE FROM articoli_carrello WHERE carrello_id IN (SELECT id FROM carrelli WHERE utente_id = ?)', [$idUtente]);
            db_run('DELETE FROM carrelli WHERE utente_id = ?', [$idUtente]);
            db_run('DELETE FROM articoli_ordine WHERE ordine_id IN (SELECT id FROM ordini WHERE utente_id = ?)', [$idUtente]);
            db_run('DELETE FROM ordini WHERE utente_id = ?', [$idUtente]);
            db_run('DELETE FROM indirizzi WHERE utente_id = ?', [$idUtente]);
            db_run('DELETE FROM utenti_ruoli WHERE utente_id = ?', [$idUtente]);
            db_run('DELETE FROM tentativi_autenticazione WHERE utente_id = ?', [$idUtente]);
            
            // Elimina l'account utente
            db_run('DELETE FROM utenti WHERE id = ?', [$idUtente]);
            
            db()->commit();
            
            // Distruggi la sessione
            session_destroy();
            $successo = true;
            
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errore = 'Errore durante l\'eliminazione dell\'account. Riprova più tardi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $successo ? 'Account Eliminato' : 'Elimina Account' ?> - Tech Hub</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo">🛒 Tech Hub</a>
        </div>
    </header>
    
    <div class="container-xs">
        <div class="card">
            <?php if ($successo): ?>
                <h1 class="card-header">✅ Account Eliminato</h1>
                <div class="alert alert-success">
                    <strong>Il tuo account è stato eliminato con successo.</strong>
                    <p style="margin-top: 0.5rem;">
                        Tutti i tuoi dati personali sono stati rimossi dal nostro sistema in conformità con il GDPR.
                        Grazie per aver utilizzato Tech Hub. Speriamo di rivederti presto!
                    </p>
                </div>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="/index.php" class="btn btn-primary">← Torna alla Home</a>
                </div>
            <?php else: ?>
                <h1 class="card-header">⚠️ Elimina Account</h1>
                
                <?php if ($errore): ?>
                    <div class="alert alert-error">
                        <strong>Errore:</strong> <?= htmlspecialchars($errore) ?>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-warning">
                    <strong>⚠️ Attenzione: Questa azione è irreversibile!</strong>
                    <p style="margin-top: 0.5rem;">
                        Eliminando il tuo account, verranno cancellati permanentemente:
                    </p>
                    <ul style="margin-left: 2rem; margin-top: 0.5rem;">
                        <li>I tuoi dati personali (nome, cognome, email)</li>
                        <li>Lo storico degli ordini</li>
                        <li>Gli indirizzi di spedizione salvati</li>
                        <li>Il contenuto del carrello</li>
                        <li>Tutti i dati associati al tuo account</li>
                    </ul>
                </div>
                
                <form method="post" action="" class="form" onsubmit="return confirm('Sei sicuro di voler eliminare definitivamente il tuo account? Questa azione NON può essere annullata.');">
                    <?= campo_csrf() ?>
                    
                    <div style="background: var(--danger-bg, #fee); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--danger); margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="conferma_cancellazione" value="1" required style="width: auto;">
                            <span>Confermo di voler eliminare <strong>permanentemente</strong> il mio account e tutti i dati associati</span>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="/index.php" class="btn btn-secondary">← Annulla</a>
                        <button type="submit" class="btn" style="background: var(--danger); color: white;">
                            🗑️ Elimina Account Definitivamente
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 2rem; padding: 1rem; background: var(--bg-secondary, #f9f9f9); border-radius: var(--radius);">
                    <p style="font-size: 0.9rem; color: var(--text-secondary, #666);">
                        <strong>Diritti GDPR:</strong> Hai il diritto di richiedere la cancellazione dei tuoi dati personali 
                        (art. 17 GDPR - "Diritto all'oblio"). Per maggiori informazioni, consulta la nostra 
                        <a href="/informativa_privacy.php" style="color: var(--primary);">Informativa Privacy</a>.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>