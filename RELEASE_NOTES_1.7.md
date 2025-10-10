# OrdiGO POS 1.7 – Note sintetiche

Questa release introduce il nuovo ordinamento per data di inserimento nella pagina Ordini, rimuove il badge celeste "Esaurito" nella pagina Vendite, e normalizza in maiuscolo il nome cliente al checkout.

## Novità principali
- Ordini: selettore "Ordina per" con Numero o Inserimento (data creazione). Ordinamento lato client per `created_at` quando selezionato.
- Vendite: rimosso l'overlay celeste "Esaurito" nelle card prodotto; resta l'etichetta rossa quando scorta = 0.
- Checkout: `customer_name` salvato in maiuscolo lato server.

## Tecnico
- File interessati: `orders.php`, `sales.php`.
- Funzioni: sorting client aggiornato, `stockLabel(p)` rimane la fonte dell'etichetta rossa.
- Database: nessuna migrazione; inclusione esplicita del file `database/ordigo.db` nel repository.

## Link
- Changelog dettagliato: vedi `CHANGELOG.md` sezione v1.7.0.