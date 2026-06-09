<?php
session_start();
require_once 'php/db_connection.php';

// Prendo l'id dall'url (GET), se non c'è imposto 0
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

if ($id <= 0) {
    // Se l'id è sbagliato lo rimando alla home
    header('Location: homePage.php');
    exit();
}

$stmt = $conn->prepare(
    "SELECT p.id, p.nome, p.descrizione, p.prezzo, p.sconto_percentuale, p.giacenza, p.immagine_path, c.nome AS categoria
     FROM products p
     LEFT JOIN categories c ON p.categoria_id = c.id
     WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$prodotto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prodotto) {
    header('Location: homePage.php?cart_error=notfound');
    exit();
}

$esaurito = $prodotto['giacenza'] <= 0;
$nelCarrello = isset($_SESSION['carrello'][$id]);
$nellWishlist = in_array($id, $_SESSION['wishlist'] ?? []);

$cartMsg = $_GET['cart_msg'] ?? '';
$cartError = $_GET['cart_error'] ?? '';
?>
<!doctype html>
<html lang="it">
  <head>
    <title><?= htmlspecialchars($prodotto['nome']) ?> - MikeSullyShop</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="mss-page">
    <?php include 'php/header.php'; ?>

    <!-- Toast "Link copiato" -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1100">
      <div id="toastCondividi" class="toast align-items-center" role="alert" data-bs-delay="2500">
        <div class="d-flex">
          <div class="toast-body"><i class="bi bi-check2-circle me-2"></i> Link copiato negli appunti!</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>

    <!-- Modal popup "già nel carrello" -->
    <div class="modal fade" id="modalDuplicato" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0 mss-modal-surface">
            <h5 class="modal-title fw-bold mss-modal-title"><i class="bi bi-cart-check me-2 mss-modal-accent"></i>Già nel carrello</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body py-3 mss-modal-surface mss-modal-body-text">
            <p class="mb-3"><strong class="mss-text-heading"><?= htmlspecialchars($prodotto['nome']) ?></strong> è già nel carrello.</p>
            <div class="d-flex align-items-center gap-3 justify-content-center">
              <span class="small mss-modal-muted">Quantità attuale:</span>
              <span class="fw-bold fs-5 mss-modal-accent" id="modalQtyAttuale"><?= (int)($_SESSION['carrello'][$id]['qta'] ?? 0) ?></span>
            </div>
            <hr class="my-3">
            <p class="small mb-0 mss-modal-muted">Vuoi aggiungere ancora 1 unità?</p>
          </div>
          <div class="modal-footer border-0 pt-0 gap-2 mss-modal-surface">
            <button type="button" class="btn mss-btn-outline" data-bs-dismiss="modal">No, lascia stare</button>
            <button type="button" class="btn mss-btn-primary" id="btnConfermaAggiungi">
              <i class="bi bi-cart-plus me-1"></i>Sì, aggiungi +1
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="container mt-4 mb-5 fade-in">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="homePage.php">Home</a></li>
          <?php if ($prodotto['categoria']): ?>
            <li class="breadcrumb-item"><a href="homePage.php?cat=<?= urlencode($prodotto['categoria']) ?>"><?= htmlspecialchars($prodotto['categoria']) ?></a></li>
          <?php endif; ?>
          <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($prodotto['nome']) ?></li>
        </ol>
      </nav>

      <?php if ($cartMsg === 'aggiunto'): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 small mb-3" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Prodotto aggiunto al carrello!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif ($cartMsg === 'aggiunto_ancora'): ?>
        <div class="alert alert-info alert-dismissible fade show py-2 small mb-3" role="alert">
          <i class="bi bi-info-circle-fill me-2"></i> Quantità aggiornata nel carrello.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif ($cartError === 'giacenza'): ?>
        <div class="alert alert-warning alert-dismissible fade show py-2 small mb-3" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> Hai già raggiunto la quantità massima disponibile in magazzino.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="mss-detail-card">
        <div class="row g-0">
          <div class="col-md-6 d-flex align-items-center justify-content-center p-4 p-lg-5">
            <div class="mss-detail-img w-100">
              <?php 
                $imgs = mss_get_product_images($prodotto['immagine_path']); 
                if (count($imgs) > 1):
              ?>
                <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                  <div class="carousel-indicators">
                    <?php foreach ($imgs as $idx => $img): ?>
                      <button type="button" data-bs-target="#productCarousel" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                  </div>
                  <div class="carousel-inner text-center">
                    <?php foreach ($imgs as $idx => $img): ?>
                      <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                        <img
                          src="<?= htmlspecialchars($img) ?>"
                          class="img-fluid mss-image-detail"
                          alt="<?= htmlspecialchars($prodotto['nome']) ?> - foto <?= $idx + 1 ?>"
                        >
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Precedente</span>
                  </button>
                  <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Successivo</span>
                  </button>
                </div>
              <?php else: ?>
                <img
                  src="<?= htmlspecialchars($imgs[0] ?? '') ?>"
                  class="img-fluid mss-image-detail"
                  alt="<?= htmlspecialchars($prodotto['nome']) ?>"
                >
              <?php endif; ?>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card-body p-4 p-lg-5">
              <?php if ($prodotto['categoria']): ?>
                <div class="badge-cat d-inline-block mb-2"><?= htmlspecialchars($prodotto['categoria']) ?></div>
              <?php endif; ?>
              <?php if ($esaurito): ?>
                <div class="badge bg-danger mb-2 ms-1">OUT OF STOCK</div>
              <?php endif; ?>

              <h1 class="display-6 fw-bold mb-3 mss-text-heading"><?= htmlspecialchars($prodotto['nome']) ?></h1>

              <p class="mb-4 mss-text-muted" style="font-size:1.05rem; line-height:1.8;"><?= nl2br(htmlspecialchars($prodotto['descrizione'])) ?></p>

              <div class="mb-4">
                <?php if (!empty($prodotto['sconto_percentuale']) && $prodotto['sconto_percentuale'] > 0): ?>
                  <span class="text-decoration-line-through text-muted" style="font-size:1.2rem;"><?= number_format($prodotto['prezzo'], 2, ',', '.') ?>€</span>
                  <span class="mss-price-tag text-danger ms-2"><?= number_format($prodotto['prezzo'] * (1 - $prodotto['sconto_percentuale']/100), 2, ',', '.') ?>€</span>
                  <span class="badge bg-danger ms-2 align-middle fs-6" style="vertical-align: text-bottom !important;">-<?= (int)$prodotto['sconto_percentuale'] ?>%</span>
                <?php else: ?>
                  <span class="mss-price-tag"><?= number_format($prodotto['prezzo'], 2, ',', '.') ?>€</span>
                <?php endif; ?>
              </div>

              <div class="mb-4 p-3 mss-stock-box">
                <?php if ($esaurito): ?>
                  <p class="mb-1 fw-bold text-danger"><i class="bi bi-x-circle me-2"></i> Prodotto esaurito</p>
                <?php else: ?>
                  <p class="mb-1 mss-text-heading"><i class="bi bi-box-seam me-2 mss-link-primary"></i> Disponibilità:
                    <span class="fw-bold mss-link-primary"><?= (int)$prodotto['giacenza'] ?> pezzi in magazzino</span>
                  </p>
                <?php endif; ?>
                <p class="mb-0 small mss-text-muted"><i class="bi bi-truck me-2"></i> Consegna stimata: 2-3 giorni lavorativi</p>
              </div>

              <?php if (!$esaurito): ?>
              <div class="mss-cart-widget mt-2" data-cart-widget data-product-id="<?= (int)$prodotto['id'] ?>" data-max-qty="<?= (int)$prodotto['giacenza'] ?>">
                <form id="formAggiungi" action="php/cart_handler.php" method="POST" class="js-cart-form" data-cart-form="add">
                  <input type="hidden" name="azione" value="aggiungi">
                  <input type="hidden" name="id" value="<?= $prodotto['id'] ?>">
                  <div class="row g-3 mb-3">
                    <div class="col-4 col-md-3">
                      <label for="qty" class="form-label small fw-bold mss-text-muted">Quantità</label>
                      <input type="number" class="form-control mss-input text-center" id="qty" name="qta" value="1" min="1" max="<?= (int)$prodotto['giacenza'] ?>">
                    </div>
                    <div class="col-8 col-md-9 d-flex align-items-end">
                      <button type="submit" id="btnAggiungi" class="btn btn-cart btn-lg w-100 fw-bold">
                        <i class="bi bi-cart-plus me-2"></i>Aggiungi al Carrello
                      </button>
                    </div>
                  </div>
                </form>
              </div>
              <?php endif; ?>

              <hr class="my-3">

              <div class="d-flex gap-2 flex-wrap">
                <form action="php/wishlist_handler.php" method="POST" class="m-0" data-wishlist-form="toggle">
                  <input type="hidden" name="id" value="<?= $prodotto['id'] ?>">
                  <input type="hidden" name="azione" value="<?= $nellWishlist ? 'rimuovi' : 'aggiungi' ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm" data-wishlist-button aria-pressed="<?= $nellWishlist ? 'true' : 'false' ?>">
                    <i class="bi bi-heart<?= $nellWishlist ? '-fill' : '' ?> me-1"></i>
                    <span data-wishlist-label><?= $nellWishlist ? 'In Wishlist' : 'Aggiungi alla Wishlist' ?></span>
                  </button>
                </form>
                <button type="button" id="btnCondividi" class="btn mss-btn-outline btn-sm">
                  <i class="bi bi-share me-1"></i> Condividi
                </button>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/mss-cart.js"></script>
    <script src="assets/js/mss-wishlist.js"></script>
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

      document.getElementById('btnCondividi').addEventListener('click', function () {
        navigator.clipboard.writeText(window.location.href).then(function () {
          bootstrap.Toast.getOrCreateInstance(document.getElementById('toastCondividi')).show();
        });
      });

    </script>
  </body>
</html>
