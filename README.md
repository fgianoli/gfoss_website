# GFOSS.it APS — sito WordPress

Sito istituzionale e gestionale dell'Associazione Italiana per l'Informazione Geografica Libera ([gfoss.it](https://gfoss.it/)).

## Architettura

Stack containerizzato pensato per VPS Ubuntu con **Portainer + Nginx Proxy Manager** già installati. NPM gestisce dominio, certificati Let's Encrypt e reverse proxy: questo compose **non** include un nginx proprio.

| Servizio | Immagine | Scopo |
|---|---|---|
| `wordpress` | custom da `wordpress:6.7-php8.3-apache` (vedi `Dockerfile`) | Frontend e backend WP |
| `db`        | `mariadb:11` | Database |
| `mailpit`   | `axllent/mailpit` (profilo `dev`) | SMTP fittizio per test email |
| `wpcli`     | `wordpress:cli-php8.3` (profilo `tools`) | One-shot CLI per import / migrazioni |

Codice applicativo:

```
wp-content/
├── themes/gfoss-2026/        # tema custom presentazionale
├── plugins/gfoss-members/    # gestione soci, quote, area personale, tessera PDF, PayPal
└── plugins/gfoss-accounting/ # contabilità entrate/uscite (ruolo tesoriere)
```

## Setup iniziale (sviluppo locale)

```bash
cp .env.example .env                                          # 1. password DB
cp docker-compose.override.yml.example docker-compose.override.yml   # 2. config dev
docker network create npm-net                                  # 3. fa contento il compose
docker compose up -d --build                                   # 4. avvia tutto
```

Apri:
- **http://localhost:8080** → installer WordPress (lingua italiano, scegli account admin)
- **http://localhost:8025** → Mailpit (tutte le email finiscono qui, niente raggiunge l'esterno)

Dopo l'installer WP:
1. Aspetto → Temi → attiva **GFOSS 2026**
2. Plugin → attiva **GFOSS Members** e **GFOSS Accounting**
3. Una sola volta, per la tessera PDF:
   ```bash
   docker compose exec wordpress sh -c \
     "cd /var/www/html/wp-content/plugins/gfoss-members && composer install --no-dev"
   ```
4. Carica le pagine del menù Associazione:
   ```bash
   docker compose --profile tools run --rm wpcli /scripts/seed-pages.sh
   ```

Ferma tutto con `docker compose down` (i dati restano nei volumi). Per pulizia totale: `docker compose down -v`.

## Deploy su VPS con Portainer + NPM

1. **Network condiviso con NPM**
   ```bash
   docker network ls                  # individua il network di NPM
   # se non esiste un network condiviso, creane uno e ricollega NPM
   docker network create npm-net
   ```
   Imposta il nome esatto in `.env` → `NPM_NETWORK_NAME=...`.

2. **Clona il repo nella VPS** in `/opt/gfoss-wp` e:
   ```bash
   cp .env.example .env
   nano .env
   docker compose build
   docker compose up -d
   ```
   In Portainer: *Stacks → Add stack → Git repository* puntando a questo repo, oppure carica `docker-compose.yml` manualmente.

3. **Configura NPM**
   - Aggiungi un Proxy Host per `gfoss.it` → forward a `gfoss-wp:80`
   - Abilita SSL (Let's Encrypt) e Force HTTPS, HTTP/2, HSTS
   - WebSocket Support: ON

4. **Prima visita** → installa WordPress, attiva il tema `GFOSS 2026`, attiva i plugin `gfoss-members` e `gfoss-accounting`. L'attivazione crea ruoli, capability e tabelle.

5. **Installa le dipendenze del plugin gfoss-members** (mPDF + endroid/qr-code per la tessera digitale):
   ```bash
   docker compose exec wordpress sh -c \
     "cd /var/www/html/wp-content/plugins/gfoss-members && composer install --no-dev --optimize-autoloader"
   ```
   Senza questo step la tessera PDF e il QR non funzionano (il sito resta usabile, mostra un errore esplicito).

6. **Seed pagine Associazione**
   ```bash
   docker compose --profile tools run --rm wpcli /scripts/seed-pages.sh
   ```
   (Lo script verrà aggiunto in fase 5: importa i contenuti del menù Associazione.)

## Email in produzione

Il container WordPress invia mail via PHP `mail()` di default. In produzione usa il plugin **WP Mail SMTP** (o equivalente) con i valori di `.env` (SMTP_*).

In sviluppo, le email finiscono su Mailpit:
```bash
docker compose --profile dev up -d
# Poi proxa mailpit:8025 via NPM, oppure pubblica la porta in compose.override.yml
```

## Backup

Volume DB: `gfoss_db_data`. Volume WP core: `gfoss_wp_core` (file media in `./wp-content/uploads`).

```bash
# Backup DB
docker compose exec db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > backup-$(date +%F).sql

# Backup uploads
tar czf uploads-$(date +%F).tar.gz wp-content/uploads
```

## Roadmap

Vedi `docs/roadmap.md` (in arrivo). Fasi:

1. ✅ Foundation Docker + scaffolding tema/plugin
2. ⏳ Pagine Associazione + branding finale
3. ⏳ Plugin `gfoss-members`: workflow ammissione, quote, area personale
4. ⏳ Tessera digitale PDF + PayPal
5. ⏳ Plugin `gfoss-accounting`
6. ⏳ Newsletter + documenti riservati + 5x1000
7. 🔜 Voto online (fase 2 separata)
