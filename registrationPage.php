<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['utente_id'])) {
    header('Location: homePage.php');
    exit();
}
require_once 'php/mailer.php';
require_once 'php/db_connection.php';

$errori = [
  'campi' => 'Tutti i campi obbligatori devono essere compilati.',
  'email' => 'Indirizzo email non valido.',
  'password' => 'Le due password non coincidono.',
  'corta' => 'La password deve essere di almeno 6 caratteri.',
  'exists' => 'Esiste già un account con questa email.',
  'server' => 'Errore interno. Riprova più tardi.',
  'otp' => 'Codice OTP non valido o scaduto.',
  'mail_failed' => 'Impossibile inviare l\'email con l\'OTP. Verifica la connessione o contatta l\'assistenza.',
];

// Gestione step OTP
$otpStep = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
  $nome = trim($_POST['nome'] ?? '');
  $cognome = trim($_POST['cognome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = (string)($_POST['password'] ?? '');
  $confirm = (string)($_POST['password_confirm'] ?? '');

  // Validazioni minime lato pagina
  if ($nome === '' || $cognome === '' || $email === '' || $pass === '' || $confirm === '') {
    $err = 'campi';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Uso filter_var di PHP che mi controlla subito se l'email ha la chiocciola ecc.
    $err = 'email';
  } elseif ($pass !== $confirm) {
    $err = 'password';
  } elseif (strlen($pass) < 6) {
    $err = 'corta';
  } else {
    // Genera OTP e invia via email
    $otp = random_int(100000, 999999);
    $_SESSION['pending_reg'] = [
      'nome' => $nome,
      'cognome' => $cognome,
      'email' => $email,
      'password' => $pass,
      'password_confirm' => $confirm,
      'otp' => (string)$otp,
      // salvo anche l'ora attuale per vedere se scade
      'ts' => time(),
    ];
    // Salva OTP in DB (hash) valido 10 minuti
    try {
      $otpHash = password_hash((string)$otp, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO user_otps (email, code_hash, purpose, expires_at) VALUES (?, ?, 'registration', DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
      $stmt->bind_param('ss', $email, $otpHash);
      $stmt->execute();
      $stmt->close();
    } catch (Throwable $e) {
      // Se il DB va in tilt o c'è un errore, scrivo l'errore nei log del server
      // e vado avanti usando la sessione per non bloccare tutto
      error_log('OTP DB insert error: ' . $e->getMessage());
    }
    $subject = 'Codice OTP MikeSullyShop';
    $body = '<div style="font-family:Inter,Arial,sans-serif;font-size:14px;color:#0f172a">'
          . '<h2 style="margin:0 0 8px">Il tuo codice di verifica</h2>'
          . '<p>Usa questo codice per completare la registrazione:</p>'
          . '<p style="font-size:24px;font-weight:800;margin:12px 0">' . htmlspecialchars((string)$otp) . '</p>'
          . '<p style="color:#64748b">Il codice scade tra 10 minuti.</p>'
          . '</div>';
    
    $mailSent = @mss_send_mail($email, $subject, $body);
    if (!$mailSent) {
      error_log("OTP fallito via email per $email. CODICE: $otp");
      $err = 'mail_failed';
      $otpStep = false;
      unset($_SESSION['pending_reg']);
    } else {
      $otpStep = true;
    }
  }
}

// Conferma OTP: se valido, inviamo i dati a create_account.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_otp'])) {
  $code = trim($_POST['otp_code'] ?? '');
  $pending = $_SESSION['pending_reg'] ?? null;
  $ok = false;
  if ($pending && $code !== '') {
    // Verifica contro DB (prioritario)
    try {
      $stmt = $conn->prepare("SELECT id, code_hash, expires_at, used_at FROM user_otps WHERE email = ? AND purpose = 'registration' ORDER BY id DESC LIMIT 1");
      $stmt->bind_param('s', $pending['email']);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      $stmt->close();
      if ($row) {
        // Controllo se la data di scadenza (expires_at) è maggiore di adesso (time)
        $notExpired = strtotime((string)$row['expires_at']) >= time();
        // E controllo se 'used_at' è vuoto (non l'ha ancora usato)
        $notUsed = empty($row['used_at']);
        if ($notExpired && $notUsed && password_verify($code, (string)$row['code_hash'])) {
          $ok = true;
          // Mark used
          $upd = $conn->prepare("UPDATE user_otps SET used_at = NOW() WHERE id = ?");
          $id = (int)$row['id'];
          $upd->bind_param('i', $id);
          $upd->execute();
          $upd->close();
        }
      }
    } catch (Throwable $e) {
      error_log('OTP DB verify error: ' . $e->getMessage());
    }
    // Fallback alla sessione se il DB non conferma ma i dati sono presenti
    if (!$ok && $code === (string)($pending['otp'] ?? '') && (time() - (int)($pending['ts'] ?? 0)) <= 600) {
      $ok = true;
    }
  }
  if ($ok) {
    // Render form auto-post verso create_account.php con i dati salvati
    echo '<form id="otp-pass" method="POST" action="php/create_account.php">'
       . '<input type="hidden" name="nome" value="' . htmlspecialchars($pending['nome']) . '">'
       . '<input type="hidden" name="cognome" value="' . htmlspecialchars($pending['cognome']) . '">'
       . '<input type="hidden" name="email" value="' . htmlspecialchars($pending['email']) . '">'
       . '<input type="hidden" name="password" value="' . htmlspecialchars($pending['password']) . '">'
       . '<input type="hidden" name="password_confirm" value="' . htmlspecialchars($pending['password_confirm']) . '">'
       . '</form>'
       . '<script>document.getElementById("otp-pass").submit();</script>';
    exit();
  }
  $err = 'otp';
  $otpStep = true;
}

$err = $err ?? ($_GET['error'] ?? '');
$preNome = htmlspecialchars($_GET['nome'] ?? ($_SESSION['pending_reg']['nome'] ?? ''));
$preCognome = htmlspecialchars($_GET['cognome'] ?? ($_SESSION['pending_reg']['cognome'] ?? ''));
$preEmail = htmlspecialchars($_GET['email'] ?? ($_SESSION['pending_reg']['email'] ?? ''));
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Registrati - MikeSullyShop</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>

  <body>
    <?php include 'php/header.php'; ?>
    <div class="container-fluid">
      <div class="row justify-content-center align-items-center py-5">
        <div class="col-sm-12 col-md-9 col-lg-7 col-xl-6 text-center">
          <div class="mss-auth-card p-4 p-md-5">

            <a href="homePage.php" class="d-inline-flex align-items-center gap-1 text-decoration-none mb-4 mss-inline-muted-link">
              <i class="bi bi-arrow-left"></i> Torna allo shop
            </a>

            <img src="assets/img/insegnaNuova.png" class="img-fluid mb-3 mss-image-tall" alt="MikeSullyShop" />
            <h1 class="mb-1 mss-text-heading">Crea Account</h1>
            <p class="mb-4 mss-text-muted" style="font-size:.95rem;">Registrati gratuitamente in pochi secondi</p>

            <?php if ($err && isset($errori[$err])): ?>
              <div class="alert alert-danger py-2 small mb-3 mss-alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $errori[$err] ?>
              </div>
            <?php endif; ?>

            <?php if (!$otpStep): ?>
            <form action="registrationPage.php" method="POST">
              <div class="row g-3 mb-3">
                <div class="col-6 text-start">
                  <label class="form-label fw-semibold mss-text-heading">Nome</label>
                  <input type="text" name="nome" class="form-control mss-input" placeholder="Mario" value="<?= $preNome ?>" required />
                </div>
                <div class="col-6 text-start">
                  <label class="form-label fw-semibold mss-text-heading">Cognome</label>
                  <input type="text" name="cognome" class="form-control mss-input" placeholder="Rossi" value="<?= $preCognome ?>" required />
                </div>
              </div>

              <div class="mb-3 text-start">
                <label class="form-label fw-semibold mss-text-heading">Email</label>
                <div class="input-group">
                  <span class="input-group-text mss-input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" name="email" class="form-control mss-input" placeholder="name@example.com" value="<?= $preEmail ?>" required />
                </div>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-6 text-start">
                  <label class="form-label fw-semibold mss-text-heading">Password</label>
                  <div class="input-group">
                    <span class="input-group-text mss-input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="passwordInputReg" class="form-control mss-input" placeholder="Min. 6 caratteri" required />
                    <button class="btn btn-outline-secondary mss-btn-outline border-start-0" type="button" id="togglePasswordReg" style="border-color: var(--input-border); color: var(--text-muted);">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
                <div class="col-6 text-start">
                  <label class="form-label fw-semibold mss-text-heading">Conferma</label>
                  <div class="input-group">
                    <span class="input-group-text mss-input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="password_confirm" id="passwordConfirmInput" class="form-control mss-input" placeholder="Ripeti" required />
                    <button class="btn btn-outline-secondary mss-btn-outline border-start-0" type="button" id="toggleConfirm" style="border-color: var(--input-border); color: var(--text-muted);">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
              </div>

              <button type="submit" name="request_otp" value="1" class="btn mss-btn-primary w-100 py-2">
                <i class="bi bi-shield-lock me-2"></i>Invia OTP e Prosegui
              </button>
            </form>
            <?php else: ?>
            <form action="registrationPage.php" method="POST">
              <div class="mb-3 text-start">
                <label class="form-label fw-semibold mss-text-heading">Codice OTP inviato a <?= htmlspecialchars($preEmail) ?></label>
                <div class="input-group">
                  <span class="input-group-text mss-input-group-text"><i class="bi bi-key"></i></span>
                  <input type="text" name="otp_code" class="form-control mss-input" placeholder="Es. 123456" pattern="[0-9]{6}" required />
                </div>
                <div class="form-text">Controlla la posta (anche nello spam). Il codice scade dopo 10 minuti.</div>
              </div>
              <div class="d-flex gap-2">
                <button type="submit" name="confirm_otp" value="1" class="btn mss-btn-primary w-100 py-2">
                  <i class="bi bi-person-check me-2"></i>Conferma e Crea Account
                </button>
                <a href="registrationPage.php" class="btn btn-outline-secondary w-100 py-2">Annulla</a>
              </div>
            </form>
            <?php endif; ?>

            <div class="d-flex align-items-center gap-2 my-3">
              <hr class="flex-grow-1 m-0">
              <span class="mss-text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:1px">hai già un account?</span>
              <hr class="flex-grow-1 m-0">
            </div>

            <a href="loginPage.php" class="btn mss-btn-outline w-100 py-2">
              <i class="bi bi-box-arrow-in-right me-2"></i>Accedi
            </a>

          </div>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      function setupToggle(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const inp = document.getElementById(inputId);
        if(btn && inp) {
          btn.addEventListener('click', () => {
            const type = inp.getAttribute('type') === 'password' ? 'text' : 'password';
            inp.setAttribute('type', type);
            btn.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
          });
        }
      }
      setupToggle('togglePasswordReg', 'passwordInputReg');
      setupToggle('toggleConfirm', 'passwordConfirmInput');
    </script>
  </body>
</html>
