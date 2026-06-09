<?php
include 'php/auth_check.php';
checkAdmin();
require_once 'php/db_connection.php';
require_once 'php/order_functions.php';
require_once 'php/mailer.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['ordine_id'] ?? 0);
    $status = trim($_POST['stato'] ?? '');

    try {
        if ($orderId > 0) {
            mss_update_order_status($conn, $orderId, $status);
            // Invia mail al cliente con il nuovo stato
            $ord = mss_fetch_order_by_id($conn, $orderId);
            if ($ord) {
                $to = trim((string)($ord['ship_email'] ?? '')) ?: trim((string)($ord['user_email'] ?? ''));
                if ($to !== '') {
                    $statusLbl = mss_display_order_status((string)$status);
                    $subject = "Ordine #{$orderId} - Stato aggiornato a {$statusLbl}";
                    $body = "<div style=\"font-family:Inter,Arial,sans-serif;font-size:14px;color:#0f172a\">"
                          . "<h2 style=\"margin:0 0 12px\">MikeSullyShop</h2>"
                          . "<p>Ciao, ti informiamo che lo stato del tuo ordine <strong>#{$orderId}</strong> è stato aggiornato a <strong>{$statusLbl}</strong>.</p>"
                          . "<p>Puoi controllare i dettagli accedendo alla tua area ordini.</p>"
                          . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:16px 0\">"
                          . "<p style=\"color:#64748b\">Questa è una notifica automatica. Per assistenza rispondi a questa email.</p>"
                          . "</div>";
                    @mss_send_mail($to, $subject, $body);
                }
            }
            header('Location: adminOrdini.php?success=1');
            exit();
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$statuses = mss_allowed_order_statuses();

// Filtri
$q = trim($_GET['q'] ?? '');
$stato = trim($_GET['stato'] ?? '');
$prodotto = trim($_GET['prodotto'] ?? '');

// Costruiamo query con filtri basilari (utente/email/id/stato)
$sql = "
  SELECT o.id, o.data_ordine, o.totale, o.stato,
         o.utente_id,
         COALESCE(u.nome, s.nome) AS display_nome,
         COALESCE(u.cognome, s.cognome) AS display_cognome,
         COALESCE(u.email, s.email) AS display_email,
         COUNT(d.id) AS items_count
  FROM orders o
  LEFT JOIN users u ON u.id = o.utente_id
  LEFT JOIN order_shipping s ON s.ordine_id = o.id
  LEFT JOIN order_details d ON d.ordine_id = o.id
";
$where = [];
$types = '';
$params = [];
if ($q !== '') {
  $like = '%' . $q . '%';
  $isNumeric = ctype_digit($q);
  if ($isNumeric) {
    $where[] = '(o.id = ? OR u.nome LIKE ? OR u.cognome LIKE ? OR u.email LIKE ? OR s.nome LIKE ? OR s.cognome LIKE ? OR s.email LIKE ? OR CONCAT_WS(\' \', u.nome, u.cognome) LIKE ? OR CONCAT_WS(\' \', s.nome, s.cognome) LIKE ?)';
    $types  .= 'issssssss';
    $params[] = (int)$q;
    $params[] = $like; $params[] = $like; $params[] = $like;
    $params[] = $like; $params[] = $like; $params[] = $like;
    $params[] = $like; $params[] = $like;
  } else {
    $where[] = '(u.nome LIKE ? OR u.cognome LIKE ? OR u.email LIKE ? OR s.nome LIKE ? OR s.cognome LIKE ? OR s.email LIKE ? OR CONCAT_WS(\' \', u.nome, u.cognome) LIKE ? OR CONCAT_WS(\' \', s.nome, s.cognome) LIKE ?)';
    $types  .= 'ssssssss';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $params[] = $like; $params[] = $like; $params[] = $like;
    $params[] = $like; $params[] = $like;
  }
}
if ($stato !== '') {
  $where[] = 'o.stato = ?';
  $types  .= 's';
  $params[] = mss_normalize_order_status($stato);
}
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' GROUP BY o.id, o.data_ordine, o.totale, o.stato, o.utente_id, u.nome, u.cognome, u.email, s.nome, s.cognome, s.email ORDER BY o.data_ordine DESC, o.id DESC';

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Filtro per prodotto per semplicità: rimuove ordini che non contengono il prodotto cercato
if ($prodotto !== '' && !empty($orders)) {
  $ordiniFiltrati = [];
  foreach ($orders as $o) {
    // Cerco i dettagli dell'ordine per vedere cosa ha comprato l'utente
    $dettagliOrdine = mss_fetch_order_details($conn, (int)$o['id']);
    $prodottoTrovato = false;
    foreach ($dettagliOrdine as $d) {
      if (stripos((string)($d['nome'] ?? ''), $prodotto) !== false) {
        $prodottoTrovato = true;
        break; // Trovato, non serve cercare oltre in questo ordine
      }
    }
    
    // Se c'è il prodotto, lo tengo
    if ($prodottoTrovato) {
      $ordiniFiltrati[] = $o;
    }
  }
  // Sostituisco l'array degli ordini con quello filtrato
  $orders = $ordiniFiltrati;
}
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Gestione Ordini - MikeSullyShop Admin</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="mss-page">
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 mb-5 fade-in">
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Stato ordine aggiornato con successo.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h2 class="mss-section-title mb-0">
          <i class="bi bi-truck me-2 text-gradient"></i> Gestione Ordini
        </h2>
        <span class="mss-user-chip py-2 px-3">
          <i class="bi bi-shield-lock me-1"></i> Stati visibili e modificabili in tempo reale
        </span>
      </div>

      <div class="row g-4">
        <div class="col-12">
          <form class="mss-auth-card p-3" method="GET" action="adminOrdini.php">
            <div class="row g-2 align-items-end">
              <div class="col-sm-4">
                <label class="form-label small fw-semibold"><i class="bi bi-search me-1"></i> Cerca (ID/Nome/Cognome/Email)</label>
                <input type="text" name="q" class="form-control mss-input" value="<?= htmlspecialchars($q) ?>" placeholder="#123 o Mario o mario@email.it">
              </div>
              <div class="col-sm-3">
                <label class="form-label small fw-semibold"><i class="bi bi-filter-right me-1"></i> Stato</label>
                <select name="stato" class="form-select mss-select" onchange="this.form.submit()">
                  <option value="" <?= $stato === '' ? 'selected' : '' ?>>Tutti</option>
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $stato === $s ? 'selected' : '' ?>><?= htmlspecialchars(mss_display_order_status($s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-4">
                <label class="form-label small fw-semibold"><i class="bi bi-box-seam me-1"></i> Prodotto (contiene)</label>
                <input type="text" name="prodotto" class="form-control mss-input" value="<?= htmlspecialchars($prodotto) ?>" placeholder="Nome prodotto">
              </div>
              <div class="col-sm-1 d-grid">
                <button class="btn mss-btn-primary" type="submit"><i class="bi bi-search"></i></button>
              </div>
            </div>
          </form>
        </div>
        <div class="col-12">
          <div class="mss-auth-card">
            <div class="mss-panel-header mss-panel-header-primary">
              <h5 class="mb-0 fw-bold">Tutti gli orders</h5>
            </div>
            <div class="card-body p-0">
              <?php if (empty($orders)): ?>
                <div class="p-5 text-center">
                  <i class="bi bi-inboxes mss-empty-icon"></i>
                  <h4 class="mt-3 fw-bold mss-empty-title">Nessun ordine disponibile</h4>
                  <p class="mss-empty-text mb-0">Gli orders degli users appariranno qui non appena completeranno un acquisto.</p>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-hover mb-0 align-middle">
                    <thead>
                      <tr>
                        <th style="cursor:pointer; min-width:100px;" data-sort="id">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-hash mss-link-primary"></i>
                            <span>Ordine</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th style="cursor:pointer;" data-sort="cliente">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person mss-link-primary"></i>
                            <span>Cliente</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th style="cursor:pointer;" data-sort="data">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-event mss-link-primary"></i>
                            <span>Data</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th>
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-box-seam mss-link-primary"></i>
                            <span>Articoli</span>
                          </div>
                        </th>
                        <th style="cursor:pointer;" data-sort="totale">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-currency-euro mss-link-primary"></i>
                            <span>Totale</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th style="cursor:pointer;" data-sort="stato">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle mss-link-primary"></i>
                            <span>Stato</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th class="text-end pe-3">Azione</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($orders as $order): ?>
                        <tr data-row>
                          <td class="fw-bold" data-id>#<?= (int)$order['id'] ?></td>
                          <td data-cliente>
                            <div class="fw-bold"><?= htmlspecialchars(($order['display_nome'] ?? '') . ' ' . ($order['display_cognome'] ?? '')) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($order['display_email'] ?? '') ?></div>
                          </td>
                          <td data-data><?= date('d/m/Y H:i', strtotime($order['data_ordine'])) ?></td>
                          <td><?= (int)$order['items_count'] ?></td>
                          <td class="fw-bold mss-link-primary" data-totale><?= number_format((float)$order['totale'], 2, ',', '.') ?>€</td>
                          <td data-stato="<?= htmlspecialchars($order['stato']) ?>">
                            <span class="badge <?= mss_order_status_class($order['stato']) ?>">
                              <?= htmlspecialchars(mss_display_order_status($order['stato'])) ?>
                            </span>
                          </td>
                          <td>
                            <?php $isIrreversible = in_array(strtolower($order['stato']), ['consegnato', 'annullato'], true); ?>
                            <form action="adminOrdini.php" method="POST" class="d-flex gap-2 align-items-center flex-wrap" onsubmit="return confirm('Confermi la modifica di stato?\nATTENZIONE: Se imposti \'Consegnato\' o \'Annullato\', la scelta sarà irreversibile.');">
                              <input type="hidden" name="ordine_id" value="<?= (int)$order['id'] ?>">
                              <select name="stato" class="form-select form-select-sm mss-select" <?= $isIrreversible ? 'disabled' : '' ?>>
                                <?php foreach ($statuses as $status): ?>
                                  <option value="<?= htmlspecialchars($status) ?>" <?= $order['stato'] === $status ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(mss_display_order_status($status)) ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                              <button type="submit" class="btn btn-sm mss-btn-primary" <?= $isIrreversible ? 'disabled' : '' ?>>Salva</button>
                            </form>
                          </td>
                        </tr>
                        <tr class="table-light" data-detail-row>
                          <td colspan="7">
                            <?php $details = mss_fetch_order_details($conn, (int)$order['id']); ?>
                            <div class="d-flex flex-wrap gap-2">
                              <?php foreach ($details as $detail): ?>
                                <span class="mss-user-chip px-3 py-2 d-inline-flex align-items-center gap-2">
                                  <i class="bi bi-box-seam"></i>
                                  <?= htmlspecialchars($detail['nome'] ?? 'Prodotto') ?> × <?= (int)$detail['quantita'] ?>
                                </span>
                              <?php endforeach; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      const getVal = (tr, key) => {
        if (key === 'id') return parseInt(tr.querySelector('[data-id]').textContent.replace('#','')) || 0;
        if (key === 'data') {
            const parts = tr.querySelector('[data-data]').textContent.split(' ');
            const d = parts[0].split('/');
            const t = parts[1].split(':');
            return new Date(d[2], d[1]-1, d[0], t[0], t[1]).getTime();
        }
        if (key === 'cliente') return tr.querySelector('[data-cliente]').textContent.toLowerCase();
        if (key === 'totale') return parseFloat(tr.querySelector('[data-totale]').textContent.replace('€','').replace('.','').replace(',','.')) || 0;
        if (key === 'stato') return tr.querySelector('[data-stato]').getAttribute('data-stato');
        return '';
      };

      document.querySelectorAll('[data-sort]').forEach(th => {
        let dir = 1;
        th.addEventListener('click', () => {
          const key = th.getAttribute('data-sort');
          const tbody = th.closest('table').querySelector('tbody');
          
          // Select only the primary order rows (tr[data-row])
          const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
          
          rows.sort((a, b) => {
            const va = getVal(a, key), vb = getVal(b, key);
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb)) * dir;
          });
          
          dir *= -1;
          
          // Re-append each parent row and its immediate detail row
          rows.forEach(r => {
            tbody.appendChild(r);
            const detail = r.nextElementSibling;
            if (detail && detail.hasAttribute('data-detail-row')) {
              tbody.appendChild(detail);
            }
          });
        });
      });
    </script>
  </body>
</html>
