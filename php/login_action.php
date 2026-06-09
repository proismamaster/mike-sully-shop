<?php
session_start();
require_once 'db_connection.php';

// Un po' di sicurezza: se qualcuno carica questo script direttamente dall'URL senza inviare il modulo (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../loginPage.php'); // Lo rimandiamo alla pagina corretta
    exit();
}

// Recuperiamo l'email e la password scritte dall'utente, rimuovendo gli spazi vuoti accidentali
$emailInserita = trim($_POST['mail'] ?? '');
$passwordInserita = $_POST['password'] ?? '';

// Se ha lasciato qualcosa di vuoto
if ($emailInserita === '' || $passwordInserita === '') {
    // Errore 1: Dati mancanti o errati
    header('Location: ../loginPage.php?error=1');
    exit();
}

// 1. Chiediamo al Database se esiste un utente con questa email
$ricercaUtente = $conn->prepare("SELECT id, nome, cognome, password, ruolo FROM users WHERE email = ? LIMIT 1");
$ricercaUtente->bind_param("s", $emailInserita);
$ricercaUtente->execute();

$risultati = $ricercaUtente->get_result();
$datiUtente = $risultati->fetch_assoc(); // Estraiamo la riga con i dati
$ricercaUtente->close();

// 2. Controlliamo se abbiamo trovato qualcuno e, soprattutto, se la password combacia!
// (password_verify controlla in modo sicuro la password scritta con quella "criptata" salvata nel database)
if ($datiUtente && password_verify($passwordInserita, $datiUtente['password'])) {
    
    // Login riuscito! Salviamo chi è nella memoria del server (Sessione)
    $_SESSION['utente_id'] = $datiUtente['id'];
    $_SESSION['user'] = $datiUtente['nome'] . ' ' . $datiUtente['cognome'];
    $_SESSION['ruolo'] = $datiUtente['ruolo'];

    // 3. Ora recuperiamo i suoi Prodotti Preferiti (Wishlist) che aveva salvato nelle visite precedenti
    $ricercaPreferiti = $conn->prepare("SELECT prodotto_id FROM wishlist WHERE utente_id = ?");
    $ricercaPreferiti->bind_param("i", $datiUtente['id']);
    $ricercaPreferiti->execute();
    
    $risultatiPreferiti = $ricercaPreferiti->get_result();
    
    // Creiamo una scatola vuota per la sua wishlist
    $_SESSION['wishlist'] = []; 
    
    // Ci infiliamo dentro tutti gli ID dei prodotti che troviamo nel database
    while ($riga = $risultatiPreferiti->fetch_assoc()) {
        $_SESSION['wishlist'][] = $riga['prodotto_id'];
    }
    $ricercaPreferiti->close();

    // 4. Tutto pronto! Dove lo mandiamo? Dipende da chi è.
    if ($datiUtente['ruolo'] === 'admin') {
        // I capi vanno nel gestionale
        header('Location: ../adminMagazzino.php');
    } else {
        // I clienti normali vanno a fare shopping
        header('Location: ../homePage.php');
    }
    exit();
}

// Se siamo arrivati fin qui, significa che l'email non esiste o la password è sbagliata.
header('Location: ../loginPage.php?error=1');
exit();
?>