<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "mikesully_shop";

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Errore di connessione al database: " . $conn->connect_error);
}

require_once __DIR__ . '/site_bootstrap.php';
mss_bootstrap_schema($conn);
?>
