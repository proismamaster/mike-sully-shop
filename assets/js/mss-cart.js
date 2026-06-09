(() => {
  // L'indirizzo a cui mandiamo le richieste per il carrello
  const INDIRIZZO_SERVER = 'php/cart_handler.php';


  // Questa funzione si occupa di parlare con il server in background (AJAX)
  async function richiediDatiAlServer(formData) {
    // Aggiungiamo un segnale per dire al server che questa è una chiamata in background
    formData.set('ajax', '1'); 

    // Facciamo la chiamata al server
    const risposta = await fetch(INDIRIZZO_SERVER, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    });

    let dati;
    try {
      // Proviamo a leggere la risposta come JSON (formato dati standard)
      dati = await risposta.json();
    } catch (errore) {
      // Se il server risponde con un formato non valido, creiamo un oggetto vuoto per non far bloccare tutto
      dati = {}; 
    }

    // Se c'è un errore di connessione o il server ci dice che l'operazione è fallita
    if (risposta.ok === false || dati.ok === false) {
      throw new Error(dati.message || 'Operazione non riuscita.');
    }
    return dati;
  }

  // Crea un contenitore invisibile per i messaggini di notifica (toast) se non esiste già
  function creaContenitoreNotifiche() {
    let contenitore = document.getElementById('mss-toast-container');
    
    // Se non lo trova, lo crea da zero e lo attacca in fondo alla pagina
    if (!contenitore) {
      contenitore = document.createElement('div');
      contenitore.id = 'mss-toast-container';
      contenitore.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      contenitore.style.zIndex = '1100'; // Lo tiene in primo piano
      document.body.appendChild(contenitore);
    }
    return contenitore;
  }

  // Mostra un messaggino a comparsa (toast) sullo schermo
  function mostraNotifica(messaggio, tipoNotifica = 'info') {
    const contenitore = creaContenitoreNotifiche();
    const elementoNotifica = document.createElement('div');
    
    // Impostiamo il colore in base al tipo (es: rosso per errori, verde per successi)
    elementoNotifica.className = `toast align-items-center text-bg-${tipoNotifica} border-0`;
    elementoNotifica.role = 'alert';
    elementoNotifica.ariaLive = 'assertive';
    elementoNotifica.ariaAtomic = 'true';
    
    // Costruiamo l'interno della notifica con il testo e la X per chiuderla
    elementoNotifica.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${messaggio}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Chiudi"></button>
      </div>
    `;
    contenitore.appendChild(elementoNotifica);

    // Proviamo ad avviare la notifica usando Bootstrap
    try {
      const toastBootstrap = new bootstrap.Toast(elementoNotifica, { delay: 2600 }); // Scompare dopo 2.6 secondi
      toastBootstrap.show();
    } catch (errore) {
      alert(messaggio);
    }
  }

  // Aggiorna i numerini rossi (badge) con le quantità scritte sull'icona del carrello
  function updateCartBadges(numeroOggetti) {
    const tuttiIBadge = document.querySelectorAll('[data-cart-badge]');
    
    tuttiIBadge.forEach((badge) => {
      badge.textContent = numeroOggetti;
      
      // Se il carrello è vuoto (0), nascondiamo il badge, altrimenti lo mostriamo
      if (numeroOggetti <= 0) {
        badge.classList.add('d-none');
      } else {
        badge.classList.remove('d-none');
      }
    });
  }

  // Ricalcola i totali in euro scritti nella pagina del carrello visibile
  function recalculateVisibleCart() {
    const righeProdotti = document.querySelectorAll('[data-prezzo]');
    if (righeProdotti.length === 0) return; // Se non ci sono prodotti, ci fermiamo

    let totaleCarrello = 0;

    // Passiamo in rassegna ogni singolo prodotto nel carrello
    righeProdotti.forEach((riga) => {
      const prezzoSingolo = Number(riga.dataset.prezzo || 0);
      
      // Cerchiamo l'input dove l'utente scrive la quantità
      let campoQuantita = riga.querySelector('[data-cart-qty-input]');
      if (!campoQuantita) {
        campoQuantita = riga.querySelector('.qtyInput'); // Metodo di riserva
      }

      const testoSubtotale = riga.querySelector('.subtotale');
      
      // Assicuriamoci che la quantità sia almeno 1
      let quantita = 1;
      if (campoQuantita && campoQuantita.value) {
        quantita = Number(campoQuantita.value);
      }
      if (quantita < 1) quantita = 1;

      // Calcoliamo il costo di questo prodotto moltiplicato per la sua quantità
      const subtotaleProdotto = prezzoSingolo * quantita;
      totaleCarrello = totaleCarrello + subtotaleProdotto;

      // Se c'è l'elemento HTML per mostrare il subtotale, lo aggiorniamo con i decimali giusti
      if (testoSubtotale) {
        testoSubtotale.textContent = subtotaleProdotto.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
      }
    });

    // Infine, aggiorniamo il totale finale generale in fondo alla pagina
    const elementoTotaleFinale = document.getElementById('totaleCarrello');
    if (elementoTotaleFinale) {
      elementoTotaleFinale.textContent = totaleCarrello.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
    }
  }

  // Genera i bottoni "+" e "-" per cambiare la quantità
  function renderQtyControls(contenitoreWidget, quantitaAttuale) {
    const idProdotto = contenitoreWidget.dataset.productId;
    const visualeALista = (contenitoreWidget.dataset.cartView === 'line');
    
    // Nelle schede normali non mostriamo i bottoni + e -, mostriamo solo "Aggiungi al carrello"
    if (!visualeALista) {
      setWidgetAddState(contenitoreWidget, idProdotto);
      return;
    }

    const quantitaMassima = contenitoreWidget.dataset.maxQty || quantitaAttuale;
    contenitoreWidget.dataset.state = 'qty'; // Cambiamo lo stato interno
    
    // Creiamo la struttura HTML per i bottoni + e -
    contenitoreWidget.innerHTML = `
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center rounded overflow-hidden mss-cart-qty-shell">
          <button type="button" class="mss-qty-btn" data-cart-action="decrease" data-product-id="${idProdotto}">−</button>
          <input type="number" class="form-control form-control-sm text-center border-0 mss-cart-qty-input"
            value="${quantitaAttuale}" min="1" max="${quantitaMassima}" data-cart-qty-input data-product-id="${idProdotto}">
          <button type="button" class="mss-qty-btn" data-cart-action="increase" data-product-id="${idProdotto}">+</button>
        </div>
      </div>
    `;
  }

  // Imposta il pulsante di base "Aggiungi al carrello"
  function setWidgetAddState(contenitoreWidget, idProdotto) {
    contenitoreWidget.dataset.state = 'add';
    contenitoreWidget.innerHTML = `
      <form action="php/cart_handler.php" method="POST" class="js-cart-form w-100" data-cart-form="add">
        <input type="hidden" name="azione" value="aggiungi">
        <input type="hidden" name="id" value="${idProdotto}">
        <input type="hidden" name="qta" value="1">
        <button type="submit" class="btn btn-cart w-100">
          <i class="bi bi-cart-plus me-1"></i> Aggiungi al carrello
        </button>
      </form>
    `;
  }

  // Cambia il bottone in "Vai al carrello" per 5 secondi dopo che l'utente ha aggiunto qualcosa
  function setWidgetTempCartButton(contenitoreWidget) {
    const codiceHtmlOriginale = contenitoreWidget.innerHTML;
    contenitoreWidget.dataset.state = 'temp-cart';
    
    contenitoreWidget.innerHTML = `
      <a href="cart.php" class="btn btn-cart w-100 animate-pulse">
        <i class="bi bi-cart-check me-1"></i> Vai al carrello
      </a>
    `;
    
    // Facciamo partire un timer di 5 secondi (5000 millisecondi)
    setTimeout(() => {
      // Se nel frattempo l'utente ha fatto altre cose e lo stato è cambiato, non facciamo nulla
      if (contenitoreWidget.dataset.state !== 'temp-cart') return;
      
      // Altrimenti, rimettiamo il bottone originale "Aggiungi"
      contenitoreWidget.dataset.state = 'add';
      contenitoreWidget.innerHTML = codiceHtmlOriginale;
    }, 5000);
  }

  // Aggiorna visivamente il numerino dentro l'input della quantità
  function setWidgetQty(contenitoreWidget, nuovaQuantita) {
    if (!contenitoreWidget) return;
    
    if (contenitoreWidget.dataset.cartView === 'line') {
      const campoInput = contenitoreWidget.querySelector('[data-cart-qty-input]');
      if (campoInput) {
        campoInput.value = nuovaQuantita;
      }
    } else {
      renderQtyControls(contenitoreWidget, nuovaQuantita);
    }
  }

  // Invia al server il comando di cambiare la quantità (+ o -)
  async function inviaAggiornamentoQuantita(contenitoreWidget, nuovaQuantita) {
    const datiDaInviare = new FormData();
    datiDaInviare.append('azione', 'aggiorna_qty');
    datiDaInviare.append('id', contenitoreWidget.dataset.productId);
    datiDaInviare.append('qta', String(nuovaQuantita));
    
    const rispostaServer = await richiediDatiAlServer(datiDaInviare);
    
    // Aggiorniamo le grafiche con le risposte del server
    updateCartBadges(Number(rispostaServer.cart_count || 0));
    setWidgetQty(contenitoreWidget, Number(rispostaServer.item_qty || nuovaQuantita));
    recalculateVisibleCart();
    
    return rispostaServer;
  }
  // 1. Quando l'utente preme "Aggiungi al carrello" (invia il form)
  document.addEventListener('submit', async (evento) => {
    const modulo = evento.target.closest('[data-cart-form]');
    if (!modulo) return; // Se non è un form del carrello, ignoriamo

    evento.preventDefault(); // Blocchiamo il caricamento della pagina
    
    const contenitoreWidget = modulo.closest('[data-cart-widget]');
    const bottoneInvio = modulo.querySelector('[type="submit"]');
    
    // Salviamo il testo originale del bottone per rimetterlo dopo
    let testoOriginaleBottone = '';
    if (bottoneInvio) {
      testoOriginaleBottone = bottoneInvio.innerHTML;
      bottoneInvio.disabled = true; // Disabilitiamo il bottone per evitare doppi click
      bottoneInvio.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Attendere...'; // Mettiamo un'icona di caricamento
    }

    try {
      // Inviamo i dati del form al server
      const datiDaInviare = new FormData(modulo);
      const rispostaServer = await richiediDatiAlServer(datiDaInviare);
      
      // Aggiorniamo il numero sul carrello in alto a destra
      updateCartBadges(Number(rispostaServer.cart_count || 0));
      
      // Mostriamo il bottone temporaneo "Vai al carrello"
      if (contenitoreWidget) {
        setWidgetTempCartButton(contenitoreWidget);
      }
      recalculateVisibleCart();

    } catch (errore) {
      mostraNotifica(errore.message || 'Errore durante l\'aggiornamento del carrello.', 'danger');
    } finally {
      // In ogni caso (successo o errore), riattiviamo il bottone
      if (bottoneInvio) {
        bottoneInvio.disabled = false;
        bottoneInvio.innerHTML = testoOriginaleBottone;
      }
    }
  });

  // 2. Quando l'utente clicca i pulsanti "+" o "-" per la quantità
  document.addEventListener('click', async (evento) => {
    const bottoneCliccato = evento.target.closest('[data-cart-action]');
    if (!bottoneCliccato) return;

    const contenitoreWidget = bottoneCliccato.closest('[data-cart-widget]');
    if (!contenitoreWidget) return;

    const campoInput = contenitoreWidget.querySelector('[data-cart-qty-input]');
    const quantitaMassima = Number(contenitoreWidget.dataset.maxQty || 9999);
    
    // Capiamo quant'è la quantità attuale
    let quantitaAttuale = 1;
    if (campoInput && campoInput.value) {
      quantitaAttuale = Number(campoInput.value);
    }
    if (quantitaAttuale < 1) quantitaAttuale = 1;

    let nuovaQuantita = quantitaAttuale;

    // Logica per aumentare o diminuire senza superare i limiti
    if (bottoneCliccato.dataset.cartAction === 'increase') {
      nuovaQuantita = quantitaAttuale + 1;
      if (nuovaQuantita > quantitaMassima) nuovaQuantita = quantitaMassima;
    } else if (bottoneCliccato.dataset.cartAction === 'decrease') {
      nuovaQuantita = quantitaAttuale - 1;
      if (nuovaQuantita < 1) nuovaQuantita = 1;
    }

    // Aggiorniamo subito l'input visivamente per dare feedback immediato all'utente
    if (campoInput) {
      campoInput.value = nuovaQuantita;
    }

    try {
      bottoneCliccato.disabled = true; // Blocchiamo il bottone mentre il server pensa
      await inviaAggiornamentoQuantita(contenitoreWidget, nuovaQuantita);
    } catch (errore) {
      mostraNotifica(errore.message || 'Impossibile aggiornare la quantità.', 'danger');
      // Se va male, torniamo al numero precedente
      if (campoInput) {
        campoInput.value = quantitaAttuale;
      }
    } finally {
      bottoneCliccato.disabled = false; // Riattiviamo il bottone
    }
  });

  // 3. Quando l'utente scrive a mano un numero dentro l'input della quantità
  document.addEventListener('change', async (evento) => {
    const campoInput = evento.target.closest('[data-cart-qty-input]');
    if (!campoInput) return;

    const contenitoreWidget = campoInput.closest('[data-cart-widget]');
    if (!contenitoreWidget) return;

    const quantitaMassima = Number(contenitoreWidget.dataset.maxQty || 9999);
    
    let quantitaScritta = Number(campoInput.value);
    if (isNaN(quantitaScritta) || quantitaScritta < 1) quantitaScritta = 1;
    
    let nuovaQuantita = quantitaScritta;
    if (nuovaQuantita > quantitaMassima) nuovaQuantita = quantitaMassima;

    // Correggiamo l'input se l'utente ha scritto un numero fuori dai limiti
    campoInput.value = nuovaQuantita;

    try {
      await inviaAggiornamentoQuantita(contenitoreWidget, nuovaQuantita);
    } catch (errore) {
      mostraNotifica(errore.message || 'Impossibile aggiornare la quantità.', 'danger');
      // Se fallisce, qui l'utente dovrà ricaricare o riprovare, lasciamo il numero per comodità
    }
  });

  // Rendiamo pubbliche alcune funzioni per permettere ad altri script di usarle.
  window.mssCart = {
    updateCartBadges: updateCartBadges,
    recalculateVisibleCart: recalculateVisibleCart,
    renderQtyControls: renderQtyControls,
    setWidgetAddState: setWidgetAddState,
    setWidgetQty: setWidgetQty
  };
})();