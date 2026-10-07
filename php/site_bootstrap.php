<?php

// --- PROTEZIONE CSRF (per i form più sensibili: profilo, area admin) ---
// Un token legato alla sessione: senza saperlo in anticipo, un sito esterno
// non può far partire di nascosto una richiesta a nome dell'utente loggato.

function mss_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function mss_csrf_valid(): bool {
    $atteso = $_SESSION['csrf_token'] ?? '';
    $inviato = $_POST['csrf_token'] ?? '';
    return $atteso !== '' && is_string($inviato) && hash_equals($atteso, (string)$inviato);
}

// --- LIMITE TENTATIVI (anti brute-force su login e codici OTP) ---
// $chiave identifica cosa si sta proteggendo, es. "login:mario@x.com" o
// "otp:profile:mario@x.com" — così un blocco sul login non influenza l'OTP
// e viceversa, pur condividendo la stessa tabella.

function mss_rate_limited($conn, string $chiave): bool {
    // true = bloccato (troppi tentativi recenti), false = può procedere
    $stmt = $conn->prepare("SELECT locked_until FROM rate_limits WHERE rl_key = ? LIMIT 1");
    $stmt->bind_param('s', $chiave);
    $stmt->execute();
    $riga = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $riga && $riga['locked_until'] && strtotime((string)$riga['locked_until']) > time();
}

function mss_rate_limit_hit($conn, string $chiave, int $maxTentativi = 5, int $finestraSecondi = 900): void {
    $stmt = $conn->prepare("SELECT id, attempt_count, first_attempt_at FROM rate_limits WHERE rl_key = ? LIMIT 1");
    $stmt->bind_param('s', $chiave);
    $stmt->execute();
    $riga = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$riga) {
        $stmt = $conn->prepare("INSERT INTO rate_limits (rl_key, attempt_count, first_attempt_at) VALUES (?, 1, NOW())
                                 ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1");
        $stmt->bind_param('s', $chiave);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $finestraScaduta = (strtotime((string)$riga['first_attempt_at']) + $finestraSecondi) < time();
    if ($finestraScaduta) {
        $stmt = $conn->prepare("UPDATE rate_limits SET attempt_count = 1, first_attempt_at = NOW(), locked_until = NULL WHERE id = ?");
        $idRiga = (int)$riga['id'];
        $stmt->bind_param('i', $idRiga);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $nuovoConteggio = (int)$riga['attempt_count'] + 1;
    $idRiga = (int)$riga['id'];
    if ($nuovoConteggio >= $maxTentativi) {
        $stmt = $conn->prepare("UPDATE rate_limits SET attempt_count = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?");
        $stmt->bind_param('iii', $nuovoConteggio, $finestraSecondi, $idRiga);
    } else {
        $stmt = $conn->prepare("UPDATE rate_limits SET attempt_count = ? WHERE id = ?");
        $stmt->bind_param('ii', $nuovoConteggio, $idRiga);
    }
    $stmt->execute();
    $stmt->close();
}

function mss_rate_limit_clear($conn, string $chiave): void {
    $stmt = $conn->prepare("DELETE FROM rate_limits WHERE rl_key = ?");
    $stmt->bind_param('s', $chiave);
    $stmt->execute();
    $stmt->close();
}

// Ogni volta che il sito si connette al database, questo
// fa un giro d'ispezione per assicurarsi che le tabelle siano in piedi e che non manchino colonne.
function mss_bootstrap_schema($conn): void
{
    // Elenco delle tabelle che DEBBONO esistere. Se non ci sono, le costruisce al volo.
    $queryCreazioneTabelle = [
        "CREATE TABLE IF NOT EXISTS homepage_collections (
            id INT PRIMARY KEY,
            badge_text VARCHAR(60) NOT NULL DEFAULT 'NOVITÀ',
            title VARCHAR(120) NOT NULL DEFAULT 'Scopri la Nuova Collezione',
            subtitle TEXT NOT NULL,
            product_ids TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS order_shipping (
            ordine_id INT PRIMARY KEY,
            nome VARCHAR(60) NOT NULL,
            cognome VARCHAR(60) NOT NULL,
            email VARCHAR(120) NOT NULL,
            telefono VARCHAR(30) NOT NULL,
            indirizzo VARCHAR(160) NOT NULL,
            citta VARCHAR(80) NOT NULL,
            cap VARCHAR(10) NOT NULL,
            provincia VARCHAR(10) NOT NULL,
            metodo_pagamento VARCHAR(40) NOT NULL,
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ordine_id) REFERENCES orders(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS product_categories (
            prodotto_id INT NOT NULL,
            categoria_id INT NOT NULL,
            PRIMARY KEY (prodotto_id, categoria_id),
            FOREIGN KEY (prodotto_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (categoria_id) REFERENCES categories(id) ON DELETE CASCADE
        )",
        // Questa tabella serve a salvare i codici segreti numerici (OTP) inviati via email per il profilo
        "CREATE TABLE IF NOT EXISTS user_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            purpose VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (email),
            INDEX (purpose),
            INDEX (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Contatore tentativi falliti (login, codici OTP) per bloccare temporaneamente
        // chi ne sbaglia troppi di fila — senza questo, login e OTP sarebbero
        // indovinabili a forza bruta senza alcun freno.
        "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rl_key VARCHAR(190) NOT NULL UNIQUE,
            attempt_count INT NOT NULL DEFAULT 0,
            first_attempt_at DATETIME NOT NULL,
            locked_until DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Archivio storico delle email inviate
        "CREATE TABLE IF NOT EXISTS emails_outbox (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(64) NOT NULL DEFAULT 'queued',
            error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    // Eseguiamo la creazione di base
    foreach ($queryCreazioneTabelle as $istruzioneSql) {
        $conn->query($istruzioneSql);
    }

    // Se per caso stavamo usando un vecchio sistema in cui un prodotto aveva una sola categoria,
    // copiamo quei dati nel nuovo sistema multi-categoria senza fare danni (IGNORE evita duplicati).
    $conn->query(
        "INSERT IGNORE INTO product_categories (prodotto_id, categoria_id)
         SELECT id AS prodotto_id, categoria_id FROM products
         WHERE categoria_id IS NOT NULL AND categoria_id > 0"
    );

    // Controlliamo se esiste la tabella categorie...
    $esisteTabellaCat = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($esisteTabellaCat && $esisteTabellaCat->num_rows > 0) {
        // Se manca la categoria "Nuova Collezione", la creiamo noi!
        $conn->query("INSERT INTO categories (nome) SELECT 'Nuova Collezione' WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nome = 'Nuova Collezione')");
    }

    // Andiamo a ispezionare la colonna "stato" degli ordini...
    $ispezioneStati = $conn->query("SHOW COLUMNS FROM orders LIKE 'stato'");
    $tipoColonnaStato = '';
    if ($ispezioneStati && ($riga = $ispezioneStati->fetch_assoc())) {
        $tipoColonnaStato = $riga['Type'] ?? '';
    }

    // ...ci assicuriamo che abbia tutti i nomi di stato più recenti e moderni
    $statiDesiderati = "enum('preparazione','spedito','annullato','consegnato','in attesa','completato')";
    if (stripos($tipoColonnaStato, 'preparazione') === false || stripos($tipoColonnaStato, 'spedito') === false || stripos($tipoColonnaStato, 'annullato') === false || stripos($tipoColonnaStato, 'consegnato') === false) {
        $conn->query("ALTER TABLE orders MODIFY stato {$statiDesiderati} DEFAULT 'preparazione'");
    }

    // Il muratore controlla: C'è la colonna dello "Sconto" nei prodotti? Se no, la aggiunge.
    $ispezioneSconti = $conn->query("SHOW COLUMNS FROM products LIKE 'sconto_percentuale'");
    if ($ispezioneSconti && $ispezioneSconti->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN sconto_percentuale INT NOT NULL DEFAULT 0 AFTER prezzo");
    }

    // C'è la colonna del "Cestino morbido" (Soft Delete)? Serve per nascondere i prodotti senza cancellarli fisicamente.
    $ispezioneCancellati = $conn->query("SHOW COLUMNS FROM products LIKE 'deleted_at'");
    if ($ispezioneCancellati && $ispezioneCancellati->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    }

    // Allarghiamo lo spazio per i nomi delle immagini per permetterci di metterne più di una (Formato Testo Lungo)
    $conn->query("ALTER TABLE products MODIFY immagine_path TEXT");
}


// --- FUNZIONI DI UTILITÀ VARIE ---

// Legge una stringa di testo dal DB. Se vede che è scritta in formato JSON (le parentesi quadre []), la trasforma in una lista vera e propria di immagini.
function mss_get_product_images(?string $percorso): array {
    if (!$percorso) return [];
    
    $percorso = trim($percorso);
    if (str_starts_with($percorso, '[')) {
        $listaDecodificata = json_decode($percorso, true);
        if (is_array($listaDecodificata)) {
            return $listaDecodificata;
        }
    }
    return [$percorso]; // Se non era json, ritorna solo quell'immagine singola
}

// Salva un'immagine caricata dal magazzino e restituisce il percorso da mettere nel prodotto
// (null se il file non va bene). L'estensione dice solo come si chiama il file: getimagesize()
// guarda i byte veri e fallisce se non è davvero un'immagine. In demo le credenziali admin sono
// pubbliche: l'immagine resta nel database del visitatore come data URI, niente file sul server.
function mss_save_uploaded_image(string $tmpPath, string $uploadsDir, string $fileName): ?string {
    $info = @getimagesize($tmpPath);
    if ($info === false) return null;
    if (defined('MSS_DEMO_MODE')) {
        if (filesize($tmpPath) > 2 * 1024 * 1024) return null;
        return 'data:' . $info['mime'] . ';base64,' . base64_encode((string)file_get_contents($tmpPath));
    }
    return move_uploaded_file($tmpPath, $uploadsDir . DIRECTORY_SEPARATOR . $fileName) ? 'assets/img/products/' . $fileName : null;
}

// Fornisce i testi standard della bacheca centrale (La "Nuova Collezione" nella Home)
function mss_default_home_collection(): array {
    return [
        'badge_text' => 'NOVITÀ 2026',
        'title' => 'Scopri la Nuova Collezione',
        'subtitle' => "Articoli esclusivi e in edizione limitata ispirati al mondo di Monstropolis.\nSolo per i fan più coraggiosi!",
        'product_ids' => '',
    ];
}

function mss_get_home_collection($conn): array {
    $testiStandard = mss_default_home_collection();
    $istruzione = $conn->prepare("SELECT badge_text, title, subtitle, product_ids FROM homepage_collections WHERE id = 1 LIMIT 1");
    if (!$istruzione) {
        return $testiStandard;
    }
    $istruzione->execute();
    $risultatiBacheca = $istruzione->get_result()->fetch_assoc();
    $istruzione->close();

    // Se la bacheca nel DB è vuota, gli diamo noi quella di riserva (i testi standard)
    return $risultatiBacheca ?: $testiStandard;
}

function mss_save_home_collection($conn, array $datiBacheca): bool {
    $targhetta = trim($datiBacheca['badge_text'] ?? 'NOVITÀ');
    $titolo = trim($datiBacheca['title'] ?? 'Scopri la Nuova Collezione');
    $sottotitolo = trim($datiBacheca['subtitle'] ?? '');
    $idProdottiSelezionati = trim($datiBacheca['product_ids'] ?? '');

    // Aggiorna la bacheca centrale (se non c'è, crea la riga 1)
    $istruzione = $conn->prepare(
        "INSERT INTO homepage_collections (id, badge_text, title, subtitle, product_ids)
         VALUES (1, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE badge_text = VALUES(badge_text), title = VALUES(title), subtitle = VALUES(subtitle), product_ids = VALUES(product_ids)"
    );
    $istruzione->bind_param('ssss', $targhetta, $titolo, $sottotitolo, $idProdottiSelezionati);
    $salvataggioRiuscito = $istruzione->execute();
    $istruzione->close();

    return $salvataggioRiuscito;
}

// L'amministratore scrive "12, 5, 8" nel magazzino. Questa funzione pulisce quel testo e lo trasforma in una lista pulita di numeri.
function mss_parse_product_ids($idScritti) {
    $pezzi = preg_split('/[\s,;]+/', $idScritti) ?: [];
    $pezzi = array_map('intval', $pezzi);
    
    $pezziPuliti = [];
    foreach ($pezzi as $id) {
        if ($id > 0) $pezziPuliti[] = $id;
    }
    return array_values(array_unique($pezziPuliti));
}

// Trova rapidamente tutti i prodotti che corrispondono a una lista precisa di ID (utile per mostrare la vetrina in Home)
function mss_fetch_products_by_ids($conn, $listaId) {
    if (!$listaId) return [];

    $idValidi = [];
    foreach ($listaId as $idSingolo) {
        $idIntero = (int)$idSingolo;
        if ($idIntero > 0) $idValidi[] = $idIntero;
    }
    
    if (!$idValidi) return [];

    $codiceSql = "SELECT p.id, p.nome, p.descrizione, p.prezzo, p.sconto_percentuale, p.giacenza, p.immagine_path, c.nome AS cat
            FROM products p
            LEFT JOIN categories c ON p.categoria_id = c.id
            WHERE p.id IN (" . implode(',', $idValidi) . ") AND p.deleted_at IS NULL
            ORDER BY p.id ASC";
            
    $risultato = $conn->query($codiceSql);
    if (!$risultato) return [];

    $prodottiTrovati = [];
    while ($riga = $risultato->fetch_assoc()) {
        $prodottiTrovati[] = $riga;
    }

    return $prodottiTrovati;
}