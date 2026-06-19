# Guida Rapida

Inizia a usare RNDT Manager in 5 minuti.

---

## 1. Requisiti minimi

- WordPress 5.8+
- PHP 7.4+ con estensione `pdo_pgsql`
- PostgreSQL 12+

---

## 2. Installazione veloce

### Se hai Node.js installato:

```bash
cd wp-content/plugins/rndt-manager
npm install
npm run build
```

### Se NON hai Node.js:

Scarica una release pre-compilata che include già la cartella `assets/build/`.

---

## 3. Configura PostgreSQL

```sql
CREATE DATABASE rndt_metadata;
CREATE USER rndt_user WITH PASSWORD 'tuapassword';
GRANT ALL PRIVILEGES ON DATABASE rndt_metadata TO rndt_user;
```

---

## 4. Configura il plugin

1. Attiva il plugin in WordPress
2. Vai su **RNDT Manager → Impostazioni**
3. Inserisci i dati PostgreSQL
4. Clicca **Test connessione**
5. Salva

---

## 5. Crea il primo metadato

1. **RNDT Manager → Aggiungi nuovo**
2. Seleziona **Dataset**
3. Compila almeno:
   - Titolo
   - Abstract
   - Bounding box (usa la mappa)
   - Data di creazione
   - Tema INSPIRE
4. Clicca **Salva**
5. Clicca **Valida** per verificare

---

## 6. Esporta XML

Dal metadato salvato, clicca **Esporta XML** per scaricare il file ISO 19139.

---

## Prossimi passi

- Leggi il [README](../README.md) completo
- Configura [pyCSW](../README.md#sezione-pycsw-opzionale) per la pubblicazione
- Consulta la [guida sviluppatori](DEVELOPER.md) per personalizzazioni
