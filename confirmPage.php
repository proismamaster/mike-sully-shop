<?php
session_start();
require_once 'php/db_connection.php';
require_once 'php/order_functions.php';

$orderId = (int)($_GET['ordine'] ?? ($_SESSION['ultimo_ordine'] ?? 0));
$order = $orderId > 0 ? mss_fetch_order_by_id($conn, $orderId) : null;
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Grazie! - MikeSullyShop</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body>
    <?php include 'php/header.php'; ?>
    <div class="container text-center fade-in py-5">
      <div class="mss-auth-card p-5 mx-auto" style="max-width: 600px;">
        <div class="mb-4">
          <i class="bi bi-check-circle-fill" style="font-size: 5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
        </div>
        <h1 class="fw-bold mb-3">Ordine Ricevuto!</h1>
        <p class="lead mb-4" style="color: var(--text-muted);">
          Grazie per il tuo acquisto su MikeSullyShop. <br />
          Mike Wazowski sta personalmente impacchettando il tuo ordine!
        </p>
        <hr class="my-4" />
        <p>Numero Ordine: <strong style="color: var(--primary-dark);"><?= $order ? '#' . (int)$order['id'] : 'in attesa di conferma' ?></strong></p>
        <?php if ($order): ?>
          <p class="mb-0">Stato attuale: <span class="badge <?= mss_order_status_class($order['stato']) ?>"><?= htmlspecialchars(mss_display_order_status($order['stato'])) ?></span></p>
          <?php if (!empty($order['ship_nome'])): ?>
            <hr class="my-4">
            <div class="text-start small">
              <p class="mb-1"><strong>Spedizione a:</strong> <?= htmlspecialchars($order['ship_nome']) ?> <?= htmlspecialchars($order['ship_cognome']) ?></p>
              <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($order['ship_email']) ?></p>
              <p class="mb-1"><strong>Telefono:</strong> <?= htmlspecialchars($order['telefono']) ?></p>
              <p class="mb-1"><strong>Indirizzo:</strong> <?= htmlspecialchars($order['indirizzo']) ?>, <?= htmlspecialchars($order['citta']) ?> (<?= htmlspecialchars($order['provincia']) ?>) <?= htmlspecialchars($order['cap']) ?></p>
              <p class="mb-0"><strong>Pagamento:</strong> <?= htmlspecialchars($order['metodo_pagamento']) ?></p>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <a href="homePage.php" class="btn mss-btn-primary btn-lg mt-3">
          <i class="bi bi-house-door me-2"></i> Torna alla Home
        </a>
        <a href="ordini.php" class="btn mss-btn-outline btn-lg mt-3 ms-2">
          <i class="bi bi-receipt me-2"></i> I miei ordini
        </a>
      </div>
    </div>
    <?php include 'php/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
