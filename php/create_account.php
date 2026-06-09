<?php
session_start();
require_once 'db_connection.php';

// Se qualcuno tenta di aprire questo file furbescamente digitando il link senza inviare il modulo (metodo POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../registrationPage.php'); // Lo rimandiamo indietro a pedate
    exit();
}

// Recuperiamo tutti i dati inseriti nel form e diamo una pulita agli spazi
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordDiConferma = $_POST['password_confirm'] ?? '';

// Controlliamo se ha indicato un ruolo. Di base è tutti sono clienti.
$ruoloScelto = trim($_POST['ruolo'] ?? 'cliente');

// Normalizzazione: se qualcuno fa il furbetto dal codice per darsi un ruolo che non esiste, forziamolo a cliente.
if (!in_array($ruoloScelto, ['admin', 'cliente'])) {
    $ruoloScelto = 'cliente';
}

// Salviamo quello che ha scritto l'utente (così se sbaglia, quando ricarica la pagina glieli rimettiamo e non deve riscrivere tutto)
$datiPerUrl = http_build_query(['nome' => $nome, 'cognome' => $cognome, 'email' => $email]);

// Ha lasciato vuoto qualche campo obbligatorio?
if ($nome === '' || $cognome === '' || $email === '' || $password === '') {
    header('Location: ../registrationPage.php?error=campi&' . $datiPerUrl);
    exit();
}

// La mail inserita sembra davvero un indirizzo email (ha la chiocciola e un formato valido)?
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../registrationPage.php?error=email&' . $datiPerUrl);
    exit();
}

// Quando a creare l'account è l'amministratore, non c'è il campo di conferma password. 
// Ma se invece il campo c'è (utente normale) e le password non sono uguali, diamo errore.
if (isset($_POST['password_confirm']) && $password !== $passwordDiConferma) {
    header('Location: ../registrationPage.php?error=password&' . $datiPerUrl);
    exit();
}

// Una password troppo corta è poco sicura. Fissiamo il limite a 6 caratteri.
if (strlen($password) < 6) {
    header('Location: ../registrationPage.php?error=corta&' . $datiPerUrl);
    exit();
}

// Verifichiamo se c'è già qualcun altro nel sistema registrato con la stessa email
$controlloMailEsistente = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$controlloMailEsistente->bind_param("s", $email);
$controlloMailEsistente->execute();
$controlloMailEsistente->store_result();

// Se l'ha trovato
if ($controlloMailEsistente->num_rows > 0) {
    $controlloMailEsistente->close();
    
    // Riscriviamo i dati per rimandarli alla pagina
    $richiestaErrore = http_build_query(['error' => 'exists', 'nome' => $nome, 'cognome' => $cognome, 'email' => $email]);
    header('Location: ../registrationPage.php?' . $richiestaErrore);
    exit();
}
$controlloMailEsistente->close();

// Trasformiamo la sua password in una stringa di testo indecifrabile per sicurezza
$passwordCriptata = password_hash($password, PASSWORD_DEFAULT);

// Procediamo: inseriamo i dati nel database!
$istruzioneDiInserimento = $conn->prepare("INSERT INTO users (nome, cognome, email, password, ruolo) VALUES (?, ?, ?, ?, ?)");
$istruzioneDiInserimento->bind_param("sssss", $nome, $cognome, $email, $passwordCriptata, $ruoloScelto);

// Se il salvataggio va a buon fine...
if ($istruzioneDiInserimento->execute()) {
    
    // Prendiamo l'ID progressivo che il database ha assegnato a questo nuovo cliente
    $nuovoIdUtente = $conn->insert_id;
    $istruzioneDiInserimento->close();

    // ECCEZIONE: Se stiamo creando l'account agendo come Amministratori dalla pagina del Magazzino...
    if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin' && strpos($_SERVER['HTTP_REFERER'], 'adminMagazzino.php') !== false) {
        // Torniamo nel magazzino e mostriamo il messaggio verde!
        header('Location: ../adminMagazzino.php?success=account');
        exit();
    }

    // Se è un utente normale, gli facciamo il login automatico subito dopo essersi iscritto. Benvenuto!
    $_SESSION['utente_id'] = $nuovoIdUtente;
    $_SESSION['user'] = $nome . ' ' . $cognome;
    $_SESSION['ruolo'] = $ruoloScelto;
    $_SESSION['wishlist'] = []; // Inizializziamo subito una lista desideri vuota
    
    // Rimandiamolo felice alla home
    header('Location: ../homePage.php?benvenuto=1');
    exit();
}

// Se il database è andato in errore durante il salvataggio
$istruzioneDiInserimento->close();
header('Location: ../registrationPage.php?error=server');
exit();
?>