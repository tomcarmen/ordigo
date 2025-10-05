# Ordigo POS – Changelog modifiche recenti

Questa sezione descrive le modifiche applicate per risolvere problemi di stock degli extras e migliorare l’esperienza d’uso nella pagina vendite (`sales.php`).

## Correzioni stock extras e payload checkout
- Problema: aggiungendo un hamburger con extras (es. due cheddar), lo stock dell’extra non veniva scalato correttamente.
- Causa: gli extras venivano inviati al server senza una quantità propria; il server assumeva la quantità del prodotto o non interpretava correttamente.
- Soluzione lato client:
  - Introduzione gestione quantità extras per variante carrello, poi semplificata a quantità fissa 1 su richiesta.
  - Sincronizzazione immediata degli extras selezionati dentro l’item del carrello per aggiornare subito i totali.
  - Calcolo `cartTotal()` e `unitTotal()` aggiornati per includere il prezzo degli extras con quantità corretta (ora fissa a 1).
  - Payload di checkout: ogni extra invia `quantity = 1 * item.quantity` per scalare correttamente lo stock lato server.
- Soluzione lato server (già presente, verificata):
  - Endpoint `sales.php?ajax=checkout` calcola totali con `extrasTotal`, verifica lo stock degli extras e lo scala in base alla `quantity` ricevuta.

## Aggiornamenti UI
- Rimosso ritardo nell’aggiornamento dei totali: sincronizzazione immediata degli extras nel carrello quando si selezionano/deselezionano.
- Spostati e poi rimossi i selettori di quantità degli extras:
  - Inizialmente aggiunti accanto al bottone “Aggiungi” per controlli rapidi.
  - Su richiesta, rimossi del tutto e fissata quantità extras a 1.
- Lista extras prodotto:
  - Rimosse le icone “+ / −” accanto al nome dell’extra; ora restano checkbox, nome, prezzo e disponibilità.
- Drawer carrello:
  - Rimosso il subtotale “Aggiunte”; restano solo le righe dei singoli extras (es. “CHEDDAR”) con il loro prezzo.
  - Nelle righe extras non si mostra più “× quantità”, perché gli extras hanno quantità fissa 1.

## Funzioni e metodi toccati in `sales.php`
- UI rendering extras nella griglia prodotto: rimozione controlli quantità nella lista.
- Sezione accanto al bottone “Aggiungi”: rimossa la visualizzazione dei selettori extras.
- Drawer carrello: rimozione subtotale “Aggiunte” e visualizzazione pulita delle righe extras.
- Metodi Alpine:
  - `getExtraQty(pid, exId)`: ora restituisce sempre 1.
  - `incExtraQty`, `decExtraQty`: resi no-op con quantità fissa.
  - `toggleExtra(pid, ex)`: imposta sempre `quantity: 1` e sincronizza nel carrello.
  - `addToCart(p, delta)`: mantiene `extrasList` con `quantity: 1` e sincronizza varianti.
  - `cartTotal()`, `unitTotal(p)`: calcolano gli extras con quantità 1.
  - `syncSelectedExtrasIntoCart(pid)`: mantiene allineati gli extras dell’item se la variante coincide.
- Checkout client: payload costruito con extras `quantity: 1 * item.quantity`.
- Checkout server: verifica stock e scala quantità extras come da payload.

## Note operative
- Impatto database: nessuna migrazione necessaria; si usa la tabella `product_extras` esistente.
- Regressioni attese: nessuna. La rimozione dei selettori quantità è intenzionale e coerente con la richiesta.
- Test manuali consigliati:
  1. Aggiungere un prodotto con uno o più extras selezionati; verificare aggiornamento immediato dei totali.
  2. Confermare l’ordine e verificare che lo stock dell’extra diminuisca di 1 per ogni unità di prodotto.
  3. Verificare che nel carrello non compaia la voce “Aggiunte” e che le righe extras mostrino correttamente nome e prezzo.

## Versione
- Data modifica: 2025-10-06
- Autore: Team Ordigo