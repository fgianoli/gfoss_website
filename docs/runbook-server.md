# Runbook server — GIS (PostGIS + GeoServer) e Nextcloud

Guida operativa per attivare, sulla VPS, i servizi che il sito usa ma che vivono
in container separati. Presuppone **Nginx Proxy Manager (NPM)** già attivo e il
sito GFOSS già in produzione (`/opt/gfoss-wp`).

Convenzioni:
- Container del sito: `gfoss-wp`. Rete di NPM: la chiamiamo `npm-net` (verifica il
  nome reale con `docker network ls`).
- Sostituisci i domini d'esempio (`cloud.gfoss.it`, `geoserver.gfoss.it`) con i tuoi.

---

## 0. Ricognizione iniziale

```bash
docker ps -a                 # stato dei container (PostGIS, GeoServer, NPM, ecc.)
docker network ls            # nome della rete di NPM
```

Annota a quale **rete** sono collegati PostGIS e GeoServer:
```bash
docker inspect geoserver --format '{{json .NetworkSettings.Networks}}'
```

---

## 1. PostGIS

### 1a. Rimetterlo in esecuzione
Nel tuo `docker ps` PostGIS risultava **Exited/Created** e con due container in
conflitto di nome. Pulisci e riavvia (dal Portainer/stack che li ha creati, oppure):

```bash
# Individua i due container postgis e rimuovi quello morto/duplicato
docker ps -a | grep -i postgis
docker rm <id_container_postgis_morto>        # solo quello Exited/duplicato, NON i volumi
# Avvia quello buono (o ricrea lo stack che lo definisce)
docker start <id_container_postgis_buono>
docker logs --tail 50 <id_container_postgis_buono>   # verifica che parta
```
> ⚠️ Non toccare i **volumi**: contengono i dati. Rimuovi solo il container.

### 1b. Credenziali (immagine kartoza)
```bash
docker inspect <postgis> --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -iE 'POSTGRES|PASS|DB'
```
Default kartoza se non impostate: utente `docker`, password `docker`, db `gis`.

### 1c. Ruolo dedicato + DB per i soci
Collegati come superuser e crea un ruolo **non superuser** (solo `CREATEROLE`) e il
database condiviso con PostGIS:

```bash
docker exec -it <postgis> psql -U <POSTGRES_USER> -c \
  "CREATE ROLE gfoss_provisioner LOGIN PASSWORD 'METTI_PASSWORD_FORTE' CREATEROLE;"
docker exec -it <postgis> psql -U <POSTGRES_USER> -c \
  "CREATE DATABASE soci_gis OWNER gfoss_provisioner;"
docker exec -it <postgis> psql -U <POSTGRES_USER> -d soci_gis -c \
  "CREATE EXTENSION IF NOT EXISTS postgis; REVOKE CREATE ON SCHEMA public FROM PUBLIC;"
```

---

## 2. GeoServer

### 2a. Credenziali admin REST
```bash
docker inspect geoserver --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -iE 'GEOSERVER_ADMIN'
```
Default se non impostate: `admin` / `geoserver` (cambiala dopo il primo accesso!).

### 2b. Esporre GeoServer via NPM (opzionale, per i soci)
Su NPM → **Proxy Hosts → Add**:
- Domain: `geoserver.gfoss.it`
- Forward Hostname/IP: `geoserver`  · Port: `8080`  (nome container sulla rete condivisa)
- Websockets: ON · SSL: richiedi certificato Let's Encrypt · Force SSL: ON

`GFOSS_GEOSERVER_PUBLIC_URL` sarà `https://geoserver.gfoss.it/geoserver`.

---

## 3. Collegare il sito ai container GIS

`gfoss-wp` deve raggiungere `postgis` e `geoserver` **per nome**: vanno sulla stessa
rete. Il modo più semplice:

```bash
# Collega gfoss-wp alla rete dove stanno PostGIS/GeoServer (es. la npm-net condivisa)
docker network connect <rete-dei-container-gis> gfoss-wp
# verifica
docker exec gfoss-wp getent hosts postgis
docker exec gfoss-wp getent hosts geoserver
```

### 3a. Ricostruire l'immagine WordPress (estensione pgsql)
Il `Dockerfile` ora include `pgsql`/`pdo_pgsql`:
```bash
cd /opt/gfoss-wp
git pull
docker compose build wordpress
docker compose up -d
```

### 3b. Compilare il `.env` (blocco GIS)
```dotenv
GFOSS_PG_HOST=postgis
GFOSS_PG_PORT=5432
GFOSS_PG_ADMIN_DB=soci_gis
GFOSS_PG_ADMIN_USER=gfoss_provisioner
GFOSS_PG_ADMIN_PASS=METTI_PASSWORD_FORTE
GFOSS_PG_PUBLIC_HOST=gfoss.it          # host mostrato ai soci per QGIS (o IP/host pubblico)
GFOSS_PG_PUBLIC_PORT=5432
GFOSS_PG_INTERNAL_HOST=postgis
GFOSS_GEOSERVER_URL=http://geoserver:8080/geoserver
GFOSS_GEOSERVER_PUBLIC_URL=https://geoserver.gfoss.it/geoserver
GFOSS_GEOSERVER_ADMIN_USER=admin
GFOSS_GEOSERVER_ADMIN_PASS=LA_PASSWORD_ADMIN_GEOSERVER
```
Poi `docker compose up -d`.

### 3c. Verifica connessione
```bash
docker compose exec wordpress php -r \
 'var_dump((bool)@pg_connect("host=postgis dbname=soci_gis user=gfoss_provisioner password=METTI_PASSWORD_FORTE"));'
```
`true` = ok. Poi un socio in regola vedrà la card **“Il tuo spazio dati GIS”** e potrà attivarlo.

> Nota: i soci si connettono a PostGIS dall'esterno (QGIS) solo se la porta 5432 è
> raggiungibile pubblicamente. Se non vuoi esporre Postgres su Internet, valuta un
> accesso via VPN o lascia attivo solo GeoServer come punto di pubblicazione.

---

## 4. Nextcloud (documenti del Direttivo)

### 4a. Avvio dello stack
I file sono nel repo in `docker/nextcloud/`. Sul server:
```bash
mkdir -p /opt/nextcloud && cd /opt/nextcloud
# copia docker-compose.yml e .env.example dal repo (docker/nextcloud/)
cp .env.example .env && nano .env      # imposta domini e password
docker compose up -d
docker compose logs -f nextcloud-app   # attendi "initializing finished"
```

### 4b. Proxy Host su NPM
NPM → **Proxy Hosts → Add**:
- Domain: `cloud.gfoss.it`
- Forward Hostname/IP: `nextcloud-app` · Port: `80`
- Websockets: ON · SSL: Let's Encrypt · Force SSL: ON · HTTP/2: ON
- Tab **Advanced** → Custom Nginx Configuration:
  ```nginx
  client_max_body_size 512M;
  location /.well-known/carddav { return 301 $scheme://$host/remote.php/dav; }
  location /.well-known/caldav  { return 301 $scheme://$host/remote.php/dav; }
  ```

Apri `https://cloud.gfoss.it`, completa il primo accesso con l'admin del `.env`.
Se compare un warning sui *trusted domains*, è già coperto da `NEXTCLOUD_TRUSTED_DOMAINS`.

### 4c. Cartella riservata al Direttivo
1. In Nextcloud crea un **gruppo** `direttivo` (Impostazioni → Utenti → Gruppi).
2. Crea una cartella es. `Direttivo/` e **condividila col gruppo** `direttivo` (permessi di modifica).
3. Aggiungi al gruppo gli account dei consiglieri.
4. Copia il link interno della cartella (o usa la home di Nextcloud).

### 4d. Collegare il sito
Nel `.env` di `/opt/gfoss-wp`:
```dotenv
GFOSS_NEXTCLOUD_URL=https://cloud.gfoss.it/apps/files/?dir=/Direttivo
```
`docker compose up -d` → nell'area soci/Console del Direttivo compare **“Documenti del Direttivo”**.

> SSO (login unico sito↔Nextcloud) è un passo successivo: si fa con WordPress come
> provider OIDC + l'app *OpenID Connect Login* su Nextcloud. Da pianificare a parte.

---

## 5. Checklist finale

- [ ] PostGIS in esecuzione, ruolo `gfoss_provisioner` + DB `soci_gis` + estensione creati
- [ ] GeoServer raggiungibile (eventuale proxy host NPM), credenziali admin note
- [ ] `gfoss-wp` collegato alla rete dei container GIS (`getent hosts postgis` risolve)
- [ ] Immagine WP ricostruita (pgsql), `.env` GIS compilato, `pg_connect` = true
- [ ] Nextcloud su `cloud.gfoss.it` via NPM, gruppo+cartella `direttivo`, `GFOSS_NEXTCLOUD_URL` impostato
- [ ] Seed pagine eseguito dopo l'ultimo `git pull`:
      `docker compose --profile tools run --rm wpcli eval-file /scripts/seed-pages.php`
