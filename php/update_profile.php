<?php
session_start();
require_once 'db_connection.php';
require_once 'mailer.php';

// Se non c'è nessuno loggato o se è un accesso sospetto via URL
if (!isset($_SESSION['utente_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../profilo.php');
    exit();
}

$idUtenteLoggato = (int)$_SESSION['utente_id'];


//VERIFICA DEL CODICE SEGRETO (OTP)

if (isset($_POST['confirm_otp'])) {
    $codiceInserito = trim($_POST['otp_code'] ?? '');
    
    // Recuperiamo dalla memoria del server quali modifiche stava cercando di fare
    $modificheInSospeso = $_SESSION['pending_profile_update'] ?? null;
    
    if (!$modificheInSospeso) {
        header('Location: ../profilo.php?error=otp'); // Modifiche scadute o inesistenti
        exit();
    }
    
    $codiceCorretto = false;
    
    if ($codiceInserito !== '') {
        try {
            // Capiamo chi è l'utente per sapere dove guardare
            $istruzione = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $istruzione->bind_param('i', $idUtenteLoggato);
            $istruzione->execute();
            $datiAttualiUtente = $istruzione->get_result()->fetch_assoc();
            $istruzione->close();
            
            if ($datiAttualiUtente) {
                // Cerchiamo nel DB l'ultimo codice segreto inviato a questa mail per il cambio password
                $istruzione = $conn->prepare("SELECT id, code_hash, expires_at, used_at FROM user_otps WHERE email = ? AND purpose = 'password_change' ORDER BY id DESC LIMIT 1");
                $istruzione->bind_param('s', $datiAttualiUtente['email']);
                $istruzione->execute();
                $rigaOtpDb = $istruzione->get_result()->fetch_assoc();
                $istruzione->close();
                
                if ($rigaOtpDb) {
                    // Controlliamo che il timer di 10 minuti non sia scaduto
                    $ancoraValido = strtotime((string)$rigaOtpDb['expires_at']) >= time();
                    // E che nessuno l'abbia già usato
                    $maiUsato = empty($rigaOtpDb['used_at']);
                    
                    // Se è tutto in regola e il codice combacia
                    if ($ancoraValido && $maiUsato && password_verify($codiceInserito, (string)$rigaOtpDb['code_hash'])) {
                        $codiceCorretto = true;
                        
                        // Bruciamo il codice! Segnamolo come usato così non funziona più
                        $istruzioneBrucia = $conn->prepare("UPDATE user_otps SET used_at = NOW() WHERE id = ?");
                        $idRiga = (int)$rigaOtpDb['id'];
                        $istruzioneBrucia->bind_param('i', $idRiga);
                        $istruzioneBrucia->execute();
                        $istruzioneBrucia->close();
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('OTP DB verify error: ' . $e->getMessage());
        }
        
        // Piano di riserva (Fallback): Se il DB andasse in crash, controlliamo se il codice è salvato nella memoria temporanea del server (session)
        if (!$codiceCorretto && $codiceInserito === (string)($modificheInSospeso['otp'] ?? '') && (time() - (int)($modificheInSospeso['ts'] ?? 0)) <= 600) {
            $codiceCorretto = true;
        }
    }
    
    // Se ha indovinato il codice, FINALMENTE applichiamo le modifiche al profilo!
    if ($codiceCorretto) {
        $nuovoNome = $modificheInSospeso['nome'];
        $nuovoCognome = $modificheInSospeso['cognome'];
        $nuovaEmail = $modificheInSospeso['email'];
        $nuovaPasswordText = $modificheInSospeso['password'];
        $passwordCriptata = password_hash($nuovaPasswordText, PASSWORD_DEFAULT);
        
        $istruzioneAggiorna = $conn->prepare("UPDATE users SET nome=?, cognome=?, email=?, password=? WHERE id=?");
        $istruzioneAggiorna->bind_param("ssssi", $nuovoNome, $nuovoCognome, $nuovaEmail, $passwordCriptata, $idUtenteLoggato);
        $istruzioneAggiorna->execute();
        $istruzioneAggiorna->close();
        
        // Aggiorniamo il nome di benvenuto in alto a destra
        $_SESSION['user'] = $nuovoNome . ' ' . $nuovoCognome;
        
        // Puliamo le vecchie pratiche in sospeso
        unset($_SESSION['pending_profile_update']);
        
        header('Location: ../profilo.php?success=ok');
        exit();
    } else {
        // Codice sbagliato!
        header('Location: ../profilo.php?error=otp');
        exit();
    }
}


// RICEZIONE DATI DAL MODULO DEL PROFILO E CREAZIONE OTP


$nuovoNome = trim($_POST['nome'] ?? '');
$nuovoCognome = trim($_POST['cognome'] ?? '');
$nuovaEmail = trim($_POST['email'] ?? '');
$nuovaPasswordTesto = $_POST['password'] ?? '';
$confermaPasswordTesto = $_POST['password_confirm'] ?? '';

// Controlli base di validità (campi vuoti o mail malformate)
if ($nuovoNome === '' || $nuovoCognome === '' || !filter_var($nuovaEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../profilo.php?error=campi');
    exit();
}

// Vuole usare un'email. È già usata da qualcun altro?
$controlloOmonimi = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
$controlloOmonimi->bind_param("si", $nuovaEmail, $idUtenteLoggato);
$controlloOmonimi->execute();
$controlloOmonimi->store_result();
if ($controlloOmonimi->num_rows > 0) {
    $controlloOmonimi->close();
    header('Location: ../profilo.php?error=email'); // L'email è già presa!
    exit();
}
$controlloOmonimi->close();

// SE L'UTENTE HA SCRITTO QUALCOSA NEL CAMPO NUOVA PASSWORD...
if ($nuovaPasswordTesto !== '') {
    
    // Controlliamo che le due caselle password coincidano e siano abbastanza lunghe
    if ($nuovaPasswordTesto !== $confermaPasswordTesto || strlen($nuovaPasswordTesto) < 6) {
        header('Location: ../profilo.php?error=password');
        exit();
    }
    
    // Ha scritto una password uguale a quella che ha già? Inutile.
    $istruzione = $conn->prepare("SELECT email, password FROM users WHERE id = ? LIMIT 1");
    $istruzione->bind_param("i", $idUtenteLoggato);
    $istruzione->execute();
    $datiAttualiUtente = $istruzione->get_result()->fetch_assoc();
    $istruzione->close();
    
    if ($datiAttualiUtente && password_verify($nuovaPasswordTesto, $datiAttualiUtente['password'])) {
        header('Location: ../profilo.php?error=same_password');
        exit();
    }
    
    // La password è nuova e valida -> ATTIVIAMO IL SISTEMA DI SICUREZZA (Generazione Codice OTP)
    $codiceSegreto = random_int(100000, 999999); // Genera 6 numeri a caso
    
    // Mettiamo in cassaforte (sessione) i dati che voleva salvare, per riprenderli nella FASE 2
    $_SESSION['pending_profile_update'] = [
        'nome' => $nuovoNome,
        'cognome' => $nuovoCognome,
        'email' => $nuovaEmail,
        'password' => $nuovaPasswordTesto,
        'otp' => (string)$codiceSegreto,
        'ts' => time()
    ];
    
    $emailStorica = $datiAttualiUtente['email']; // La mail va mandata alla VECCHIA email per sicurezza! Non a quella che sta cercando di impostare.
    
    try {
        // Salviamo il codice criptato nel DB
        $codiceSegretoCriptato = password_hash((string)$codiceSegreto, PASSWORD_DEFAULT);
        $istruzione = $conn->prepare("INSERT INTO user_otps (email, code_hash, purpose, expires_at) VALUES (?, ?, 'password_change', DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $istruzione->bind_param('ss', $emailStorica, $codiceSegretoCriptato);
        $istruzione->execute();
        $istruzione->close();
    } catch (Throwable $e) {
        error_log('OTP DB insert error: ' . $e->getMessage());
    }
    
    // Prepariamo la mail da inviargli
    $oggettoMail = 'Modifica Password - Codice OTP MikeSullyShop';
    $testoMail = '<div style="font-family:Inter,Arial,sans-serif;font-size:14px;color:#0f172a">'
          . '<h2 style="margin:0 0 8px">Conferma la modifica della password</h2>'
          . '<p>Usa questo codice per autorizzare il cambio di password e dei dati del tuo profilo:</p>'
          . '<p style="font-size:24px;font-weight:800;margin:12px 0">' . htmlspecialchars((string)$codiceSegreto) . '</p>'
          . '<p style="color:#64748b">Il codice scade tra 10 minuti.</p>'
          . '<p style="color:#dc3545;font-weight:bold;">Se non hai richiesto tu la modifica, ignora questa email e controlla il tuo account.</p>'
          . '</div>';
    
    // Chiamiamo il nostro "Postino" (da mailer.php)
    $emailInviata = @mss_send_mail($emailStorica, $oggettoMail, $testoMail);
    
    if (!$emailInviata) {
        error_log("OTP fallito via email per $emailStorica. CODICE: $codiceSegreto");
        unset($_SESSION['pending_profile_update']);
        header('Location: ../profilo.php?error=mail_failed');
        exit();
    }
    
    // Rimandiamo l'utente alla schermata per fargli inserire il codice (Fase 2)
    header('Location: ../profilo.php?otp_step=1');
    exit();
    
} else {
    // SE INVECE L'UTENTE HA LASCIATO VUOTO IL CAMPO PASSWORD...
    // Vuol dire che voleva solo cambiare nome/cognome/email. Non c'è bisogno di codici di sicurezza, cambiamo subito!
    $istruzioneAggiornamentoVeloce = $conn->prepare("UPDATE users SET nome=?, cognome=?, email=? WHERE id=?");
    $istruzioneAggiornamentoVeloce->bind_param("sssi", $nuovoNome, $nuovoCognome, $nuovaEmail, $idUtenteLoggato);
    $istruzioneAggiornamentoVeloce->execute();
    $istruzioneAggiornamentoVeloce->close();
    
    // Aggiorniamo la dicitura in alto
    $_SESSION['user'] = $nuovoNome . ' ' . $nuovoCognome;
    
    // Pulizie primaverili per sicurezza
    unset($_SESSION['pending_profile_update']);
    
    header('Location: ../profilo.php?success=ok');
    exit();
}