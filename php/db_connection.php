<?php
// Non fidarsi del php.ini dell'host: anche se lì display_errors fosse acceso
// (capita spesso su hosting condivisi), qui lo spegniamo esplicitamente così
// un errore imprevisto non mostra mai stack trace o query SQL ai visitatori —
// finisce solo nei log del server. Un errore non gestito mostra una pagina
// generica invece del messaggio tecnico di PHP.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e) {
    error_log('Errore non gestito: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Si è verificato un errore imprevisto. Riprova più tardi.';
});

$host = getenv('MSS_DB_HOST') ?: 'localhost';
$user = getenv('MSS_DB_USER') ?: 'root';
$pass = getenv('MSS_DB_PASS'); // false se la variabile non è impostata
$dbname = getenv('MSS_DB_NAME') ?: 'mikesully_shop';
$port = getenv('MSS_DB_PORT') ?: '3306';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function try_connect($host, $user, $pass, $dbname, $port) {
    $conn = new mysqli($host, $user, $pass, $dbname, (int)$port);
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Ci connettiamo SOLO con le credenziali indicate esplicitamente via variabili d'ambiente.
// Niente più tentativi con utenze di default deboli (root senza password, root/root, ecc.):
// se non è configurato nulla, passiamo alla demo: un database SQLite privato per visitatore (demo_db.php).
require_once __DIR__ . '/site_bootstrap.php';
if ($pass === false) {
    require_once __DIR__ . '/demo_db.php';
} else {
    try {
        $conn = try_connect($host, $user, $pass, $dbname, $port);
    } catch (mysqli_sql_exception $e) {
        // Credenziali impostate ma database irraggiungibile: ci fermiamo, niente demo.
        // La demo ha credenziali admin pubbliche: su un sito vero sarebbe un pannello aperto a tutti.
        error_log('Connessione MySQL fallita: ' . $e->getMessage());
        http_response_code(503);
        exit('Servizio momentaneamente non disponibile. Riprova più tardi.');
    }
    mss_bootstrap_schema($conn);
}
