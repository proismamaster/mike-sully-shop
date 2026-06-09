<?php
// Includiamo il file che controlla se l'utente ha i permessi giusti
include 'auth_check.php';

// Ci assicuriamo che chi sta facendo questa richiesta sia un vero amministratore
checkAdmin();

// Ci colleghiamo al database
require_once 'db_connection.php';

// Diciamo al browser che la nostra risposta non sarà una pagina web, ma un pacchetto di dati (JSON)
header('Content-Type: application/json');

// Leggiamo l'azione richiesta dall'URL, se non c'è mettiamo una stringa vuota di base
$azione = $_GET['action'] ?? '';

// L'amministratore ha chiesto di generare il report di ricerca dei clienti
if ($azione === 'client_report') {
    
    // Prendiamo la parola cercata (nome, cognome o email) e togliamo gli spazi inutili all'inizio e alla fine
    $termineRicerca = trim($_GET['term'] ?? '');
    
    // Se non ha scritto nulla, ci fermiamo e mandiamo un errore
    if ($termineRicerca === '') {
        echo json_encode(['success' => false, 'error' => 'Termine di ricerca vuoto.']);
        exit;
    }

    // Vogliamo prendere i clienti, contare i loro ordini (che non siano stati annullati) e sommare quanto hanno speso.
    $istruzioneSql = $conn->prepare("
        SELECT u.id, u.nome, u.cognome, u.email,
        COUNT(o.id) as total_orders, SUM(o.totale) as total_spent
        FROM users u
        LEFT JOIN orders o ON o.utente_id = u.id AND o.stato != 'annullato'
        WHERE (u.cognome LIKE ? OR u.email LIKE ? OR u.nome LIKE ?) AND u.ruolo = 'cliente'
        GROUP BY u.id
        ORDER BY total_spent DESC
    ");
    
    // Aggiungiamo il simbolo "%" per cercare quella parola anche in mezzo ad altre (es. "Mario" trova anche "Gianmario")
    $termineConJolly = "%$termineRicerca%";
    
    // Inseriamo i termini di ricerca in modo protetto, così da evitare attacchi hacker (SQL Injection)
    $istruzioneSql->bind_param('sss', $termineConJolly, $termineConJolly, $termineConJolly);
    $istruzioneSql->execute();
    
    // Raccogliamo i risultati e prepariamo un "cassetto" vuoto per metterci i clienti
    $risultati = $istruzioneSql->get_result();
    $listaClienti = [];
    
    // Scorriamo le righe trovate nel database, una per una
    while ($riga = $risultati->fetch_assoc()) {
        $listaClienti[] = [
            'id' => $riga['id'],
            'nome' => $riga['nome'],
            'cognome' => $riga['cognome'],
            'email' => $riga['email'],
            'total_orders' => (int)$riga['total_orders'],
            'total_spent' => (float)($riga['total_spent'] ?? 0)
        ];
    }
    $istruzioneSql->close();

    // Se non abbiamo trovato niente nel database
    if (empty($listaClienti)) {
        echo json_encode(['success' => false, 'error' => 'Nessun cliente trovato.']);
        exit;
    }

    // Se invece è andato tutto bene, impacchettiamo i clienti e li spediamo alla pagina!
    echo json_encode([
        'success' => true,
        'clients' => $listaClienti
    ]);
    exit;
}

// Se lo script arriva fin qui, vuol dire che l'azione richiesta non era tra quelle previste
echo json_encode(['success' => false, 'error' => 'Azione non valida.']);