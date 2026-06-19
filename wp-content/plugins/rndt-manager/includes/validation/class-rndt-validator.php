<?php
/**
 * Orchestratore validazione RNDT
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-validation-rules.php';
require_once __DIR__ . '/class-rndt-xsd-validator.php';

/**
 * Classe RNDT_Validator
 *
 * Valida metadati secondo le regole RNDT 2020 e opzionalmente XSD
 */
class RNDT_Validator {

    /**
     * Errori di validazione
     *
     * @var array
     */
    private $errors = array();

    /**
     * Avvisi (non bloccanti)
     *
     * @var array
     */
    private $warnings = array();

    /**
     * Validatore XSD
     *
     * @var RNDT_XSD_Validator
     */
    private $xsd_validator;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->xsd_validator = new RNDT_XSD_Validator();
    }

    /**
     * Valida metadati completi
     *
     * @param RNDT_Metadata_Model $metadata Modello metadati
     * @param bool                $include_xsd Includi validazione XSD
     * @return array Array con 'valid', 'errors', 'warnings'
     */
    public function validate( RNDT_Metadata_Model $metadata, $include_xsd = false ) {
        $this->errors = array();
        $this->warnings = array();

        $data = $metadata->to_array();
        $resource_type = $metadata->resource_type ?? 'dataset';

        // 1. Validazione campi obbligatori
        $this->validate_required_fields( $data, $resource_type );

        // 2. Validazione formato campi
        $this->validate_field_formats( $data );

        // 3. Validazione cross-field
        $this->validate_cross_field( $data, $resource_type );

        // 4. Validazione INSPIRE
        $this->validate_inspire_requirements( $data, $metadata );

        // 5. Validazione contatti
        $this->validate_contacts( $metadata->get_responsible_parties() );

        // 6. Validazione specifica per tipo risorsa
        $this->validate_resource_type_specific( $data, $resource_type, $metadata );

        // 7. Validazione XSD (opzionale)
        if ( $include_xsd && empty( $this->errors ) ) {
            $this->validate_xsd( $metadata );
        }

        return array(
            'valid'    => empty( $this->errors ),
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        );
    }

    /**
     * Valida array dati (per uso da API)
     *
     * @param array $data          Array dati
     * @param string $resource_type Tipo risorsa
     * @return array
     */
    public function validate_array( $data, $resource_type = 'dataset' ) {
        $this->errors = array();
        $this->warnings = array();

        // 1. Validazione campi obbligatori
        $this->validate_required_fields( $data, $resource_type );

        // 2. Validazione formato campi
        $this->validate_field_formats( $data );

        // 3. Validazione cross-field
        $this->validate_cross_field( $data, $resource_type );

        // 4. Validazione INSPIRE base
        $keywords = $data['keywords'] ?? array();
        $conformity = $data['conformity'] ?? array();

        if ( ! RNDT_Validation_Rules::has_inspire_keyword( $keywords ) ) {
            $this->add_warning( 'keywords', __( 'Nessuna parola chiave INSPIRE trovata. Obbligatoria per conformità INSPIRE.', 'rndt-manager' ) );
        }

        if ( ! RNDT_Validation_Rules::has_inspire_conformity( $conformity ) ) {
            $this->add_warning( 'conformity', __( 'Nessuna dichiarazione di conformità INSPIRE trovata.', 'rndt-manager' ) );
        }

        // 5. Validazione contatti
        $parties = $data['responsible_parties'] ?? array();
        $this->validate_contacts( $parties );

        // 6. Validazione specifica per tipo risorsa
        $this->validate_resource_type_specific_array( $data, $resource_type );

        return array(
            'valid'    => empty( $this->errors ),
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        );
    }

    /**
     * Valida campi obbligatori
     *
     * @param array  $data          Dati metadato
     * @param string $resource_type Tipo risorsa
     */
    private function validate_required_fields( $data, $resource_type ) {
        $required = RNDT_Validation_Rules::get_required_fields();

        // Campi comuni
        foreach ( $required['common'] as $field => $label ) {
            if ( $this->is_empty( $data[ $field ] ?? null ) ) {
                $this->add_error( $field, sprintf(
                    /* translators: %s: Nome campo */
                    __( 'Il campo "%s" è obbligatorio.', 'rndt-manager' ),
                    $label
                ) );
            }
        }

        // Campi specifici per tipo risorsa
        if ( isset( $required[ $resource_type ] ) ) {
            foreach ( $required[ $resource_type ] as $field => $label ) {
                if ( $this->is_empty( $data[ $field ] ?? null ) ) {
                    $this->add_error( $field, sprintf(
                        /* translators: %s: Nome campo */
                        __( 'Il campo "%s" è obbligatorio per questo tipo di risorsa.', 'rndt-manager' ),
                        $label
                    ) );
                }
            }
        }
    }

    /**
     * Valida formato campi
     *
     * @param array $data Dati metadato
     */
    private function validate_field_formats( $data ) {
        // Date
        $date_fields = array( 'metadata_date', 'date_creation', 'date_publication', 'date_revision' );
        foreach ( $date_fields as $field ) {
            if ( ! empty( $data[ $field ] ) && ! RNDT_Validation_Rules::is_valid_date( $data[ $field ] ) ) {
                $this->add_error( $field, sprintf(
                    /* translators: %s: Nome campo */
                    __( 'Il campo "%s" non è in formato data valido (YYYY-MM-DD).', 'rndt-manager' ),
                    $field
                ) );
            }
        }

        // Codice EPSG
        if ( ! empty( $data['reference_system_code'] ) ) {
            if ( ! RNDT_Validation_Rules::is_valid_epsg( $data['reference_system_code'] ) ) {
                $this->add_error( 'reference_system_code', __( 'Il codice EPSG non è in formato valido.', 'rndt-manager' ) );
            }
        }

        // Bounding box
        if ( isset( $data['bbox_west'] ) && isset( $data['bbox_east'] )
          && isset( $data['bbox_south'] ) && isset( $data['bbox_north'] ) ) {
            $bbox_errors = RNDT_Validation_Rules::validate_bbox(
                (float) $data['bbox_west'],
                (float) $data['bbox_east'],
                (float) $data['bbox_south'],
                (float) $data['bbox_north']
            );
            foreach ( $bbox_errors as $error ) {
                $this->add_error( 'bbox', $error );
            }
        }

        // Lingua
        if ( ! empty( $data['metadata_language'] ) ) {
            $valid_langs = RNDT_Validation_Rules::get_valid_language_codes();
            $lang = $data['metadata_language'];
            // Accetta anche codici 2 lettere
            if ( strlen( $lang ) === 2 ) {
                $lang = RNDT_XML_Codelists::get_language_code( $lang );
            }
            if ( ! in_array( $lang, $valid_langs, true ) ) {
                $this->add_warning( 'metadata_language', __( 'Codice lingua non standard ISO 639-2/B.', 'rndt-manager' ) );
            }
        }

        // Topic categories
        if ( ! empty( $data['topic_categories'] ) && is_array( $data['topic_categories'] ) ) {
            $valid_cats = RNDT_Validation_Rules::get_valid_topic_categories();
            foreach ( $data['topic_categories'] as $cat ) {
                if ( ! in_array( $cat, $valid_cats, true ) ) {
                    $this->add_error( 'topic_categories', sprintf(
                        /* translators: %s: Categoria */
                        __( 'Categoria tematica non valida: %s', 'rndt-manager' ),
                        $cat
                    ) );
                }
            }
        }

        // Equivalent scale (deve essere intero positivo)
        if ( ! empty( $data['equivalent_scale'] ) ) {
            if ( ! is_numeric( $data['equivalent_scale'] ) || (int) $data['equivalent_scale'] < 1 ) {
                $this->add_error( 'equivalent_scale', __( 'La scala equivalente deve essere un intero positivo.', 'rndt-manager' ) );
            }
        }
    }

    /**
     * Valida regole cross-field
     *
     * @param array  $data          Dati metadato
     * @param string $resource_type Tipo risorsa
     */
    private function validate_cross_field( $data, $resource_type ) {
        // Almeno una data è obbligatoria
        if ( ! RNDT_Validation_Rules::has_at_least_one_date( $data ) ) {
            $this->add_error( 'dates', __( 'Almeno una data (creazione, pubblicazione o revisione) è obbligatoria.', 'rndt-manager' ) );
        }

        // Bounding box completo se parzialmente fornito
        $bbox_fields = array( 'bbox_west', 'bbox_east', 'bbox_south', 'bbox_north' );
        $bbox_provided = 0;
        foreach ( $bbox_fields as $field ) {
            if ( ! $this->is_empty( $data[ $field ] ?? null ) ) {
                $bbox_provided++;
            }
        }
        if ( $bbox_provided > 0 && $bbox_provided < 4 ) {
            $this->add_error( 'bbox', __( 'Il bounding box deve essere completo (tutti e 4 i valori).', 'rndt-manager' ) );
        }

        // Temporal extent: begin deve essere prima di end
        if ( ! empty( $data['temporal_extent_begin'] ) && ! empty( $data['temporal_extent_end'] ) ) {
            if ( $data['temporal_extent_begin'] > $data['temporal_extent_end'] ) {
                $this->add_error( 'temporal_extent', __( 'La data di inizio estensione temporale deve essere precedente alla data di fine.', 'rndt-manager' ) );
            }
        }

        // Other constraints obbligatorio se use_constraints = otherRestrictions
        if ( ( $data['use_constraints'] ?? '' ) === 'otherRestrictions' ) {
            if ( $this->is_empty( $data['other_constraints'] ?? null ) ) {
                $this->add_error( 'other_constraints', __( 'Il campo "Altri vincoli" è obbligatorio quando i vincoli d\'uso sono "otherRestrictions".', 'rndt-manager' ) );
            }
        }
    }

    /**
     * Valida requisiti INSPIRE
     *
     * @param array               $data     Dati metadato
     * @param RNDT_Metadata_Model $metadata Modello
     */
    private function validate_inspire_requirements( $data, $metadata ) {
        $keywords = $metadata->get_keywords();
        $conformity = $metadata->get_conformity();

        // Keyword INSPIRE obbligatoria
        if ( ! RNDT_Validation_Rules::has_inspire_keyword( $keywords ) ) {
            $this->add_error( 'keywords', __( 'Almeno una parola chiave INSPIRE con thesaurus GEMET è obbligatoria.', 'rndt-manager' ) );
        }

        // Conformità INSPIRE obbligatoria
        if ( ! RNDT_Validation_Rules::has_inspire_conformity( $conformity ) ) {
            $this->add_warning( 'conformity', __( 'Si raccomanda di dichiarare la conformità alle specifiche INSPIRE.', 'rndt-manager' ) );
        }

        // Almeno una topic category per dataset/series
        $resource_type = $data['resource_type'] ?? 'dataset';
        if ( in_array( $resource_type, array( 'dataset', 'series' ), true ) ) {
            $has_topic = ! empty( $data['topic_categories'] );
            // Controlla anche nelle keywords (le topic categories sono salvate come keyword_type=topicCategory)
            if ( ! $has_topic ) {
                $kws = $data['keywords'] ?? $keywords ?? array();
                foreach ( $kws as $kw ) {
                    if ( isset( $kw['keyword_type'] ) && 'topicCategory' === $kw['keyword_type'] ) {
                        $has_topic = true;
                        break;
                    }
                }
            }
            if ( ! $has_topic ) {
                $this->add_error( 'topic_categories', __( 'Almeno una categoria tematica è obbligatoria.', 'rndt-manager' ) );
            }
        }

        // Lineage obbligatorio per dataset/series
        if ( in_array( $resource_type, array( 'dataset', 'series' ), true ) ) {
            if ( $this->is_empty( $data['lineage'] ?? null ) ) {
                $this->add_error( 'lineage', __( 'La genealogia (lineage) è obbligatoria per dataset e serie.', 'rndt-manager' ) );
            }
        }
    }

    /**
     * Valida contatti
     *
     * @param array $parties Array responsible parties
     */
    private function validate_contacts( $parties ) {
        // Contatto metadato obbligatorio
        if ( ! RNDT_Validation_Rules::has_metadata_contact( $parties ) ) {
            $this->add_error( 'metadata_contact', __( 'È obbligatorio un contatto per il metadato.', 'rndt-manager' ) );
        }

        // Contatto risorsa obbligatorio
        if ( ! RNDT_Validation_Rules::has_resource_poc( $parties ) ) {
            $this->add_error( 'resource_poc', __( 'È obbligatorio un punto di contatto per la risorsa.', 'rndt-manager' ) );
        }

        // Valida formato email per tutti i contatti
        foreach ( $parties as $party ) {
            if ( ! empty( $party['email'] ) && ! RNDT_Validation_Rules::is_valid_email( $party['email'] ) ) {
                $this->add_error( 'email', sprintf(
                    /* translators: %s: Email */
                    __( 'Email non valida: %s', 'rndt-manager' ),
                    $party['email']
                ) );
            }
            if ( ! empty( $party['url'] ) && ! RNDT_Validation_Rules::is_valid_url( $party['url'] ) ) {
                $this->add_warning( 'url', sprintf(
                    /* translators: %s: URL */
                    __( 'URL potenzialmente non valido: %s', 'rndt-manager' ),
                    $party['url']
                ) );
            }
        }
    }

    /**
     * Valida requisiti specifici per tipo risorsa
     *
     * @param array               $data          Dati metadato
     * @param string              $resource_type Tipo risorsa
     * @param RNDT_Metadata_Model $metadata      Modello
     */
    private function validate_resource_type_specific( $data, $resource_type, $metadata ) {
        if ( $resource_type === 'service' ) {
            // Service type valido
            if ( ! empty( $data['service_type'] ) ) {
                $valid_types = RNDT_Validation_Rules::get_valid_service_types();
                if ( ! in_array( $data['service_type'], $valid_types, true ) ) {
                    $this->add_error( 'service_type', __( 'Tipo di servizio non valido.', 'rndt-manager' ) );
                }
            }

            // Coupling type valido
            if ( ! empty( $data['coupling_type'] ) ) {
                $valid_coupling = RNDT_Validation_Rules::get_valid_coupling_types();
                if ( ! in_array( $data['coupling_type'], $valid_coupling, true ) ) {
                    $this->add_error( 'coupling_type', __( 'Tipo di coupling non valido.', 'rndt-manager' ) );
                }
            }

            // Coupled resources per tight coupling
            if ( ( $data['coupling_type'] ?? '' ) === 'tight' ) {
                $coupled = $metadata->get_coupled_resources();
                if ( empty( $coupled ) ) {
                    $this->add_error( 'coupled_resources', __( 'Le risorse accoppiate sono obbligatorie per coupling tight.', 'rndt-manager' ) );
                }
            }

            // Almeno un'operazione
            $operations = $metadata->get_service_operations();
            if ( empty( $operations ) ) {
                $this->add_error( 'service_operations', __( 'Almeno un\'operazione del servizio è obbligatoria.', 'rndt-manager' ) );
            }
        }

        if ( $resource_type === 'series' ) {
            // Parent identifier consigliato
            if ( $this->is_empty( $data['parent_identifier'] ?? null ) ) {
                $this->add_warning( 'parent_identifier', __( 'Si consiglia di specificare l\'identificativo parent per le serie.', 'rndt-manager' ) );
            }
        }
    }

    /**
     * Valida requisiti specifici per tipo risorsa (da array)
     *
     * @param array  $data          Dati metadato
     * @param string $resource_type Tipo risorsa
     */
    private function validate_resource_type_specific_array( $data, $resource_type ) {
        if ( $resource_type === 'service' ) {
            // Service type valido
            if ( ! empty( $data['service_type'] ) ) {
                $valid_types = RNDT_Validation_Rules::get_valid_service_types();
                if ( ! in_array( $data['service_type'], $valid_types, true ) ) {
                    $this->add_error( 'service_type', __( 'Tipo di servizio non valido.', 'rndt-manager' ) );
                }
            }

            // Coupling type valido
            if ( ! empty( $data['coupling_type'] ) ) {
                $valid_coupling = RNDT_Validation_Rules::get_valid_coupling_types();
                if ( ! in_array( $data['coupling_type'], $valid_coupling, true ) ) {
                    $this->add_error( 'coupling_type', __( 'Tipo di coupling non valido.', 'rndt-manager' ) );
                }
            }

            // Coupled resources per tight coupling
            if ( ( $data['coupling_type'] ?? '' ) === 'tight' ) {
                $coupled = $data['coupled_resources'] ?? array();
                if ( empty( $coupled ) ) {
                    $this->add_error( 'coupled_resources', __( 'Le risorse accoppiate sono obbligatorie per coupling tight.', 'rndt-manager' ) );
                }
            }

            // Almeno un'operazione
            $operations = $data['service_operations'] ?? array();
            if ( empty( $operations ) ) {
                $this->add_error( 'service_operations', __( 'Almeno un\'operazione del servizio è obbligatoria.', 'rndt-manager' ) );
            }
        }
    }

    /**
     * Valida XSD
     *
     * @param RNDT_Metadata_Model $metadata Modello
     */
    private function validate_xsd( $metadata ) {
        require_once RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-generator.php';

        $generator = new RNDT_XML_Generator();
        $xml = $generator->generate( $metadata );

        if ( ! $this->xsd_validator->validate( $xml ) ) {
            foreach ( $this->xsd_validator->get_errors() as $error ) {
                if ( $error['type'] === 'warning' ) {
                    $this->add_warning( 'xsd', $error['message'] );
                } else {
                    $this->add_error( 'xsd', $error['message'] );
                }
            }
        }
    }

    /**
     * Aggiungi errore
     *
     * @param string $field   Campo
     * @param string $message Messaggio
     */
    private function add_error( $field, $message ) {
        $this->errors[] = array(
            'field'   => $field,
            'message' => $message,
        );
    }

    /**
     * Aggiungi avviso
     *
     * @param string $field   Campo
     * @param string $message Messaggio
     */
    private function add_warning( $field, $message ) {
        $this->warnings[] = array(
            'field'   => $field,
            'message' => $message,
        );
    }

    /**
     * Verifica se un valore è vuoto
     *
     * @param mixed $value Valore
     * @return bool
     */
    private function is_empty( $value ) {
        if ( is_null( $value ) ) {
            return true;
        }
        if ( is_string( $value ) && trim( $value ) === '' ) {
            return true;
        }
        if ( is_array( $value ) && empty( $value ) ) {
            return true;
        }
        return false;
    }

    /**
     * Ottieni errori
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Ottieni avvisi
     *
     * @return array
     */
    public function get_warnings() {
        return $this->warnings;
    }

    /**
     * Ottieni tutti i messaggi formattati
     *
     * @return string
     */
    public function get_messages_string() {
        $messages = array();

        foreach ( $this->errors as $error ) {
            $messages[] = sprintf( '[ERRORE] %s: %s', $error['field'], $error['message'] );
        }

        foreach ( $this->warnings as $warning ) {
            $messages[] = sprintf( '[AVVISO] %s: %s', $warning['field'], $warning['message'] );
        }

        return implode( "\n", $messages );
    }
}
