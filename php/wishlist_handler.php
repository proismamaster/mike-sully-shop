<?php
session_start();
require_once 'db_connection.php';

// Prendiamo i dati che ci passano dal click del cuoricino (in PHP si fa così)
$idOggetto = (int)($_POST['id'] ?? 0);
$azione = $_POST['azione'] ?? '';

// Scopriamo se la richiesta arriva in "sottofondo" tramite Javascript (AJAX) 
$richiestaNascostaAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1' || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

// Questo è un mini-postino che impacchetta le risposte per Javascript
function wishlist_json($datiDaInviare, $codiceStato = 200) {
    http_response_code($codiceStato);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datiDaInviare, JSON_UNESCAPED_UNICODE);
    exit();
}

// Se l'ID è sballato, non proseguiamo
if ($idOggetto <= 0) {
    if ($richiestaNascostaAjax) {
        wishlist_json(['ok' => false, 'message' => 'Prodotto non valido.'], 400);
    }
    header('Location: ../homePage.php');
    exit();
}

$idDellUtente = $_SESSION['utente_id'] ?? null;

//  Utente Registrato vs Ospite Anonimo

if ($idDellUtente) {
    // L'UTENTE È LOGGATO (Salviamo i dati nel Database)
    
    if ($azione === 'aggiungi') {
        // IGNORE evita errori se l'oggetto è già nei preferiti
        $istruzione = $conn->prepare("INSERT IGNORE INTO wishlist (utente_id, prodotto_id) VALUES (?, ?)");
        $istruzione->bind_param("ii", $idDellUtente, $idOggetto);
        $istruzione->execute();
        $istruzione->close();
        
    } elseif ($azione === 'rimuovi') {
        // Cancella il prodotto per quell'utente
        $istruzione = $conn->prepare("DELETE FROM wishlist WHERE utente_id = ? AND prodotto_id = ?");
        $istruzione->bind_param("ii", $idDellUtente, $idOggetto);
        $istruzione->execute();
        $istruzione->close();
    }
    
    // Per far aggiornare istantaneamente il badge (i pallini rossi in alto a destra),
    // dobbiamo copiare il database dentro la memoria del server (Sessione)
    $istruzioneRisincronizzazione = $conn->prepare("SELECT prodotto_id FROM wishlist WHERE utente_id = ?");
    $istruzioneRisincronizzazione->bind_param("i", $idDellUtente);
    $istruzioneRisincronizzazione->execute();
    $risultatiSalvati = $istruzioneRisincronizzazione->get_result();
    
    $_SESSION['wishlist'] = []; // Svuotiamo la memoria...
    while ($riga = $risultatiSalvati->fetch_assoc()) {
        $_SESSION['wishlist'][] = $riga['prodotto_id']; // ...e la riempiamo con i dati freschi dal DB
    }
    $istruzioneRisincronizzazione->close();
    
} else {
    // L'UTENTE È UN OSPITE (Usiamo SOLO la memoria temporanea) 
    
    // Se non ha ancora una lista desideri, creiamogliela
    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }
    
    if ($azione === 'aggiungi' && !in_array($idOggetto, $_SESSION['wishlist'])) {
        // Aggiungiamo l'id all'elenco (solo se non c'è già)
        $_SESSION['wishlist'][] = $idOggetto;
        
    } elseif ($azione === 'rimuovi') {
        // Filtriamo la lista scartando quello che ha l'ID che vogliamo rimuovere
        $_SESSION['wishlist'] = array_values(array_filter($_SESSION['wishlist'], function($valoreMemoria) use ($idOggetto) { 
            return $valoreMemoria !== $idOggetto; 
        }));
    }
}


// PREPARIAMO LA RISPOSTA

$listaDeiDesideriAttuale = $_SESSION['wishlist'] ?? [];

// Restituisce "true" se il prodotto è effettivamente nei preferiti, altrimenti "false"
$prodottoNeiPreferiti = in_array($idOggetto, $listaDeiDesideriAttuale, true);

// Se era Javascript a chiamarci, rispondiamo coi dati in JSON
if ($richiestaNascostaAjax) {
    wishlist_json([
        'ok' => true,
        'product_id' => $idOggetto,
        'wishlist_count' => count($listaDeiDesideriAttuale), // Quanti ne ha ora?
        'in_wishlist' => $prodottoNeiPreferiti, // Il cuoricino deve essere pieno o vuoto?
        'remove_item' => ($azione === 'rimuovi'), // Lo stiamo togliendo?
    ]);
}

// Se Javascript non funziona o la richiesta è classica, lo riportiamo fisicamente alla pagina in cui era
$paginaPrecedente = $_SERVER['HTTP_REFERER'] ?? '../homePage.php';
header('Location: ' . $paginaPrecedente);
exit();
?>