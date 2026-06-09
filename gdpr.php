<!doctype html>
<html lang="it">
  <head>
    <title>MikeSullyShop - Privacy Policy</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>

  <body>
    <?php include 'php/header.php'; ?>

    <div class="container my-5">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
          <div class="mss-auth-card p-4 p-md-5">
            <h1 class="fw-bold mb-1" style="color:var(--text-heading)">Privacy Policy</h1>
            <p class="small mb-4" style="color: var(--text-muted);">Ultimo aggiornamento: Gennaio 2026</p>

            <p>
              MikeSullyShop (<em>di seguito "il Sito"</em>) si impegna a proteggere la privacy dei propri users
              nel rispetto del Regolamento UE 2016/679 (GDPR) e del D.Lgs. 196/2003.
            </p>

            <h4 class="fw-bold mt-4">1. Titolare del Trattamento</h4>
            <p>
              Monsters Academy S.r.l. &mdash; Via delle Urla 1, Monstropolis (MO) &mdash;
              <a href="mailto:mikesullyshop@gmail.com">mikesullyshop@gmail.com</a>
            </p>

            <h4 class="fw-bold mt-4">2. Dati Raccolti</h4>
            <p>Raccogliamo i seguenti dati personali:</p>
            <ul>
              <li>Nome e cognome</li>
              <li>Indirizzo email e numero di telefono</li>
              <li>Dati di spedizione (indirizzo, città, CAP, provincia)</li>
              <li>Cronologia degli acquisti e prodotti preferiti (Wishlist)</li>
              <li>Password di accesso (salvate esclusivamente in formato crittografato e irrecuperabile)</li>
              <li>Dati di navigazione (cookies tecnici)</li>
            </ul>

            <h4 class="fw-bold mt-4">3. Finalità del Trattamento</h4>
            <p>I dati sono trattati per:</p>
            <ul>
              <li>Gestione degli ordini e del profilo utente</li>
              <li>Invio di comunicazioni di servizio (es. notifiche di spedizione)</li>
              <li>Miglioramento del servizio (dati aggregati anonimi)</li>
            </ul>

            <h4 class="fw-bold mt-4">4. Comunicazione a Terzi</h4>
            <p>
              I tuoi dati personali non verranno mai venduti a terze parti. I dati di spedizione 
              (nome, indirizzo, telefono) potranno essere condivisi esclusivamente con i corrieri 
              incaricati della consegna fisica dei prodotti ordinati sul Sito.
            </p>

            <h4 class="fw-bold mt-4">5. Misure di Sicurezza</h4>
            <p>
              Il Sito adotta misure di sicurezza avanzate per prevenire accessi non autorizzati. 
              Le password degli utenti sono crittografate nel database tramite algoritmi di Hash sicuri. 
              Inoltre, per operazioni sensibili come il cambio della password, utilizziamo un sistema di verifica a due passaggi 
              tramite codici temporanei (OTP) inviati direttamente all'email dell'interessato.
            </p>

            <h4 class="fw-bold mt-4">6. Base Giuridica</h4>
            <p>
              Il trattamento è basato sul consenso dell'interessato (art. 6.1.a GDPR)
              e sull'esecuzione del contratto di compravendita (art. 6.1.b GDPR).
            </p>

            <h4 class="fw-bold mt-4">7. Conservazione dei Dati</h4>
            <p>
              I dati vengono conservati per il tempo strettamente necessario alle finalità per cui
              sono stati raccolti e, comunque, non oltre 5 anni dalla cessazione del rapporto contrattuale.
            </p>

            <h4 class="fw-bold mt-4">8. Diritti dell'Interessato</h4>
            <p>Puoi esercitare i seguenti diritti scrivendo a <a href="mailto:mikesullyshop@gmail.com">mikesullyshop@gmail.com</a>:</p>
            <ul>
              <li>Accesso ai propri dati (art. 15 GDPR)</li>
              <li>Rettifica (art. 16 GDPR)</li>
              <li>Cancellazione ("diritto all'oblio", art. 17 GDPR)</li>
              <li>Portabilità dei dati (art. 20 GDPR)</li>
              <li>Opposizione al trattamento (art. 21 GDPR)</li>
            </ul>

            <h4 class="fw-bold mt-4">9. Cookie</h4>
            <p>
              Il Sito utilizza esclusivamente cookie tecnici di sessione, strettamente necessari per il
              funzionamento del carrello e dell'autenticazione. Non vengono in alcun modo utilizzati cookie
              di profilazione o di terze parti a scopo pubblicitario.
            </p>

            <div class="mt-5">
              <a href="homePage.php" class="btn mss-btn-outline">
                <i class="bi bi-arrow-left me-2"></i>Torna alla Home
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include 'php/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.querySelectorAll('.fade-up').forEach(el => {
        new IntersectionObserver(([e]) => { if (e.isIntersecting) { e.target.classList.add('visible'); } }, { threshold: 0.1 }).observe(el);
      });
    </script>
  </body>
</html>
