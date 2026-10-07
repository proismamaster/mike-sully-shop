<?php
// Backend della demo: un database SQLite privato per ogni visitatore, dietro la piccola
// parte dell'API mysqli che il negozio usa. Le pagine non cambiano: vedono un $conn che
// risponde come mysqli. Chi prova la demo può aggiungere, ordinare e cancellare senza
// toccare i dati degli altri; il database del visitatore sparisce dopo un giorno.

if (session_status() === PHP_SESSION_NONE) {
    // Cookie di sessione più severi: niente accesso da JS, solo HTTPS, non
    // inviato in richieste cross-site — riduce hijacking/CSRF sulla sessione.
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

define('MSS_DEMO_MODE', true);
const MSS_DEMO_DIR_NAME = 'mss-demo';
const MSS_DEMO_TTL = 86400; // un giorno: poi il database del visitatore viene rifatto

class DemoResult
{
    private array $rows;
    private int $pos = -1;
    public int $num_rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() { return $this->rows[++$this->pos] ?? null; }
    public function fetch_all($mode = MYSQLI_ASSOC) { return $this->rows; }
    public function fetch_row()
    {
        $row = $this->fetch_assoc();
        return $row === null ? null : array_values($row);
    }
    public function free() {}
    public function close() {}
}

class DemoStatement
{
    private array $params = [];
    private string $types = '';
    private ?DemoResult $result = null;
    public int $affected_rows = 0;
    public int $insert_id = 0;
    public int $num_rows = 0;
    public string $error = '';

    public function __construct(private DemoDB $db, private string $sql) {}

    public function bind_param($types, &...$vars)
    {
        $this->types = $types;
        $this->params = [];
        foreach ($vars as &$v) { $this->params[] = &$v; }
        return true;
    }

    public function execute($params = null)
    {
        if (is_array($params)) {
            $this->params = $params;
            $this->types = str_repeat('s', count($params));
        }
        $st = $this->db->pdo->prepare($this->db->translate($this->sql));
        foreach ($this->params as $i => $value) {
            $type = $this->types[$i] ?? 's';
            if ($value === null) {
                $st->bindValue($i + 1, null, PDO::PARAM_NULL);
            } elseif ($type === 'i') {
                $st->bindValue($i + 1, (int)$value, PDO::PARAM_INT);
            } elseif ($type === 'd') {
                $st->bindValue($i + 1, (float)$value);
            } else {
                $st->bindValue($i + 1, (string)$value);
            }
        }
        $st->execute();
        $this->result = $st->columnCount() > 0 ? new DemoResult($st->fetchAll(PDO::FETCH_ASSOC)) : null;
        $this->num_rows = $this->result ? $this->result->num_rows : 0;
        $this->affected_rows = $this->db->affected_rows = $st->rowCount();
        if (preg_match('/^\s*INSERT/i', $this->sql)) {
            $this->insert_id = $this->db->insert_id = (int)$this->db->pdo->lastInsertId();
        }
        return true;
    }

    public function get_result() { return $this->result ?? new DemoResult([]); }
    public function store_result() { return true; }
    public function fetch_assoc() { return $this->result?->fetch_assoc(); }
    public function free_result() {}
    public function close() { return true; }
}

class DemoDB
{
    public PDO $pdo;
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';

    public function __construct()
    {
        // Una cartella per utente di sistema: php-fpm (www-data) e un "php -S" di prova lanciato
        // da un altro utente condividono /tmp, e la cartella dell'uno non è scrivibile dall'altro.
        $utente = function_exists('posix_geteuid') ? (string)posix_geteuid() : get_current_user();
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . MSS_DEMO_DIR_NAME . '-' . $utente;
        if (!is_dir($dir)) { mkdir($dir, 0700, true); }
        // Nel nome anche la versione di questo file: se lo schema cambia, nessuno resta col database vecchio.
        $file = $dir . DIRECTORY_SEPARATOR . 'v-' . hash('sha256', session_id() . filemtime(__FILE__)) . '.sqlite';
        if (!is_file($file) || filemtime($file) < time() - MSS_DEMO_TTL) {
            copy(self::template($dir), $file);
            self::cleanup($dir);
        }
        $this->pdo = self::open($file);
    }

    private static function open(string $file): PDO
    {
        // Vincoli di chiave esterna lasciati spenti, come li tollerava il vecchio database finto.
        return new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    // Il modello si rifà ogni giorno: gli ordini di esempio hanno date relative a oggi,
    // così il fatturato "dell'ultimo mese" e i nuovi clienti non vanno mai a zero.
    private static function template(string $dir): string
    {
        $tpl = $dir . DIRECTORY_SEPARATOR . 'template.sqlite';
        $fresh = is_file($tpl) && filemtime($tpl) > max(time() - MSS_DEMO_TTL, filemtime(__FILE__));
        if (!$fresh) {
            $tmp = $tpl . '.' . bin2hex(random_bytes(4));
            $pdo = self::open($tmp);
            $pdo->exec(mss_demo_schema());
            mss_demo_seed($pdo);
            $pdo = null;
            rename($tmp, $tpl);
        }
        return $tpl;
    }

    private static function cleanup(string $dir): void
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'v-*.sqlite') ?: [] as $old) {
            if (filemtime($old) < time() - MSS_DEMO_TTL) { @unlink($old); }
        }
    }

    // Le poche espressioni MySQL che il negozio usa, riscritte per SQLite.
    public function translate(string $sql): string
    {
        $now = "datetime('now','localtime')";
        $sql = preg_replace_callback(
            '/DATE_(ADD|SUB)\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i',
            fn($m) => "datetime('now','localtime','" . (strtoupper($m[1]) === 'ADD' ? '+' : '-') . $m[2] . ' ' . strtolower($m[3]) . "')",
            $sql
        );
        $sql = preg_replace('/\bNOW\(\)/i', $now, $sql);
        $sql = preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql);
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);
        if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $sql = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', 'ON CONFLICT DO UPDATE SET', $sql);
            $sql = preg_replace('/\bVALUES\((\w+)\)/i', 'excluded.$1', $sql);
        }
        return $sql;
    }

    public function prepare($sql) { return new DemoStatement($this, $sql); }

    public function query($sql)
    {
        $st = $this->prepare($sql);
        $st->execute();
        return preg_match('/^\s*SELECT/i', $sql) ? $st->get_result() : true;
    }

    public function real_escape_string($s) { return substr($this->pdo->quote((string)$s), 1, -1); }
    public function begin_transaction() { return $this->pdo->inTransaction() ? true : $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->inTransaction() ? $this->pdo->commit() : true; }
    public function rollback() { return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true; }
    public function set_charset($charset) { return true; }
    public function close() { return true; }
}

// COLLATE NOCASE sui testi: come MySQL (utf8mb4_general_ci), 'Annullato' = 'annullato'.
function mss_demo_schema(): string
{
    return <<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL COLLATE NOCASE,
    cognome TEXT NOT NULL COLLATE NOCASE,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password TEXT NOT NULL,
    ruolo TEXT NOT NULL DEFAULT 'cliente' COLLATE NOCASE,
    data_creazione TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL UNIQUE COLLATE NOCASE
);
CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL COLLATE NOCASE,
    descrizione TEXT NOT NULL COLLATE NOCASE,
    prezzo REAL NOT NULL,
    sconto_percentuale INTEGER NOT NULL DEFAULT 0,
    giacenza INTEGER NOT NULL DEFAULT 0,
    immagine_path TEXT,
    categoria_id INTEGER REFERENCES categories(id),
    deleted_at TEXT NULL
);
CREATE TABLE product_categories (
    prodotto_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    categoria_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    PRIMARY KEY (prodotto_id, categoria_id)
);
CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    utente_id INTEGER NULL REFERENCES users(id),
    data_ordine TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    totale REAL NOT NULL,
    stato TEXT NOT NULL DEFAULT 'preparazione' COLLATE NOCASE
);
CREATE TABLE order_details (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ordine_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    prodotto_id INTEGER NOT NULL REFERENCES products(id),
    quantita INTEGER NOT NULL,
    prezzo_unitario REAL NOT NULL
);
CREATE TABLE order_shipping (
    ordine_id INTEGER PRIMARY KEY REFERENCES orders(id) ON DELETE CASCADE,
    nome TEXT NOT NULL COLLATE NOCASE,
    cognome TEXT NOT NULL COLLATE NOCASE,
    email TEXT NOT NULL COLLATE NOCASE,
    telefono TEXT NOT NULL,
    indirizzo TEXT NOT NULL,
    citta TEXT NOT NULL COLLATE NOCASE,
    cap TEXT NOT NULL,
    provincia TEXT NOT NULL,
    metodo_pagamento TEXT NOT NULL,
    note TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE homepage_collections (
    id INTEGER PRIMARY KEY,
    badge_text TEXT NOT NULL DEFAULT 'NOVITÀ',
    title TEXT NOT NULL DEFAULT 'Scopri la Nuova Collezione',
    subtitle TEXT NOT NULL,
    product_ids TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE wishlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    utente_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    prodotto_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    data_aggiunta TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    UNIQUE (utente_id, prodotto_id)
);
CREATE TABLE user_otps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL COLLATE NOCASE,
    code_hash TEXT NOT NULL,
    purpose TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rl_key TEXT NOT NULL UNIQUE,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    first_attempt_at TEXT NOT NULL,
    locked_until TEXT NULL
);
CREATE TABLE emails_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    to_email TEXT NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    error TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL;
}

// Catalogo, clienti e ordini di esempio. Personaggi di Monsters & Co., nessuna persona reale.
function mss_demo_seed(PDO $pdo): void
{
    $img = fn(string ...$files) => json_encode(array_map(fn($f) => 'assets/img/prodotti/' . $f, $files));

    $categories = [1 => 'Peluche', 2 => 'Abbigliamento', 3 => 'Accessori', 4 => 'Casa', 5 => 'Gadget', 6 => 'Nuova Collezione'];
    $st = $pdo->prepare('INSERT INTO categories (id, nome) VALUES (?, ?)');
    foreach ($categories as $id => $nome) { $st->execute([$id, $nome]); }

    // [nome, descrizione, prezzo, sconto %, giacenza, immagini, categoria, in Nuova Collezione]
    // Attenzione: i file peluche-sulley-* ritraggono Boo e i peluche-boo-* ritraggono Sulley (nomi scambiati all'origine).
    $products = [
        1 => ['Peluche Sulley', 'Morbidissimo peluche di James P. Sullivan, perfetto per le notti senza paura. Altezza 40 cm, pelo azzurro a macchie viola.', 34.99, 0, 15, $img('peluche-boo-in-costume-monsters-co--1778233287-2.jpg', 'peluche-boo-in-costume-monsters-co--1778233287-1.jpg', 'peluche-boo-in-costume-monsters-co--1778233287-0.jpg', 'pupazzo.png'), 1, false],
        2 => ['Peluche Boo in costume', 'Boo nel suo costume da mostro, come nella scena più famosa del film. Altezza 30 cm, tessuto lavabile.', 32.99, 5, 10, $img('peluche-sulley-monsters-co--1778233251-2.jpg', 'peluche-sulley-monsters-co--1778233251-0.jpg', 'peluche-sulley-monsters-co--1778233251-1.jpg', 'pupazzoBoo.png'), 1, false],
        3 => ['Funko Pop Sulley', 'Funko Pop di Sulley con il coperchio dei bidoni di Monsters & Co., edizione da collezione con scatola originale.', 29.99, 10, 8, $img('funko-pop-sulley-con-coperchio-monsters-co--1778233058-0.jpg', 'funko-pop-sulley-con-coperchio-monsters-co--1778233058-1.jpg'), 5, false],
        4 => ['T-Shirt Monsters & Co. anni \'90', 'T-shirt in cotone con Sulley e Mike in stile retrò anni \'90. Taglie dalla S alla XL.', 24.99, 0, 25, $img('t-shirt-monsters-co-sulley-mike-retro-90s-1778232992-0.png'), 2, false],
        5 => ['T-Shirt Mike Wazowski', 'T-shirt verde con il sorriso di Mike Wazowski stampato sul petto. Cotone biologico, taglie dalla S alla XL.', 22.99, 0, 30, $img('t-shirt-monsters-co-mike-wazowski-face-1778233104-0.png'), 2, true],
        6 => ['Astuccio Mike Wazowski', 'Astuccio portamatite a forma di Mike: si allunga per contenere penne, matite e righello.', 12.99, 0, 40, $img('astuccio-porta-penne-mike-wazowski-1778233175-0.jpg', 'astuccio-porta-penne-mike-wazowski-1778233175-1.jpg', 'astuccio-porta-penne-mike-wazowski-1778233175-2.jpg'), 3, true],
        7 => ['Calzini Monsters & Co.', 'Due paia di calzini in cotone con il logo Monsters & Co. e i colori di Sulley. Taglia unica 36-44.', 9.99, 15, 50, $img('calzini.png'), 2, true],
        8 => ['Zaino Monsters & Co.', 'Zaino bicolore con la porta di Boo ricamata e uno scomparto imbottito per il portatile da 15 pollici.', 39.99, 0, 12, $img('zaino.png'), 3, false],
        9 => ['Tazza Mike & Sulley', 'Tazza in ceramica da 350 ml con Mike e Sulley, per le pause più spaventose. Lavabile in lavastoviglie.', 14.99, 0, 30, $img('tazza.png'), 4, false],
    ];
    $st = $pdo->prepare('INSERT INTO products (id, nome, descrizione, prezzo, sconto_percentuale, giacenza, immagine_path, categoria_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $pivot = $pdo->prepare('INSERT INTO product_categories (prodotto_id, categoria_id) VALUES (?, ?)');
    $prices = [];
    foreach ($products as $id => [$nome, $desc, $prezzo, $sconto, $giacenza, $immagini, $cat, $nuova]) {
        $st->execute([$id, $nome, $desc, $prezzo, $sconto, $giacenza, $immagini, $cat]);
        $pivot->execute([$id, $cat]);
        if ($nuova) { $pivot->execute([$id, 6]); }
        $prices[$id] = round($prezzo * (1 - $sconto / 100), 2);
    }

    $pdo->prepare('INSERT INTO homepage_collections (id, badge_text, title, subtitle, product_ids) VALUES (1, ?, ?, ?, ?)')
        ->execute(['NOVITÀ 2026', 'Scopri la Nuova Collezione', "Articoli esclusivi e in edizione limitata ispirati al mondo di Monstropolis.\nSolo per i fan più coraggiosi!", '5,6,7']);

    // [nome, cognome, email, password, ruolo, giorni fa]
    $users = [
        1 => ['Admin', 'MikeSully', 'admin@mikesully.shop', 'admin123', 'admin', 120],
        2 => ['Mike', 'Wazowski', 'mike@monsters.com', 'password123', 'cliente', 90],
        3 => ['Celia', 'Mae', 'celia@monsters.com', 'password123', 'cliente', 25],
        4 => ['Randall', 'Boggs', 'randall@monsters.com', 'password123', 'cliente', 12],
        5 => ['Roz', 'Agente', 'roz@monsters.com', 'password123', 'cliente', 6],
    ];
    $st = $pdo->prepare("INSERT INTO users (id, nome, cognome, email, password, ruolo, data_creazione) VALUES (?, ?, ?, ?, ?, ?, datetime('now','localtime', ?))");
    foreach ($users as $id => [$nome, $cognome, $email, $pass, $ruolo, $giorni]) {
        $st->execute([$id, $nome, $cognome, $email, password_hash($pass, PASSWORD_DEFAULT), $ruolo, "-$giorni days"]);
    }

    $addresses = [
        2 => ['Via delle Urla 12', 'Monstropolis', '00142', 'MO', '+39 333 1200001'],
        3 => ['Piazza della Centrale 3', 'Monstropolis', '00143', 'MO', '+39 333 1200002'],
        4 => ['Vicolo dei Camaleonti 7', 'Monstropolis', '00144', 'MO', '+39 333 1200003'],
        5 => ['Corso Moduli 21', 'Monstropolis', '00145', 'MO', '+39 333 1200004'],
    ];
    // [cliente, giorni fa, stato, pagamento, [prodotto => quantità]]
    $orders = [
        [2, 40, 'consegnato', 'Carta di Credito', [1 => 1, 9 => 2]],
        [3, 18, 'consegnato', 'PayPal', [2 => 1, 3 => 1]],
        [3, 12, 'consegnato', 'Carta di Credito', [9 => 1, 1 => 1]],
        [4, 8, 'annullato', 'Contanti alla ricezione', [8 => 1]],
        [2, 6, 'spedito', 'PayPal', [4 => 1, 7 => 2]],
        [4, 3, 'spedito', 'Carta di Credito', [5 => 2]],
        [5, 2, 'preparazione', 'Carta di Credito', [1 => 2, 7 => 1]],
        [2, 1, 'preparazione', 'Contanti alla ricezione', [6 => 1]],
    ];
    $insOrder = $pdo->prepare("INSERT INTO orders (utente_id, data_ordine, totale, stato) VALUES (?, datetime('now','localtime', ?), ?, ?)");
    $insDetail = $pdo->prepare('INSERT INTO order_details (ordine_id, prodotto_id, quantita, prezzo_unitario) VALUES (?, ?, ?, ?)');
    $insShip = $pdo->prepare("INSERT INTO order_shipping (ordine_id, nome, cognome, email, telefono, indirizzo, citta, cap, provincia, metodo_pagamento, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, datetime('now','localtime', ?))");
    foreach ($orders as [$uid, $giorni, $stato, $pagamento, $righe]) {
        $totale = 0;
        foreach ($righe as $pid => $qta) { $totale += $prices[$pid] * $qta; }
        $quando = "-$giorni days";
        $insOrder->execute([$uid, $quando, round($totale, 2), $stato]);
        $oid = (int)$pdo->lastInsertId();
        foreach ($righe as $pid => $qta) { $insDetail->execute([$oid, $pid, $qta, $prices[$pid]]); }
        [$nome, $cognome, $email] = $users[$uid];
        [$via, $citta, $cap, $prov, $tel] = $addresses[$uid];
        $insShip->execute([$oid, $nome, $cognome, $email, $tel, $via, $citta, $cap, $prov, $pagamento, $quando]);
    }

    $st = $pdo->prepare('INSERT INTO wishlist (utente_id, prodotto_id) VALUES (?, ?)');
    foreach ([[2, 3], [2, 8]] as $row) { $st->execute($row); }
}

$conn = new DemoDB();
