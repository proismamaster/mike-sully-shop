<?php
// Questo file restituisce semplicemente un "dizionario" con le istruzioni per far partire le email dal sito.
// Le credenziali vere NON vanno mai scritte qui (finirebbero committate in Git): si leggono da variabili
// d'ambiente, impostate sul server (o in un .env locale escluso da Git, mai in questo file).
return [
  // L'indirizzo del server di Google
  'host' => getenv('MSS_MAIL_HOST') ?: 'smtp.gmail.com',
  // La "porta d'ingresso" del server (587 è la porta standard per connessioni sicure TLS)
  'port' => (int)(getenv('MSS_MAIL_PORT') ?: 587),
  // Il tipo di scudo protettivo per i messaggi (tls = sicurezza moderna)
  'encryption' => getenv('MSS_MAIL_ENCRYPTION') ?: 'tls',
  // L'indirizzo email da cui partono fisicamente i messaggi
  'username' => getenv('MSS_MAIL_USERNAME') ?: '',
  // ATTENZIONE: Questa non è la password della mail, ma la "App Password" generata da Google
  // che permette al tuo sito di inviare mail aggirando l'autenticazione a due fattori.
  // Va impostata SOLO come variabile d'ambiente (MSS_MAIL_PASSWORD), mai scritta qui in chiaro.
  'password' => getenv('MSS_MAIL_PASSWORD') ?: '',
  // Chi deve apparire come mittente quando il cliente apre l'email?
  'from_email' => getenv('MSS_MAIL_FROM_EMAIL') ?: 'no-reply@example.com',
  // Il nome "umano" del negozio che si leggerà nella notifica (es: "Da: MikeSullyShop")
  'from_name' => getenv('MSS_MAIL_FROM_NAME') ?: 'MikeSullyShop',
];
