# Guida Installazione Dettagliata

Questa guida spiega passo-passo come installare RNDT Manager.

---

## Schema del processo

```
┌─────────────────────────────────────────────────────────────────────┐
│                        TUO PC (sviluppo)                            │
│                                                                     │
│  1. Scarica sorgenti                                                │
│  2. npm install        ← scarica React, webpack, etc. (200+ MB)    │
│  3. npm run build      ← compila JS/CSS (pochi secondi)            │
│                                                                     │
│  Risultato: cartella assets/build/ con file compilati              │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │ Copia cartella plugin
                                │ (SENZA node_modules!)
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     SERVER WORDPRESS                                │
│                                                                     │
│  wp-content/plugins/rndt-manager/                                   │
│  ├── rndt-manager.php                                               │
│  ├── includes/                                                      │
│  ├── admin/                                                         │
│  ├── assets/build/      ← file compilati (pochi KB)                │
│  └── ...                                                            │
│                                                                     │
│  NON serve: node_modules/, assets/src/, package.json               │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Passo 1: Prepara l'ambiente di sviluppo

### Requisiti sul tuo PC

1. **Node.js 16+** - Scarica da https://nodejs.org/
   ```bash
   # Verifica installazione
   node --version   # deve essere v16 o superiore
   npm --version    # deve essere v8 o superiore
   ```

2. **Git** (opzionale) - Per clonare il repository

### Scarica i sorgenti

```bash
# Opzione A: Clona con Git
git clone https://github.com/your-repo/rndt-manager.git
cd rndt-manager

# Opzione B: Scarica ZIP e estrai
# Poi entra nella cartella estratta
cd rndt-manager
```

---

## Passo 2: Compila gli asset

```bash
# Installa dipendenze Node.js (solo la prima volta)
npm install

# Questo crea la cartella node_modules/ (~200-300 MB)
# Contiene React, Webpack, Babel, etc.
# NON va copiata sul server!

# Compila per produzione
npm run build

# Questo crea:
# - assets/build/rndt-wizard.js      (~500 KB)
# - assets/build/rndt-wizard.css     (~50 KB)
# - assets/build/rndt-wizard.asset.php
```

### Verifica compilazione

```bash
# Controlla che i file esistano
ls -la assets/build/

# Dovresti vedere:
# rndt-wizard.js
# rndt-wizard.css
# rndt-wizard.asset.php
```

---

## Passo 3: Prepara il pacchetto per il server

### Cosa copiare sul server

```
rndt-manager/
├── rndt-manager.php        ✓ COPIA
├── uninstall.php           ✓ COPIA
├── includes/               ✓ COPIA (tutta la cartella)
├── admin/                  ✓ COPIA (tutta la cartella)
├── public/                 ✓ COPIA (tutta la cartella)
├── assets/build/           ✓ COPIA (solo questa sottocartella)
├── languages/              ✓ COPIA (tutta la cartella)
├── schemas/                ✓ COPIA (se presente)
│
├── assets/src/             ✗ NON COPIARE (sorgenti)
├── node_modules/           ✗ NON COPIARE (dipendenze dev)
├── package.json            ✗ NON COPIARE (non serve in produzione)
├── package-lock.json       ✗ NON COPIARE
├── webpack.config.js       ✗ NON COPIARE
└── docs/                   ✗ NON COPIARE (documentazione)
```

### Script per creare pacchetto pulito

```bash
# Crea cartella temporanea per il deploy
mkdir -p ../rndt-manager-deploy

# Copia solo i file necessari
cp rndt-manager.php ../rndt-manager-deploy/
cp uninstall.php ../rndt-manager-deploy/
cp -r includes ../rndt-manager-deploy/
cp -r admin ../rndt-manager-deploy/
cp -r public ../rndt-manager-deploy/
cp -r languages ../rndt-manager-deploy/
mkdir -p ../rndt-manager-deploy/assets
cp -r assets/build ../rndt-manager-deploy/assets/

# Crea ZIP per upload
cd ..
zip -r rndt-manager.zip rndt-manager-deploy/
```

---

## Passo 4: Installa su WordPress

### Metodo A: Via FTP/SFTP

1. Connettiti al server via FTP/SFTP
2. Vai in `wp-content/plugins/`
3. Carica la cartella `rndt-manager/` (quella pulita, senza node_modules)
4. Vai su WordPress Admin → Plugin
5. Attiva "RNDT Manager"

### Metodo B: Via WordPress Admin

1. Crea il file ZIP (vedi sopra)
2. Vai su WordPress Admin → Plugin → Aggiungi nuovo
3. Clicca "Carica plugin"
4. Seleziona il file ZIP
5. Clicca "Installa ora"
6. Attiva il plugin

### Metodo C: Via WP-CLI

```bash
# Sul server, nella cartella WordPress
wp plugin install /path/to/rndt-manager.zip --activate
```

---

## Passo 5: Configura il database PostgreSQL

### Sul server PostgreSQL

```sql
-- Connettiti come superuser
psql -U postgres

-- Crea database
CREATE DATABASE rndt_metadata
    WITH ENCODING 'UTF8'
    LC_COLLATE = 'it_IT.UTF-8'
    LC_CTYPE = 'it_IT.UTF-8';

-- Crea utente
CREATE USER rndt_user WITH PASSWORD 'password_sicura_qui';

-- Assegna permessi
GRANT ALL PRIVILEGES ON DATABASE rndt_metadata TO rndt_user;

-- Connettiti al database
\c rndt_metadata

-- Assegna permessi sullo schema
GRANT ALL ON SCHEMA public TO rndt_user;
```

### Verifica estensione PHP

```php
<?php
// Crea un file test.php nella root di WordPress
echo extension_loaded('pdo_pgsql') ? 'PDO PostgreSQL OK' : 'PDO PostgreSQL MANCANTE';
```

Se manca, installa l'estensione:

```bash
# Ubuntu/Debian
sudo apt install php-pgsql
sudo systemctl restart apache2

# CentOS/RHEL
sudo yum install php-pgsql
sudo systemctl restart httpd
```

---

## Passo 6: Configura il plugin

1. Vai su **WordPress Admin → RNDT Manager → Impostazioni**

2. **Sezione Database PostgreSQL**:
   - Host: `localhost` (o IP del server PostgreSQL)
   - Porta: `5432`
   - Nome database: `rndt_metadata`
   - Schema: `public`
   - Utente: `rndt_user`
   - Password: `password_sicura_qui`

3. Clicca **Test connessione**

4. Se il test passa, le tabelle vengono create automaticamente

5. Clicca **Salva modifiche**

---

## Verifica installazione

1. Vai su **RNDT Manager → Aggiungi nuovo**
2. Dovresti vedere il wizard con la selezione del tipo di risorsa
3. Seleziona "Dataset" e verifica che si carichino gli step

Se vedi una pagina vuota o errori:
1. Apri la console browser (F12 → Console)
2. Cerca errori JavaScript
3. Verifica che `assets/build/rndt-wizard.js` esista

---

## Aggiornamenti futuri

Quando scarichi una nuova versione:

1. **Se hai modificato solo PHP**: sostituisci i file PHP
2. **Se sono cambiati i sorgenti React**: ricompila con `npm run build`
3. **Se è una release pre-compilata**: sostituisci tutto

```bash
# Per ricompilare dopo aggiornamento sorgenti
cd rndt-manager
git pull
npm install    # solo se package.json è cambiato
npm run build
```

---

## Troubleshooting

### Errore: "Cannot find module..."

```bash
# Rimuovi e reinstalla node_modules
rm -rf node_modules
npm install
npm run build
```

### Errore: "assets/build/rndt-wizard.js not found"

Il plugin non è stato compilato. Esegui:
```bash
npm install
npm run build
```

### Errore: "pdo_pgsql extension not loaded"

Installa l'estensione PHP per PostgreSQL (vedi sopra).

### Le tabelle non vengono create

1. Verifica connessione PostgreSQL
2. Verifica che l'utente abbia permessi CREATE TABLE
3. Controlla i log di WordPress in `wp-content/debug.log`
