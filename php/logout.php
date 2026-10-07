<?php
// Cookie di sessione più severi: niente accesso da JS, solo HTTPS, non
// inviato in richieste cross-site — riduce hijacking/CSRF sulla sessione.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

session_unset();
session_destroy();
header('Location: ../loginPage.php');
exit();
?>
