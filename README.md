# OrdiGO - Sistema di Gestione Ordini per Festa Oratorio

OrdiGO è un sistema web completo per la gestione degli ordini durante le feste dell'oratorio. Sviluppato in PHP con database SQLite, offre un'interfaccia moderna e intuitiva per gestire prodotti, categorie, ordini e generare report dettagliati.

## 🚀 Caratteristiche Principali

- **Gestione Prodotti**: Creazione, modifica ed eliminazione di prodotti con controllo scorte
- **Gestione Categorie**: Organizzazione dei prodotti per categorie con colori personalizzati
- **Sistema Ordini**: Gestione completa degli ordini con stati e tracking
- **Report Avanzati**: Statistiche dettagliate su vendite, prodotti più venduti e performance
- **Dashboard Proiettore**: Visualizzazione in tempo reale per schermi esterni
- **Interfaccia Responsive**: Design moderno con Tailwind CSS
- **PWA Ready**: Supporto per Progressive Web App con funzionalità offline

## 🛠️ Tecnologie Utilizzate

- **Backend**: PHP 7.4+
- **Database**: SQLite
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework CSS**: Tailwind CSS
- **Icone**: Font Awesome
- **Grafici**: Chart.js (per i report)

## 📋 Requisiti di Sistema

- PHP 7.4 o superiore
- Estensioni PHP: PDO, SQLite
- Web server (Apache, Nginx, o PHP built-in server)

## 🔧 Installazione

1. **Clona il repository**:
   ```bash
   git clone https://github.com/tuousername/ordigo.git
   cd ordigo
   ```

2. **Configura il web server**:
   - Per sviluppo locale con PHP built-in server:
     ```bash
     php -S localhost:8080
     ```
   - Per Apache/Nginx: configura il document root sulla cartella del progetto

3. **Accedi all'applicazione**:
   - Apri il browser e vai su `http://localhost:8080`
   - Il database SQLite verrà creato automaticamente al primo accesso

## 📁 Struttura del Progetto

```
ordigo/
├── admin/                  # Pannello amministrativo
│   ├── categories.php     # Gestione categorie
│   ├── products.php       # Gestione prodotti
│   ├── reports.php        # Report e statistiche
│   └── (rimosso)          # Dashboard proiettore
├── config/                # Configurazioni
│   └── database.php       # Configurazione database
├── database/              # File database SQLite
├── js/                    # File JavaScript
├── pages/                 # Pagine pubbliche
├── templates/             # Template HTML
│   ├── header.php
│   └── footer.php
├── icons/                 # Icone PWA
├── manifest.json          # Manifest PWA
└── index.php             # Entry point principale
```

## 🎯 Funzionalità

### Area Amministrativa
- **Prodotti**: Gestione completa con nome, descrizione, prezzo, categoria e scorte
- **Categorie**: Creazione categorie con colori personalizzati e icone
- **Report**: Statistiche vendite, prodotti più venduti, analisi per categoria
- **Dashboard**: Panoramica generale con metriche principali

### Dashboard Proiettore
- Visualizzazione ordini in tempo reale
- Design ottimizzato per schermi grandi
- Aggiornamento automatico

### PWA Features
- Installabile come app
- Funzionalità offline
- Notifiche push (in sviluppo)

## 🔒 Sicurezza

- Sanitizzazione input utente
- Prepared statements per query database
- Protezione XSS e SQL injection
- Validazione lato server

## 🤝 Contribuire

1. Fork del progetto
2. Crea un branch per la tua feature (`git checkout -b feature/AmazingFeature`)
3. Commit delle modifiche (`git commit -m 'Add some AmazingFeature'`)
4. Push del branch (`git push origin feature/AmazingFeature`)
5. Apri una Pull Request

## 📝 Licenza

Questo progetto è distribuito sotto licenza MIT. Vedi il file `LICENSE` per maggiori dettagli.

## 📞 Supporto

Per supporto o domande, apri una issue su GitHub o contatta il team di sviluppo.

---

Sviluppato con ❤️ per la comunità oratoriana

## 🧾 Guida rapida POS vendite

Questa sezione descrive il flusso aggiornato di selezione e aggiunta prodotti nella pagina vendite (`sales.php`).

- Pulsanti `+ / −` sulle card prodotto: modificano solo la quantità "pendente" dei singoli articoli senza aggiungerli subito al carrello.
- Bottone `Aggiungi`: conferma l'azione corrente.
  - Se è selezionata un'offerta (bundle), aggiunge il pacchetto al carrello e resetta la selezione dell'offerta.
  - Se non è selezionata un'offerta, aggiunge la quantità pendente di singoli articoli e azzera il contatore pendente.
- Etichetta `Singolo` nel carrello: compare solo per prodotti che hanno offerte stock; non viene mostrata per prodotti senza offerte (es. solo aggiunte/extras).
- Stato disabilitazione: `Aggiungi` è disabilitato finché non c'è una selezione valida (offerta oppure quantità pendente > 0).
- Descrizione offerta in carrello: accanto al nome del prodotto viene visualizzato il dettaglio dell'offerta selezionata (es. `6x 5,00€`).

Suggerimenti operativi:
- Usa `+ / −` per impostare velocemente la quantità singola desiderata senza sporcare il carrello.
- Premi `Aggiungi` per confermare: pacchetto se hai selezionato un'offerta, altrimenti i singoli.
- La selezione offerta si resetta automaticamente solo dopo l'aggiunta di un bundle; i singoli non alterano la selezione delle offerte.
