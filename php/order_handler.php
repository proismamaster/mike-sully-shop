<?php
session_start();
require_once 'db_connection.php';
require_once 'order_functions.php';

// Se uno prova a entrare in questo script senza premere un bottone, lo buttiamo fuori
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ordini.php');
    exit();
}
$azioneRichiesta = $_POST['action'] ?? '';
$idOrdineDaElaborare = (int)($_POST['ordine_id'] ?? 0);
// Controlliamo che l'utente abbia fatto l'accesso
if (!isset($_SESSION['utente_id'])) {
    header('Location: ../loginPage.php');
    exit();
}
try {
    // Se ha chiesto di annullare un ordine...
    if ($azioneRichiesta === 'annulla' && $idOrdineDaElaborare > 0) {
        // Lanciamo la funzione che rimette la merce sugli scaffali e annulla tutto
        mss_cancel_order($conn, $idOrdineDaElaborare, (int)$_SESSION['utente_id']);
        
        // Lo rimandiamo alla pagina dei suoi ordini col messaggio verde di successo
        header('Location: ../ordini.php?success=annullato');
        exit();
    }

    // Se l'azione non è valida, errore
    header('Location: ../ordini.php?error=azione');
    exit();
    
} catch (Throwable $erroreGenerato) {
    // Se la funzione mss_cancel_order "urla" un errore (es. pacco già spedito), lo mostriamo all'utente in rosso
    header('Location: ../ordini.php?error=' . urlencode($erroreGenerato->getMessage()));
    exit();
}