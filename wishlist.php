<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'php/db_connection.php';

$utenteId = $_SESSION['utente_id'] ?? null;

// Se loggato, carica wishlist dal DB e risincronizza sessione
if ($utenteId) {
    $stmt = $conn->prepare(
        "SELECT p.id, p.nome, p.descrizione, p.prezzo, p.sconto_percentuale, p.giacenza, p.immagine_path, c.nome AS categoria
         FROM wishlist w
         JOIN products p ON w.prodotto_id = p.id
         LEFT JOIN categories c ON p.categoria_id = c.id
         WHERE w.utente_id = ? AND p.deleted_at IS NULL
         ORDER BY w.data_aggiunta DESC"
    );
    $stmt->bind_param("i", $utenteId);
    $stmt->execute();
    $res = $stmt->get_result();
    $products = [];
    while ($row = $res->fetch_assoc()) $products[] = $row;
    $stmt->close();
    // Aggiorno la sessione con i dati veri del DB
    $listaId = [];
    foreach ($products as $p) {
        $listaId[] = $p['id'];
    }
    $_SESSION['wishlist'] = $listaId;
} else {
    // Se l'utente non è loggato, la sua wishlist è solo nella sessione
    $idsSessione = isset($_SESSION['wishlist']) ? $_SESSION['wishlist'] : [];
    
    // Mi assicuro che siano tutti numeri interi per evitare SQL Injection
    $ids = [];
    foreach ($idsSessione as $id) {
        $ids[] = (int)$id;
    }
    
    $products = [];
    if (!empty($ids)) {
        // Visto che ho una lista di ID, devo creare i punti interrogativi per la query
        // Es. se ho 3 ID, mi serve "?, ?, ?"
        $puntiInterrogativi = [];
        for ($i = 0; $i < count($ids); $i++) {
            $puntiInterrogativi[] = "?";
        }
        $placeholders = implode(',', $puntiInterrogativi);
        
        // Creo una stringa di "i" (integer) lunga quanto gli ID, per il bind_param
        $types = "";
        for ($i = 0; $i < count($ids); $i++) {
            $types .= "i";
        }
        
        $stmt = $conn->prepare(
            "SELECT p.id, p.nome, p.descrizione, p.prezzo, p.sconto_percentuale, p.giacenza, p.immagine_path, c.nome AS categoria
             FROM products p
             LEFT JOIN categories c ON p.categoria_id = c.id
             WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL
             ORDER BY p.id ASC"
        );
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Wishlist - MikeSullyShop</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>

  <body>
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 mb-5">

      <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="mss-section-title mb-0">
          <i class="bi bi-heart-fill me-2 text-gradient"></i> La tua Wishlist
        </h2>
        <?php if (!$utenteId): ?>
          <div class="py-2 px-3 small mb-0 mss-user-chip">
            <i class="bi bi-info-circle me-1"></i>
            <a href="loginPage.php" class="mss-link-primary fw-semibold">Accedi</a> per salvare la wishlist in modo permanente.
          </div>
        <?php endif; ?>
      </div>

      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'rimosso'): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 small mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Prodotto rimosso dalla wishlist.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($products)): ?>
        <div class="text-center py-5" data-wishlist-empty>
          <i class="bi bi-heart mss-empty-icon"></i>
          <h4 class="mt-3 fw-bold mss-empty-title">La tua watchlist è vuota</h4>
          <p class="mss-empty-text">Torna allo shop e aggiungi i tuoi preferiti.</p>
          <a href="homePage.php" class="btn mss-btn-primary mt-2">
            <i class="bi bi-shop me-2"></i>Vai allo shop
          </a>
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4">
          <?php foreach ($products as $p): ?>
          <div class="col">
            <div class="mss-card h-100" data-wishlist-item>
              <a href="productDetail.php?id=<?= $p['id'] ?>">
                <div class="card-img-wrapper">
                  <?php 
                    $imgs = mss_get_product_images($p['immagine_path']); 
                    $imgJson = count($imgs) > 1 ? htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8') : '';
                  ?>
                  <img src="<?= htmlspecialchars($imgs[0] ?? '') ?>" alt="<?= htmlspecialchars($p['nome']) ?>" <?= $imgJson ? 'class="mss-hover-gallery" data-hover-images="'.$imgJson.'"' : '' ?>>
                </div>
              </a>
              <div class="card-body d-flex flex-column">
                <?php if ($p['categoria']): ?>
                  <div class="badge-cat"><?= htmlspecialchars($p['categoria']) ?></div>
                <?php endif; ?>
                <h5 class="card-title"><?= htmlspecialchars($p['nome']) ?></h5>
                <p class="card-text mb-auto"><?= htmlspecialchars(mb_strimwidth($p['descrizione'] ?? '', 0, 60, '…')) ?></p>
                <div class="mt-auto pt-3">
                  <?php if (!empty($p['sconto_percentuale']) && $p['sconto_percentuale'] > 0): ?>
                    <p class="price mb-2">
                      <span class="text-decoration-line-through text-muted" style="font-size:0.85em; -webkit-text-fill-color: initial;"><?= number_format($p['prezzo'], 2, ',', '.') ?>€</span>
                      <span class="text-danger ms-1" style="-webkit-text-fill-color: initial;"><?= number_format($p['prezzo'] * (1 - $p['sconto_percentuale']/100), 2, ',', '.') ?>€</span>
                      <span class="badge bg-danger ms-1" style="-webkit-text-fill-color: initial; color: white;">-<?= (int)$p['sconto_percentuale'] ?>%</span>
                    </p>
                  <?php else: ?>
                    <p class="price mb-2"><?= number_format($p['prezzo'], 2, ',', '.') ?>€</p>
                  <?php endif; ?>

                  <div class="d-flex gap-2">
                    <?php if ($p['giacenza'] <= 0): ?>
                      <button class="btn btn-secondary flex-grow-1" disabled>
                        <i class="bi bi-x-circle me-1"></i> Esaurito
                      </button>
                    <?php else: ?>
                      <form action="php/cart_handler.php" method="POST" class="flex-grow-1 js-cart-form" data-cart-form="add">
                        <input type="hidden" name="azione" value="aggiungi">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="qta" value="1">
                        <button type="submit" class="btn btn-cart w-100">
                          <i class="bi bi-cart-plus me-1"></i> Carrello
                        </button>
                      </form>
                    <?php endif; ?>
                    <form action="php/wishlist_handler.php" method="POST" data-wishlist-form="toggle">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <input type="hidden" name="azione" value="rimuovi">
                      <button type="submit" class="btn btn-outline-danger" title="Rimuovi dalla watchlist" data-wishlist-button aria-pressed="true">
                        <i class="bi bi-heart-fill"></i>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/mss-cart.js"></script>
    <script src="assets/js/mss-wishlist.js"></script>
    <script>
      // Hover Gallery Logic
      document.querySelectorAll('.mss-hover-gallery').forEach(img => {
        const images = JSON.parse(img.getAttribute('data-hover-images'));
        let interval;
        let currentIndex = 0;
        
        img.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        
        img.addEventListener('mouseenter', () => {
          if (images.length > 1) {
            interval = setInterval(() => {
              currentIndex = (currentIndex + 1) % images.length;
              img.style.opacity = '0.5';
              img.style.transform = 'scale(0.98)';
              setTimeout(() => {
                img.src = images[currentIndex];
                img.style.opacity = '1';
                img.style.transform = 'scale(1)';
              }, 200);
            }, 1000);
          }
        });
        
        img.addEventListener('mouseleave', () => {
          clearInterval(interval);
          currentIndex = 0;
          img.style.opacity = '0.5';
          setTimeout(() => {
            img.src = images[0];
            img.style.opacity = '1';
          }, 200);
        });
      });
    </script>
  </body>
</html>
