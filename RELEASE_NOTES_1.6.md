# OrdiGO POS 1.6 – Note sintetiche

Questa release introduce miglioramenti alla pagina vendite per mantenere i badge su una linea e una visualizzazione chiara dei breakdown dove richiesto.

## Novità principali
- Badge prezzi compatti: font ridotto (`text-[9px]`), padding (`px-1`), gap (`gap-0.5`), margine (`ml-2`), e `whitespace-nowrap` per evitare a capo.
- Righe non avvolgenti: uso di `flex-nowrap` nelle sezioni dei badge per impedire wrap indesiderato.
- Colori badge pacchetti in base alla quantità: indaco per `10`, viola per `6`, blu per `>=4`, verde per `>=3`, primario altrimenti.
- Modal pagamento: sotto "Totale" mostrato solo l’importo totale (rimosso breakdown `2x10`, `1x6`, `+ N singoli`).
- Modalità dettagli nel carrello: ripristinati i dettagli `2x10`, `1x6`, `+ N singoli` sotto ogni item.

## Tecnico
- File interessati: `sales.php`.
- Funzioni toccate: `itemOfferSegments`, `badgeClassForQty` (regole colore aggiornate).
- Nessuna migrazione DB o cambiamenti lato server.

## Link
- Changelog dettagliato: vedi `CHANGELOG.md` sezione v1.6.0.
- Release: https://github.com/tomcarmen/ordigo/releases/tag/v1.6.0