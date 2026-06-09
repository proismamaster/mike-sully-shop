<?php
include 'php/auth_check.php';
checkAdmin();
require_once 'php/db_connection.php';
require_once 'php/site_bootstrap.php';

$success = $_GET['success'] ?? '';
$error = '';

$categories = [];
// Prendo i dati delle categorie dal database per la select
$catResult = $conn->query("SELECT id, nome FROM categories ORDER BY nome ASC");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Aggiorna prodotto esistente (immagine opzionale)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $pid = (int)($_POST['product_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $prezzo = (float)str_replace(',', '.', trim($_POST['prezzo'] ?? '0'));
    $giacenza = max(0, (int)($_POST['giacenza'] ?? 0));
    $categoriaId = (int)($_POST['categoria_id'] ?? 0);
    $sconto_percentuale = min(100, max(0, (int)($_POST['sconto_percentuale'] ?? 0)));

    if ($pid <= 0 || $nome === '' || $descrizione === '' || $prezzo <= 0 || $categoriaId <= 0) {
        $error = 'Compila tutti i campi del prodotto.';
    } else {
        // Recupera path immagine attuale
        $stmt = $conn->prepare("SELECT immagine_path FROM products WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $currentImages = mss_get_product_images($old['immagine_path'] ?? '');

        $imageOrder = $_POST['image_order'] ?? [];
        if (!is_array($imageOrder)) {
            $imageOrder = [$imageOrder];
        }

        $finalImages = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0775, true); }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($nome));

        foreach ($imageOrder as $idx => $item) {
            if (in_array($item, $currentImages, true)) {
                $finalImages[] = $item;
            } else {
                if (!empty($_FILES['immagini']['name'])) {
                    foreach ($_FILES['immagini']['name'] as $fileIdx => $originalName) {
                        if ($originalName === $item && $_FILES['immagini']['error'][$fileIdx] === UPLOAD_ERR_OK) {
                            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                            if (in_array($extension, $allowedExtensions, true)) {
                                $fileName = $slug . '-' . time() . '-' . $idx . '.' . $extension;
                                $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $fileName;
                                if (move_uploaded_file($_FILES['immagini']['tmp_name'][$fileIdx], $targetPath)) {
                                    $finalImages[] = 'assets/img/products/' . $fileName;
                                    $_FILES['immagini']['name'][$fileIdx] = null; 
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
        $totalImages = count($finalImages);

        if ($totalImages < 1) {
            $error = 'Devi lasciare almeno un\'immagine per il prodotto.';
        } elseif ($totalImages > 5) {
            $error = 'Non puoi avere più di 5 immagini per prodotto.';
        } else {
            $relativePath = json_encode($finalImages);
            $stmt = $conn->prepare("UPDATE products SET nome = ?, descrizione = ?, prezzo = ?, giacenza = ?, categoria_id = ?, immagine_path = ?, sconto_percentuale = ? WHERE id = ?");
            $stmt->bind_param('ssdissii', $nome, $descrizione, $prezzo, $giacenza, $categoriaId, $relativePath, $sconto_percentuale, $pid);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: adminMagazzino.php?success=prodotto');
                exit();
            }
            $stmt->close();
            $error = 'Errore durante l\'aggiornamento del prodotto.';
        }
    }
}

// Elimina prodotto (Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($pid > 0) {
        $stmt = $conn->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $pid);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: adminMagazzino.php?success=prodotto');
            exit();
        }
        $stmt->close();
        $error = 'Eliminazione non riuscita.';
    }
}

// Elimina products multipli (Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $pids = $_POST['product_ids'] ?? [];
    if (!empty($pids) && is_array($pids)) {
        $deleted = 0;
        foreach ($pids as $pid) {
            $pid = (int)$pid;
            $stmt = $conn->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $pid);
            if ($stmt->execute()) {
                $deleted++;
            }
            $stmt->close();
        }
        $msg = "Eliminati $deleted products.";
        header('Location: adminMagazzino.php?success=bulk&msg=' . urlencode($msg));
        exit();
    }
}
// ID della categoria "Nuova Collezione"
$nuovaCollezioneId = 0;
foreach ($categories as $c) {
    if (strcasecmp($c['nome'], 'Nuova Collezione') === 0) { $nuovaCollezioneId = (int)$c['id']; break; }
}
// Rinomina categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_category'])) {
    $catId   = (int)($_POST['category_id'] ?? 0);
    $newName = trim($_POST['new_name'] ?? '');
    if ($catId <= 0 || $newName === '') {
        $error = 'Dati categoria non validi.';
    } else {
        $stmt = $conn->prepare("UPDATE categories SET nome = ? WHERE id = ?");
        $stmt->bind_param('si', $newName, $catId);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: adminMagazzino.php?success=cat_renamed');
            exit();
        }
        $stmt->close();
        $error = 'Impossibile rinominare la categoria.';
    }
}

// Elimina categoria (forzata)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $catId = (int)($_POST['category_id'] ?? 0);
    if ($catId <= 0) {
        $error = 'Categoria non valida.';
    } else {
        // Svincola i products associati
        $stmt = $conn->prepare("UPDATE products SET categoria_id = NULL WHERE categoria_id = ?");
        $stmt->bind_param('i', $catId);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param('i', $catId);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: adminMagazzino.php?success=cat_deleted');
            exit();
        }
        $stmt->close();
        $error = 'Eliminazione categoria non riuscita.';
    }
}

// Assegna categoria ad un prodotto (schema attuale: singola categoria)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product_category'])) {
    $productId  = (int)($_POST['product_id'] ?? 0);
    $categoryId = (int)($_POST['categoria_id'] ?? 0);
    if ($productId <= 0 || $categoryId <= 0) {
        $error = 'Selezione non valida.';
    } else {
        $stmt = $conn->prepare("UPDATE products SET categoria_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $categoryId, $productId);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: adminMagazzino.php?success=cat_assigned');
            exit();
        }
        $stmt->close();
        $error = 'Impossibile aggiornare la categoria del prodotto.';
    }
}

// Toggle aggiunta/rimozione in pivot product_categories (es. Nuova Collezione)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pivot'])) {
    $productId  = (int)($_POST['product_id'] ?? 0);
    $categoryId = (int)($_POST['categoria_id'] ?? 0);
    $checked    = (int)($_POST['checked'] ?? 0);
    if ($productId > 0 && $categoryId > 0) {
        if ($checked === 1) {
            $stmt = $conn->prepare("INSERT IGNORE INTO product_categories (prodotto_id, categoria_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $productId, $categoryId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("DELETE FROM product_categories WHERE prodotto_id = ? AND categoria_id = ?");
            $stmt->bind_param('ii', $productId, $categoryId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: adminMagazzino.php?success=pivot_updated');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $categoryName = trim($_POST['category_name'] ?? '');
    if ($categoryName === '') {
        $error = 'Inserisci il nome della categoria.';
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (nome) VALUES (?)");
        $stmt->bind_param('s', $categoryName);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: adminMagazzino.php?success=categoria');
            exit();
        }
        $stmt->close();
        $error = 'Categoria non salvata.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_collection'])) {
    $selectedProducts = $_POST['collection_products'] ?? [];
    if (!is_array($selectedProducts)) {
        $selectedProducts = [];
    }
    // Prendo solo gli ID validi (maggiori di zero)
    $selectedIds = [];
    foreach ($selectedProducts as $idStr) {
        $idNum = (int)$idStr;
        if ($idNum > 0) {
            $selectedIds[] = $idNum;
        }
    }
    $payload = [
        'badge_text' => $_POST['collection_badge'] ?? 'NOVITÀ 2026',
        'title' => $_POST['collection_title'] ?? 'Scopri la Nuova Collezione',
        'subtitle' => $_POST['collection_subtitle'] ?? '',
        'product_ids' => implode(',', $selectedIds),
    ];

    if (mss_save_home_collection($conn, $payload)) {
        header('Location: adminMagazzino.php?success=collezione');
        exit();
    }
    $error = 'Impossibile salvare la collezione in evidenza.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $nome = trim($_POST['nome'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $prezzo = (float)str_replace(',', '.', trim($_POST['prezzo'] ?? '0'));
    $giacenza = max(0, (int)($_POST['giacenza'] ?? 0));
    $categoriaId = (int)($_POST['categoria_id'] ?? 0);
    $sconto_percentuale = min(100, max(0, (int)($_POST['sconto_percentuale'] ?? 0)));

    if ($nome === '' || $descrizione === '' || $prezzo <= 0 || $categoriaId <= 0) {
        $error = 'Compila tutti i campi del prodotto.';
    } elseif (empty($_FILES['immagini']['name'][0])) {
        $error = 'Seleziona almeno un\'immagine per il prodotto.';
    } else {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
        }

        $imageOrder = $_POST['image_order'] ?? [];
        if (!is_array($imageOrder)) {
            $imageOrder = [$imageOrder];
        }

        $uploadedPaths = [];
        // Sostituisco gli spazi e i caratteri speciali per creare un nome file pulito
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($nome));
        
        foreach ($imageOrder as $idx => $item) {
            if (!empty($_FILES['immagini']['name'])) {
                foreach ($_FILES['immagini']['name'] as $fileIdx => $originalName) {
                    if ($originalName === $item && $_FILES['immagini']['error'][$fileIdx] === UPLOAD_ERR_OK) {
                        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        if (in_array($extension, $allowedExtensions, true)) {
                            $fileName = $slug . '-' . time() . '-' . $idx . '.' . $extension;
                            $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $fileName;
                            if (move_uploaded_file($_FILES['immagini']['tmp_name'][$fileIdx], $targetPath)) {
                                $uploadedPaths[] = 'assets/img/products/' . $fileName;
                                $_FILES['immagini']['name'][$fileIdx] = null;
                            }
                        }
                        break;
                    }
                }
            }
        }

        if (empty($uploadedPaths)) {
            $error = 'Impossibile salvare le immagini o formati non validi. Usa JPG, PNG, WEBP o GIF.';
        } elseif (count($uploadedPaths) > 5) {
            $error = 'Non puoi avere più di 5 immagini per prodotto.';
        } else {
            $relativePath = json_encode($uploadedPaths);
            $stmt = $conn->prepare("INSERT INTO products (nome, descrizione, prezzo, giacenza, categoria_id, immagine_path, sconto_percentuale) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdissi', $nome, $descrizione, $prezzo, $giacenza, $categoriaId, $relativePath, $sconto_percentuale);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: adminMagazzino.php?success=prodotto');
                exit();
            }

            $stmt->close();
            $error = 'Errore durante il salvataggio del prodotto.';
        }
    }
}

$products = [];
$productResult = $conn->query("SELECT p.id, p.nome, p.prezzo, p.sconto_percentuale, p.giacenza, p.immagine_path, p.categoria_id, c.nome AS categoria FROM products p LEFT JOIN categories c ON p.categoria_id = c.id WHERE p.deleted_at IS NULL ORDER BY p.id DESC");
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}
// Mappa products appartenenti a "Nuova Collezione" (via pivot)
$inNuovaCollezione = [];
if ($nuovaCollezioneId > 0 && !empty($products)) {
    // Prendo tutti gli ID dei prodotti attualmente visibili
    $ids = [];
    foreach ($products as $p) {
        $idCorrente = (int)$p['id'];
        if ($idCorrente > 0) {
            $ids[] = $idCorrente;
        }
    }
    if ($ids) {
        $sql = "SELECT prodotto_id FROM product_categories WHERE categoria_id = ? AND prodotto_id IN (" . implode(',', $ids) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $nuovaCollezioneId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $inNuovaCollezione[(int)$r['prodotto_id']] = true; }
        $stmt->close();
    }
}

$collectionSettings = mss_get_home_collection($conn);
$selectedCollectionIds = mss_parse_product_ids($collectionSettings['product_ids'] ?? '');
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Gestione Magazzino - MikeSullyShop Admin</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="mss-page">
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 mb-5 fade-in">
      <!-- Messaggi di successo/errore sulla stessa pagina -->
      <?php if($success === 'prodotto'): ?>
        <div class="alert alert-success alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Prodotto inserito con successo!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif($success === 'bulk'): ?>
        <div class="alert alert-success alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? '') ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif($success === 'categoria'): ?>
        <div class="alert alert-success alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Categoria creata con successo!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif($success === 'collezione'): ?>
        <div class="alert alert-success alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Collezione homepage aggiornata!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif(in_array($success, ['cat_assigned','pivot_updated','cat_renamed','cat_deleted'], true)): ?>
        <div class="alert alert-success alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> Modifiche salvate con successo.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mss-alert" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>



      <h2 class="mss-section-title mb-4">
        <i class="bi bi-boxes me-2 text-gradient"></i> Gestione Magazzino
      </h2>

      <div class="row g-4">
        <!-- Inserimento Nuovo Prodotto -->
        <div class="col-lg-4">
          <div class="mss-auth-card mb-4">
            <div class="mss-panel-header mss-panel-header-primary">
              <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i><?= isset($_GET['edit']) ? 'Modifica Articolo' : 'Aggiungi Nuovo Articolo' ?></h5>
            </div>
            <div class="card-body p-4">
              <?php
                $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
                $editProduct = null;
                if ($editId > 0) {
                  $stmt = $conn->prepare("SELECT id, nome, descrizione, prezzo, sconto_percentuale, giacenza, categoria_id, immagine_path FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                  $stmt->bind_param('i', $editId);
                  $stmt->execute();
                  $editProduct = $stmt->get_result()->fetch_assoc();
                  $stmt->close();
                  if (!$editProduct) { $editId = 0; }
                }
              ?>
              <form action="adminMagazzino.php<?= $editId ? '?edit='.$editId : '' ?>" method="POST" enctype="multipart/form-data">
                <?php if ($editId): ?>
                  <input type="hidden" name="update_product" value="1">
                  <input type="hidden" name="product_id" value="<?= $editId ?>">
                <?php else: ?>
                  <input type="hidden" name="save_product" value="1">
                <?php endif; ?>
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Nome Prodotto</label>
                  <input type="text" name="nome" class="form-control mss-input" placeholder="es: Portachiavi Boo" value="<?= htmlspecialchars($editProduct['nome'] ?? '') ?>" required>
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Descrizione</label>
                  <textarea name="descrizione" class="form-control mss-input" rows="4" placeholder="Descrivi il prodotto, i materiali, le caratteristiche..." required><?= htmlspecialchars($editProduct['descrizione'] ?? '') ?></textarea>
                </div>
                <div class="row mb-3">
                  <div class="col-4 text-start">
                    <label class="form-label fw-semibold small"><i class="bi bi-currency-euro me-1"></i>Prezzo (€)</label>
                    <input type="number" step="0.01" name="prezzo" class="form-control mss-input" placeholder="0.00" value="<?= isset($editProduct['prezzo']) ? number_format((float)$editProduct['prezzo'], 2, '.', '') : '' ?>" required>
                  </div>
                  <div class="col-4 text-start">
                    <label class="form-label fw-semibold small"><i class="bi bi-percent me-1"></i>Sconto (%)</label>
                    <input type="number" name="sconto_percentuale" class="form-control mss-input" placeholder="0" min="0" max="100" value="<?= htmlspecialchars($editProduct['sconto_percentuale'] ?? '0') ?>">
                  </div>
                  <div class="col-4 text-start">
                    <label class="form-label fw-semibold small"><i class="bi bi-box-seam me-1"></i>Giacenza</label>
                    <input type="number" name="giacenza" class="form-control mss-input" placeholder="10" min="0" value="<?= htmlspecialchars($editProduct['giacenza'] ?? '') ?>" required>
                  </div>
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Categoria</label>
                  <select name="categoria_id" class="form-select mss-select" required>
                    <option value="">Seleziona categoria</option>
                    <?php foreach ($categories as $category): ?>
                      <?php if ((int)$category['id'] === (int)$nuovaCollezioneId) continue; ?>
                      <option value="<?= (int)$category['id'] ?>" <?= isset($editProduct['categoria_id']) && (int)$editProduct['categoria_id'] === (int)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Immagini prodotto (Trascina per riordinare, Max 5)</label>
                  <input type="file" id="image-upload-input" name="immagini[]" class="d-none" accept="image/*" multiple>
                  
                  <div class="d-flex flex-wrap gap-3 align-items-center mb-2" id="sortable-images-container" style="min-height: 90px; padding: 10px; border: 2px dashed #dee2e6; border-radius: 8px;">
                    <?php if ($editId && !empty($editProduct['immagine_path'])): ?>
                      <?php 
                      $imgs = mss_get_product_images($editProduct['immagine_path']); 
                      foreach ($imgs as $idx => $imgUrl):
                      ?>
                        <div class="mss-image-preview-item position-relative border rounded p-1" style="background:#fff; cursor:grab;" data-is-new="0">
                          <img src="<?= htmlspecialchars($imgUrl) ?>" style="width: 80px; height: 80px; object-fit: contain;">
                          <input type="hidden" name="image_order[]" value="<?= htmlspecialchars($imgUrl) ?>">
                          <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0 mss-remove-img" style="width:20px;height:20px;line-height:1;"><i class="bi bi-x"></i></button>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <button type="button" id="btn-add-images" class="btn btn-outline-secondary border-dashed d-flex align-items-center justify-content-center" style="width:90px; height:90px; border-style: dashed;">
                      <i class="bi bi-plus-lg fs-3"></i>
                    </button>
                  </div>
                  <div class="form-text text-danger d-none" id="image-error-msg"></div>
                </div>
                <div class="d-flex gap-2">
                  <button type="submit" class="btn mss-btn-primary w-100"><?= $editId ? 'Aggiorna Prodotto' : 'Inserisci Prodotto' ?></button>
                  <?php if ($editId): ?>
                    <a href="adminMagazzino.php" class="btn btn-outline-secondary w-100">Annulla</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>

          <div class="mss-auth-card">
            <div class="mss-panel-header mss-panel-header-accent">
              <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Crea Nuovo Account</h5>
            </div>
            <div class="card-body p-4">
              <form action="php/create_account.php" method="POST">
                <div class="mb-2">
                  <input type="text" name="nome" class="form-control mss-input form-control-sm" placeholder="Nome" required>
                </div>
                <div class="mb-2">
                  <input type="text" name="cognome" class="form-control mss-input form-control-sm" placeholder="Cognome" required>
                </div>
                <div class="mb-2">
                  <input type="email" name="email" class="form-control mss-input form-control-sm" placeholder="Email" required>
                </div>
                <div class="mb-2">
                  <input type="password" name="password" class="form-control mss-input form-control-sm" placeholder="Password" required>
                </div>
                <div class="mb-3">
                  <select name="ruolo" class="form-select mss-select form-select-sm">
                    <option value="cliente">Ruolo: Cliente</option>
                    <option value="admin">Ruolo: Admin</option>
                  </select>
                </div>
                <button type="submit" class="btn mss-btn-primary btn-sm w-100 fw-bold">CREA ACCOUNT</button>
              </form>
            </div>
          </div>

          <div class="mss-auth-card mt-4">
            <div class="mss-panel-header mss-panel-header-primary">
              <h5 class="mb-0 fw-bold"><i class="bi bi-tags me-2"></i>Nuova Categoria</h5>
            </div>
            <div class="card-body p-4">
              <form action="adminMagazzino.php" method="POST">
                <input type="hidden" name="save_category" value="1">
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Nome categoria</label>
                  <input type="text" name="category_name" class="form-control mss-input" placeholder="Es: Gadget" required>
                </div>
                <button type="submit" class="btn mss-btn-primary w-100">Crea Categoria</button>
              </form>
              <div class="mt-3">
                <?php foreach ($categories as $category): ?>
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge-cat flex-shrink-0" style="min-width: 140px; text-align:center;">
                      <?= htmlspecialchars($category['nome']) ?>
                    </span>
                    <form action="adminMagazzino.php" method="POST" class="d-flex align-items-center gap-2 flex-grow-1">
                      <input type="hidden" name="rename_category" value="1">
                      <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                      <input type="text" name="new_name" class="form-control form-control-sm mss-input" placeholder="Rinomina categoria">
                      <button type="submit" class="btn btn-sm btn-outline-primary" title="Rinomina"><i class="bi bi-pencil-square"></i></button>
                    </form>
                    <form action="adminMagazzino.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare la categoria?');">
                      <input type="hidden" name="delete_category" value="1">
                      <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="mss-auth-card mt-4">
            <div class="mss-panel-header mss-panel-header-accent">
              <h5 class="mb-0 fw-bold"><i class="bi bi-stars me-2"></i>Collezione Home</h5>
            </div>
            <div class="card-body p-4">
              <form action="adminMagazzino.php" method="POST">
                <input type="hidden" name="save_collection" value="1">
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Scritta badge</label>
                  <input type="text" name="collection_badge" class="form-control mss-input" value="<?= htmlspecialchars($collectionSettings['badge_text'] ?? '') ?>" placeholder="NOVITÀ 2026">
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Titolo collezione</label>
                  <input type="text" name="collection_title" class="form-control mss-input" value="<?= htmlspecialchars($collectionSettings['title'] ?? '') ?>" placeholder="Scopri la Nuova Collezione" required>
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label fw-semibold small">Descrizione</label>
                  <textarea name="collection_subtitle" class="form-control mss-input" rows="4" placeholder="Testo visibile nella sezione home"><?= htmlspecialchars($collectionSettings['subtitle'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn mss-btn-primary w-100">Salva Collezione</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Modifica Giacenza Esistente -->
        <div class="col-lg-8">
          <form id="bulk-delete-form" action="adminMagazzino.php" method="POST" onsubmit="return confirm('Eliminare i products selezionati?');">
            <input type="hidden" name="bulk_delete" value="1">
            <div class="mss-auth-card h-100">
              <div class="mss-panel-header mss-panel-header-primary d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                  <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Inventario Attuale</h5>
                  <button type="submit" class="btn btn-sm btn-danger d-none" id="btn-bulk-delete">
                    <i class="bi bi-trash me-1"></i> Elimina Selezionati
                  </button>
                </div>
                <input id="admin-search" type="text" class="form-control form-control-sm w-auto mss-input" style="min-width: 240px;" placeholder="Cerca per nome...">
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead style="background: linear-gradient(135deg, rgba(37,99,235,0.08) 0%, rgba(20,184,166,0.08) 100%);">
                      <tr>
                        <th class="ps-3" style="width: 40px;">
                          <input type="checkbox" class="form-check-input" id="select-all">
                        </th>
                        <th class="fw-semibold" data-sort="id" style="cursor:pointer;">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-hash mss-link-primary"></i>
                            <span>ID</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th class="fw-semibold" data-sort="name" style="cursor:pointer;">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-bag mss-link-primary"></i>
                            <span>Articolo</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th class="fw-semibold">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-tags mss-link-primary"></i>
                            <span>Categoria</span>
                          </div>
                        </th>
                        <th class="fw-semibold">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-image mss-link-primary"></i>
                            <span>Immagine</span>
                          </div>
                        </th>
                        <th class="fw-semibold" data-sort="giacenza" style="cursor:pointer;">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-box-seam mss-link-primary"></i>
                            <span>Giacenza</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th class="fw-semibold" data-sort="prezzo" style="cursor:pointer;">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-currency-euro mss-link-primary"></i>
                            <span>Prezzo</span>
                            <i class="bi bi-arrow-down-up small opacity-50"></i>
                          </div>
                        </th>
                        <th class="fw-semibold text-end pe-3">Azioni</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($products as $product): ?>
                        <tr data-row>
                          <td class="ps-3">
                            <input type="checkbox" name="product_ids[]" value="<?= (int)$product['id'] ?>" class="form-check-input product-check">
                          </td>
                          <td>#<?= (int)$product['id'] ?></td>
                          <td class="fw-medium" data-name><?= htmlspecialchars($product['nome']) ?></td>
                          <td data-cat>
                            <form action="adminMagazzino.php" method="POST" class="d-flex align-items-center gap-3 flex-wrap">
                              <input type="hidden" name="update_product_category" value="1">
                              <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                              <select name="categoria_id" class="form-select form-select-sm mss-select" style="min-width: 160px;" onchange="this.form.submit()">
                                <option value="">Seleziona…</option>
                                <?php foreach ($categories as $cat): ?>
                                  <?php if ($cat['nome'] === 'Nuova Collezione') continue; ?>
                                  <option value="<?= (int)$cat['id'] ?>" <?= ((int)($product['categoria_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                                <?php endforeach; ?>
                              </select>
                            </form>
                            <?php if ($nuovaCollezioneId > 0): ?>
                            <form action="adminMagazzino.php" method="POST" class="d-inline-block align-middle ms-1">
                              <input type="hidden" name="toggle_pivot" value="1">
                              <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                              <input type="hidden" name="categoria_id" value="<?= (int)$nuovaCollezioneId ?>">
                              <input type="hidden" name="checked" value="<?= isset($inNuovaCollezione[(int)$product['id']]) ? '1' : '0' ?>">
                              <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" <?= isset($inNuovaCollezione[(int)$product['id']]) ? 'checked' : '' ?> onchange="this.form.querySelector('input[name=checked]').value = this.checked ? '1' : '0'; this.form.submit();">
                                <label class="form-check-label small">Nuova Collezione</label>
                              </div>
                            </form>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php $imgs = mss_get_product_images($product['immagine_path']); ?>
                            <img src="<?= htmlspecialchars($imgs[0] ?? '') ?>" alt="<?= htmlspecialchars($product['nome']) ?>" style="width:48px;height:48px;object-fit:contain;">
                          </td>
                          <td data-giacenza-val="<?= (int)$product['giacenza'] ?>"><?= (int)$product['giacenza'] ?></td>
                          <td class="fw-bold" style="color: var(--primary-dark);" data-prezzo-val="<?= (float)$product['prezzo'] ?>">
                            <?= number_format((float)$product['prezzo'], 2, ',', '.') ?>€
                            <?php if (!empty($product['sconto_percentuale']) && $product['sconto_percentuale'] > 0): ?>
                              <span class="badge bg-danger ms-1">-<?= (int)$product['sconto_percentuale'] ?>%</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-1">
                              <a href="adminMagazzino.php?edit=<?= (int)$product['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica"><i class="bi bi-pencil"></i></a>
                              <form action="adminMagazzino.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare definitivamente questo prodotto?');">
                                <input type="hidden" name="delete_product" value="1">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina"><i class="bi bi-trash"></i></button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
      // Client-side search filter (solo nome)
      const q = document.getElementById('admin-search');
      if (q) {
        q.addEventListener('input', () => {
          const term = q.value.trim().toLowerCase();
          document.querySelectorAll('[data-row]').forEach(tr => {
            const name = (tr.querySelector('[data-name]')?.textContent || '').toLowerCase();
            const match = name.includes(term);
            tr.style.display = match ? '' : 'none';
          });
        });
      }

      // Selection logic for bulk delete
      const selectAll = document.getElementById('select-all');
      const productChecks = document.querySelectorAll('.product-check');
      const btnBulkDelete = document.getElementById('btn-bulk-delete');

      const updateBulkBtn = () => {
        const checkedCount = document.querySelectorAll('.product-check:checked').length;
        if (checkedCount > 0) {
          btnBulkDelete.classList.remove('d-none');
        } else {
          btnBulkDelete.classList.add('d-none');
        }
      };

      if (selectAll) {
        selectAll.addEventListener('change', () => {
          productChecks.forEach(cb => cb.checked = selectAll.checked);
          updateBulkBtn();
        });
      }

      productChecks.forEach(cb => {
        cb.addEventListener('change', updateBulkBtn);
      });

      // Sorting per colonna (ID, Nome, Giacenza, Prezzo)
      const getCellVal = (tr, key) => {
        if (key === 'id') return parseInt(tr.cells[1].textContent.replace('#','').trim()) || 0;
        if (key === 'name') return (tr.querySelector('[data-name]')?.textContent || '').toLowerCase();
        if (key === 'giacenza') return parseInt(tr.querySelector('[data-giacenza-val]').getAttribute('data-giacenza-val')) || 0;
        if (key === 'prezzo') return parseFloat(tr.querySelector('[data-prezzo-val]').getAttribute('data-prezzo-val')) || 0;
        return '';
      };
      document.querySelectorAll('[data-sort]').forEach(th => {
        let dir = 1;
        th.addEventListener('click', () => {
          const key = th.getAttribute('data-sort');
          const tbody = th.closest('table')?.querySelector('tbody');
          if (!tbody) return;
          const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
          rows.sort((a,b) => {
            const va = getCellVal(a, key), vb = getCellVal(b, key);
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb)) * dir;
          });
          dir *= -1;
          rows.forEach(r => tbody.appendChild(r));
        });
      });

      // Sortable Images Logic
      const imgContainer = document.getElementById('sortable-images-container');
      const btnAddImages = document.getElementById('btn-add-images');
      const fileInput = document.getElementById('image-upload-input');
      const errorMsg = document.getElementById('image-error-msg');

      if (imgContainer && fileInput && btnAddImages) {
        new Sortable(imgContainer, {
          animation: 150,
          filter: '#btn-add-images',
          onMove: function(evt) {
            return evt.related.id !== 'btn-add-images';
          }
        });

        btnAddImages.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function(e) {
          const files = Array.from(this.files);
          const currentItems = imgContainer.querySelectorAll('.mss-image-preview-item');
          
          if (currentItems.length + files.length > 5) {
            errorMsg.textContent = "Puoi avere al massimo 5 immagini in totale.";
            errorMsg.classList.remove('d-none');
            this.value = '';
            return;
          }
          errorMsg.classList.add('d-none');

          files.forEach(file => {
            const reader = new FileReader();
            reader.onload = function(evt) {
              const div = document.createElement('div');
              div.className = 'mss-image-preview-item position-relative border rounded p-1';
              div.style.background = '#fff';
              div.style.cursor = 'grab';
              div.dataset.isNew = '1';
              div.innerHTML = `
                <img src="${evt.target.result}" style="width: 80px; height: 80px; object-fit: contain;">
                <input type="hidden" name="image_order[]" value="${file.name}">
                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0 mss-remove-img" style="width:20px;height:20px;line-height:1;"><i class="bi bi-x"></i></button>
              `;
              imgContainer.insertBefore(div, btnAddImages);
            };
            reader.readAsDataURL(file);
          });
        });

        imgContainer.addEventListener('click', function(e) {
          if (e.target.closest('.mss-remove-img')) {
            const item = e.target.closest('.mss-image-preview-item');
            if (item.dataset.isNew === '1') {
              // Note: true file input manipulation is hard, so we just remove the visual and hidden input.
              // We rely on backend filtering by image_order[] to ignore uploaded files that are removed.
            }
            item.remove();
          }
        });

        // Form submit validation
        const form = imgContainer.closest('form');
        form.addEventListener('submit', function(e) {
          const itemsCount = imgContainer.querySelectorAll('.mss-image-preview-item').length;
          if (itemsCount < 1) {
            e.preventDefault();
            errorMsg.textContent = "Devi inserire almeno un'immagine.";
            errorMsg.classList.remove('d-none');
          } else if (itemsCount > 5) {
            e.preventDefault();
            errorMsg.textContent = "Non puoi avere più di 5 immagini.";
            errorMsg.classList.remove('d-none');
          }
        });
      }
    </script>
  </body>
</html>
