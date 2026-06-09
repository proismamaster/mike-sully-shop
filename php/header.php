<?php
// Controlliamo se la sessione (la memoria temporanea del server) è già accesa.
// Se non lo è, la accendiamo per poter leggere il carrello e l'utente loggato.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Scopriamo come si chiama la pagina in cui ci troviamo adesso 
// Questo ci servirà per colorare di blu l'icona giusta nel menu!
$paginaAttuale = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark mss-navbar fixed-top">
  <div class="container">
    <a class="navbar-brand mss-brand font-monospace" href="homePage.php">MikeSullyShop</a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="navbar-nav ms-auto flex-row align-items-center">
        
        <?php 
        // Se l'utente è loggato ed è un capo (admin), mostriamo queste tre icone extra.
        if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin'): 
        ?>
          <a href="adminMagazzino.php" class="nav-link px-3 <?= $paginaAttuale === 'adminMagazzino.php' ? 'text-primary' : 'text-dark' ?> mss-nav-link" title="Magazzino">
            <i class="bi bi-boxes fs-4"></i>
          </a>
          <a href="adminOrdini.php" class="nav-link px-3 <?= $paginaAttuale === 'adminOrdini.php' ? 'text-primary' : 'text-dark' ?> mss-nav-link" title="Ordini">
            <i class="bi bi-truck fs-4"></i>
          </a>
          <a href="adminVendite.php" class="nav-link px-3 <?= $paginaAttuale === 'adminVendite.php' ? 'text-primary' : 'text-dark' ?> mss-nav-link" title="Vendite">
            <i class="bi bi-graph-up-arrow fs-4"></i>
          </a>
        <?php endif; ?>
        
        
        <a href="homePage.php" class="nav-link px-3 <?= $paginaAttuale === 'homePage.php' ? 'text-primary' : 'text-dark' ?> mss-nav-link" title="Home">
          <i class="bi bi-house fs-4"></i>
        </a>

        <a href="cart.php" class="nav-link px-3 <?= $paginaAttuale === 'cart.php' ? 'text-primary' : 'text-dark' ?> mss-nav-link position-relative" title="Carrello">
          <i class="bi bi-cart fs-4"></i>
          
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger mss-badge-tiny<?= empty($_SESSION['carrello']) ? ' d-none' : '' ?>" data-cart-badge>
            <?php 
              // Se il carrello NON è vuoto, sommiamo tutte le quantità dei singoli prodotti (la colonna 'qta')
              // Se è vuoto scriviamo 0.
              echo !empty($_SESSION['carrello']) ? array_sum(array_column($_SESSION['carrello'], 'qta')) : 0; 
            ?>
          </span>
        </a>

        <a href="wishlist.php" class="nav-link px-3 <?= $paginaAttuale === 'wishlist.php' ? 'text-primary' : 'text-dark' ?> mss-nav-link position-relative" title="Wishlist">
          <i class="bi bi-heart fs-4"></i>
          
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger mss-badge-tiny<?= empty($_SESSION['wishlist']) ? ' d-none' : '' ?>" data-wishlist-badge>
            <?php 
              // Se ci sono preferiti salvati, contiamo quanti sono (count). Altrimenti 0.
              echo !empty($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0; 
            ?>
          </span>
        </a>

        <?php 
          // Se è già loggato mandiamolo al Profilo, altrimenti mandiamolo alla pagina di Login
          $linkUtente = isset($_SESSION['utente_id']) ? 'profilo.php' : 'loginPage.php';
          // Se ci troviamo in una di queste 3 pagine relative all'account, coloriamo l'icona di blu
          $pagineAccount = ['profilo.php', 'loginPage.php', 'registrationPage.php'];
          $coloreIconaUtente = in_array($paginaAttuale, $pagineAccount) ? 'text-primary' : 'text-dark';
        ?>
        <a href="<?= $linkUtente ?>" class="nav-link px-3 <?= $coloreIconaUtente ?> mss-nav-link" title="Profilo">
          <i class="bi bi-person-circle fs-4"></i>
        </a>

        <?php 
        // Mostriamo il pulsante di Logout solo se l'utente è effettivamente connesso
        if (isset($_SESSION['utente_id'])): 
        ?>
          <a href="php/logout.php" class="nav-link px-3 text-dark mss-nav-link" title="Logout">
            <i class="bi bi-box-arrow-right fs-4"></i>
          </a>
        <?php endif; ?>

      </div>
    </div>
  </div>
</nav>

<div class="mss-page-spacer-lg"></div>