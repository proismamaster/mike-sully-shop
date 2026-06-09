(() => {
  // L'indirizzo base per salvare i dati della wishlist
  const INDIRIZZO_SERVER = 'php/wishlist_handler.php';

  // Parla con il server per aggiungere o rimuovere l'elemento
  async function inviaRichiestaWishlist(modulo) {
    const datiDaInviare = new FormData(modulo);
    datiDaInviare.set('ajax', '1'); // Segnale di chiamata in background

    // Decidiamo a chi mandare i dati (all'action del form, o a quello di default)
    const indirizzoDiDestinazione = modulo.action || INDIRIZZO_SERVER;

    const risposta = await fetch(indirizzoDiDestinazione, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: datiDaInviare
    });

    let dati;
    try {
      dati = await risposta.json();
    } catch (errore) {
      dati = {}; // In caso di errore di lettura, non blocchiamo il codice
    }

    if (risposta.ok === false || dati.ok === false) {
      throw new Error(dati.message || 'Operazione non riuscita.');
    }
    
    return dati;
  }

  // Aggiorna il numerino (badge) dei preferiti in giro per il sito
  function aggiornaContatoriWishlist(numeroOggetti) {
    const tuttiIBadge = document.querySelectorAll('[data-wishlist-badge]');
    
    tuttiIBadge.forEach((badge) => {
      badge.textContent = numeroOggetti;
      
      // Nascondiamo il badge se è 0
      if (numeroOggetti <= 0) {
        badge.classList.add('d-none');
      } else {
        badge.classList.remove('d-none');
      }
    });
  }

  // Modifica l'aspetto del cuoricino (pieno o vuoto) e le scritte in base allo stato
  function aggiornaGraficaPulsante(modulo, eNellaWishlist) {
    // 1. Prepariamo il campo nascosto "azione" per il prossimo click
    const campoAzione = modulo.querySelector('input[name="azione"]');
    if (campoAzione) {
      // Se è già dentro, la prossima azione sarà "rimuovi", e viceversa
      if (eNellaWishlist) {
        campoAzione.value = 'rimuovi';
      } else {
        campoAzione.value = 'aggiungi';
      }
    }

    // 2. Troviamo il bottone
    const bottone = modulo.querySelector('[data-wishlist-button]') || modulo.querySelector('[type="submit"]');
    if (!bottone) return;

    // Aggiungiamo o togliamo la classe 'active' per lo stile CSS
    if (eNellaWishlist) {
      bottone.classList.add('active');
      bottone.setAttribute('aria-pressed', 'true');
      bottone.title = 'Rimuovi dalla wishlist';
    } else {
      bottone.classList.remove('active');
      bottone.setAttribute('aria-pressed', 'false');
      bottone.title = 'Aggiungi alla wishlist';
    }

    // 3. Cambiamo l'icona del cuore (pieno = inserito, vuoto = non inserito)
    const icona = bottone.querySelector('i');
    if (icona) {
      if (eNellaWishlist) {
        icona.className = 'bi bi-heart-fill';
      } else {
        icona.className = 'bi bi-heart';
      }
    }

    // 4. Cambiamo l'eventuale testo descrittivo del bottone
    const etichettaTesto = bottone.querySelector('[data-wishlist-label]');
    if (etichettaTesto) {
      if (eNellaWishlist) {
        etichettaTesto.textContent = 'In Wishlist';
      } else {
        etichettaTesto.textContent = 'Aggiungi alla Wishlist';
      }
    }
  }
  
  document.addEventListener('submit', async (evento) => {
    // Verifichiamo se il modulo inviato è un modulo della wishlist
    const modulo = evento.target.closest('[data-wishlist-form]');
    if (!modulo) return;

    evento.preventDefault(); // Impediamo alla pagina di ricaricarsi
    
    const bottoneInvio = modulo.querySelector('[type="submit"]');

    try {
      // Disabilitiamo temporaneamente il pulsante per evitare doppi click veloci
      if (bottoneInvio) {
        bottoneInvio.disabled = true;
      }

      // Parliamo col server
      const rispostaServer = await inviaRichiestaWishlist(modulo);
      
      // Aggiorniamo il numerino in alto
      aggiornaContatoriWishlist(Number(rispostaServer.wishlist_count || 0));

      // Se ci troviamo proprio dentro la pagina dei preferiti e l'abbiamo rimosso...
      // ...allora facciamo svanire l'intera riga del prodotto dallo schermo!
      if (rispostaServer.remove_item && modulo.closest('[data-wishlist-item]')) {
        modulo.closest('[data-wishlist-item]').remove();
      } else {
        // Altrimenti, cambiamo semplicemente colore e scritte al cuoricino
        aggiornaGraficaPulsante(modulo, !!rispostaServer.in_wishlist);
      }

      // Gestione del messaggio "La tua lista è vuota"
      const bloccoStatoVuoto = document.querySelector('[data-wishlist-empty]');
      if (bloccoStatoVuoto) {
        // Contiamo se ci sono ancora prodotti visibili
        const oggettiRimasti = document.querySelectorAll('[data-wishlist-item]').length;
        
        if (oggettiRimasti === 0) {
          // Se non ne è rimasto nessuno, mostriamo il messaggio di lista vuota
          bloccoStatoVuoto.classList.remove('d-none');
        } else {
          // Altrimenti lo nascondiamo
          bloccoStatoVuoto.classList.add('d-none');
        }
      }

    } catch (errore) {
      alert(errore.message || 'Impossibile aggiornare la wishlist.');
    } finally {
      // Alla fine di tutto, riattiviamo il pulsante
      if (bottoneInvio) {
        bottoneInvio.disabled = false;
      }
    }
  });
})();