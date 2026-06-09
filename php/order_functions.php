<?php
// Restituisce l'elenco degli stati validi in cui può trovarsi un ordine
function mss_allowed_order_statuses() {
    return ['preparazione', 'spedito', 'consegnato', 'annullato'];
}

// Assegna un colore grafico (classi Bootstrap) in base allo stato dell'ordine
function mss_order_status_class($stato) {
    $statoNormale = mss_normalize_order_status($stato);
    switch ($statoNormale) {
        case 'preparazione': return 'bg-warning text-dark'; // Giallo
        case 'spedito': return 'bg-info text-dark';    // Azzurro
        case 'consegnato': return 'bg-success text-white'; // Verde
        case 'annullato': return 'bg-danger text-white';  // Rosso
        default: return 'bg-secondary text-white'; // Grigio (Sconosciuto)
    }
}

// Corregge vecchi stati usati in passato o scritti male, portandoli allo standard attuale
function mss_normalize_order_status($stato) {
    switch ($stato) {
        case 'in attesa': return 'preparazione';
        case 'completato': return 'consegnato';
        default: return $stato;
    }
}

// Mette la lettera maiuscola allo stato per renderlo bello da leggere per l'utente
function mss_display_order_status($stato) {
    $statoNormale = mss_normalize_order_status($stato);
    switch ($statoNormale) {
        case 'preparazione': return 'Preparazione';
        case 'spedito': return 'Spedito';
        case 'consegnato': return 'Consegnato';
        case 'annullato': return 'Annullato';
        default: return ucfirst($stato); // Mette la prima lettera maiuscola
    }
}

// Controlla se un cliente è ancora in tempo per annullare un ordine (solo se è in preparazione)
function mss_can_cancel_order($stato) {
    return mss_normalize_order_status($stato) === 'preparazione';
}

// Cerca un singolo prodotto nel database in base al suo ID
function mss_get_product_by_id($conn, $id) {
    $istruzione = $conn->prepare("SELECT id, nome, prezzo, giacenza, immagine_path FROM products WHERE id = ? LIMIT 1");
    $istruzione->bind_param('i', $id);
    $istruzione->execute();
    
    $prodottoTrovato = $istruzione->get_result()->fetch_assoc();
    $istruzione->close();

    return $prodottoTrovato ? $prodottoTrovato : null;
}

// Conta quanti oggetti in totale ci sono nel carrello (sommando le quantità)
function mss_cart_count($carrello) {
    $totalePezzi = 0;
    foreach ($carrello as $prodotto) {
        $totalePezzi += (int)(isset($prodotto['qta']) ? $prodotto['qta'] : 0);
    }
    return $totalePezzi;
}

// Calcola il prezzo totale del carrello in Euro
function mss_cart_total($carrello) {
    $totaleEuro = 0.0;
    foreach ($carrello as $prodotto) {
        $prezzo = isset($prodotto['prezzo']) ? (float)$prodotto['prezzo'] : 0.0;
        $quantita = isset($prodotto['qta']) ? (int)$prodotto['qta'] : 0;
        $totaleEuro += $prezzo * $quantita;
    }
    return $totaleEuro;
}


//  FUNZIONI CREARE L'ORDINE (CHECKOUT)

function mss_create_order_from_cart($conn, $idUtente, $carrello, $datiSpedizione, $metodoPagamento) {
    
    // Controlli di sicurezza iniziali
    if (empty($carrello)) {
        throw new RuntimeException('Carrello vuoto');
    }

    // Assicuriamoci che abbia compilato tutti i campi obbligatori dell'indirizzo
    $campiObbligatori = ['nome', 'cognome', 'email', 'telefono', 'indirizzo', 'citta', 'cap', 'provincia'];
    foreach ($campiObbligatori as $campo) {
        if (trim((string)($datiSpedizione[$campo] ?? '')) === '') {
            throw new InvalidArgumentException('Compila tutti i dati di spedizione.');
        }
    }

    $metodoPagamento = trim($metodoPagamento);
    if ($metodoPagamento === '') {
        throw new InvalidArgumentException('Seleziona un metodo di pagamento.');
    }

    // AVVIAMO UNA TRANSAZIONE: o va tutto a buon fine (scalare scorte, creare ordine, ecc) o non si salva nulla.
    // Serve a evitare di far pagare uno senza togliere le scorte, o viceversa in caso di crash a metà.
    $conn->begin_transaction();

    try {
        $listaProdottiOrdine = [];
        $totaleOrdine = 0.0;

        // 1. Analizziamo ogni prodotto del carrello e prepariamolo
        foreach ($carrello as $idProdotto => $datiCarrello) {
            $idProdotto = (int)$idProdotto;
            $quantitaDesiderata = max(1, (int)($datiCarrello['qta'] ?? 1));

            // Chiediamo al DB i dati del prodotto e li BLOCCHIAMO (FOR UPDATE) per evitare che un altro
            // cliente compri l'ultimo pezzo nello stesso millisecondo
            $istruzione = $conn->prepare("SELECT id, nome, prezzo, giacenza, immagine_path FROM products WHERE id = ? FOR UPDATE");
            $istruzione->bind_param('i', $idProdotto);
            $istruzione->execute();
            $prodottoInMagazzino = $istruzione->get_result()->fetch_assoc();
            $istruzione->close();

            // Se qualcuno ha cancellato il prodotto dal sistema mentre l'utente pagava
            if (!$prodottoInMagazzino) {
                throw new RuntimeException("Prodotto non trovato: {$idProdotto}");
            }

            // Se l'ha comprato qualcun altro poco prima
            if ((int)$prodottoInMagazzino['giacenza'] < $quantitaDesiderata) {
                throw new RuntimeException("Giacenza insufficiente per {$prodottoInMagazzino['nome']}");
            }

            $listaProdottiOrdine[] = [
                'id' => (int)$prodottoInMagazzino['id'],
                'nome' => $prodottoInMagazzino['nome'],
                'prezzo' => (float)$prodottoInMagazzino['prezzo'],
                'qta' => $quantitaDesiderata,
            ];

            $totaleOrdine += (float)$prodottoInMagazzino['prezzo'] * $quantitaDesiderata;
        }

        // 2. Creiamo la riga principale dell'ordine ("Scatola vuota")
        if ($idUtente !== null) {
            $istruzione = $conn->prepare("INSERT INTO orders (utente_id, totale, stato) VALUES (?, ?, 'preparazione')");
            $istruzione->bind_param('id', $idUtente, $totaleOrdine);
        } else {
            // Se è un ospite anonimo
            $istruzione = $conn->prepare("INSERT INTO orders (utente_id, totale, stato) VALUES (NULL, ?, 'preparazione')");
            $istruzione->bind_param('d', $totaleOrdine);
        }
        $istruzione->execute();
        $idNuovoOrdine = $conn->insert_id; // Prendiamo il numero progressivo che gli ha dato il DB
        $istruzione->close();

        // 3. Inseriamo i prodotti uno ad uno dentro l'ordine e SCALIAMO le scorte dal magazzino
        $istruzioneDettagli = $conn->prepare("INSERT INTO order_details (ordine_id, prodotto_id, quantita, prezzo_unitario) VALUES (?, ?, ?, ?)");
        $istruzioneScorte = $conn->prepare("UPDATE products SET giacenza = giacenza - ? WHERE id = ?");

        foreach ($listaProdottiOrdine as $prodotto) {
            $istruzioneDettagli->bind_param('iiid', $idNuovoOrdine, $prodotto['id'], $prodotto['qta'], $prodotto['prezzo']);
            $istruzioneDettagli->execute();

            $istruzioneScorte->bind_param('ii', $prodotto['qta'], $prodotto['id']);
            $istruzioneScorte->execute();
        }

        $istruzioneDettagli->close();
        $istruzioneScorte->close();

        // 4. Salviamo l'indirizzo e l'etichetta di spedizione
        $istruzioneSpedizione = $conn->prepare(
            "INSERT INTO order_shipping (ordine_id, nome, cognome, email, telefono, indirizzo, citta, cap, provincia, metodo_pagamento, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $note = trim((string)($datiSpedizione['note'] ?? ''));
        
        $istruzioneSpedizione->bind_param(
            'issssssssss',
            $idNuovoOrdine,
            $datiSpedizione['nome'],
            $datiSpedizione['cognome'],
            $datiSpedizione['email'],
            $datiSpedizione['telefono'],
            $datiSpedizione['indirizzo'],
            $datiSpedizione['citta'],
            $datiSpedizione['cap'],
            $datiSpedizione['provincia'],
            $metodoPagamento,
            $note
        );
        $istruzioneSpedizione->execute();
        $istruzioneSpedizione->close();

        // 5.confermiamo il salvataggio totale.
        $conn->commit();
        return $idNuovoOrdine;

    } catch (Throwable $erroreCritico) {
        // Se si rompe qualcosa, ANNULLA TUTTO (non scalare soldi/scorte e non creare ordini a metà)
        $conn->rollback();
        throw $erroreCritico; // Rilancia l'errore per farlo leggere
    }
}


// RECUPERO DATI ORDINI DAL DATABASE

// Prende tutti gli ordini fatti da uno specifico utente (Per il suo profilo)
function mss_fetch_orders_for_user($conn, $idUtente) {
    $codiceSql = "
        SELECT o.id, o.data_ordine, o.totale, o.stato,
               COUNT(d.id) AS items_count
        FROM orders o
        LEFT JOIN order_details d ON d.ordine_id = o.id
        WHERE o.utente_id = ?
        GROUP BY o.id, o.data_ordine, o.totale, o.stato
        ORDER BY o.data_ordine DESC, o.id DESC
    ";
    $istruzione = $conn->prepare($codiceSql);
    $istruzione->bind_param('i', $idUtente);
    $istruzione->execute();
    $risultati = $istruzione->get_result();

    $elencoOrdini = [];
    while ($riga = $risultati->fetch_assoc()) {
        $elencoOrdini[] = $riga;
    }

    $istruzione->close();
    return $elencoOrdini;
}

// Prende la lista dei prodotti contenuti dentro un ordine specifico
function mss_fetch_order_details($conn, $idOrdine) {
    $codiceSql = "
        SELECT d.prodotto_id, d.quantita, d.prezzo_unitario, p.nome, p.immagine_path
        FROM order_details d
        LEFT JOIN products p ON p.id = d.prodotto_id
        WHERE d.ordine_id = ?
        ORDER BY d.id ASC
    ";
    $istruzione = $conn->prepare($codiceSql);
    $istruzione->bind_param('i', $idOrdine);
    $istruzione->execute();
    $risultati = $istruzione->get_result();

    $prodottiNellOrdine = [];
    while ($riga = $risultati->fetch_assoc()) {
        $prodottiNellOrdine[] = $riga;
    }

    $istruzione->close();
    return $prodottiNellOrdine;
}

// Prende TUTTI gli ordini del negozio (usato dall'Amministratore nel gestionale)
function mss_fetch_all_orders($conn, $idUtenteFiltro = null) {
    $codiceSql = "
        SELECT o.id, o.data_ordine, o.totale, o.stato,
               o.utente_id, u.nome, u.cognome, u.email,
               COUNT(d.id) AS items_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.utente_id
        LEFT JOIN order_details d ON d.ordine_id = o.id
    ";

    // Se stiamo cercando gli ordini di una persona in particolare, aggiungiamo il filtro
    if ($idUtenteFiltro !== null) {
        $codiceSql .= " WHERE o.utente_id = ? ";
    }

    $codiceSql .= " GROUP BY o.id, o.data_ordine, o.totale, o.stato, o.utente_id, u.nome, u.cognome, u.email ORDER BY o.data_ordine DESC, o.id DESC";

    $istruzione = $conn->prepare($codiceSql);
    if ($idUtenteFiltro !== null) {
        $istruzione->bind_param('i', $idUtenteFiltro);
    }
    $istruzione->execute();
    $risultati = $istruzione->get_result();

    $tuttiGliOrdini = [];
    while ($riga = $risultati->fetch_assoc()) {
        $tuttiGliOrdini[] = $riga;
    }

    $istruzione->close();
    return $tuttiGliOrdini;
}

// L'amministratore cambia lo stato (es: da Preparazione a Spedito)
function mss_update_order_status($conn, $idOrdine, $nuovoStato) {
    $nuovoStato = mss_normalize_order_status(trim($nuovoStato));
    
    // Assicuriamoci che non stia inventando stati strani
    if (!in_array($nuovoStato, mss_allowed_order_statuses(), true)) {
        throw new InvalidArgumentException('Stato ordine non valido');
    }

    $istruzione = $conn->prepare("UPDATE orders SET stato = ? WHERE id = ?");
    $istruzione->bind_param('si', $nuovoStato, $idOrdine);
    $esito = $istruzione->execute();
    $istruzione->close();

    return $esito;
}

// Prende un solo ordine con tutti i dati del cliente e della spedizione (Per stamparlo/visualizzarlo)
function mss_fetch_order_by_id($conn, $idOrdine) {
    $istruzione = $conn->prepare("SELECT o.id, o.data_ordine, o.totale, o.stato, o.utente_id, u.nome AS user_nome, u.cognome AS user_cognome, u.email AS user_email, s.nome AS ship_nome, s.cognome AS ship_cognome, s.email AS ship_email, s.telefono, s.indirizzo, s.citta, s.cap, s.provincia, s.metodo_pagamento, s.note FROM orders o LEFT JOIN users u ON u.id = o.utente_id LEFT JOIN order_shipping s ON s.ordine_id = o.id WHERE o.id = ? LIMIT 1");
    $istruzione->bind_param('i', $idOrdine);
    $istruzione->execute();
    
    $datiOrdine = $istruzione->get_result()->fetch_assoc();
    $istruzione->close();

    return $datiOrdine ? $datiOrdine : null;
}

function mss_fetch_order_shipping($conn, $idOrdine) {
    $istruzione = $conn->prepare("SELECT * FROM order_shipping WHERE ordine_id = ? LIMIT 1");
    $istruzione->bind_param('i', $idOrdine);
    $istruzione->execute();
    $datiSpedizione = $istruzione->get_result()->fetch_assoc();
    $istruzione->close();

    return $datiSpedizione ? $datiSpedizione : null;
}

// L'utente si è pentito e clicca su "Annulla Ordine"
function mss_cancel_order($conn, $idOrdine, $idUtente) {
    $ordine = mss_fetch_order_by_id($conn, $idOrdine);
    
    // Controlliamo che l'ordine esista e che appartenga veramente a lui
    if (!$ordine || (int)$ordine['utente_id'] !== $idUtente) {
        throw new RuntimeException('Ordine non trovato.');
    }

    // Controlliamo che il pacco non sia già in viaggio
    if (!mss_can_cancel_order((string)$ordine['stato'])) {
        throw new RuntimeException('Questo ordine non può più essere annullato.');
    }

    // Apriamo un'altra transazione protetta
    $conn->begin_transaction();

    try {
        // 1. Troviamo quali e quanti prodotti aveva comprato
        $prodottiComprati = mss_fetch_order_details($conn, $idOrdine);
        
        $istruzioneRestituisciScorte = $conn->prepare("UPDATE products SET giacenza = giacenza + ? WHERE id = ?");
        
        // 2. Per ogni prodotto, rimettiamolo sugli scaffali (ridiamo la quantità al magazzino)
        foreach ($prodottiComprati as $prodotto) {
            $quantitaDaRestituire = (int)$prodotto['quantita'];
            $idOggetto = (int)$prodotto['prodotto_id'];
            $istruzioneRestituisciScorte->bind_param('ii', $quantitaDaRestituire, $idOggetto);
            $istruzioneRestituisciScorte->execute();
        }
        $istruzioneRestituisciScorte->close();

        // 3. Cambiamo la targhetta dell'ordine in "annullato"
        $istruzioneAnnulla = $conn->prepare("UPDATE orders SET stato = 'annullato' WHERE id = ?");
        $istruzioneAnnulla->bind_param('i', $idOrdine);
        $istruzioneAnnulla->execute();
        $istruzioneAnnulla->close();

        // 4. Salviamo i rimborsi di scorte!
        $conn->commit();
    } catch (Throwable $errore) {
        $conn->rollback();
        throw $errore;
    }
}