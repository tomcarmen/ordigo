# OrdiGO POS – Changelog modifiche recenti

Questa sezione descrive le modifiche applicate per risolvere problemi di stock degli extras e migliorare l’esperienza d’uso nella pagina vendite (`sales.php`).

## UI vendite (v1.6.0)
- Badge prezzi più compatti e sempre su una riga:
  - Resa la riga non avvolgente con `flex-nowrap` e `whitespace-nowrap`.
  - Compattazione badge prezzi: font ridotto (`text-[9px]`), padding (`px-1`), gap interno (`gap-0.5`), margine (`ml-2`).
- Colori dei badge pacchetti basati sulla quantità (`badgeClassForQty`):
  - `10` → indaco; `6` → viola; `>=4` → blu; `>=3` → verde; altrimenti colore primario.
- Riduzione del testo dei badge stock e del testo “+ N singoli” a `text-[9px]` per massima compattezza.
- Modal pagamento (“Dettagli pagamento”): rimosso il breakdown sotto il totale (niente `2x10`, `1x6`, `+ N singoli`).
- Modalità “Dettagli” nel carrello: ripristinata la visualizzazione del breakdown sotto ogni voce (`2x10`, `1x6`, `+ N singoli`) insieme al badge prezzi compatto.

Note tecniche:
- File toccati: `sales.php` (markup Alpine e funzioni di supporto).
- Funzioni interessate: `itemOfferSegments`, `badgeClassForQty` (regole colore aggiornate).
- Nessuna modifica al database.

## UI tabellone e modali spese (v1.5.0)
- Tabellone "In preparazione": formattazione testo migliorata con due spazi fissi dopo i due punti e tra numero comanda e nome cliente; sfondo evidenziato in giallo intenso (`#f9d71c`) e numero comanda in rosso (`#dc2626`) per maggiore visibilità.
- Spese generali: pagina semplificata mostrando solo il bottone "Aggiungi Spesa"; inserimento e modifica ora tramite finestre modali con precompilazione automatica per la modifica.
- Integrazione modali: apertura/chiusura con backdrop-click ed ESC; submit puntano agli handler server-side esistenti (`action=create` / `action=update`).
- Nessuna modifica al protocollo backend: gli endpoint PHP esistenti gestiscono `POST` come prima.

## Migrazione ordini robusta e vincolo payment_method (v1.4.1)
- Migrazione sbloccata e applicata con successo: aggiornato il `CHECK` su `orders.payment_method` con valori italiani (`Contanti`, `Bancomat`, `Satispay`) e mapping dai vecchi valori (`cash`, `card`, `digital`).
- Robustezza SQLite: aumentato `PRAGMA busy_timeout` a `20000`, confermato `PRAGMA journal_mode = WAL`, impostato `PRAGMA synchronous = NORMAL`, aggiunto `wal_checkpoint(FULL)` prima delle transazioni critiche per ridurre i blocchi.
- Gestione cursori: chiusura esplicita dei cursori prima di `BEGIN` nelle migrazioni per evitare lock ricorrenti.
- Script strumenti:
  - `tools/force_orders_migration.php`: ricrea la tabella `orders` con il nuovo vincolo e mappa i valori legacy.
  - `tools/db_check.php`: diagnostica schema e valori distinti dei metodi di pagamento.
  - `tools/test_insert_contanti.php`: test di inserimento con `payment_method = 'Contanti'` e rollback.
- Operativo: aggiunto `.gitignore` per artefatti SQLite (`*.db-wal`, `*.db-shm`, ecc.).
- Verifiche: esecuzione degli script diagnostici e di test con esito positivo.

## Metodi di pagamento obbligatori e gestione duplicati (v1.4.0)
- Metodo di pagamento ora obbligatorio: nessuna preselezione all’apertura del modal.
- Validazione client: messaggio di errore inline sotto i chip di pagamento e pulsante Conferma disabilitato finché non selezionato.
- Validazione server: rifiuto con HTTP 422 se `payment_method` mancante o non valido.
- Numero comanda: gestione duplicati con controllo anticipato lato server e risposta HTTP 409; messaggio inline sotto l’input.
- Altre validazioni confermate: numero comanda numerico e nome cliente obbligatorio.
- UI modernizzata per i metodi di pagamento: chip arrotondati con icone, transizioni e colori coerenti.

Note tecniche:
- `sales.php?ajax=checkout`: aggiunta verifica `payment_method` obbligatorio/valido; controllo duplicato `order_number` prima della transazione.
- Alpine state: `paymentMethod` inizializzato a stringa vuota; aggiunta `paymentMethodError` e feedback inline.
- Payload: rimosso default di fallback sul client; inviato `payment_method` scelto.

## Nuova UX selezione singoli e conferma aggiunta (v1.3.0)
- Introduzione stato `pendingSingles` per selezione quantità singoli temporanea nelle card prodotto.
- Pulsanti `+ / −` aggiornati per modificare solo il contatore pendente senza toccare il carrello.
- Bottone `Aggiungi` ora chiama `confirmAdd(p, $event)`:
  - Se c'è un'offerta selezionata, aggiunge il bundle e resetta `selectedOffer`.
  - Altrimenti aggiunge la quantità pendente di singoli e azzera `pendingSingles`.
- Etichetta `Singolo` nel carrello: mostrata solo per prodotti con offerte stock; non per prodotti con sole aggiunte.
- Disabilitazione del bottone `Aggiungi` finché non c'è selezione offerta o quantità pendente > 0.
- Dettaglio offerta accanto al nome nel carrello (es. `6x 5,00€`).

Note tecniche:
- Aggiunti metodi Alpine: `getPendingSingles`, `setPendingSingles`, `incPendingSingles`, `decPendingSingles`, `confirmAdd`.
- `addToCart` aggiornata per resettare `selectedOffer` solo in caso di bundle.
- UI card prodotto e drawer carrello aggiornati per riflettere le nuove condizioni di rendering.

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
- Autore: Team OrdiGO
## Ordinamento ordini e badge Esaurito (v1.7.0)
- Pagina `orders.php`: aggiunto selettore "Ordina per" con opzioni Numero e Inserimento (data di creazione). La logica JS adesso ordina anche per `created_at` quando selezionato.
- Pagina `sales.php`: rimosso il badge celeste sovrapposto "Esaurito" nelle card prodotto; resta solo l'indicatore rosso basato su `stockLabel(p)` quando la scorta è zero.
- Checkout: normalizzazione del nome cliente in maiuscolo lato server con `mb_strtoupper`.

Note tecniche:
- File toccati: `orders.php`, `sales.php`.
- UI: nuovi chip di ordinamento con `chipClass`; sorting per data basato su `created_at`.
- Server: uppercasing di `customer_name` prima dell'insert.