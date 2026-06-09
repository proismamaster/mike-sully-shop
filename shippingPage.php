<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'php/db_connection.php';

$carrello = $_SESSION['carrello'] ?? [];
if (empty($carrello)) {
    header('Location: cart.php?msg=vuoto');
    exit();
}

$totale = 0;
foreach ($carrello as $item) {
    $totale += (float)$item['prezzo'] * (int)$item['qta'];
}

$userData = [
    'nome' => '',
    'cognome' => '',
    'email' => '',
];

if (!empty($_SESSION['utente_id'])) {
    $stmt = $conn->prepare("SELECT nome, cognome, email FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['utente_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $userData = $row;
    }
}

$fullName = trim(($_SESSION['user'] ?? '') . ' ' . '');
if ($fullName !== '' && empty($userData['nome'])) {
    $parts = preg_split('/\s+/', trim($_SESSION['user'] ?? ''), 2);
    $userData['nome'] = $parts[0] ?? '';
    $userData['cognome'] = $parts[1] ?? '';
}
?>
<!doctype html>
<html lang="it">
  <head>
    <title>MikeSullyShop - Checkout</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>

  <body>
    <?php include 'php/header.php'; ?>

    <div class="container mt-4 py-4 fade-in">
      <div class="row g-4">
        <div class="col-12 col-lg-7">
          <form action="php/cart_handler.php" method="POST" class="mss-auth-card p-4">
            <input type="hidden" name="azione" value="checkout">
            <h2 class="mss-section-title mb-4 text-center w-100">Informazioni Spedizione</h2>

            <div class="row g-3">
              <div class="col-md-6 text-start">
                <label for="nameInput" class="form-label ms-1 fw-semibold">Nome</label>
                <input type="text" name="nome" class="form-control mss-input" id="nameInput" value="<?= htmlspecialchars($userData['nome']) ?>" required>
              </div>
              <div class="col-md-6 text-start">
                <label for="surnameInput" class="form-label ms-1 fw-semibold">Cognome</label>
                <input type="text" name="cognome" class="form-control mss-input" id="surnameInput" value="<?= htmlspecialchars($userData['cognome']) ?>" required>
              </div>

              <div class="col-12 text-start">
                <label for="mailInput" class="form-label ms-1 fw-semibold">Email</label>
                <input type="email" name="email" class="form-control mss-input" id="mailInput" value="<?= htmlspecialchars($userData['email']) ?>" required>
              </div>

              <div class="col-md-6 text-start">
                <label class="form-label ms-1 fw-semibold">Telefono</label>
                <input type="text" name="telefono" class="form-control mss-input" placeholder="+39 ..." required>
              </div>
              <div class="col-md-6 text-start">
                <label class="form-label ms-1 fw-semibold">Indirizzo</label>
                <input type="text" name="indirizzo" class="form-control mss-input" placeholder="Via, numero civico" required>
              </div>

              <div class="col-md-6 text-start">
                <label class="form-label ms-1 fw-semibold">Città</label>
                <input type="text" name="citta" class="form-control mss-input" placeholder="Crema" required>
              </div>
              <div class="col-md-3 text-start">
                <label class="form-label ms-1 fw-semibold">CAP</label>
                <input type="text" name="cap" class="form-control mss-input" placeholder="26010" required>
              </div>
              <div class="col-md-3 text-start">
                <label class="form-label ms-1 fw-semibold">Provincia</label>
                <input type="text" name="provincia" class="form-control mss-input" placeholder="CR" required>
              </div>

              <div class="col-12 text-start">
                <label class="form-label ms-1 fw-semibold">Note ordine</label>
                <textarea name="note" class="form-control mss-input" rows="3" placeholder="Istruzioni per la consegna (opzionale)"></textarea>
              </div>
            </div>

            <hr class="my-4">

            <h2 class="mss-section-title mb-4 text-center w-100">Metodo di Pagamento</h2>

            <div class="d-flex flex-column gap-3">
              <div class="form-check p-0">
                <input type="radio" class="btn-check" name="payment_method" id="creditCard" value="Carta di Credito" checked>
                <label class="btn mss-btn-outline w-100 p-3 text-start d-flex justify-content-between align-items-center" for="creditCard">
                  <span><i class="bi bi-credit-card-2-front me-2"></i> Carta di Credito</span>
                </label>
              </div>

              <div class="form-check p-0">
                <input type="radio" class="btn-check" name="payment_method" id="paypal" value="PayPal">
                <label class="btn mss-btn-outline w-100 p-3 text-start d-flex justify-content-between align-items-center" for="paypal">
                  <span><i class="bi bi-paypal me-2 text-primary"></i> PayPal</span>
                </label>
              </div>

              <div class="form-check p-0">
                <input type="radio" class="btn-check" name="payment_method" id="cod" value="Contanti alla ricezione">
                <label class="btn mss-btn-outline w-100 p-3 text-start d-flex justify-content-between align-items-center" for="cod">
                  <span><i class="bi bi-cash-coin me-2 text-success"></i> Contanti alla ricezione</span>
                </label>
              </div>
            </div>

            <div class="d-grid gap-2 mt-4 mb-2">
              <button type="submit" id="btn-conferma" class="btn btn-cart btn-lg py-3 fw-bold fs-4">
                CONFERMA ORDINE <i class="bi bi-shield-lock ms-2"></i>
              </button>
              <p class="text-center small mt-2 mss-text-muted">
                <i class="bi bi-info-circle"></i> Ti invieremo un'email di conferma. Per i pagamenti online, sarà richiesto un codice OTP (mock).
              </p>
            </div>
          </form>
        </div>

        <div class="col-12 col-lg-5">
          <div class="mss-auth-card p-4 h-100">
            <h2 class="mss-section-title mb-4 text-center w-100">Riepilogo Carrello</h2>
            <div class="list-group list-group-flush mb-3">
              <?php foreach ($carrello as $item): ?>
                <div class="list-group-item px-0 d-flex justify-content-between gap-2 align-items-center">
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($item['nome']) ?></div>
                    <div class="small mss-text-muted">Q.tà <?= (int)$item['qta'] ?></div>
                  </div>
                  <div class="fw-bold"><?= number_format((float)$item['prezzo'] * (int)$item['qta'], 2, ',', '.') ?>€</div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <span class="mss-text-muted">Totale ordine</span>
              <span class="fw-bold mss-price-tag fs-4"><?= number_format($totale, 2, ',', '.') ?>€</span>
            </div>
            <a href="cart.php" class="btn mss-btn-outline w-100 mt-4">Torna al carrello</a>
          </div>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.querySelector('form').addEventListener('submit', function(e) {
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
        if (paymentMethod !== 'Contanti alla ricezione') {
          e.preventDefault();
          const email = document.getElementById('mailInput').value;
          const otp = prompt(`Invio richiesta alla tua banca...\nAbbiamo inviato un codice OTP all\'indirizzo ${email}.\nInserisci il codice di 4 cifre per confermare il pagamento (mock):`, '1234');
          if (otp) {
            alert('Pagamento autorizzato con successo! Creazione ordine in corso...');
            this.submit();
          } else {
            alert('Pagamento annullato. Riprova quando sei pronto.');
          }
        }
      });
    </script>
  </body>
</html>
