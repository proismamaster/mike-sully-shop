<?php
include 'php/auth_check.php';
checkAdmin();
require_once 'php/db_connection.php';

// Prendo tutti gli ordini dal database per farli vedere in una tabella riassuntiva
$transactions = [];
$sql = "SELECT o.id, o.data_ordine, o.totale, u.nome, u.cognome, u.email 
        FROM orders o 
        JOIN users u ON o.utente_id = u.id 
        ORDER BY o.data_ordine DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Calcolo delle statistiche principali
// 1. Fatturato mensile (sommo il totale di tutti gli ordini non annullati nell'ultimo mese)
$fatturatoMensile = 0;
$stmt = $conn->prepare("SELECT SUM(totale) as fatturato FROM orders WHERE data_ordine >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND stato != 'Annullato'");
$stmt->execute();
$fatturatoMensile = (float)($stmt->get_result()->fetch_assoc()['fatturato'] ?? 0);
$stmt->close();

// 2. Prodotto più venduto in assoluto (sommo le quantità vendute per ogni prodotto e prendo il primo)
$bestSeller = "N/D";
$sqlBest = "SELECT p.nome, SUM(d.quantita) as qta 
            FROM order_details d 
            JOIN products p ON d.prodotto_id = p.id 
            GROUP BY d.prodotto_id 
            ORDER BY qta DESC LIMIT 1";
$resBest = $conn->query($sqlBest);
if ($resBest && $rowBest = $resBest->fetch_assoc()) {
    $bestSeller = $rowBest['nome'];
}

// 3. Quanti nuovi clienti si sono iscritti nell'ultimo mese
$nuoviClienti = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE data_creazione >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND ruolo = 'cliente'");
$stmt->execute();
$nuoviClienti = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Report Vendite - MikeSullyShop Admin</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="mss-page">
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 mb-5 fade-in">
      <h2 class="mss-section-title mb-4">
        <i class="bi bi-graph-up-arrow me-2 text-gradient"></i> Reportistica Vendite
      </h2>

      <div class="row g-4">
        <!-- Tabella Vendite -->
        <div class="col-lg-8">
            <div class="mss-auth-card">
                <div class="mss-panel-header mss-panel-header-primary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Riepilogo Transazioni</h5>
                    <input type="text" id="search-transactions" class="form-control form-control-sm w-auto mss-input" placeholder="Cerca cliente o ID...">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="table-transactions">
                            <thead style="background: linear-gradient(135deg, rgba(37,99,235,0.08) 0%, rgba(20,184,166,0.08) 100%);">
                                <tr>
                                    <th class="fw-semibold ps-3" style="cursor:pointer;" data-sort="id">
                                      <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-hash mss-link-primary"></i>
                                        <span>Transazione</span>
                                        <i class="bi bi-arrow-down-up small opacity-50"></i>
                                      </div>
                                    </th>
                                    <th class="fw-semibold" style="cursor:pointer;" data-sort="data">
                                      <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-calendar-event mss-link-primary"></i>
                                        <span>Data</span>
                                        <i class="bi bi-arrow-down-up small opacity-50"></i>
                                      </div>
                                    </th>
                                    <th class="fw-semibold" style="cursor:pointer;" data-sort="cliente">
                                      <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-person mss-link-primary"></i>
                                        <span>Cliente</span>
                                        <i class="bi bi-arrow-down-up small opacity-50"></i>
                                      </div>
                                    </th>
                                    <th class="fw-semibold" style="cursor:pointer;" data-sort="totale">
                                      <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-currency-euro mss-link-primary"></i>
                                        <span>Totale</span>
                                        <i class="bi bi-arrow-down-up small opacity-50"></i>
                                      </div>
                                    </th>
                                    <th class="fw-semibold text-end pe-3">Dettaglio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                <tr data-row>
                                    <td>#<?= (int)$t['id'] ?></td>
                                    <td data-data><?= date('d/m/Y H:i', strtotime($t['data_ordine'])) ?></td>
                                    <td class="fw-medium" data-cliente><?= htmlspecialchars($t['nome'] . ' ' . $t['cognome']) ?></td>
                                    <td class="fw-bold" style="color: var(--primary-dark);" data-totale><?= number_format((float)$t['totale'], 2, ',', '.') ?>€</td>
                                    <td><a href="adminOrdini.php?q=<?= (int)$t['id'] ?>" class="btn btn-sm mss-btn-outline"><i class="bi bi-eye"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transactions)): ?>
                                <tr><td colspan="5" class="text-center p-4 mss-text-muted">Nessuna transazione trovata.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Cliente Specifico -->
        <div class="col-lg-4">
            <div class="mss-auth-card">
                <div class="mss-panel-header mss-panel-header-accent">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Report Cliente</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cerca Cliente</label>
                        <div class="input-group">
                            <input type="text" id="search-client-report" class="form-control mss-input" placeholder="Cognome o Email">
                            <button class="btn mss-btn-outline" id="btn-search-client"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                    <div id="client-report-result" class="d-none">
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-hover" id="table-client-report">
                                <thead class="small fw-bold">
                                    <tr>
                                        <th style="cursor:pointer;" data-sort-cr="nome">
                                          <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-person mss-link-primary"></i>
                                            <span>Cliente</span>
                                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                                          </div>
                                        </th>
                                        <th style="cursor:pointer;" data-sort-cr="orders">
                                          <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-receipt mss-link-primary"></i>
                                            <span>Ordini</span>
                                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                                          </div>
                                        </th>
                                        <th style="cursor:pointer;" data-sort-cr="spesa">
                                          <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-wallet2 mss-link-primary"></i>
                                            <span>Spesa</span>
                                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                                          </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="client-report-body" class="small"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="client-report-placeholder" class="text-center p-3 mss-text-muted small">
                        Inserisci un cognome o email per visualizzare il report dettagliato del cliente.
                    </div>
                </div>
            </div>
        </div>
      </div>

      <!-- Statistiche Rapide -->
      <div class="row mt-4 g-4">
        <div class="col-md-4">
            <div class="mss-auth-card" style="background: linear-gradient(135deg, rgba(37,99,235,0.08) 0%, rgba(20,184,166,0.08) 100%) !important; border: 1px solid var(--border-light);">
                <div class="card-body text-center p-4">
                    <h6 class="opacity-75">Fatturato Mensile (Spediti/Consegnati)</h6>
                    <h2 class="fw-bold"><?= number_format($fatturatoMensile, 2, ',', '.') ?>€</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mss-auth-card" style="background: linear-gradient(135deg, rgba(20,184,166,0.08) 0%, rgba(37,99,235,0.08) 100%) !important; border: 1px solid var(--border-light);">
                <div class="card-body text-center p-4">
                    <h6 class="opacity-75">Articolo più Venduto</h6>
                    <h2 class="fw-bold"><?= htmlspecialchars($bestSeller) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mss-auth-card" style="background: linear-gradient(135deg, rgba(255,145,0,0.08) 0%, rgba(255,109,0,0.08) 100%) !important; border: 1px solid var(--border-light);">
                <div class="card-body text-center p-4">
                    <h6 class="opacity-75">Nuovi Clienti (Ultimo Mese)</h6>
                    <h2 class="fw-bold">+<?= $nuoviClienti ?></h2>
                </div>
            </div>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Search transactions
      const searchT = document.getElementById('search-transactions');
      if (searchT) {
        searchT.addEventListener('input', () => {
          const term = searchT.value.trim().toLowerCase();
          document.querySelectorAll('#table-transactions [data-row]').forEach(tr => {
            const text = tr.textContent.toLowerCase();
            tr.style.display = text.includes(term) ? '' : 'none';
          });
        });
      }

      // Sorting transactions
      const getVal = (tr, key) => {
        if (key === 'id') return parseInt(tr.cells[0].textContent.replace('#','')) || 0;
        if (key === 'data') {
            const parts = tr.querySelector('[data-data]').textContent.split(' ');
            const d = parts[0].split('/');
            const t = parts[1].split(':');
            return new Date(d[2], d[1]-1, d[0], t[0], t[1]).getTime();
        }
        if (key === 'cliente') return tr.querySelector('[data-cliente]').textContent.toLowerCase();
        if (key === 'totale') return parseFloat(tr.querySelector('[data-totale]').textContent.replace('€','').replace('.','').replace(',','.')) || 0;
        return '';
      };

      document.querySelectorAll('[data-sort]').forEach(th => {
        let dir = 1;
        th.addEventListener('click', () => {
          const key = th.getAttribute('data-sort');
          const tbody = th.closest('table').querySelector('tbody');
          const rows = Array.from(tbody.querySelectorAll('[data-row]'));
          rows.sort((a,b) => {
            const va = getVal(a, key), vb = getVal(b, key);
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return va.localeCompare(vb) * dir;
          });
          dir *= -1;
          rows.forEach(r => tbody.appendChild(r));
        });
      });

      // Search Client Report
      const btnSearchClient = document.getElementById('btn-search-client');
      const inputSearchClient = document.getElementById('search-client-report');
      const clientReportBody = document.getElementById('client-report-body');
      
      const searchClient = () => {
        const term = inputSearchClient.value.trim();
        if (!term) return;

        fetch(`php/admin_handler.php?action=client_report&term=${encodeURIComponent(term)}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              clientReportBody.innerHTML = '';
              data.clients.forEach(c => {
                const tr = document.createElement('tr');
                tr.setAttribute('data-row-cr', '');
                tr.innerHTML = `
                  <td data-cr-nome="${c.nome} ${c.cognome}">
                    <div class="fw-bold">${c.nome} ${c.cognome}</div>
                    <div class="small text-muted">${c.email}</div>
                  </td>
                  <td class="text-center" data-cr-orders="${c.total_orders}">${c.total_orders}</td>
                  <td class="text-end fw-bold" data-cr-spesa="${c.total_spent}">${c.total_spent.toFixed(2).replace('.', ',')}€</td>
                `;
                clientReportBody.appendChild(tr);
              });
              
              document.getElementById('client-report-result').classList.remove('d-none');
              document.getElementById('client-report-placeholder').classList.add('d-none');
            } else {
              alert(data.error || 'Nessun cliente trovato.');
            }
          })
          .catch(err => {
            console.error(err);
            alert('Errore durante la ricerca.');
          });
      };

      btnSearchClient.addEventListener('click', searchClient);
      inputSearchClient.addEventListener('keypress', (e) => { if(e.key === 'Enter') searchClient(); });

      // Sorting Client Report
      document.querySelectorAll('[data-sort-cr]').forEach(th => {
        let dir = 1;
        th.addEventListener('click', () => {
          const key = th.getAttribute('data-sort-cr');
          const tbody = document.getElementById('client-report-body');
          const rows = Array.from(tbody.querySelectorAll('[data-row-cr]'));
          
          rows.sort((a, b) => {
            let va, vb;
            if (key === 'nome') {
                va = a.querySelector('[data-cr-nome]').getAttribute('data-cr-nome').toLowerCase();
                vb = b.querySelector('[data-cr-nome]').getAttribute('data-cr-nome').toLowerCase();
            } else if (key === 'orders') {
                va = parseInt(a.querySelector('[data-cr-orders]').getAttribute('data-cr-orders'));
                vb = parseInt(b.querySelector('[data-cr-orders]').getAttribute('data-cr-orders'));
            } else {
                va = parseFloat(a.querySelector('[data-cr-spesa]').getAttribute('data-cr-spesa'));
                vb = parseFloat(b.querySelector('[data-cr-spesa]').getAttribute('data-cr-spesa'));
            }
            
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return va.localeCompare(vb) * dir;
          });
          
          dir *= -1;
          rows.forEach(r => tbody.appendChild(r));
        });
      });
    </script>
  </body>
</html>
