<?php
// Questo file restituisce semplicemente un "dizionario" con le istruzioni per far partire le email dal sito
return [
  // L'indirizzo del server di Google
  'host' => 'smtp.gmail.com',
  // La "porta d'ingresso" del server (587 è la porta standard per connessioni sicure TLS)
  'port' => 587,
  // Il tipo di scudo protettivo per i messaggi (tls = sicurezza moderna)
  'encryption' => 'tls',
  // L'indirizzo email da cui partono fisicamente i messaggi
  'username' => 'mikesullyshop@gmail.com',
  // ATTENZIONE: Questa non è la password della mail, ma la "App Password" generata da Google
  // che permette al tuo sito di inviare mail aggirando l'autenticazione a due fattori.
  'password' => 'gurrxjfsupxfuhwh',
  // Chi deve apparire come mittente quando il cliente apre l'email?
  'from_email' => 'mikesullyshop@gmail.com',
  // Il nome "umano" del negozio che si leggerà nella notifica (es: "Da: MikeSullyShop")
  'from_name' => 'MikeSullyShop',
];