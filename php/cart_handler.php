<?php
// Avviamo la sessione per ricordare i prodotti del carrello
session_start();

require_once 'db_connection.php';
require_once 'order_functions.php';

// Stabiliamo il tempo limite vitale del carrello se l'utente è inattivo (30 minuti scritti in secondi)
define('CART_TTL', 1800); 

function scadenzaCarrello() {
    global $conn; // Aggiunto per poter comunicare col database
    // Se il carrello non è vuoto e sappiamo a che ora è stata fatta l'ultima modifica
    if (!empty($_SESSION['carrello']) && isset($_SESSION['carrello_ts'])) {
        
        // Se l'ora di adesso meno l'ultima modifica supera i 30 minuti...
        if (time() - $_SESSION['carrello_ts'] > CART_TTL) {
            
            // RIPRISTINO SCORTE: Visto che il tempo è scaduto, ridiamo i prodotti al magazzino
            foreach ($_SESSION['carrello'] as $idProdotto => $dati) {
                $qtaDaRestituire = (int)$dati['qta'];
                $idProdotto = (int)$idProdotto;
                $conn->query("UPDATE products SET giacenza = giacenza + $qtaDaRestituire WHERE id = $idProdotto");
            }

            // Svuotiamo brutalmente il carrello per non tenere occupati i prodotti
            $_SESSION['carrello'] = [];
            $_SESSION['carrello_ts'] = null;
        }
    }
}

// Verifica se questa richiesta PHP sta arrivando dai nostri script Javascript in modo silenzioso (AJAX)
function isAjaxRequest() {
    return (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');
}

// Prepara e spedisce i dati al browser formattati in JSON per Javascript, fermando poi lo script
function sendJson($datiPacchetto, $codiceStato = 200) {
    http_response_code($codiceStato); // 200 = OK, 404 = Non trovato, ecc.
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datiPacchetto, JSON_UNESCAPED_UNICODE);
    exit();
}

// Leggiamo cosa vuole fare l'utente, cercando sia nei form invisibili (POST) che nell'URL (GET)
$azione = $_POST['azione'] ?? $_GET['azione'] ?? '';
$prodottoId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

// Capiamo se dobbiamo rispondere in JSON o cambiare pagina fisicamente
$isAjax = isAjaxRequest();

// Guardiamo qual è l'indirizzo della pagina da cui è partito il clic, per sapere dove rimandarlo indietro
$paginaDiProvenienza = $_SERVER['HTTP_REFERER'] ?? '';
$veniamoDaDettaglioProdotto = (strpos($paginaDiProvenienza, 'productDetail.php') !== false);

// Facciamo pulizia se il carrello è vecchio
scadenzaCarrello();


// AZIONE 1: AGGIUNGERE UN PRODOTTO
if ($azione === 'aggiungi' && $prodottoId > 0) {
    // Cerchiamo le info di questo prodotto nel database
    $prodotto = mss_get_product_by_id($conn, $prodottoId);

    // Se prova ad aggiungere un prodotto inesistente
    if (!$prodotto) {
        if ($isAjax) {
            sendJson(['ok' => false, 'message' => 'Prodotto non trovato.'], 404);
        }
        header('Location: ../homePage.php?cart_error=notfound');
        exit();
    }

    // Se il prodotto c'è ma le scorte in magazzino sono a zero
    if ($prodotto['giacenza'] <= 0) {
        if ($isAjax) {
            sendJson(['ok' => false, 'message' => 'Spiacente, questo prodotto è esaurito.'], 409);
        }
        
        // Lo rimandiamo alla pagina in cui era prima
        $destinazione = $veniamoDaDettaglioProdotto
            ? '../productDetail.php?id=' . $prodottoId . '&cart_error=esaurito'
            : '../homePage.php?cart_error=esaurito';
        header('Location: ' . $destinazione);
        exit();
    }

    // Se è il primissimo prodotto che aggiunge, inizializziamo la memoria del carrello e il timer
    if (!isset($_SESSION['carrello'])) {
        $_SESSION['carrello']    = [];
        $_SESSION['carrello_ts'] = time();
    }

    // Quanti pezzi di questo prodotto ha già nel carrello?
    $quantitaGiaPresente = $_SESSION['carrello'][$prodottoId]['qta'] ?? 0;
    // Quanti ne vuole aggiungere ora? (Se non specifica niente, diamo per scontato 1)
    $quantitaDaAggiungere = max(1, (int)($_POST['qta'] ?? 1));

    // Controlliamo che ci sia abbastanza giacenza residua in magazzino per questa aggiunta
    if ($quantitaDaAggiungere > $prodotto['giacenza']) {
        if ($isAjax) {
            sendJson(['ok' => false, 'message' => 'Hai già raggiunto la quantità massima disponibile.'], 409);
        }
        $destinazione = $veniamoDaDettaglioProdotto
            ? '../productDetail.php?id=' . $prodottoId . '&cart_error=giacenza'
            : '../homePage.php?cart_error=giacenza';
        header('Location: ' . $destinazione);
        exit();
    }

    // Procediamo con l'inserimento
    if ($quantitaGiaPresente > 0) {
        // Se c'era già, sommiamo i numeri
        $_SESSION['carrello'][$prodottoId]['qta'] += $quantitaDaAggiungere;
        // PRENOTIAMO FISICAMENTE LA GIACENZA DAL DB
        $conn->query("UPDATE products SET giacenza = giacenza - $quantitaDaAggiungere WHERE id = $prodottoId");
        
        $destinazione = $veniamoDaDettaglioProdotto
            ? '../productDetail.php?id=' . $prodottoId . '&cart_msg=aggiunto_ancora'
            : '../homePage.php?cart_msg=aggiunto_ancora';
    } else {
        // Se è nuovo nel carrello, salviamo tutti i dati necessari per vederlo a schermo
        $_SESSION['carrello'][$prodottoId] = [
            'nome'   => $prodotto['nome'],
            'prezzo' => (float)$prodotto['prezzo'],
            'img'    => mss_get_product_images($prodotto['immagine_path'])[0] ?? '',
            'qta'    => $quantitaDaAggiungere,
        ];
        // PRENOTIAMO FISICAMENTE LA GIACENZA DAL DB
        $conn->query("UPDATE products SET giacenza = giacenza - $quantitaDaAggiungere WHERE id = $prodottoId");
        
        $destinazione = $veniamoDaDettaglioProdotto
            ? '../productDetail.php?id=' . $prodottoId . '&cart_msg=aggiunto'
            : '../homePage.php?cart_msg=aggiunto';
    }

    // Riazzzeriamo il timer per non farglielo scadere mentre fa shopping
    $_SESSION['carrello_ts'] = time();

    // Se è uno script, rispondiamo e chiudiamo tutto
    if ($isAjax) {
        sendJson([
            'ok' => true,
            'product_id' => $prodottoId,
            'item_qty' => (int)$_SESSION['carrello'][$prodottoId]['qta'],
            'cart_count' => mss_cart_count($_SESSION['carrello']),
            'cart_total' => mss_cart_total($_SESSION['carrello']),
            'message' => $quantitaGiaPresente > 0 ? 'Quantità aggiornata nel carrello.' : 'Prodotto aggiunto al carrello.',
        ]);
    }

    // Se è una chiamata classica, usiamo i link di destinazione calcolati prima
    header('Location: ' . $destinazione);
    exit();
}


// AZIONE 2: RIMUOVERE COMPLETAMENTE UN PRODOTTO
if ($azione === 'rimuovi' && $prodottoId > 0) {
    if (isset($_SESSION['carrello'][$prodottoId])) {
        // Restituiamo la giacenza al magazzino prima di toglierlo dal carrello
        $qtaDaRestituire = (int)$_SESSION['carrello'][$prodottoId]['qta'];
        $conn->query("UPDATE products SET giacenza = giacenza + $qtaDaRestituire WHERE id = $prodottoId");
    }
    
    // Togliamo il prodotto dalla memoria della sessione
    unset($_SESSION['carrello'][$prodottoId]);
    
    // Se togliendo questo prodotto il carrello diventa vuoto, eliminiamo anche il timer
    if (empty($_SESSION['carrello'])) {
        $_SESSION['carrello_ts'] = null;
    }

    if ($isAjax) {
        sendJson([
            'ok' => true,
            'product_id' => $prodottoId,
            'removed' => true,
            'cart_count' => mss_cart_count($_SESSION['carrello'] ?? []),
            'cart_total' => mss_cart_total($_SESSION['carrello'] ?? []),
        ]);
    }

    header('Location: ../cart.php?msg=rimosso');
    exit();
}


// AZIONE 3: AGGIORNARE LA QUANTITÀ CON I TASTI PIÙ E MENO
if ($azione === 'aggiorna_qty' && $prodottoId > 0) {
    $nuovaQuantitaDesiderata = (int)($_POST['qta'] ?? 1);
    
    // Controlliamo che il prodotto sia effettivamente nel suo carrello
    if (isset($_SESSION['carrello'][$prodottoId])) {
        $quantitaAttuale = (int)$_SESSION['carrello'][$prodottoId]['qta'];
        $differenza = $nuovaQuantitaDesiderata - $quantitaAttuale;

        // Ripeschiamo i dati dal DB per capire quante scorte abbiamo
        $prodotto = mss_get_product_by_id($conn, $prodottoId);
        
        // Se il prodotto esiste ancora e il numero richiesto ha senso (almeno 1)
        if ($prodotto && $nuovaQuantitaDesiderata >= 1) {
            
            // Se chiede più pezzi, controlliamo se c'è abbastanza giacenza residua
            if ($differenza > 0 && (int)$prodotto['giacenza'] < $differenza) {
                if ($isAjax) {
                    sendJson(['ok' => false, 'message' => 'Quantità non valida o superiore alla disponibilità.'], 409);
                }
                header('Location: ../cart.php?error=giacenza');
                exit();
            }

            // Aggiorniamo la giacenza fisica nel DB!
            if ($differenza > 0) {
                $conn->query("UPDATE products SET giacenza = giacenza - $differenza WHERE id = $prodottoId");
            } elseif ($differenza < 0) {
                $daRestituire = abs($differenza);
                $conn->query("UPDATE products SET giacenza = giacenza + $daRestituire WHERE id = $prodottoId");
            }

            // Aggiorniamo il carrello in memoria!
            $_SESSION['carrello'][$prodottoId]['qta'] = $nuovaQuantitaDesiderata;
            $_SESSION['carrello_ts'] = time();

            if ($isAjax) {
                sendJson([
                    'ok' => true,
                    'product_id' => $prodottoId,
                    'item_qty' => $nuovaQuantitaDesiderata,
                    'cart_count' => mss_cart_count($_SESSION['carrello']),
                    'cart_total' => mss_cart_total($_SESSION['carrello']),
                ]);
            }
        } elseif ($isAjax) {
            sendJson(['ok' => false, 'message' => 'Quantità non valida.'], 409);
        }
    }

    // Se cercava di aggiornare qualcosa che non è nel carrello
    if ($isAjax) {
        sendJson(['ok' => false, 'message' => 'Prodotto non presente nel carrello.'], 404);
    }

    header('Location: ../cart.php');
    exit();
}


// AZIONE 4: SVUOTARE L'INTERO CARRELLO (Cestino)
if ($azione === 'svuota') {
    // Restituiamo le scorte al magazzino per tutti i prodotti nel carrello
    if (!empty($_SESSION['carrello'])) {
        foreach ($_SESSION['carrello'] as $id => $dati) {
            $qta = (int)$dati['qta'];
            $id = (int)$id;
            $conn->query("UPDATE products SET giacenza = giacenza + $qta WHERE id = $id");
        }
    }

    $_SESSION['carrello']    = [];
    $_SESSION['carrello_ts'] = null;

    if ($isAjax) {
        sendJson(['ok' => true, 'cart_count' => 0, 'cart_total' => 0]);
    }

    header('Location: ../cart.php?msg=svuotato');
    exit();
}


// AZIONE 5: CHECKOUT - L'UTENTE PAGA!
if ($azione === 'checkout') {
    
    // Un controllo di sicurezza: ha provato a pagare un carrello vuoto?
    if (empty($_SESSION['carrello'])) {
        header('Location: ../cart.php?msg=vuoto');
        exit();
    }

    // Prepariamo l'etichetta del pacco con i dati, pulendo gli spazi bianchi inutili
    $datiSpedizione = [
        'nome' => trim($_POST['nome'] ?? ''),
        'cognome' => trim($_POST['cognome'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'indirizzo' => trim($_POST['indirizzo'] ?? ''),
        'citta' => trim($_POST['citta'] ?? ''),
        'cap' => trim($_POST['cap'] ?? ''),
        'provincia' => trim($_POST['provincia'] ?? ''),
        'note' => trim($_POST['note'] ?? ''),
    ];
    $metodoDiPagamento = trim($_POST['payment_method'] ?? '');
    
    // Controlliamo se è un utente registrato o un ospite anonimo
    $idUtente = isset($_SESSION['utente_id']) ? (int)$_SESSION['utente_id'] : null;

    try {
        // Tentiamo di registrare l'ordine vero e proprio nel database!
        $idNuovoOrdine = mss_create_order_from_cart($conn, $idUtente, $_SESSION['carrello'], $datiSpedizione, $metodoDiPagamento);
        
        // Salviamo il numero dell'ordine in sessione per potergli mostrare il riepilogo nella pagina successiva
        $_SESSION['ultimo_ordine'] = $idNuovoOrdine;
        
        // Ordine concluso, il carrello torna magicamente vuoto
        $_SESSION['carrello'] = [];
        $_SESSION['carrello_ts'] = null;

        if ($isAjax) {
            sendJson(['ok' => true, 'redirect' => '../confirmPage.php?ordine=' . $idNuovoOrdine]);
        }

        // Rimandiamo il cliente felice alla pagina di conferma
        header('Location: ../confirmPage.php?ordine=' . $idNuovoOrdine);
        exit();
        
    } catch (Throwable $erroreNelDatabase) {
        // Se c'è stato un errore gravissimo col database durante il pagamento
        if ($isAjax) {
            sendJson(['ok' => false, 'message' => $erroreNelDatabase->getMessage()], 500);
        }
        header('Location: ../cart.php?msg=checkout_error');
        exit();
    }
}

// Se l'azione passata all'inizio non corrisponde a nessuna delle nostre regole, rimandiamo al carrello
header('Location: ../cart.php');
exit();
?>