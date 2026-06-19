# Deploy su VPS Ubuntu (Portainer + Nginx Proxy Manager)

Guida passo-passo per portare il sito in produzione su un VPS dove sono già installati **Portainer** e **Nginx Proxy Manager** (NPM).

## Requisiti sulla VPS

- Ubuntu 22.04+ (testato anche 24.04)
- Docker + Docker Compose v2 (entrambi già presenti se hai installato Portainer)
- Portainer in esecuzione
- NPM in esecuzione su un network Docker condivisibile
- Dominio `gfoss.it` (e/o sottodomini) puntato all'IP della VPS

## 1. Preparazione network condiviso con NPM

NPM e WordPress devono trovarsi sullo stesso network Docker per comunicare per nome container.

```bash
# Verifica se esiste già un network condiviso (cerca quello a cui è attaccato NPM)
docker network ls
docker inspect <nome_container_NPM> | grep -A2 Networks

# Se NPM è ancora sulla rete bridge di default, crea un network dedicato e ricollega NPM:
docker network create npm-net
docker network connect npm-net <nome_container_NPM>
```

Annotati il nome esatto del network — andrà nel `.env` come `NPM_NETWORK_NAME`.

## 2. Clona il repo

```bash
sudo mkdir -p /opt/gfoss-wp
sudo chown $USER:$USER /opt/gfoss-wp
git clone <url-del-tuo-repo> /opt/gfoss-wp
cd /opt/gfoss-wp
```

## 3. Configura `.env`

```bash
cp .env.example .env
nano .env
```

Da impostare obbligatoriamente:

| Variabile | Valore |
|---|---|
| `WP_HOME`, `WP_SITEURL` | `https://gfoss.it` |
| `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | password forti generate con `openssl rand -base64 32` |
| `NPM_NETWORK_NAME` | nome del network Docker condiviso con NPM |
| `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS` | credenziali del relay SMTP (es. Brevo, Mailgun, Postmark) |
| `GFOSS_IBAN` | `IT85F0306909606100000015079` (o aggiornato) |
| `PAYPAL_HOSTED_BUTTON_ID` | `AQKXPZ2EHSP34` |
| `PAYPAL_RECEIVER_EMAIL` | **email del conto PayPal dell'associazione** — obbligatoria: l'IPN rifiuta i pagamenti se il destinatario non corrisponde (anti-frode) |

## 4. Build & avvio

### Opzione A — riga di comando

```bash
docker compose build
docker compose up -d
docker compose ps   # verifica che gfoss-wp e gfoss-db siano "Up"
```

**Permessi della cartella uploads** (il container gira come `www-data`, uid 33). Senza questo, i caricamenti media e l'import di logo/favicon nel seed falliscono:

```bash
mkdir -p wp-content/uploads
sudo chown -R 33:33 wp-content/uploads
```

### Opzione B — Portainer

1. Apri Portainer → **Stacks → Add stack**
2. Nome: `gfoss-wp`
3. **Repository** → URL del tuo repo, branch `main`, Compose path `docker-compose.yml`
4. **Environment variables** → carica il file `.env`
5. **Deploy the stack**

## 5. Configura NPM (Proxy Host)

Dal pannello di NPM:

1. **Hosts → Proxy Hosts → Add Proxy Host**
2. **Domain Names**: `gfoss.it` (eventualmente anche `www.gfoss.it`)
3. **Scheme**: `http`
4. **Forward Hostname / IP**: `gfoss-wp` (nome del container)
5. **Forward Port**: `80`
6. ☑ Block Common Exploits
7. ☑ Websockets Support
8. **Custom Nginx Configuration** (consigliato, raddoppia gli upload):
   ```
   client_max_body_size 32m;
   ```
9. Tab **SSL** → Request a new SSL certificate, ☑ Force SSL, ☑ HTTP/2 Support, ☑ HSTS Enabled
10. Salva e attendi l'emissione del certificato Let's Encrypt

## 6. Installazione iniziale di WordPress

Apri `https://gfoss.it` nel browser.

1. Lingua: **Italiano**
2. Titolo sito: `GFOSS.it APS`
3. Nome utente: scegli un account amministrativo (NON `admin`)
4. Password: forte, salvala nel password manager
5. Email: amministrativa
6. **Installa WordPress**

Subito dopo:

- **Aspetto → Temi** → attiva **GFOSS 2026**
- **Plugin** → attiva **GFOSS Members** (l'attivazione crea ruoli, tabelle e pagine di sistema)
- **Plugin** → attiva **GFOSS Accounting**

### Plugin RNDT Manager (catalogo metadati soci)

Il plugin RNDT usa il database di WordPress (MariaDB). **Prima** di attivarlo va impostato il backend, altrimenti l'attivazione richiede PostgreSQL e fallisce:

```bash
docker compose --profile tools run --rm wpcli eval \
  '$s=get_option("rndt_settings",[]); if(!is_array($s))$s=[]; $s["database"]["type"]="wordpress"; update_option("rndt_settings",$s);'
docker compose --profile tools run --rm wpcli plugin activate rndt-manager
```

L'attivazione crea il CPT, il ruolo e le tabelle `rndt_*`. I soci ricevono in automatico la capability per gestire i **propri** metadati (la pagina `/area-soci/metadati-rndt/` è creata dal seed).

## 7. Installa le dipendenze Composer del plugin members

Necessario per la generazione PDF della tessera digitale (mPDF + endroid/qr-code).

```bash
docker compose exec wordpress sh -c \
  "cd /var/www/html/wp-content/plugins/gfoss-members && composer install --no-dev --optimize-autoloader"
```

Senza questo step la tessera mostrerà un errore esplicito ma il resto del sito è funzionante.

## 8. Seed delle pagine "Associazione"

```bash
docker compose --profile tools run --rm wpcli eval-file /scripts/seed-pages.php
```

Lo script è **idempotente** e fa da solo gran parte della configurazione iniziale:

- Pagine **Associazione** (Statuto integrale, Bilanci e verbali, Organi associativi, Iscrizioni e rinnovi), **Privacy**, **Cookie policy**, **Eventi**, e le pagine soci (**Materiali**, **Convocazioni**, **Mappa soci**, **Metadati RNDT**).
- **Menu principale** con sottomenu Associazione (desktop + mobile).
- **Permalink** `/%postname%/` + flush (senza, gli URL puliti non funzionano).
- **Lingua** del backend impostata su Italiano (it_IT).
- **Home statica + archivio News** (impostazioni di lettura).
- **Logo e favicon** importati dagli asset del tema e impostati come logo/icona del sito.

Rieseguibile in sicurezza per applicare nuovi contenuti.

## 9. Configurazione SMTP (email transazionali)

In **Plugin → Aggiungi nuovo** installa **WP Mail SMTP** (o **FluentSMTP**), poi configura:

- Mailer: SMTP
- Host: valore di `SMTP_HOST`
- Porta: `587` (TLS) o `465` (SSL)
- Crittografia: TLS
- Username/Password: dal `.env`
- From email: `info@gfoss.it`
- From name: `GFOSS.it APS`

Test: invia un'email di prova dall'admin del plugin.

## 10. Configurazione PayPal (IPN)

1. Accedi al pannello PayPal con l'account dell'associazione
2. **Profilo → Profilo venditore → Notifica istantanea dei pagamenti** (IPN)
3. Imposta URL: `https://gfoss.it/wp-json/gfoss/v1/paypal-ipn`
4. ☑ Ricezione messaggi IPN: **Abilitata**
5. Sul bottone hosted `AQKXPZ2EHSP34`, verifica che accetti l'override di importo via URL

## 11. Configura il Consiglio Direttivo

In **Utenti** crea (o aggiorna) gli account dei consiglieri assegnando i ruoli:

- `Presidente` → 1 utente
- `Tesoriere` → 1 utente (ottiene accesso esclusivo a Contabilità)
- `Consigliere` → gli altri membri del CD
- `Revisore` → eventuale organo di controllo

I ruoli sono cumulabili: un consigliere può essere anche tesoriere.

In **Impostazioni → Generale** (o tramite custom field, in arrivo) configura `gfoss_cd_recipients` con la mailing list del CD per le notifiche di nuove candidature e i riepiloghi settimanali.

## 12. Backup automatici

### Database

```bash
# Esempio cron giornaliero alle 02:30
30 2 * * * docker exec gfoss-db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
  | gzip > /opt/backups/gfoss/db-$(date +\%F).sql.gz && \
  find /opt/backups/gfoss -name 'db-*.sql.gz' -mtime +30 -delete
```

### Uploads

```bash
0 3 * * * tar czf /opt/backups/gfoss/uploads-$(date +\%F).tar.gz -C /opt/gfoss-wp wp-content/uploads
```

### Off-site

Configura `rclone` o `restic` con un bucket S3-compatibile (Wasabi, Backblaze B2) per copiare `/opt/backups/gfoss` settimanalmente.

## 13. Monitoring (consigliato)

- **Uptime Kuma** (container leggero) per ping HTTP su `gfoss.it`
- **Watchtower** per aggiornamenti automatici delle immagini Docker (con notifica)
- Log di Apache: `docker logs gfoss-wp -f`

## 14. Aggiornamenti

```bash
cd /opt/gfoss-wp
git pull
docker compose build wordpress    # se è cambiato il Dockerfile
docker compose up -d              # ricrea i container modificati
docker compose exec wordpress sh -c \
  "cd /var/www/html/wp-content/plugins/gfoss-members && composer install --no-dev --optimize-autoloader"
```

Gli aggiornamenti minori del core WP avvengono automaticamente (`WP_AUTO_UPDATE_CORE = minor`). Plugin e tema seguono il flusso git → deploy.

## Troubleshooting

**`gfoss-wp` riavvia in loop** → controlla i log: `docker logs gfoss-wp`. Le cause più comuni: variabili `MYSQL_*` non coerenti tra db e wordpress, network NPM inesistente.

**Le email non partono** → in dev usa Mailpit (`docker compose --profile dev up -d`). In prod verifica le credenziali SMTP e che la porta 587 sia aperta in uscita sulla VPS.

**IPN PayPal non arriva** → controlla `/var/log/apache2/access.log` dentro il container per vedere se la POST a `/wp-json/gfoss/v1/paypal-ipn` arriva. PayPal richiede HTTPS valido (Let's Encrypt OK).

**Tessera PDF non si scarica** → manca `vendor/` del plugin: rilancia il comando del passo 7.

**Seed pagine fallisce** → verifica che il container `gfoss-wp` sia healthy (`docker compose ps`) e che il database sia stato già installato (passo 6).
