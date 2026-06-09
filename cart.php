<?php
session_start();
require_once 'php/db_connection.php';

// Controllo scadenza carrello (30 min)
define('CART_TTL', 1800);
if (!empty($_SESSION['carrello']) && isset($_SESSION['carrello_ts'])) {
    // Se sono passati più di 30 minuti, svuota tutto
    if (time() - $_SESSION['carrello_ts'] > CART_TTL) {
        
        // RIPRISTINO SCORTE: il timer è scaduto, ridiamo i prodotti al magazzino
        foreach ($_SESSION['carrello'] as $idProdotto => $dati) {
            $qtaDaRestituire = (int)$dati['qta'];
            $idProdotto = (int)$idProdotto;
            $conn->query("UPDATE products SET giacenza = giacenza + $qtaDaRestituire WHERE id = $idProdotto");
        }

        $_SESSION['carrello'] = [];
        $_SESSION['carrello_ts'] = null;
    }
}

$carrello = $_SESSION['carrello'] ?? [];
$totale = 0;
foreach ($carrello as $item) {
    $totale += $item['prezzo'] * $item['qta'];
}

$stockMap = [];
if (!empty($carrello)) {
    // Prendo tutti gli ID dei prodotti attualmente nel carrello
    $ids = [];
    foreach ($carrello as $idProdotto => $dati) {
        $ids[] = (int)$idProdotto;
    }
    
    if (!empty($ids)) {
        // Controllo le giacenze aggiornate nel DB per essere sicuro che ci siano ancora
        $idSeparatiDaVirgola = implode(',', $ids);
        $sql = 'SELECT id, giacenza FROM products WHERE id IN (' . $idSeparatiDaVirgola . ')';
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $stockMap[(int)$row['id']] = (int)$row['giacenza'];
            }
        }
    }
}

$minutiRimasti = null;
if (!empty($carrello) && isset($_SESSION['carrello_ts'])) {
    $secondiPassati = time() - $_SESSION['carrello_ts'];
    $minutiRimasti = max(0, (int)ceil((CART_TTL - $secondiPassati) / 60));
}
?>
<!doctype html>
<html lang="it">
  <head>
    <title>MikeSullyShop - Carrello</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>

  <body>
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 mb-5 fade-in">

      <?php
        $msg = $_GET['msg'] ?? '';
        if ($msg === 'rimosso'): ?>
        <div class="alert alert-warning alert-dismissible fade show py-2 small mss-alert" role="alert">
          <i class="bi bi-trash-fill me-2"></i> Prodotto rimosso dal carrello.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif ($msg === 'svuotato'): ?>
        <div class="alert alert-secondary alert-dismissible fade show py-2 small mss-alert" role="alert">
          <i class="bi bi-bag-x me-2"></i> Carrello svuotato.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!empty($carrello) && isset($_SESSION['carrello_ts'])): ?>
        <div class="alert alert-info py-2 small d-flex align-items-center gap-2 mb-3 mss-alert" role="alert">
          <i class="bi bi-clock-history fs-5"></i>
          <span>Prodotti riservati per: <strong id="cartTimer">--:--</strong>. Completa l'ordine in tempo!</span>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mss-section-title mb-0">
          <i class="bi bi-bag-check me-2 text-gradient"></i> Il tuo Carrello
        </h2>
        <?php if (!empty($carrello)): ?>
          <form action="php/cart_handler.php" method="POST" class="m-0">
            <input type="hidden" name="azione" value="svuota">
            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Svuotare il carrello?')">
              <i class="bi bi-trash me-1"></i> Svuota Carrello
            </button>
          </form>
        <?php endif; ?>
      </div>

      <?php if (empty($carrello)): ?>
        <div class="text-center py-5">
          <i class="bi bi-cart-x fs-1 text-muted"></i>
          <p class="lead mt-3">Il carrello è vuoto.</p>
          <a href="homePage.php" class="btn mss-btn-outline mt-2">Vai allo Shop</a>
        </div>
      <?php else: ?>
        <div class="row">
          <div class="col-12">
            <?php foreach ($carrello as $id => $item): ?>
            <div class="mss-cart-item d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 fade-up"
                 data-cart-widget data-cart-view="line" data-product-id="<?= (int)$id ?>" data-max-qty="<?= (int)($stockMap[$id] ?? $item['qta']) ?>" data-prezzo="<?= $item['prezzo'] ?>">
              <div class="d-flex align-items-center col-12 col-md-5">
                <img
                  src="<?= htmlspecialchars($item['img']) ?>"
                  class="rounded mss-cart-thumb"
                  alt="<?= htmlspecialchars($item['nome']) ?>"
                />
                <div class="ms-3">
                  <h5 class="mb-0 fw-bold mss-text-heading"><?= htmlspecialchars($item['nome']) ?></h5>
                  <p class="small mb-0 mss-text-muted"><?= number_format($item['prezzo'], 2, ',', '.') ?>€ cad.</p>
                </div>
              </div>

              <div class="d-flex align-items-center gap-2 ms-auto flex-wrap justify-content-end">
                <div class="d-flex align-items-center rounded overflow-hidden mss-cart-qty-shell">
                  <button type="button" class="mss-qty-btn" data-cart-action="decrease" data-product-id="<?= (int)$id ?>">&minus;</button>
                  <input type="number" class="form-control form-control-sm text-center border-0 qtyInput mss-cart-qty-input"
                    value="<?= (int)$item['qta'] ?>" min="1" max="<?= (int)($stockMap[$id] ?? $item['qta']) ?>"
                    data-id="<?= (int)$id ?>" data-cart-qty-input data-product-id="<?= (int)$id ?>">
                  <button type="button" class="mss-qty-btn" data-cart-action="increase" data-product-id="<?= (int)$id ?>">+</button>
                </div>
                <span class="fw-bold fs-5 subtotale text-gradient mss-cart-subtotal">
                  <?= number_format($item['prezzo'] * $item['qta'], 2, ',', '.') ?>€
                </span>
                <form action="php/cart_handler.php" method="POST" class="m-0">
                  <input type="hidden" name="azione" value="rimuovi">
                  <input type="hidden" name="id" value="<?= (int)$id ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm border-0" title="Rimuovi">
                    <i class="bi bi-trash3 fs-5"></i>
                  </button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>

            <hr class="my-4 opacity-50" />

            <div class="row mb-5">
              <div class="col-12 text-end">
                <h3 class="mb-3 mss-text-heading">
                  Totale: <span class="fw-bold mss-price-tag" id="totaleCarrello"><?= number_format($totale, 2, ',', '.') ?>€</span>
                </h3>
                <a href="shippingPage.php" class="btn btn-cart btn-lg px-5 fw-bold">
                  Acquista Ora <i class="bi bi-credit-card ms-2"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/mss-cart.js"></script>
    <script>
      const navbar = document.querySelector('.mss-navbar');
      window.addEventListener('scroll', () => {
        if (window.scrollY > 10) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
      });
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
      }, { threshold: 0.1 });
      document.querySelectorAll('.fade-up, .fade-in').forEach(el => { el.classList.add('anim-init'); observer.observe(el); });

      // Timer countdown
      const cartTs = <?= isset($_SESSION['carrello_ts']) ? (int)$_SESSION['carrello_ts'] : 0 ?>;
      const cartTtl = <?= CART_TTL ?>;
      const timerEl = document.getElementById('cartTimer');

      if (timerEl && cartTs > 0) {
        function aggiornaTimer() {
          const ora = Math.floor(Date.now() / 1000);
          const rimasti = cartTtl - (ora - cartTs);
          if (rimasti <= 0) {
            timerEl.textContent = '00:00';
            timerEl.closest('.alert').innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> Prenotazione scaduta. Ricarica la pagina.';
            return;
          }
          const mm = String(Math.floor(rimasti / 60)).padStart(2, '0');
          const ss = String(rimasti % 60).padStart(2, '0');
          timerEl.textContent = mm + ':' + ss;
          if (rimasti <= 120) timerEl.classList.add('text-danger');
        }
        aggiornaTimer();
        setInterval(aggiornaTimer, 1000);
      }

      window.mssCart && window.mssCart.recalculateVisibleCart && window.mssCart.recalculateVisibleCart();
    </script>
  </body>
</html>
