<?php
// Usiamo la libreria PHPMailer per inviare email 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carichiamo gli strumenti necessari e il collegamento al database
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db_connection.php';

// Questa funzione legge il file "mail_config.php" per capire da quale indirizzo spedire le email
function mss_mailer_from_config() {
    $percorsoConfigurazione = __DIR__ . '/mail_config.php';
    // Se il file esiste lo leggiamo, altrimenti creiamo un array vuoto
    $configurazioneUtente = is_file($percorsoConfigurazione) ? (require $percorsoConfigurazione) : [];
    // Uniamo i dati scritti dall'utente con dei dati "di base" nel caso mancasse qualcosa
    return array_merge([
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => 'no-reply@example.com',
        'from_name' => 'MikeSullyShop',
    ], is_array($configurazioneUtente) ? $configurazioneUtente : []);
}

// La funzione principale che spedisce fisicamente l'email
function mss_send_mail($destinatario, $oggetto, $contenutoHtml) {
    // Prendiamo le configurazioni
    $configurazione = mss_mailer_from_config();

    // Creiamo il nostro "postino"
    $postino = new PHPMailer(true);
    
    try {
        // Diciamo al postino di usare il protocollo SMTP (quello di Gmail/provider)
        $postino->isSMTP();
        $postino->Host = $configurazione['host'];
        $postino->Port = (int)$configurazione['port'];
        $postino->SMTPAuth = true; // Deve fare il login
        
        // Impostiamo il livello di sicurezza (SSL o TLS)
        $postino->SMTPSecure = $configurazione['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $postino->Username = $configurazione['username'];
        $postino->Password = $configurazione['password'];

        // Chi manda e chi riceve?
        $postino->setFrom($configurazione['from_email'], $configurazione['from_name']);
        $postino->addAddress($destinatario);

        // Prepariamo l'email
        $postino->isHTML(true); // L'email conterrà grafica, non solo testo piatto
        $postino->CharSet = 'UTF-8'; // Per supportare accenti ed emoji
        $postino->Subject = $oggetto;
        
        // Costruiamo la cornice grafica dell'email (il template aziendale)
        $emailGraficata = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$oggetto}</title>
</head>
<body style="font-family: 'Inter', Arial, sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <div style="background-color: #2563eb; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800;">MikeSullyShop</h1>
            <p style="color: #bfdbfe; margin: 8px 0 0 0; font-size: 14px;">Il tuo portale ufficiale per il merchandise di Monstropolis</p>
        </div>
        <div style="padding: 32px;">
            {$contenutoHtml}
        </div>
        <div style="background-color: #f1f5f9; padding: 24px; text-align: center; color: #64748b; font-size: 13px;">
            <p style="margin: 0 0 8px 0;">Hai ricevuto questa email perché sei registrato su MikeSullyShop.</p>
            <p style="margin: 0 0 8px 0;">Contattaci: <a href="mailto:mikesullyshop@gmail.com" style="color: #2563eb; text-decoration: none;">mikesullyshop@gmail.com</a></p>
            <p style="margin: 0;">&copy; 2026 MikeSullyShop. Tutti i diritti riservati.</p>
        </div>
    </div>
</body>
</html>
HTML;

        // Inseriamo la grafica nel corpo dell'email e la inviamo!
        $postino->Body = $emailGraficata;
        $postino->send();
        
        // Se va tutto bene, registriamo l'invio nel database (Registro email)
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            @mss_store_mail_log($GLOBALS['conn'], $destinatario, $oggetto, $contenutoHtml, 'sent', null);
        }
        return true; // Spedita con successo!

    } catch (Exception $errore) {
        // Se c'è un problema (es. password Gmail sbagliata), lo registriamo nel log segreto del server
        $messaggioErrore = 'Mail error: ' . $errore->getMessage();
        error_log($messaggioErrore);
        
        // Registriamo il fallimento anche nel nostro database
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            @mss_store_mail_log($GLOBALS['conn'], $destinatario, $oggetto, $contenutoHtml, 'failed', $messaggioErrore);
        }
        return false; // Invio fallito
    }
}

// Funzione che salva lo storico delle email (inviate o fallite) nel database
function mss_store_mail_log($conn, $destinatario, $oggetto, $corpo, $stato = 'queued', $errore = null) {
    $istruzione = $conn->prepare("INSERT INTO emails_outbox (to_email, subject, body, status, error) VALUES (?, ?, ?, ?, ?)");
    $istruzione->bind_param('sssss', $destinatario, $oggetto, $corpo, $stato, $errore);
    $istruzione->execute();
    $istruzione->close();
}