# Installazione pyCSW 2.6.x con APISO per INSPIRE/RNDT

Guida per l'installazione di pyCSW 2.6.x con profilo APISO abilitato, compatibile con l'harvesting INSPIRE (es. Regione Veneto).

> **Perché 2.6.x?** L'immagine `geopython/pycsw:latest` esegue la versione 3.0-dev dove il profilo APISO non funziona correttamente. La versione 2.6.x ha APISO integrato e stabile.

---

## Requisiti

- Docker e Docker Compose
- PostgreSQL con PostGIS (può essere un container dedicato o il database esistente)
- Accesso al server via Portainer o SSH

---

## 1. Struttura file

```
pycsw/
├── docker-compose.yml      # (o aggiungere al compose esistente)
├── pycsw.cfg               # Configurazione pyCSW
└── metadata-records/       # (opzionale) Directory per import XML batch
```

---

## 2. Docker Compose

### Opzione A: Stack dedicato pyCSW + PostGIS

```yaml
version: '3.8'

services:
  pycsw-db:
    image: postgis/postgis:17-3.5
    container_name: pycsw-db
    environment:
      POSTGRES_USER: pycsw
      POSTGRES_PASSWORD: Metadata2026!
      POSTGRES_DB: pycsw
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - pycsw-pgdata:/var/lib/postgresql/data/pgdata
    networks:
      - pycsw-net
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U pycsw"]
      interval: 10s
      timeout: 5s
      retries: 5

  pycsw:
    image: geopython/pycsw:2.6.1
    container_name: pycsw
    depends_on:
      pycsw-db:
        condition: service_healthy
    environment:
      PYCSW_CONFIG: /etc/pycsw/pycsw.cfg
    ports:
      - "8000:8000"
    volumes:
      - ./pycsw.cfg:/etc/pycsw/pycsw.cfg
      - ./metadata-records:/var/lib/pycsw/records
    networks:
      - pycsw-net

networks:
  pycsw-net:

volumes:
  pycsw-pgdata:
```

### Opzione B: Usare il PostgreSQL esistente

Se hai già un container PostgreSQL/PostGIS (es. `postgres-postgis`), puoi aggiungere solo il servizio pyCSW al tuo stack esistente:

```yaml
  pycsw:
    image: geopython/pycsw:2.6.1
    container_name: pycsw
    environment:
      PYCSW_CONFIG: /etc/pycsw/pycsw.cfg
    ports:
      - "8000:8000"
    volumes:
      - ./pycsw.cfg:/etc/pycsw/pycsw.cfg
    networks:
      - your-existing-network
```

E nel `pycsw.cfg` usa la connection string del database esistente:
```ini
[repository]
database=postgresql://pycsw:Metadata2026!@postgres-postgis:5432/pycsw
table=records
```

> **Nota**: Assicurati che il database `pycsw` esista e che l'estensione PostGIS sia abilitata:
> ```sql
> CREATE DATABASE pycsw;
> \c pycsw
> CREATE EXTENSION postgis;
> ```

---

## 3. Configurazione pycsw.cfg

Il file usa formato INI (non YAML come nella 3.x).

```ini
[server]
home=/home/pycsw
url=https://pycsw.studiogis.eu
mimetype=application/xml; charset=UTF-8
encoding=UTF-8
language=it-IT
maxrecords=10
loglevel=WARNING
logfile=/tmp/pycsw.log
pretty_print=true
gzip_compresslevel=9
domainquerytype=range
domaincounts=true
spatial_ranking=true
profiles=apiso

[manager]
transactions=true
allowed_ips=0.0.0.0/0

[metadata:main]
identification_title=StudioGIS CSW Catalogue
identification_abstract=Catalogo metadati CSW - StudioGIS
identification_keywords=catalogo,csw,metadati,geospaziale,SDI
identification_keywords_type=theme
identification_fees=None
identification_accessconstraints=None
provider_name=StudioGIS
provider_url=https://studiogis.eu
contact_name=Federico
contact_position=Geographer / Geoinformatician
contact_address=
contact_city=Sevilla
contact_stateorprovince=Andalucia
contact_postalcode=
contact_country=Spain
contact_phone=
contact_fax=
contact_email=
contact_url=https://studiogis.eu
contact_hours=
contact_instructions=
contact_role=pointOfContact

[repository]
database=postgresql://pycsw:Metadata2026!@postgres-postgis:5432/pycsw
table=records

[metadata:inspire]
enabled=true
languages_supported=ita,eng,spa
default_language=ita
date=2025-01-01
gemet_keywords=Utility and governmental services
conformity_service=notEvaluated
contact_name=StudioGIS
contact_email=
temp_extent=2025-01-01/
```

### Parametri chiave

| Sezione | Parametro | Descrizione |
|---------|-----------|-------------|
| `[server]` | `profiles=apiso` | Abilita il profilo ISO 19115/19139 per INSPIRE |
| `[server]` | `url` | URL pubblico del servizio (usato nelle Capabilities) |
| `[server]` | `spatial_ranking=true` | Ranking spaziale nei risultati di ricerca |
| `[manager]` | `transactions=true` | Abilita CSW-T (Insert/Update/Delete) |
| `[manager]` | `allowed_ips` | IP/CIDR autorizzati per transazioni |
| `[metadata:inspire]` | `enabled=true` | Aggiunge ExtendedCapabilities INSPIRE |
| `[repository]` | `database` | Connection string SQLAlchemy (PostgreSQL/SQLite) |

---

## 4. Primo avvio

### 4.1 Avviare i container

```bash
docker compose up -d
```

### 4.2 Inizializzare il database

```bash
docker exec -ti pycsw pycsw-admin.py -c setup_db -f /etc/pycsw/pycsw.cfg
```

Questo crea la tabella `records` con:
- Colonne per tutti i campi Dublin Core e ISO
- Colonna geometrica PostGIS (se PostGIS è attivo)
- Indice GIN per full-text search
- Trigger per sincronizzazione WKT/geometry

### 4.3 Verificare il servizio

```bash
# GetCapabilities
curl "https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetCapabilities"

# Verificare che APISO sia attivo (deve contenere gmd nell'outputSchema)
curl -s "https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetCapabilities" \
  | grep "isotc211.org/2005/gmd"
```

Se l'output contiene `http://www.isotc211.org/2005/gmd` negli outputSchema, APISO è attivo.

---

## 5. Endpoint CSW

L'endpoint CSW è direttamente sulla root (pyCSW 2.6.x non usa `/csw`):

| Operazione | URL |
|------------|-----|
| GetCapabilities | `https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetCapabilities` |
| GetRecords (DC) | `https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetRecords&typenames=csw:Record&elementsetname=full` |
| GetRecords (ISO) | `https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetRecords&typenames=gmd:MD_Metadata&outputschema=http://www.isotc211.org/2005/gmd&elementsetname=full` |
| GetRecordById | `https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetRecordById&Id=IDENTIFIER&outputschema=http://www.isotc211.org/2005/gmd&elementsetname=full` |

> **Importante per il plugin RNDT Manager**: Configurare l'URL CSW nelle impostazioni come `https://pycsw.studiogis.eu` (senza `/csw`).

---

## 6. Integrazione con RNDT Manager

### Impostazioni plugin (wp-admin → RNDT Manager → Impostazioni → CSW)

| Campo | Valore |
|-------|--------|
| Abilita pubblicazione CSW | ✅ |
| Tipo catalogo | pyCSW |
| URL endpoint CSW | `https://pycsw.studiogis.eu` |
| Tipo autenticazione | Nessuna |
| Output Schema | ISO 19139 (GMD) |

### Flusso di pubblicazione

1. Creare un metadato nel wizard RNDT Manager
2. Salvare e validare
3. Dal menu ⋮ → **Pubblica su CSW**
4. Il plugin genera l'XML ISO 19139 e lo invia via CSW-T Insert
5. Se il metadato era già pubblicato, viene aggiornato (Update basato su fileIdentifier)

### Verifica pubblicazione

```bash
# Lista tutti i record in formato ISO 19139
curl -X POST -H "Content-Type: application/xml" -d '<?xml version="1.0" encoding="UTF-8"?>
<csw:GetRecords xmlns:csw="http://www.opengis.net/cat/csw/2.0.2"
    xmlns:gmd="http://www.isotc211.org/2005/gmd"
    service="CSW" version="2.0.2"
    resultType="results"
    outputSchema="http://www.isotc211.org/2005/gmd"
    maxRecords="10">
  <csw:Query typeNames="gmd:MD_Metadata">
    <csw:ElementSetName>full</csw:ElementSetName>
  </csw:Query>
</csw:GetRecords>' https://pycsw.studiogis.eu
```

---

## 7. Harvesting INSPIRE (Regione Veneto)

Con APISO + INSPIRE abilitati, il catalogo è compatibile con l'harvesting da parte di:
- **Regione Veneto** (IDT Veneto)
- **RNDT** (Repertorio Nazionale Dati Territoriali)
- **INSPIRE Geoportal** europeo

### Cosa deve fornire il catalogo per l'harvesting

1. **GetCapabilities** con ExtendedCapabilities INSPIRE
2. **GetRecords** con `outputSchema=http://www.isotc211.org/2005/gmd` che restituisce `gmd:MD_Metadata`
3. **GetRecordById** con supporto ISO 19139
4. Metadati conformi a:
   - ISO 19115/19139
   - INSPIRE Metadata TG v2.0.1
   - Profilo RNDT 2020

### URL da comunicare per l'harvesting

```
https://pycsw.studiogis.eu?service=CSW&version=2.0.2&request=GetCapabilities
```

L'harvester della Regione Veneto farà automaticamente GetRecords paginati per scaricare tutti i metadati.

---

## 8. Amministrazione

### Comandi utili (eseguire dentro il container pycsw)

```bash
# Inizializzare database
docker exec -ti pycsw pycsw-admin.py -c setup_db -f /etc/pycsw/pycsw.cfg

# Caricare record XML da una directory
docker exec -ti pycsw pycsw-admin.py -c load_records -f /etc/pycsw/pycsw.cfg -p /var/lib/pycsw/records -r

# Esportare tutti i record in file XML
docker exec -ti pycsw pycsw-admin.py -c export_records -f /etc/pycsw/pycsw.cfg -p /var/lib/pycsw/export

# Ottimizzare il database (solo PostgreSQL)
docker exec -ti pycsw pycsw-admin.py -c optimize_db -f /etc/pycsw/pycsw.cfg

# Eliminare tutti i record
docker exec -ti pycsw pycsw-admin.py -c delete_records -f /etc/pycsw/pycsw.cfg
```

### Harvest da servizi OGC remoti

pyCSW può importare metadati da servizi WMS/WFS/WMTS esistenti:

```bash
# Harvest da un WMS
curl -X POST -H "Content-Type: application/xml" -d '<?xml version="1.0" encoding="UTF-8"?>
<csw:Harvest xmlns:csw="http://www.opengis.net/cat/csw/2.0.2"
    service="CSW" version="2.0.2">
  <csw:Source>https://geoserver.studiogis.eu/geoserver/wms?service=WMS&amp;request=GetCapabilities</csw:Source>
  <csw:ResourceType>http://www.opengis.net/wms</csw:ResourceType>
</csw:Harvest>' https://pycsw.studiogis.eu
```

---

## 9. Sicurezza

### Produzione: limitare le transazioni

In produzione, sostituire `0.0.0.0/0` con gli IP autorizzati:

```ini
[manager]
transactions=true
allowed_ips=127.0.0.1,172.18.0.0/16,IP_DEL_SERVER_WORDPRESS
```

### Alternativa: reverse proxy con auth

```nginx
location /pycsw {
    # GET libero (harvesting, GetCapabilities, GetRecords)
    limit_except GET {
        auth_basic "CSW Admin";
        auth_basic_user_file /etc/nginx/.htpasswd;
    }
    proxy_pass http://pycsw:8000;
}
```

Il plugin RNDT Manager supporta Basic Auth nelle impostazioni CSW.

---

## 10. Differenze dalla versione 3.0-dev

| Aspetto | pyCSW 2.6.x | pyCSW 3.0-dev |
|---------|-------------|---------------|
| Config | INI (`pycsw.cfg`) | YAML (`pycsw.yml`) |
| APISO | Integrato, funzionante | Non funzionante |
| Endpoint | Root `/` | `/csw` per CSW 2.0.2 |
| ISO 19139 output | ✅ GetRecords + GetRecordById | ❌ Solo GetRepositoryItem |
| INSPIRE ExtendedCapabilities | ✅ | ❌ |
| OGC API Records (JSON) | ❌ | ✅ |
| Docker image | `geopython/pycsw:2.6.1` | `geopython/pycsw:latest` |

---

## Migrazione dalla 3.0-dev

Se hai già record pubblicati sulla 3.0-dev:

1. Esporta i record dalla 3.0-dev:
   ```bash
   docker exec -ti pycsw-old pycsw-admin.py -c export_records -f /etc/pycsw/pycsw.yml -p /tmp/export
   ```
2. Copia i file XML esportati nel volume della 2.6.x
3. Importali:
   ```bash
   docker exec -ti pycsw pycsw-admin.py -c load_records -f /etc/pycsw/pycsw.cfg -p /var/lib/pycsw/records -r -y
   ```

In alternativa, ripubblica i metadati dal plugin RNDT Manager dopo la migrazione.
