<?php
require_once 'php/db_connection.php';

if (!isset($_SESSION['utente_id'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['utente_id'])) {
        header('Location: loginPage.php');
        exit();
    }
}

$idUtenteLoggato = (int)$_SESSION['utente_id'];
$istruzione = $conn->prepare("SELECT nome, cognome, email FROM users WHERE id = ? LIMIT 1");
$istruzione->bind_param("i", $idUtenteLoggato);
$istruzione->execute();
$datiUtente = $istruzione->get_result()->fetch_assoc();
$istruzione->close();

if (!$datiUtente) {
    session_destroy();
    header('Location: loginPage.php');
    exit();
}

if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_profile_update']);
    header('Location: profilo.php');
    exit();
}

$messaggioSuccesso = $_GET['success'] ?? '';
$messaggioErrore = $_GET['error'] ?? '';

// Controllo furbo: se l'utente ha chiesto di cambiare la password, gli mostro solo il form per l'OTP
$siamoInFaseOtp = isset($_GET['otp_step']) && isset($_SESSION['pending_profile_update']);
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Il mio Profilo - MikeSullyShop</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="mss-page">

    <?php include 'php/header.php'; ?>

    <div class="container my-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-7">

          <div class="mss-auth-card p-4 p-md-5">
            <div class="d-flex align-items-center mb-4 gap-3">
              <div class="mss-avatar fs-4">
                <i class="bi bi-person-fill"></i>
              </div>
              <div>
                <h2 class="font-monospace fw-bold mb-0"><?= htmlspecialchars($datiUtente['nome'] . ' ' . $datiUtente['cognome']) ?></h2>
                <span class="badge bg-<?= $_SESSION['ruolo'] === 'admin' ? 'danger' : 'success' ?>"><?= $_SESSION['ruolo'] === 'admin' ? 'Amministratore' : 'Cliente' ?></span>
              </div>
            </div>

            <?php if ($messaggioSuccesso === 'ok'): ?>
              <div class="alert alert-success py-2 small alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i> Profilo aggiornato con successo.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>
            <?php if ($messaggioErrore === 'email'): ?>
              <div class="alert alert-danger py-2 small alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Email già in uso da un altro account.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php elseif ($messaggioErrore === 'password'): ?>
              <div class="alert alert-danger py-2 small alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Le password non coincidono oppure la password è troppo corta (min 6 caratteri).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php elseif ($messaggioErrore === 'same_password'): ?>
              <div class="alert alert-danger py-2 small alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> La nuova password non può essere uguale a quella attuale.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php elseif ($messaggioErrore === 'otp'): ?>
              <div class="alert alert-danger py-2 small alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Codice OTP non valido o scaduto.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php elseif ($messaggioErrore === 'mail_failed'): ?>
              <div class="alert alert-danger py-2 small alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Impossibile inviare l'email con l'OTP. Verifica la connessione.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>

            <?php if ($siamoInFaseOtp): ?>
              <form action="php/update_profile.php" method="POST">
                <div class="mb-3 text-start">
                  <label class="form-label fw-bold">Codice OTP di verifica</label>
                  <div class="input-group">
                    <span class="input-group-text mss-input-group-text"><i class="bi bi-key"></i></span>
                    <input type="text" name="otp_code" class="form-control mss-input" placeholder="Es. 123456" pattern="[0-9]{6}" required />
                  </div>
                  <div class="form-text">Ti abbiamo inviato un'email con un codice per autorizzare il cambio password.</div>
                </div>
                <div class="d-flex gap-2">
                  <button type="submit" name="confirm_otp" value="1" class="btn mss-btn-primary w-100 py-2">
                    <i class="bi bi-check-circle me-2"></i>Conferma e Salva
                  </button>
                  <a href="profilo.php?cancel_otp=1" class="btn btn-outline-secondary w-100 py-2">Annulla</a>
                </div>
              </form>
            <?php else: ?>
              <form action="php/update_profile.php" method="POST">
              <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                  <label class="form-label fw-bold">Nome</label>
                  <input type="text" name="nome" class="form-control mss-input" value="<?= htmlspecialchars($datiUtente['nome']) ?>" required>
                </div>
                <div class="col-12 col-sm-6">
                  <label class="form-label fw-bold">Cognome</label>
                  <input type="text" name="cognome" class="form-control mss-input" value="<?= htmlspecialchars($datiUtente['cognome']) ?>" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-bold">Email</label>
                <div class="input-group">
                  <span class="input-group-text mss-input-group-text">@</span>
                  <input type="email" name="email" class="form-control mss-input" value="<?= htmlspecialchars($datiUtente['email']) ?>" required>
                </div>
              </div>

              <hr class="my-4">
              <p class="small text-muted mb-2">Lascia vuoti i campi password se non vuoi cambiarla.</p>

              <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6">
                  <label class="form-label fw-bold">Nuova Password</label>
                  <div class="input-group">
                    <input type="password" name="password" id="pass1" class="form-control mss-input" placeholder="Min. 6 caratteri">
                    <button class="btn btn-outline-secondary mss-btn-outline toggle-password" type="button" data-target="pass1">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
                <div class="col-12 col-sm-6">
                  <label class="form-label fw-bold">Conferma Password</label>
                  <div class="input-group">
                    <input type="password" name="password_confirm" id="pass2" class="form-control mss-input" placeholder="Ripeti la password">
                    <button class="btn btn-outline-secondary mss-btn-outline toggle-password" type="button" data-target="pass2">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
              </div>

              <button type="submit" class="btn mss-btn-primary w-100 fw-bold py-2">
                <i class="bi bi-floppy me-2"></i>Salva Modifiche
              </button>
            </form>
            <?php endif; ?>

            <hr class="my-4">

            <a href="ordini.php" class="btn mss-btn-outline w-100 fw-bold mb-3">
              <i class="bi bi-receipt me-2"></i>I miei ordini
            </a>

            <a href="php/logout.php" class="btn btn-outline-danger w-100 fw-bold">
              <i class="bi bi-box-arrow-right me-2"></i>Disconnetti
            </a>
          </div>

        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const input = document.getElementById(targetId);
          const icon = this.querySelector('i');
          if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
          } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
          }
        });
      });
    </script>
  </body>
</html>
