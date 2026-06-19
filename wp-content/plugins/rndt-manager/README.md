# RNDT Manager - Plugin WordPress

Editor e validatore di metadati secondo il profilo italiano **RNDT 2020** (INSPIRE TG v2.0.1, ISO 19115/19139).

## Caratteristiche

- **Wizard multi-step** per la creazione di metadati
- **4 tipi di risorsa**: Dataset, Serie, Servizi OGC, Applicazioni
- **Doppia interfaccia**: wp-admin e frontend (shortcode)
- **Validazione RNDT 2020** con regole INSPIRE
- **Esportazione XML** ISO 19139 conforme (anteprima e download)
- **Import XML** da file o catalogo CSW
- **Pubblicazione su cataloghi CSW** (pyCSW, GeoServer CSW, GeoNetwork)
- **Integrazione GeoServer** per layer WMS/WFS via REST API
- **Controllo accessi** basato su ruoli WordPress

---

## Requisiti

### Server WordPress
- WordPress 5.8+
- PHP 7.4+
- Estensioni PHP richieste:
  - `pdo_pgsql` (per PostgreSQL)
  - `dom` (per XML)
  - `libxml`

### Database
- PostgreSQL 12+ (database separato da WordPress)

### Solo per compilazione (NON richiesto sul server)
- Node.js 16+
- npm 8+

---

## Installazione

### Metodo 1: Installazione da release pre-compilata (consigliato)

Se scarichi una release già compilata:

1. Scarica il file ZIP della release
2. Estrai in `wp-content/plugins/rndt-manager/`
3. Attiva il plugin da WordPress Admin → Plugin
4. Vai su RNDT Manager → Impostazioni per configurare

### Metodo 2: Installazione da sorgenti (per sviluppatori)

Se cloni il repository o scarichi i sorgenti:

```bash
# 1. Clona o copia la cartella del plugin
cd wp-content/plugins/
git clone https://github.com/your-repo/rndt-manager.git

# 2. Entra nella cartella
cd rndt-manager

# 3. Installa le dipendenze Node.js (solo in locale)
npm install

# 4. Compila gli asset React
npm run build

# 5. Attiva il plugin da WordPress Admin
```

**IMPORTANTE**: I passaggi 3 e 4 (`npm install` e `npm run build`) vanno eseguiti:
- Sul tuo PC di sviluppo, OPPURE
- Sul server PRIMA di attivare il plugin

Una volta compilato, la cartella `build/` conterrà i file JS/CSS pronti.

---

## Cosa fa `npm run build`?

Trasforma il codice React in file JavaScript standard che il browser può eseguire:

```
PRIMA della compilazione:          DOPO la compilazione:
───────────────────────────────────────────────────────
assets/src/index.js          →     build/index.js
assets/src/components/*.js   →     (incluso nel bundle)
assets/src/styles/main.scss  →     build/index.css
```

Il server WordPress carica solo i file dalla cartella `build/`.

---

## Configurazione

### 1. Database PostgreSQL

Crea un database PostgreSQL dedicato:

```sql
CREATE DATABASE rndt_metadata;
CREATE USER rndt_user WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE rndt_metadata TO rndt_user;
```

### 2. Impostazioni Plugin

Vai su **RNDT Manager → Impostazioni** e configura:

#### Sezione Database PostgreSQL
| Campo | Descrizione | Default |
|-------|-------------|---------|
| Host | Indirizzo server PostgreSQL | localhost |
| Porta | Porta PostgreSQL | 5432 |
| Nome database | Nome del database | rndt_metadata |
| Schema | Schema PostgreSQL | public |
| Utente | Username database | - |
| Password | Password database | - |

**Procedura:**
1. Compila i campi e clicca **Salva modifiche**
2. Clicca **Test connessione** per verificare la connessione
3. Clicca **Crea tabelle** per creare lo schema del database

**Nota**: L'utente PostgreSQL deve avere i privilegi `CREATE` e `USAGE` sullo schema.

#### Sezione Generale
| Campo | Descrizione |
|-------|-------------|
| Lingua predefinita | Lingua dei metadati (ita/eng) |
| Codice IPA | Codice ente nel registro IndicePA |
| Organizzazione | Nome organizzazione predefinito |
| Genera UUID | Generazione automatica identificativi |

#### Sezione Catalogo CSW (opzionale)

Configura la pubblicazione dei metadati su un catalogo CSW (Catalogue Service for the Web).

| Campo | Descrizione |
|-------|-------------|
| Abilita | Attiva pubblicazione CSW |
| Tipo catalogo | pyCSW / GeoServer CSW / GeoNetwork / Altro |
| URL | Endpoint CSW-T del catalogo |
| Autenticazione | None / HTTP Basic / Bearer Token |

**URL tipici per tipo catalogo:**
- **pyCSW**: `https://server/pycsw/csw`
- **GeoServer CSW**: `https://server/geoserver/csw`
- **GeoNetwork**: `https://server/geonetwork/srv/ita/csw`

**Nota**: GeoServer CSW Extension e pyCSW sono entrambi compatibili con il protocollo CSW-T (Transactional). GeoServer può essere usato sia per i metadati (CSW) che per i dati (WMS/WFS).

#### Sezione GeoServer per dati (opzionale)

Configura GeoServer per la pubblicazione di layer WMS/WFS (servizi dati).

| Campo | Descrizione |
|-------|-------------|
| Abilita | Attiva integrazione GeoServer |
| URL | URL REST API GeoServer (es: https://server/geoserver) |
| Username/Password | Credenziali admin |
| Workspace | Workspace predefinito per i layer |

**Nota**: Questa sezione è separata dal catalogo CSW. Se usi GeoServer sia per metadati CSW che per dati WMS/WFS, configura entrambe le sezioni puntando allo stesso server.

---

## Utilizzo

### Interfaccia Admin (wp-admin)

1. Vai su **RNDT Manager → Aggiungi nuovo**
2. Segui il wizard multi-step

### Interfaccia Frontend (shortcode)

Per utilizzare il plugin dal frontend del sito (non wp-admin):

1. Crea una pagina WordPress
2. Inserisci lo shortcode `[rndt_manager]`
3. Pubblica la pagina

**Shortcode disponibili:**

| Shortcode | Descrizione |
|-----------|-------------|
| `[rndt_manager]` | Interfaccia completa (lista + editor) per utenti autorizzati |
| `[rndt_catalog]` | Catalogo pubblico di sola lettura |

**Parametri `[rndt_catalog]`:**
- `limit="10"` - Numero massimo di record
- `category="environment"` - Filtra per categoria ISO
- `theme="el"` - Filtra per tema INSPIRE

**Controllo accessi:**
- L'accesso a `[rndt_manager]` richiede login e capability `manage_rndt_metadata`
- Il ruolo **RNDT Editor** ha accesso completo all'editor
- Gli amministratori hanno accesso completo + pubblicazione CSW

---

### Creare un nuovo metadato

1. Vai su **RNDT Manager → Aggiungi nuovo** (admin) oppure accedi alla pagina con shortcode
2. Seleziona il tipo di risorsa (Dataset, Serie, Servizio, Applicazione)
3. Compila i campi del wizard step-by-step:
   - **Identificazione**: Titolo, abstract, identificativo, lingua
   - **Classificazione**: Temi INSPIRE, categorie ISO, parole chiave
   - **Estensione geografica**: Bounding box con mappa interattiva
   - **Riferimento temporale**: Date di creazione/pubblicazione/revisione
   - **Qualità**: Lineage, conformità INSPIRE, risoluzione
   - **Vincoli**: Limitazioni accesso/uso, licenze
   - **Distribuzione**: Formati, risorse online (WMS, WFS, download)
   - **Parte responsabile**: Contatti, ruoli
   - **Sistema di riferimento**: Codice EPSG
   - **Info metadato**: Identificativo file, lingua, data
   - **Dettagli servizio**: (solo per servizi) Tipo, operazioni, risorse accoppiate

4. Clicca **Salva** per salvare la bozza
5. Clicca **Valida** per verificare la conformità RNDT 2020
6. Clicca **Pubblica su CSW** per pubblicare sul catalogo

### Importare metadati esistenti

1. Vai su **RNDT Manager → Importa**
2. Scegli il metodo:
   - **Upload file XML**: Trascina file ISO 19139
   - **Importa da CSW**: Cerca in un catalogo remoto

### Esportare metadati

- Dalla lista metadati: usa le azioni in blocco "Esporta XML"
- Dal singolo metadato: pulsante "Esporta XML" nel wizard

---

## REST API

Il plugin espone endpoint REST sotto `/wp-json/rndt/v1/`:

| Endpoint | Metodo | Descrizione |
|----------|--------|-------------|
| `/metadata` | GET | Lista metadati |
| `/metadata` | POST | Crea metadato |
| `/metadata/{id}` | GET | Dettaglio metadato |
| `/metadata/{id}` | PUT | Aggiorna metadato |
| `/metadata/{id}` | DELETE | Elimina metadato |
| `/validate/{id}` | POST | Valida metadato |
| `/export/{id}/xml` | GET | Esporta XML |
| `/import/xml` | POST | Importa XML |
| `/publish/{id}/csw` | POST | Pubblica su CSW |
| `/codelists/{type}` | GET | Ottieni codelist |

---

## Struttura Database PostgreSQL

Il plugin crea automaticamente le seguenti tabelle:

| Tabella | Contenuto |
|---------|-----------|
| `rndt_metadata_fields` | Campi scalari del metadato |
| `rndt_keywords` | Parole chiave con thesaurus |
| `rndt_responsible_parties` | Contatti e responsabili |
| `rndt_online_resources` | URL risorse online |
| `rndt_distribution_formats` | Formati distribuzione |
| `rndt_conformity` | Dichiarazioni conformità |
| `rndt_service_operations` | Operazioni servizio (ISO 19119) |
| `rndt_coupled_resources` | Risorse accoppiate servizio-dataset |

---

## Sviluppo

### Comandi disponibili

```bash
# Modalità sviluppo (watch con hot reload)
npm start

# Build produzione
npm run build

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css

# Formatta codice
npm run format
```

### Struttura cartelle

```
rndt-manager/
├── rndt-manager.php          # Entry point plugin
├── webpack.config.js         # Configurazione build
├── package.json              # Dipendenze npm
├── includes/                 # Classi PHP backend
│   ├── api/                  # REST API controllers
│   ├── metadata/             # Modello e repository
│   ├── xml/                  # Generatore/parser XML
│   ├── validation/           # Regole validazione
│   ├── connectors/           # Client pyCSW/GeoServer
│   └── codelists/            # Dati codelist
├── admin/                    # Area amministrazione
├── public/                   # Area pubblica (shortcode, CSS)
├── assets/src/               # Sorgenti React (da compilare)
│   ├── index.js              # Entry point React
│   ├── components/           # Componenti wizard
│   └── styles/               # SCSS
├── build/                    # File compilati (generati da npm run build)
│   ├── index.js              # Bundle JavaScript
│   └── index.css             # Stili compilati
└── languages/                # Traduzioni
```

---

## Troubleshooting

### Il wizard non si carica

1. Verifica che `build/index.js` e `build/index.css` esistano
2. Se non esistono, esegui `npm install && npm run build`
3. Verifica errori nella console browser (F12)
4. Assicurati che `webpack.config.js` esista nella root del plugin

### Errore connessione PostgreSQL

1. Verifica che l'estensione `pdo_pgsql` sia attiva:
   ```php
   <?php phpinfo(); // cerca pdo_pgsql
   ```
2. Verifica credenziali e accessibilità del server PostgreSQL
3. Assicurati che il database esista

### Errore validazione XSD

Il plugin include validazione XSD opzionale. Se mancano gli schemi:
1. Gli schemi verranno scaricati automaticamente al primo utilizzo
2. Oppure disabilita "Validazione XSD" nelle impostazioni

---

## Licenza

GPL-2.0-or-later

---

## Crediti

- Profilo RNDT 2020: [Repertorio Nazionale Dati Territoriali](https://geodati.gov.it/)
- INSPIRE Technical Guidelines: [INSPIRE Knowledge Base](https://inspire.ec.europa.eu/)
- ISO 19115/19139: [ISO TC211](https://www.iso.org/committee/54904.html)
