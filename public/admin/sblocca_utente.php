<?php
require __DIR__ . '/../../includes/sessione.php';
require __DIR__ . '/../../includes/connessione_db.php';
require __DIR__ . '/../../includes/autenticazione.php';

richiedi_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !valida_csrf()) {
    header('Location: /admin/pannello.php');
    exit;
}

$userId = (int)($_POST['id_utente'] ?? $_POST['user_id'] ?? 0);

if ($userId > 0) {
    try {
        db_run(
            'UPDATE utenti SET bloccato_fino_a = NULL, conteggio_tentativi_falliti = 0 WHERE id = ?',
            [$userId]
        );
    } catch (Throwable $e) {
        // Log error
    }
}

header('Location: /admin/pannello.php');
exit;
