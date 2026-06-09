<footer class="mss-footer mt-auto">
  <div class="container text-center text-md-start">
    <div class="row text-center text-md-start">
      
      <div class="col-md-3 col-lg-3 col-xl-3 mx-auto mt-3">
        <h5 class="font-monospace">MikeSullyShop</h5>
        <p>Il tuo portale ufficiale per il merchandise di Monstropolis. Qualità garantita da Mike Wazowski in persona!</p>
      </div>
      
      <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mt-3">
        <h5>Link Utili</h5>
        <p><a href="homePage.php">Home</a></p>
        <p><a href="gdpr.php">Privacy</a></p>
        
        <?php 
        // Se l'utente è un amministratore, mostriamo il link diretto e funzionante al magazzino
        if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin'): 
        ?>
            <p><a href="adminMagazzino.php">Amministrazione</a></p>
        <?php 
        // Se NON è un amministratore (o non è loggato), mostriamo un link con un ID speciale (linkAdminFooter)
        // Questo ID verrà catturato dallo script Javascript qui sotto per bloccarlo.
        else: 
        ?>
            <p><a href="adminMagazzino.php" id="linkAdminFooter">Amministrazione</a></p>
        <?php endif; ?>
      </div>
      
      <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3">
        <h5>Contatti</h5>
        <p><i class="bi bi-house me-3"></i> Via delle Urla 1, Monstropolis</p>
        <p><i class="bi bi-envelope me-3"></i> mikesullyshop@gmail.com</p>
        <p><i class="bi bi-phone me-3"></i> +39 012 3456789</p>
      </div>
    </div>
    
    <hr class="mb-4 opacity-25">
    
    <div class="row align-items-center">
      <div class="col-md-7 col-lg-8">
        <p class="mb-0 opacity-75">&copy; 2026 MikeSullyShop &mdash; Tutti i diritti riservati</p>
      </div>
      <div class="col-md-5 col-lg-4 mt-3 mt-md-0">
        <div class="text-center text-md-end">
          <ul class="list-unstyled list-inline mb-0">
            <li class="list-inline-item">
              <a href="https://www.facebook.com/PixarMonstersInc/" target="_blank" rel="noopener" class="social-link">
                <i class="bi bi-facebook"></i>
              </a>
            </li>
            <li class="list-inline-item">
              <a href="https://www.instagram.com/pixar/" target="_blank" rel="noopener" class="social-link">
                <i class="bi bi-instagram"></i>
              </a>
            </li>
            <li class="list-inline-item">
              <a href="https://t.me/telegram" target="_blank" rel="noopener" class="social-link">
                <i class="bi bi-telegram"></i>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</footer>

<script>
  <?php 
  // Questo pezzetto di codice Javascript viene stampato nella pagina SOLO se chi sta guardando è un "cliente"
  if (isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'cliente'): 
  ?>
  // Troviamo il link "Amministrazione" nel footer
  const linkAmministrazione = document.getElementById('linkAdminFooter');
  
  if (linkAmministrazione) {
    // Se ci clicca sopra
    linkAmministrazione.addEventListener('click', function(evento) {
      // Blocchiamo il comportamento normale del link (andare alla pagina)
      evento.preventDefault();
      
      // Gli mostriamo un bell'avviso
      alert('Area riservata agli Amministratori. Effettua l\'accesso da Admin.');
      
      // Lo rispediamo alla pagina di Login per cambiare account
      window.location.href = 'loginPage.php';
    });
  }
  <?php endif; ?>
</script>