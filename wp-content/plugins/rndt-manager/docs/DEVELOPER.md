# Documentazione Sviluppatori

Guida tecnica per sviluppatori che vogliono estendere o modificare RNDT Manager.

---

## Architettura

```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND (React)                         │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  App.js → MetadataContext → Wizard → Steps → Fields     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              │ REST API calls                   │
│                              ▼                                  │
├─────────────────────────────────────────────────────────────────┤
│                        BACKEND (PHP)                            │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    REST API Layer                        │   │
│  │  RNDT_REST_Metadata | Validation | Export | Import      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Business Logic                        │   │
│  │  RNDT_Metadata_Repository | RNDT_Validator | XML Gen    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Data Layer                            │   │
│  │  RNDT_Database (PostgreSQL) | WordPress CPT (metadata)  │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Classi Principali

### Core

| Classe | File | Descrizione |
|--------|------|-------------|
| `RNDT_Manager` | `class-rndt-manager.php` | Singleton orchestratore |
| `RNDT_Loader` | `class-rndt-loader.php` | Registra hook WordPress |
| `RNDT_Database` | `class-rndt-database.php` | Connessione PostgreSQL |
| `RNDT_Activator` | `class-rndt-activator.php` | Attivazione plugin |

### Metadata

| Classe | File | Descrizione |
|--------|------|-------------|
| `RNDT_Metadata_Model` | `metadata/class-rndt-metadata-model.php` | Modello dati |
| `RNDT_Metadata_Repository` | `metadata/class-rndt-metadata-repository.php` | CRUD operations |
| `RNDT_Metadata_Fields` | `metadata/class-rndt-metadata-fields.php` | Definizioni campi |

### XML

| Classe | File | Descrizione |
|--------|------|-------------|
| `RNDT_XML_Generator` | `xml/class-rndt-xml-generator.php` | Genera ISO 19139 |
| `RNDT_XML_Parser` | `xml/class-rndt-xml-parser.php` | Parse XML import |
| `RNDT_XML_Namespaces` | `xml/class-rndt-xml-namespaces.php` | Costanti namespace |

### Validation

| Classe | File | Descrizione |
|--------|------|-------------|
| `RNDT_Validator` | `validation/class-rndt-validator.php` | Orchestratore validazione |
| `RNDT_Validation_Rules` | `validation/class-rndt-validation-rules.php` | Regole RNDT 2020 |
| `RNDT_XSD_Validator` | `validation/class-rndt-xsd-validator.php` | Validazione XSD |

### Connectors

| Classe | File | Descrizione |
|--------|------|-------------|
| `RNDT_CSW_Client` | `connectors/class-rndt-csw-client.php` | Client CSW-T |
| `RNDT_GeoServer_Client` | `connectors/class-rndt-geoserver-client.php` | Client REST |
| `RNDT_HTTP_Client` | `connectors/class-rndt-http-client.php` | Base HTTP |

---

## Hooks e Filtri

### Actions

```php
// Dopo salvataggio metadato
do_action( 'rndt_metadata_saved', $post_id, $metadata );

// Dopo validazione
do_action( 'rndt_metadata_validated', $post_id, $validation_result );

// Dopo pubblicazione CSW
do_action( 'rndt_metadata_published_csw', $post_id, $csw_id );

// Dopo generazione XML
do_action( 'rndt_xml_generated', $post_id, $xml_string );
```

### Filters

```php
// Modifica XML prima dell'export
$xml = apply_filters( 'rndt_xml_output', $xml, $metadata );

// Aggiungi regole di validazione custom
$rules = apply_filters( 'rndt_validation_rules', $rules, $resource_type );

// Modifica campi obbligatori per tipo
$required = apply_filters( 'rndt_required_fields', $required, $resource_type );

// Modifica opzioni codelist
$options = apply_filters( 'rndt_codelist_options', $options, $codelist_type );

// Modifica configurazione steps wizard
$steps = apply_filters( 'rndt_wizard_steps', $steps, $resource_type );
```

### Esempio: Aggiungere validazione custom

```php
add_filter( 'rndt_validation_rules', function( $rules, $resource_type ) {
    // Aggiungi regola custom per dataset
    if ( $resource_type === 'dataset' ) {
        $rules['custom_field'] = array(
            'required' => true,
            'validate' => function( $value ) {
                return strlen( $value ) >= 10;
            },
            'message' => 'Il campo deve avere almeno 10 caratteri',
        );
    }
    return $rules;
}, 10, 2 );
```

### Esempio: Modificare XML output

```php
add_filter( 'rndt_xml_output', function( $xml, $metadata ) {
    // Aggiungi elemento custom
    $doc = new DOMDocument();
    $doc->loadXML( $xml );

    // Modifica...

    return $doc->saveXML();
}, 10, 2 );
```

---

## REST API Endpoints

### Autenticazione

Tutti gli endpoint richiedono autenticazione WordPress. Usa il nonce `wp_rest`:

```javascript
fetch('/wp-json/rndt/v1/metadata', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});
```

### Endpoints Metadata

#### GET /rndt/v1/metadata

Lista metadati con paginazione.

**Parametri:**
- `page` (int): Pagina corrente
- `per_page` (int): Elementi per pagina
- `resource_type` (string): Filtra per tipo
- `status` (string): draft|pending|publish

**Response:**
```json
{
    "items": [...],
    "total": 100,
    "pages": 10
}
```

#### POST /rndt/v1/metadata

Crea nuovo metadato.

**Body:**
```json
{
    "resource_type": "dataset",
    "title": "Titolo metadato",
    "abstract": "Descrizione...",
    "fields": {
        "resource_language": "ita",
        "bbox_west": 6.5,
        "bbox_east": 18.5,
        "bbox_south": 35.5,
        "bbox_north": 47.0
    },
    "keywords": [
        {"keyword": "ambiente", "thesaurus": "GEMET"}
    ]
}
```

#### GET /rndt/v1/metadata/{id}

Dettaglio singolo metadato.

#### PUT /rndt/v1/metadata/{id}

Aggiorna metadato esistente.

#### DELETE /rndt/v1/metadata/{id}

Elimina metadato.

### Endpoints Validazione

#### POST /rndt/v1/validate/{id}

Valida metadato esistente.

**Response:**
```json
{
    "valid": false,
    "errors": [
        {"field": "title", "message": "Il titolo è obbligatorio"}
    ],
    "warnings": [
        {"field": "lineage", "message": "Si consiglia di specificare il lineage"}
    ]
}
```

#### POST /rndt/v1/validate

Valida dati senza salvare.

**Body:** stesso formato di POST /metadata

### Endpoints Export

#### GET /rndt/v1/export/{id}/xml

Esporta metadato in XML ISO 19139.

**Response:** `application/xml`

### Endpoints Import

#### POST /rndt/v1/import/xml

Importa XML.

**Body:** `multipart/form-data` con file XML

#### POST /rndt/v1/import/csw

Importa da catalogo CSW.

**Body:**
```json
{
    "csw_url": "https://example.com/csw",
    "identifiers": ["uuid1", "uuid2"],
    "action": "import"
}
```

### Endpoints Pubblicazione

#### POST /rndt/v1/publish/{id}/csw

Pubblica su pyCSW.

#### POST /rndt/v1/publish/{id}/geoserver

Pubblica su GeoServer.

#### POST /rndt/v1/publish/test-connection

Testa connessione a servizio esterno.

**Body:**
```json
{
    "service": "pycsw",
    "url": "https://...",
    "auth_type": "basic",
    "username": "...",
    "password": "..."
}
```

---

## Frontend React

### Struttura componenti

```
assets/src/
├── index.js                    # Entry point, monta App
├── components/
│   ├── App.js                  # Router principale
│   ├── Wizard/
│   │   ├── Wizard.js           # Container wizard
│   │   ├── WizardStepper.js    # Navigazione steps
│   │   ├── WizardActions.js    # Pulsanti azione
│   │   └── ResourceTypeSelector.js
│   ├── Steps/
│   │   ├── StepIdentification.js
│   │   ├── StepClassification.js
│   │   └── ... (11 step totali)
│   └── Fields/
│       └── FieldWrapper.js     # Wrapper campo con validazione
├── context/
│   └── MetadataContext.js      # Stato globale React Context
├── hooks/
│   └── useApi.js               # Hook per chiamate REST
└── styles/
    └── main.scss               # Stili SCSS
```

### MetadataContext

Gestisce lo stato dell'applicazione con useReducer:

```javascript
const {
    metadata,           // Dati metadato corrente
    validation,         // Risultato validazione
    isDirty,           // Modifiche non salvate
    updateField,       // Aggiorna singolo campo
    updateFields,      // Aggiorna multipli campi
    setValidation,     // Imposta validazione
    resetMetadata      // Reset stato
} = useMetadata();
```

### Aggiungere un nuovo Step

1. Crea componente in `components/Steps/`:

```javascript
// StepMyCustom.js
import { useMetadata } from '../../context/MetadataContext';
import FieldWrapper from '../Fields/FieldWrapper';

const StepMyCustom = ({ codelists, resourceType }) => {
    const { metadata, updateField, validation } = useMetadata();

    const getError = (field) => {
        return validation.errors?.find(e => e.field === field);
    };

    return (
        <div className="rndt-step">
            <h3>My Custom Step</h3>

            <FieldWrapper
                label="Campo custom"
                required={true}
                error={getError('custom_field')}
            >
                <input
                    type="text"
                    value={metadata.custom_field || ''}
                    onChange={(e) => updateField('custom_field', e.target.value)}
                />
            </FieldWrapper>
        </div>
    );
};

export default StepMyCustom;
```

2. Registra lo step in `Wizard.js`:

```javascript
import StepMyCustom from '../Steps/StepMyCustom';

const getStepsConfig = (resourceType) => {
    const baseSteps = [
        // ... altri steps
        {
            id: 'my_custom',
            title: __('My Custom', 'rndt-manager'),
            component: StepMyCustom,
            required: true,
        },
    ];
    // ...
};
```

### useApi Hook

```javascript
const {
    loading,
    error,
    fetchMetadata,
    saveMetadata,
    validateMetadata,
    exportXml,
    publishToCsw
} = useApi();

// Esempio utilizzo
const handleSave = async () => {
    const result = await saveMetadata(metadata);
    if (result.success) {
        // ...
    }
};
```

---

## Database PostgreSQL

### Schema tabelle

```sql
-- Campi principali metadato
CREATE TABLE rndt_metadata_fields (
    id SERIAL PRIMARY KEY,
    post_id INTEGER NOT NULL,
    resource_type VARCHAR(50),
    file_identifier VARCHAR(255),
    resource_identifier VARCHAR(255),
    resource_language VARCHAR(10),
    parent_identifier VARCHAR(255),
    -- ... altri campi
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Parole chiave
CREATE TABLE rndt_keywords (
    id SERIAL PRIMARY KEY,
    metadata_id INTEGER REFERENCES rndt_metadata_fields(id),
    keyword VARCHAR(255) NOT NULL,
    keyword_type VARCHAR(50),
    thesaurus_name VARCHAR(255),
    thesaurus_date DATE,
    thesaurus_date_type VARCHAR(50)
);

-- Contatti responsabili
CREATE TABLE rndt_responsible_parties (
    id SERIAL PRIMARY KEY,
    metadata_id INTEGER REFERENCES rndt_metadata_fields(id),
    party_type VARCHAR(50), -- metadata_contact, resource_poc, distributor
    organisation_name VARCHAR(255),
    individual_name VARCHAR(255),
    position_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(100),
    role VARCHAR(50),
    -- ... altri campi
);

-- Risorse online
CREATE TABLE rndt_online_resources (
    id SERIAL PRIMARY KEY,
    metadata_id INTEGER REFERENCES rndt_metadata_fields(id),
    url TEXT NOT NULL,
    protocol VARCHAR(100),
    name VARCHAR(255),
    description TEXT,
    function VARCHAR(50)
);
```

### Query di esempio

```php
// Ottieni metadato con relazioni
$metadata = $repository->get($post_id);

// Query diretta al database
$db = RNDT_Database::get_instance();
$pdo = $db->get_connection();

$stmt = $pdo->prepare("
    SELECT m.*,
           array_agg(k.keyword) as keywords
    FROM rndt_metadata_fields m
    LEFT JOIN rndt_keywords k ON k.metadata_id = m.id
    WHERE m.post_id = ?
    GROUP BY m.id
");
$stmt->execute([$post_id]);
```

---

## Testing

### PHP Unit Tests

```bash
# Installa PHPUnit
composer require --dev phpunit/phpunit

# Esegui test
./vendor/bin/phpunit tests/
```

### JavaScript Tests

```bash
# Aggiungi Jest al package.json
npm install --save-dev jest @testing-library/react

# Esegui test
npm test
```

### Test manuali consigliati

1. **Creazione metadato**: tutti i tipi di risorsa
2. **Validazione**: verifica errori/warning corretti
3. **Export XML**: valida con `xmllint --schema`
4. **Import XML**: round-trip (export → import → verifica)
5. **Pubblicazione CSW**: test con pyCSW locale
6. **Performance**: test con 1000+ metadati

---

## Contribuire

1. Fork del repository
2. Crea branch feature: `git checkout -b feature/mia-feature`
3. Commit: `git commit -m 'Aggiunge feature'`
4. Push: `git push origin feature/mia-feature`
5. Apri Pull Request

### Convenzioni codice

- **PHP**: WordPress Coding Standards
- **JavaScript**: ESLint con config WordPress
- **CSS/SCSS**: Stylelint con config WordPress
- **Commit**: Conventional Commits (feat:, fix:, docs:, etc.)
