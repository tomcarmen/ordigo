# OrdiGO POS 1.5 – Note sintetiche

Questa release introduce miglioramenti di visibilità nel tabellone e una UX semplificata per la gestione delle spese.

## Novità principali
- Tabellone “In preparazione”:
  - Spaziatura leggibile: due spazi dopo i due punti e tra numero comanda e nome cliente.
  - Evidenziazione: sfondo giallo intenso (`#f9d71c`) e numero comanda in rosso (`#dc2626`).
- Spese generali:
  - Interfaccia semplificata: solo bottone “Aggiungi Spesa”.
  - Modali per inserimento e modifica con precompilazione automatica.

## Tecnico
- Modali collegate agli handler PHP esistenti (`action=create` / `action=update`).
- Nessuna migrazione DB necessaria.

## Link
- Changelog dettagliato: vedi `CHANGELOG.md` sezione v1.5.0.
- Release: https://github.com/tomcarmen/ordigo/releases/tag/v1.5.0