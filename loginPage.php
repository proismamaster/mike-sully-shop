<?php
session_start();
if (isset($_SESSION['utente_id'])) {
    header('Location: homePage.php');
    exit();
}
?>
<!doctype html>
<html lang="it">
  <head>
    <title>Accedi - MikeSullyShop</title>
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
        <div class="col-sm-12 col-md-8 col-lg-6 col-xl-5 text-center">
          <div class="mss-auth-card p-4 p-md-5">

            <a href="homePage.php" class="d-inline-flex align-items-center gap-1 text-decoration-none mb-4 mss-inline-muted-link">
              <i class="bi bi-arrow-left"></i> Torna allo shop
            </a>

            <img src="assets/img/insegnaNuova.png" class="img-fluid mb-3 mss-image-tall" alt="MikeSullyShop" />
            <h1 class="mb-1 mss-text-heading">Bentornato!</h1>
            <p class="mb-4 mss-text-muted" style="font-size:.95rem;">Accedi al tuo account per continuare</p>

            <?php if (isset($_GET['success'])): ?>
              <div class="alert alert-success py-2 small mb-3 mss-alert">
                <i class="bi bi-check-circle-fill me-2"></i> Account creato! Accedi ora.
              </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
              <div class="alert alert-danger py-2 small mb-3 mss-alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Email o password errati.
              </div>
            <?php endif; ?>

            <form action="php/login_action.php" method="POST">
              <div class="mb-3 text-start">
                <label for="mailInput" class="form-label fw-semibold mss-text-heading">Email</label>
                <div class="input-group">
                  <span class="input-group-text mss-input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" name="mail" class="form-control mss-input" placeholder="name@example.com" id="mailInput" required>
                </div>
              </div>

              <div class="mb-4 text-start">
                <label for="passwordInput" class="form-label fw-semibold mss-text-heading">Password</label>
                <div class="input-group">
                  <span class="input-group-text mss-input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" id="passwordInput" class="form-control mss-input" placeholder="La tua password" required>
                  <button class="btn btn-outline-secondary mss-btn-outline border-start-0" type="button" id="togglePasswordLogin" style="border-color: var(--input-border); color: var(--text-muted);">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>

              <button type="submit" class="btn mss-btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Accedi
              </button>
            </form>

            <div class="d-flex align-items-center gap-2 my-3">
              <hr class="flex-grow-1 m-0">
              <span class="mss-text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:1px">oppure</span>
              <hr class="flex-grow-1 m-0">
            </div>

            <a href="registrationPage.php" class="btn mss-btn-outline w-100 py-2">
              <i class="bi bi-person-plus me-2"></i>Crea un nuovo account
            </a>

          </div>
        </div>
      </div>
    </div>
    <?php include 'php/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      const btn = document.getElementById('togglePasswordLogin');
      const inp = document.getElementById('passwordInput');
      if (btn && inp) {
        btn.addEventListener('click', () => {
          const type = inp.getAttribute('type') === 'password' ? 'text' : 'password';
          inp.setAttribute('type', type);
          btn.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
      }
    </script>
  </body>
</html>
