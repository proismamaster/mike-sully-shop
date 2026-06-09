<?php
session_start();
require_once 'php/db_connection.php';
require_once 'php/order_functions.php';

if (!isset($_SESSION['utente_id'])) {
    header('Location: loginPage.php');
    exit();
}

$utenteId = (int)$_SESSION['utente_id'];
$orders = mss_fetch_orders_for_user($conn, $utenteId);
?>
<!doctype html>
<html lang="it">
  <head>
    <title>I miei orders - MikeSullyShop</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="mss-page">
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 mb-5 fade-in">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h2 class="mss-section-title mb-0">
          <i class="bi bi-receipt me-2 text-gradient"></i> I miei orders
        </h2>
        <a href="homePage.php" class="btn mss-btn-outline">
          <i class="bi bi-bag-heart me-1"></i> Continua a comprare
        </a>
      </div>

      <?php if (isset($_GET['success']) && $_GET['success'] === 'annullato'): ?>
        <div class="alert alert-warning alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Ordine annullato con successo.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($orders)): ?>
        <div class="mss-auth-card p-5 text-center">
          <i class="bi bi-receipt mss-empty-icon"></i>
          <h4 class="mt-3 fw-bold mss-empty-title">Nessun ordine ancora</h4>
          <p class="mss-empty-text mb-4">Quando completi un acquisto, qui vedrai lo storico e lo stato aggiornato.</p>
          <a href="homePage.php" class="btn mss-btn-primary">
            <i class="bi bi-shop me-2"></i>Vai allo shop
          </a>
        </div>
      <?php else: ?>
        <div class="mss-auth-card">
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="orders-table">
              <thead style="background: linear-gradient(135deg, rgba(37,99,235,0.08) 0%, rgba(20,184,166,0.08) 100%);">
                <tr>
                  <th class="ps-4 py-3" style="cursor:pointer;" data-sort="id">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-hash mss-link-primary"></i>
                      <span>Ordine</span>
                      <i class="bi bi-arrow-down-up small opacity-50"></i>
                    </div>
                  </th>
                  <th class="py-3" style="cursor:pointer;" data-sort="data">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-calendar-event mss-link-primary"></i>
                      <span>Data</span>
                      <i class="bi bi-arrow-down-up small opacity-50"></i>
                    </div>
                  </th>
                  <th class="py-3 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <i class="bi bi-box-seam mss-link-primary"></i>
                      <span>Articoli</span>
                    </div>
                  </th>
                  <th class="py-3" style="cursor:pointer;" data-sort="totale">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-currency-euro mss-link-primary"></i>
                      <span>Totale</span>
                      <i class="bi bi-arrow-down-up small opacity-50"></i>
                    </div>
                  </th>
                  <th class="py-3" style="cursor:pointer;" data-sort="stato">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-info-circle mss-link-primary"></i>
                      <span>Stato</span>
                      <i class="bi bi-arrow-down-up small opacity-50"></i>
                    </div>
                  </th>
                  <th class="pe-4 py-3 text-end">Azioni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <tr data-row style="cursor:pointer;" onclick="toggleDetails(<?= (int)$order['id'] ?>)">
                    <td class="ps-4 fw-bold" data-id>#<?= (int)$order['id'] ?></td>
                    <td data-data><?= date('d/m/Y H:i', strtotime($order['data_ordine'])) ?></td>
                    <td class="text-center"><?= (int)$order['items_count'] ?></td>
                    <td class="fw-bold mss-link-primary" data-totale><?= number_format((float)$order['totale'], 2, ',', '.') ?>€</td>
                    <td data-stato="<?= htmlspecialchars($order['stato']) ?>">
                      <span class="badge <?= mss_order_status_class($order['stato']) ?>">
                        <?= htmlspecialchars(mss_display_order_status($order['stato'])) ?>
                      </span>
                    </td>
                    <td class="pe-4 text-end">
                      <button class="btn btn-sm mss-btn-outline px-3" onclick="event.stopPropagation(); toggleDetails(<?= (int)$order['id'] ?>)">
                        Dettagli
                      </button>
                    </td>
                  </tr>
                  <tr class="d-none" id="details-<?= (int)$order['id'] ?>" data-detail-row>
                    <td colspan="6" class="p-0">
                      <div class="px-4 py-3" style="background: rgba(37,99,235,0.02);">
                        <div class="row g-4">
                          <div class="col-md-7">
                            <h6 class="fw-bold small text-uppercase letter-spacing-1 mb-3">Prodotti ordinati</h6>
                            <?php $details = mss_fetch_order_details($conn, (int)$order['id']); ?>
                            <div class="list-group list-group-flush bg-transparent">
                              <?php foreach ($details as $detail): ?>
                                <div class="list-group-item bg-transparent px-0 border-0 d-flex justify-content-between align-items-center gap-3 py-1">
                                  <div class="small">
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($detail['nome'] ?? 'Prodotto') ?></span>
                                    <span class="ms-2 text-muted">x<?= (int)$detail['quantita'] ?></span>
                                  </div>
                                  <div class="small fw-bold"><?= number_format((float)$detail['prezzo_unitario'] * (int)$detail['quantita'], 2, ',', '.') ?>€</div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          </div>
                          <div class="col-md-5 border-start">
                            <?php $shipping = mss_fetch_order_shipping($conn, (int)$order['id']); ?>
                            <?php if ($shipping): ?>
                              <h6 class="fw-bold small text-uppercase letter-spacing-1 mb-2">Spedizione</h6>
                              <div class="small text-muted mb-3" style="line-height:1.4;">
                                <?= htmlspecialchars($shipping['nome']) ?> <?= htmlspecialchars($shipping['cognome'] ?? '') ?><br>
                                <?= htmlspecialchars($shipping['indirizzo'] ?? '') ?><br>
                                <?= htmlspecialchars($shipping['cap'] ?? '') ?> <?= htmlspecialchars($shipping['citta'] ?? '') ?> (<?= htmlspecialchars($shipping['provincia'] ?? '') ?>)
                              </div>
                            <?php endif; ?>
                            <?php if (mss_can_cancel_order($order['stato'])): ?>
                              <form action="php/order_handler.php" method="POST" onsubmit="return confirm('Annullare l\'ordine?')">
                                <input type="hidden" name="action" value="annulla">
                                <input type="hidden" name="ordine_id" value="<?= (int)$order['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100 py-1">Annulla Ordine</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/mss-cart.js"></script>
    <script>
      function toggleDetails(id) {
        const row = document.getElementById('details-' + id);
        row.classList.toggle('d-none');
      }

      // Navbar scroll effect
      const navbar = document.querySelector('.mss-navbar');
      window.addEventListener('scroll', () => {
        if (window.scrollY > 10) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
      });

      // Animations
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
      }, { threshold: 0.1 });
      document.querySelectorAll('.fade-up, .fade-in').forEach(el => { el.classList.add('anim-init'); observer.observe(el); });

      // Sorting
      const getVal = (tr, key) => {
        if (key === 'id') return parseInt(tr.querySelector('[data-id]').textContent.replace('#','')) || 0;
        if (key === 'data') {
            const txt = tr.querySelector('[data-data]').textContent;
            const parts = txt.split(' ');
            const d = parts[0].split('/');
            const t = parts[1].split(':');
            return new Date(d[2], d[1]-1, d[0], t[0], t[1]).getTime();
        }
        if (key === 'totale') return parseFloat(tr.querySelector('[data-totale]').textContent.replace('€','').replace('.','').replace(',','.')) || 0;
        if (key === 'stato') return tr.querySelector('[data-stato]').getAttribute('data-stato');
        return '';
      };

      document.querySelectorAll('[data-sort]').forEach(th => {
        let dir = 1;
        th.addEventListener('click', () => {
          const key = th.getAttribute('data-sort');
          const tbody = th.closest('table').querySelector('tbody');
          const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
          
          rows.sort((a, b) => {
            const va = getVal(a, key), vb = getVal(b, key);
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb)) * dir;
          });
          
          dir *= -1;
          rows.forEach(r => {
            tbody.appendChild(r);
            const detail = document.getElementById('details-' + r.querySelector('[data-id]').textContent.replace('#',''));
            if (detail) tbody.appendChild(detail);
          });
        });
      });
    </script>
  </body>
</html>
