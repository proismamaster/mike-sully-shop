<?php
session_start();
require_once 'php/db_connection.php';

$collectionSettings = mss_get_home_collection($conn);
$featuredProducts = mss_fetch_products_by_ids($conn, mss_parse_product_ids($collectionSettings['product_ids'] ?? ''));
?>
<!doctype html>
<html lang="it">
  <head>
    <title>MikeSullyShop - Home</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>

  <body>
    <?php include 'php/header.php'; ?>

    <div class="container text-center fade-in">
      <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
          <div class="mss-hero">
            <img
              src="assets/img/insegnaNuova.png"
              class="img-fluid mb-4 hero-img"
              alt="Insegna MikeSullyShop"
            />
            <h1>Monstropolis Store</h1>
            <p>Trova tutto quello che serve per le tue urla... o risate!<br>Qualità garantita da Mike Wazowski in persona.</p>
            <a href="#products" class="btn mss-btn-primary mt-3 px-4"><i class="bi bi-shop me-2"></i>Scopri i products</a>
          </div>
        </div>
      </div>
    </div>

    <div id="products" class="container mt-4 mb-5">
      <?php
        if (isset($_GET['benvenuto']) && isset($_SESSION['user'])):
      ?>
        <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
          <i class="bi bi-stars me-2"></i> Benvenuto <?= htmlspecialchars($_SESSION['user']) ?>! Account creato con successo.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php
        $cartMsg   = $_GET['cart_msg']   ?? '';
        $cartError = $_GET['cart_error'] ?? '';
        $msgMap = [
          'aggiunto'       => ['success', 'Prodotto aggiunto al carrello!'],
          'aggiunto_ancora'=> ['info',    'Prodotto già presente: quantità aumentata di 1.'],
        ];
        $errMap = [
          'notfound'  => ['danger', 'Prodotto non trovato.'],
          'esaurito'  => ['warning','Spiacente, questo prodotto è esaurito.'],
          'giacenza'  => ['warning','Hai già raggiunto la quantità massima disponibile.'],
        ];
        if ($cartMsg && isset($msgMap[$cartMsg])):
          [$type, $text] = $msgMap[$cartMsg];
      ?>
        <div class="alert alert-<?= $type ?> alert-dismissible fade show py-2 small" role="alert">
          <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'info-circle' ?>-fill me-2"></i><?= $text ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($cartError && isset($errMap[$cartError])):
          [$type, $text] = $errMap[$cartError];
      ?>
        <div class="alert alert-<?= $type ?> alert-dismissible fade show py-2 small" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $text ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php
        $search = trim($_GET['search'] ?? '');
        $cat = trim($_GET['cat'] ?? '');

        // Prodotti dal DB (supporto filtro categoria)
        // Prepara un array vuoto per i parametri della query e un altro per i pezzi di WHERE
        $params = [];
        $types  = '';
        $where  = ['p.deleted_at IS NULL']; // Non mostrare i prodotti nel cestino!
        $joinPivot = false;
        if ($search !== '') {
            $where[]  = 'p.nome LIKE ?';
            $params[] = '%' . $search . '%';
            $types   .= 's';
        }
        if ($cat !== '') {
            $joinPivot = true;
            $where[]  = '(c.nome = ? OR c2.nome = ?)';
            $params[] = $cat;
            $params[] = $cat;
            $types   .= 'ss';
        }
        $sql = "SELECT DISTINCT p.id, p.nome, p.descrizione, p.prezzo, p.sconto_percentuale, p.giacenza, p.immagine_path, c.nome AS cat
                FROM products p
                LEFT JOIN categories c ON p.categoria_id = c.id";
        if ($joinPivot) {
            $sql .= "\n                LEFT JOIN product_categories pc ON pc.prodotto_id = p.id
                LEFT JOIN categories c2 ON c2.id = pc.categoria_id";
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.id ASC';
        $stmt = $conn->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $products = [];
        while ($row = $res->fetch_assoc()) $products[] = $row;
        $stmt->close();
        
        // Controllo se fa parte della "Nuova Collezione" per fargli spuntare il badge
        $nuovaColId = 0;
        $r = $conn->query("SELECT id FROM categories WHERE nome = 'Nuova Collezione' LIMIT 1");
        if ($r && ($row = $r->fetch_assoc())) { $nuovaColId = (int)$row['id']; }
        $inNuova = [];
        if ($nuovaColId > 0 && !empty($products)) {
          // prendo tutti gli id dei prodotti scorrendoli uno ad uno
          $ids = [];
          foreach ($products as $p) {
              $ids[] = (int)$p['id'];
          }
          if ($ids) {
            // Cerco quali tra questi prodotti hanno la categoria "Nuova Collezione" assegnata
            $sqlIn = "SELECT prodotto_id FROM product_categories WHERE categoria_id = $nuovaColId AND prodotto_id IN (" . implode(',', $ids) . ")";
            $resIn = $conn->query($sqlIn);
            if ($resIn) { 
                while ($rr = $resIn->fetch_assoc()) { 
                    $inNuova[(int)$rr['prodotto_id']] = true; 
                } 
            }
          }
        }
      ?>

      <div class="mss-filters-bar mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-end gap-3">
          <div class="flex-grow-1">
            <form role="search" action="homePage.php" method="GET" class="d-flex flex-column">
              <?php if ($cat): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
              <label class="form-label fw-semibold mb-1"><i class="bi bi-search me-1"></i>Cerca products</label>
              <div class="input-group">
                <input class="form-control mss-input" type="search" name="search" placeholder="Cerca per nome..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn mss-btn-primary px-4" type="submit"><i class="bi bi-search"></i></button>
              </div>
            </form>
          </div>
          <div class="mss-form-side">
            <form action="homePage.php" method="GET">
              <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
              <label class="form-label fw-semibold mb-1"><i class="bi bi-tag me-1"></i>Categoria</label>
              <select name="cat" class="form-select mss-select" onchange="this.form.submit()">
                <option value="" <?= $cat === '' ? 'selected' : '' ?>>Tutte le categories</option>
                <?php
                  $catRes = $conn->query("SELECT nome FROM categories ORDER BY nome ASC");
                  while ($cRow = $catRes->fetch_assoc()):
                    if ($cRow['nome'] === 'Nuova Collezione') continue;
                ?>
                  <option value="<?= htmlspecialchars($cRow['nome']) ?>" <?= $cat === $cRow['nome'] ? 'selected' : '' ?>><?= htmlspecialchars($cRow['nome']) ?></option>
                <?php endwhile; ?>
              </select>
            </form>
          </div>
          <?php if ($cat || $search): ?>
          <div>
            <label class="form-label fw-semibold mb-1 opacity-0 d-none d-md-block">_</label>
            <a href="homePage.php" class="btn mss-btn-outline d-block"><i class="bi bi-x-circle me-1"></i>Reset</a>
          </div>
          <?php endif; ?>
        </div>
        <div class="mt-3">
          <h3 class="mss-section-title mb-0">
            <?= $cat ? "Categoria: <em>".htmlspecialchars($cat)."</em>" : ($search ? "Risultati per: <em>".htmlspecialchars($search)."</em>" : "Articoli in Vetrina") ?>
          </h3>
        </div>
      </div>

      <?php
        $wishlistIds = $_SESSION['wishlist'] ?? [];
        $carrelloIndex = $_SESSION['carrello'] ?? [];
      ?>
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 justify-content-center" id="products-grid">
        <?php if (count($products) > 0): ?>
          <?php foreach ($products as $i => $p): ?>
          <div class="col fade-up <?= $i >= 12 ? 'd-none' : '' ?>" data-product-card style="transition-delay: <?= $i * 80 ?>ms">
            <div class="mss-card h-100">

              <!-- Pulsante wishlist in alto a destra -->
              <form action="php/wishlist_handler.php" method="POST" class="mss-card-wishlist" data-wishlist-form="toggle">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="azione" value="<?= in_array($p['id'], $wishlistIds) ? 'rimuovi' : 'aggiungi' ?>">
                <button type="submit" class="mss-wishlist-btn <?= in_array($p['id'], $wishlistIds) ? 'active' : '' ?>" data-wishlist-button title="<?= in_array($p['id'], $wishlistIds) ? 'Rimuovi dalla wishlist' : 'Aggiungi alla wishlist' ?>" aria-pressed="<?= in_array($p['id'], $wishlistIds) ? 'true' : 'false' ?>">
                  <i class="bi bi-heart<?= in_array($p['id'], $wishlistIds) ? '-fill' : '' ?>"></i>
                </button>
              </form>

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
                <?php if (!empty($inNuova[(int)$p['id']]??false)): ?>
                  <div class="badge-cat">Nuova Collezione</div>
                <?php endif; ?>
                <?php if ($p['cat']): ?><div class="badge-cat"><?= htmlspecialchars($p['cat']) ?></div><?php endif; ?>
                <h5 class="card-title"><?= htmlspecialchars($p['nome']) ?></h5>
                <p class="card-text mb-auto"><?= htmlspecialchars(mb_strimwidth($p['descrizione'] ?? '', 0, 72, '…')) ?></p>
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
                  <div class="mss-cart-widget mt-2" data-cart-widget data-product-id="<?= (int)$p['id'] ?>" data-max-qty="<?= (int)$p['giacenza'] ?>">
                    <?php if ($p['giacenza'] <= 0): ?>
                      <button class="btn btn-secondary w-100" disabled><i class="bi bi-x-circle me-1"></i> Esaurito</button>
                    <?php else: ?>
                      <form action="php/cart_handler.php" method="POST" class="js-cart-form" data-cart-form="add">
                        <input type="hidden" name="azione" value="aggiungi">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="qta" value="1">
                        <button type="submit" class="btn btn-cart w-100">
                          <i class="bi bi-cart-plus me-1"></i> Aggiungi al carrello
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12 text-center py-5">
            <i class="bi bi-search fs-1 text-muted"></i>
            <p class="lead mt-3">Nessun prodotto trovato. Mike sta ancora cercando nel magazzino!</p>
            <a href="homePage.php" class="btn mss-btn-outline">Mostra tutti</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Trigger for infinite scroll -->
      <div id="infinite-scroll-trigger" style="height: 20px;"></div>
    </div>

    <div class="mss-section-gap-sm"></div>

    <!-- Banner nuova collezione FULL WIDTH -->
    <div class="mss-banner-fullwidth">
      <div class="mss-banner-fullwidth-inner">
        <div class="mss-banner-text">
          <?php $badgeTxt = trim($collectionSettings['badge_text'] ?? 'NOVITÀ 2026'); ?>
          <a href="homePage.php?cat=<?= urlencode($badgeTxt) ?>" class="mss-collection-badge text-decoration-none">
            <?= htmlspecialchars($badgeTxt) ?>
          </a>
          <h2><?= htmlspecialchars($collectionSettings['title'] ?? 'Scopri la Nuova Collezione') ?></h2>
          <p><?= $collectionSettings['subtitle'] ?? 'Articoli esclusivi e in edizione limitata ispirati al mondo di Monstropolis.' ?></p>
          <div class="d-flex gap-3 flex-wrap">
            <a href="homePage.php?cat=Nuova%20Collezione" class="btn mss-btn-primary">
              <i class="bi bi-stars me-2"></i>Esplora Nuova Collezione
            </a>
          </div>
        </div>
        <div class="mss-banner-icons" aria-hidden="true">
          <?php if (!empty($featuredProducts)): ?>
            <?php foreach (array_slice($featuredProducts, 0, 4) as $featured): ?>
              <div class="text-center">
                <img src="<?= htmlspecialchars($featured['immagine_path']) ?>" alt="<?= htmlspecialchars($featured['nome']) ?>" class="img-fluid rounded-circle" style="width:72px;height:72px;object-fit:contain;background:rgba(255,255,255,0.75);padding:8px;">
                <div class="small mt-2 fw-semibold"><?= htmlspecialchars($featured['nome']) ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <i class="bi bi-emoji-smile-upside-down"></i>
            <i class="bi bi-door-open"></i>
            <i class="bi bi-stars"></i>
            <i class="bi bi-bag-heart"></i>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($featuredProducts)): ?>
    <div class="container mt-4">
      <div class="mss-section-title mb-3">
        <i class="bi bi-stars me-2 text-gradient"></i> Prodotti in evidenza
      </div>
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
        <?php foreach ($featuredProducts as $featured): ?>
          <div class="col">
            <div class="mss-card h-100">
              <a href="productDetail.php?id=<?= (int)$featured['id'] ?>">
                <div class="card-img-wrapper">
                  <img src="<?= htmlspecialchars($featured['immagine_path']) ?>" alt="<?= htmlspecialchars($featured['nome']) ?>">
                </div>
              </a>
              <div class="card-body d-flex flex-column">
                <?php if (!empty($featured['cat'])): ?><div class="badge-cat"><?= htmlspecialchars($featured['cat']) ?></div><?php endif; ?>
                <h5 class="card-title"><?= htmlspecialchars($featured['nome']) ?></h5>
                <div class="mt-auto pt-2">
                  <?php if (!empty($featured['sconto_percentuale']) && $featured['sconto_percentuale'] > 0): ?>
                    <p class="price mb-0">
                      <span class="text-decoration-line-through text-muted" style="font-size:0.85em; -webkit-text-fill-color: initial;"><?= number_format($featured['prezzo'], 2, ',', '.') ?>€</span>
                      <span class="text-danger ms-1" style="-webkit-text-fill-color: initial;"><?= number_format($featured['prezzo'] * (1 - $featured['sconto_percentuale']/100), 2, ',', '.') ?>€</span>
                      <span class="badge bg-danger ms-1" style="-webkit-text-fill-color: initial; color: white;">-<?= (int)$featured['sconto_percentuale'] ?>%</span>
                    </p>
                  <?php else: ?>
                    <p class="price mb-0"><?= number_format($featured['prezzo'], 2, ',', '.') ?>€</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="mss-section-gap-md"></div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/mss-cart.js"></script>
    <script src="assets/js/mss-wishlist.js"></script>
    <script>
      // Navbar scroll effect
      const navbar = document.querySelector('.mss-navbar');
      window.addEventListener('scroll', () => {
        if (window.scrollY > 10) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
      });

      // Fade-up animations
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
          }
        });
      }, { threshold: 0.1 });
      document.querySelectorAll('.fade-up, .fade-in').forEach(el => { el.classList.add('anim-init'); observer.observe(el); });

      // Infinite Scroll Logic
      const scrollTrigger = document.getElementById('infinite-scroll-trigger');
      const productsGrid = document.getElementById('products-grid');
      let hiddenProducts = Array.from(document.querySelectorAll('[data-product-card].d-none'));

      if (scrollTrigger && hiddenProducts.length > 0) {
        const scrollObserver = new IntersectionObserver((entries) => {
          if (entries[0].isIntersecting) {
            // Show next 4 products
            const toShow = hiddenProducts.splice(0, 4);
            toShow.forEach(p => {
                p.classList.remove('d-none');
                // Force animation trigger if needed
                observer.observe(p);
            });
            
            if (hiddenProducts.length === 0) {
              scrollObserver.unobserve(scrollTrigger);
            }
          }
        }, { threshold: 0.1, rootMargin: '100px' });
        
        scrollObserver.observe(scrollTrigger);
      }

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
