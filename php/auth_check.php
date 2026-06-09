<?php
// Script di controllo accessi
// Avviamo la sessione (il pass temporaneo) per ricordarci chi è l'utente
session_start();

// Questa funzione controlla se l'utente è il "capo"
function checkAdmin() {
    // Controlliamo il ruolo salvato in sessione: se manca o se NON è "admin"...
    if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
        // Gli sbattiamo la porta in faccia e lo rimandiamo alla home
        header("Location: /mike-shop/homePage.php?error=accesso_negato");
        exit();
    }
}

// Questa funzione controlla semplicemente se l'utente ha fatto l'accesso (Login)
function checkLogin() {
    // Se non c'è la chiave del suo ID utente in sessione
    if (!isset($_SESSION['utente_id'])) {
        // Lo mandiamo subito alla pagina di Login
        header("Location: /mike-shop/loginPage.php");
        exit();
    }
}
?>