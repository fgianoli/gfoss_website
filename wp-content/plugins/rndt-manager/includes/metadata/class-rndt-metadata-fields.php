<?php
/**
 * Definizioni dei campi metadati per tipo di risorsa
 *
 * Specifica quali campi sono obbligatori, opzionali o non applicabili
 * per ogni tipo di risorsa (dataset, series, service, application).
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Metadata_Fields
 */
class RNDT_Metadata_Fields {

    /**
     * Campo obbligatorio
     */
    const MANDATORY = 'M';

    /**
     * Campo condizionale
     */
    const CONDITIONAL = 'C';

    /**
     * Campo opzionale
     */
    const OPTIONAL = 'O';

    /**
     * Campo non applicabile
     */
    const NOT_APPLICABLE = 'NA';

    /**
     * Ottieni la definizione di tutti i campi
     *
     * @return array
     */
    public static function get_all_fields() {
        return array(
            // ========== IDENTIFICAZIONE ==========
            'title' => array(
                'label'       => __( 'Titolo', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'text',
                'help'        => __( 'Nome caratteristico e spesso unico con il quale la risorsa è conosciuta.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'abstract' => array(
                'label'       => __( 'Descrizione', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'textarea',
                'help'        => __( 'Breve descrizione del contenuto della risorsa.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'resource_type' => array(
                'label'       => __( 'Tipo di risorsa', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'select',
                'options'     => 'resource_types',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'resource_identifier' => array(
                'label'       => __( 'Identificativo della risorsa', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'text',
                'help'        => __( 'Riferimento univoco che identifica la risorsa.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::MANDATORY,
            ),
            'resource_identifier_codespace' => array(
                'label'       => __( 'Namespace identificativo', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'text',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),
            'parent_identifier' => array(
                'label'       => __( 'Identificativo risorsa padre', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'text',
                'help'        => __( 'Identificativo del metadato della serie a cui appartiene il dataset.', 'rndt-manager' ),
                'dataset'     => self::CONDITIONAL,
                'series'      => self::NOT_APPLICABLE,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),
            'resource_language' => array(
                'label'       => __( 'Lingua della risorsa', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'select',
                'options'     => 'languages',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::CONDITIONAL,
                'application' => self::MANDATORY,
            ),
            'spatial_representation_type' => array(
                'label'       => __( 'Tipo di rappresentazione spaziale', 'rndt-manager' ),
                'section'     => 'identification',
                'type'        => 'select',
                'options'     => 'spatial_representation_types',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),

            // ========== CLASSIFICAZIONE ==========
            'topic_categories' => array(
                'label'       => __( 'Categoria ISO', 'rndt-manager' ),
                'section'     => 'classification',
                'type'        => 'multiselect',
                'options'     => 'topic_categories',
                'help'        => __( 'Categoria principale secondo la classificazione ISO 19115.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::MANDATORY,
            ),
            'inspire_themes' => array(
                'label'       => __( 'Tema INSPIRE', 'rndt-manager' ),
                'section'     => 'classification',
                'type'        => 'multiselect',
                'options'     => 'inspire_themes',
                'help'        => __( 'Tema INSPIRE secondo il thesaurus GEMET.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'keywords' => array(
                'label'       => __( 'Parole chiave', 'rndt-manager' ),
                'section'     => 'classification',
                'type'        => 'repeatable',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::OPTIONAL,
                'application' => self::OPTIONAL,
            ),

            // ========== ESTENSIONE GEOGRAFICA ==========
            'bbox_west' => array(
                'label'       => __( 'Longitudine Ovest', 'rndt-manager' ),
                'section'     => 'geographic_extent',
                'type'        => 'number',
                'min'         => -180,
                'max'         => 180,
                'step'        => 0.000001,
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'bbox_east' => array(
                'label'       => __( 'Longitudine Est', 'rndt-manager' ),
                'section'     => 'geographic_extent',
                'type'        => 'number',
                'min'         => -180,
                'max'         => 180,
                'step'        => 0.000001,
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'bbox_south' => array(
                'label'       => __( 'Latitudine Sud', 'rndt-manager' ),
                'section'     => 'geographic_extent',
                'type'        => 'number',
                'min'         => -90,
                'max'         => 90,
                'step'        => 0.000001,
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'bbox_north' => array(
                'label'       => __( 'Latitudine Nord', 'rndt-manager' ),
                'section'     => 'geographic_extent',
                'type'        => 'number',
                'min'         => -90,
                'max'         => 90,
                'step'        => 0.000001,
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'geographic_description' => array(
                'label'       => __( 'Descrizione geografica', 'rndt-manager' ),
                'section'     => 'geographic_extent',
                'type'        => 'text',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::OPTIONAL,
                'application' => self::OPTIONAL,
            ),

            // ========== RIFERIMENTO TEMPORALE ==========
            'date_creation' => array(
                'label'       => __( 'Data di creazione', 'rndt-manager' ),
                'section'     => 'temporal',
                'type'        => 'date',
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::CONDITIONAL,
                'application' => self::CONDITIONAL,
            ),
            'date_publication' => array(
                'label'       => __( 'Data di pubblicazione', 'rndt-manager' ),
                'section'     => 'temporal',
                'type'        => 'date',
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::CONDITIONAL,
                'application' => self::CONDITIONAL,
            ),
            'date_revision' => array(
                'label'       => __( 'Data di revisione', 'rndt-manager' ),
                'section'     => 'temporal',
                'type'        => 'date',
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::CONDITIONAL,
                'application' => self::CONDITIONAL,
            ),
            'temporal_extent_begin' => array(
                'label'       => __( 'Inizio estensione temporale', 'rndt-manager' ),
                'section'     => 'temporal',
                'type'        => 'date',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::OPTIONAL,
                'application' => self::OPTIONAL,
            ),
            'temporal_extent_end' => array(
                'label'       => __( 'Fine estensione temporale', 'rndt-manager' ),
                'section'     => 'temporal',
                'type'        => 'date',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::OPTIONAL,
                'application' => self::OPTIONAL,
            ),

            // ========== QUALITA ==========
            'lineage_statement' => array(
                'label'       => __( 'Genealogia', 'rndt-manager' ),
                'section'     => 'quality',
                'type'        => 'textarea',
                'help'        => __( 'Descrizione della storia e del ciclo di vita della risorsa.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),
            'spatial_resolution_scale' => array(
                'label'       => __( 'Scala equivalente', 'rndt-manager' ),
                'section'     => 'quality',
                'type'        => 'number',
                'help'        => __( 'Denominatore della scala (es. 10000 per 1:10000).', 'rndt-manager' ),
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),
            'spatial_resolution_distance' => array(
                'label'       => __( 'Risoluzione spaziale (distanza)', 'rndt-manager' ),
                'section'     => 'quality',
                'type'        => 'number',
                'step'        => 0.0001,
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),
            'conformity' => array(
                'label'       => __( 'Conformità', 'rndt-manager' ),
                'section'     => 'quality',
                'type'        => 'repeatable',
                'help'        => __( 'Dichiarazione di conformità alle specifiche INSPIRE.', 'rndt-manager' ),
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),

            // ========== VINCOLI ==========
            'use_limitation' => array(
                'label'       => __( 'Limitazioni d\'uso', 'rndt-manager' ),
                'section'     => 'constraints',
                'type'        => 'textarea',
                'dataset'     => self::OPTIONAL,
                'series'      => self::OPTIONAL,
                'service'     => self::OPTIONAL,
                'application' => self::OPTIONAL,
            ),
            'access_constraints' => array(
                'label'       => __( 'Vincoli di accesso', 'rndt-manager' ),
                'section'     => 'constraints',
                'type'        => 'select',
                'options'     => 'restriction_codes',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'use_constraints' => array(
                'label'       => __( 'Vincoli d\'uso', 'rndt-manager' ),
                'section'     => 'constraints',
                'type'        => 'select',
                'options'     => 'restriction_codes',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'other_constraints' => array(
                'label'       => __( 'Altri vincoli', 'rndt-manager' ),
                'section'     => 'constraints',
                'type'        => 'textarea',
                'help'        => __( 'Obbligatorio se vincoli = "otherRestrictions".', 'rndt-manager' ),
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::CONDITIONAL,
                'application' => self::CONDITIONAL,
            ),

            // ========== DISTRIBUZIONE ==========
            'distribution_formats' => array(
                'label'       => __( 'Formato di distribuzione', 'rndt-manager' ),
                'section'     => 'distribution',
                'type'        => 'repeatable',
                'dataset'     => self::MANDATORY,
                'series'      => self::CONDITIONAL,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),
            'online_resources' => array(
                'label'       => __( 'Risorse online', 'rndt-manager' ),
                'section'     => 'distribution',
                'type'        => 'repeatable',
                'dataset'     => self::CONDITIONAL,
                'series'      => self::CONDITIONAL,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),

            // ========== PARTE RESPONSABILE ==========
            'responsible_parties' => array(
                'label'       => __( 'Parti responsabili', 'rndt-manager' ),
                'section'     => 'responsible_party',
                'type'        => 'repeatable',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),

            // ========== SISTEMA DI RIFERIMENTO ==========
            'reference_system_code' => array(
                'label'       => __( 'Sistema di riferimento', 'rndt-manager' ),
                'section'     => 'reference_system',
                'type'        => 'select',
                'options'     => 'epsg_codes',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::NOT_APPLICABLE,
                'application' => self::OPTIONAL,
            ),

            // ========== INFO METADATO ==========
            'file_identifier' => array(
                'label'       => __( 'Identificativo del metadato', 'rndt-manager' ),
                'section'     => 'metadata_info',
                'type'        => 'text',
                'readonly'    => true,
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'metadata_language' => array(
                'label'       => __( 'Lingua del metadato', 'rndt-manager' ),
                'section'     => 'metadata_info',
                'type'        => 'select',
                'options'     => 'languages',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),
            'metadata_date' => array(
                'label'       => __( 'Data del metadato', 'rndt-manager' ),
                'section'     => 'metadata_info',
                'type'        => 'datetime',
                'dataset'     => self::MANDATORY,
                'series'      => self::MANDATORY,
                'service'     => self::MANDATORY,
                'application' => self::MANDATORY,
            ),

            // ========== SERVIZIO (ISO 19119) ==========
            'service_type' => array(
                'label'       => __( 'Tipo di servizio', 'rndt-manager' ),
                'section'     => 'service',
                'type'        => 'select',
                'options'     => 'service_types',
                'dataset'     => self::NOT_APPLICABLE,
                'series'      => self::NOT_APPLICABLE,
                'service'     => self::MANDATORY,
                'application' => self::NOT_APPLICABLE,
            ),
            'coupling_type' => array(
                'label'       => __( 'Tipo di coupling', 'rndt-manager' ),
                'section'     => 'service',
                'type'        => 'select',
                'options'     => 'coupling_types',
                'dataset'     => self::NOT_APPLICABLE,
                'series'      => self::NOT_APPLICABLE,
                'service'     => self::MANDATORY,
                'application' => self::NOT_APPLICABLE,
            ),
            'service_operations' => array(
                'label'       => __( 'Operazioni del servizio', 'rndt-manager' ),
                'section'     => 'service',
                'type'        => 'repeatable',
                'dataset'     => self::NOT_APPLICABLE,
                'series'      => self::NOT_APPLICABLE,
                'service'     => self::MANDATORY,
                'application' => self::NOT_APPLICABLE,
            ),
            'coupled_resources' => array(
                'label'       => __( 'Risorse accoppiate', 'rndt-manager' ),
                'section'     => 'service',
                'type'        => 'repeatable',
                'help'        => __( 'Obbligatorio se coupling type = tight.', 'rndt-manager' ),
                'dataset'     => self::NOT_APPLICABLE,
                'series'      => self::NOT_APPLICABLE,
                'service'     => self::CONDITIONAL,
                'application' => self::NOT_APPLICABLE,
            ),
        );
    }

    /**
     * Ottieni i campi per una sezione specifica
     *
     * @param string $section Nome sezione
     * @return array
     */
    public static function get_fields_by_section( $section ) {
        $all_fields = self::get_all_fields();
        return array_filter( $all_fields, function( $field ) use ( $section ) {
            return isset( $field['section'] ) && $field['section'] === $section;
        } );
    }

    /**
     * Ottieni i campi obbligatori per un tipo di risorsa
     *
     * @param string $resource_type Tipo di risorsa
     * @return array
     */
    public static function get_mandatory_fields( $resource_type ) {
        $all_fields = self::get_all_fields();
        return array_filter( $all_fields, function( $field ) use ( $resource_type ) {
            return isset( $field[ $resource_type ] ) && $field[ $resource_type ] === self::MANDATORY;
        } );
    }

    /**
     * Verifica se un campo e obbligatorio per un tipo di risorsa
     *
     * @param string $field_name    Nome campo
     * @param string $resource_type Tipo di risorsa
     * @return bool
     */
    public static function is_mandatory( $field_name, $resource_type ) {
        $all_fields = self::get_all_fields();
        if ( ! isset( $all_fields[ $field_name ] ) ) {
            return false;
        }
        return isset( $all_fields[ $field_name ][ $resource_type ] ) &&
               $all_fields[ $field_name ][ $resource_type ] === self::MANDATORY;
    }

    /**
     * Verifica se un campo e applicabile per un tipo di risorsa
     *
     * @param string $field_name    Nome campo
     * @param string $resource_type Tipo di risorsa
     * @return bool
     */
    public static function is_applicable( $field_name, $resource_type ) {
        $all_fields = self::get_all_fields();
        if ( ! isset( $all_fields[ $field_name ] ) ) {
            return false;
        }
        return isset( $all_fields[ $field_name ][ $resource_type ] ) &&
               $all_fields[ $field_name ][ $resource_type ] !== self::NOT_APPLICABLE;
    }

    /**
     * Ottieni le sezioni del wizard
     *
     * @return array
     */
    public static function get_sections() {
        return array(
            'identification'     => __( 'Identificazione', 'rndt-manager' ),
            'classification'     => __( 'Classificazione', 'rndt-manager' ),
            'geographic_extent'  => __( 'Estensione geografica', 'rndt-manager' ),
            'temporal'           => __( 'Riferimento temporale', 'rndt-manager' ),
            'quality'            => __( 'Qualità', 'rndt-manager' ),
            'constraints'        => __( 'Vincoli', 'rndt-manager' ),
            'distribution'       => __( 'Distribuzione', 'rndt-manager' ),
            'responsible_party'  => __( 'Parte responsabile', 'rndt-manager' ),
            'reference_system'   => __( 'Sistema di riferimento', 'rndt-manager' ),
            'metadata_info'      => __( 'Info metadato', 'rndt-manager' ),
            'service'            => __( 'Dettagli servizio', 'rndt-manager' ),
        );
    }

    /**
     * Ottieni le sezioni applicabili per un tipo di risorsa
     *
     * @param string $resource_type Tipo di risorsa
     * @return array
     */
    public static function get_sections_for_type( $resource_type ) {
        $all_sections = self::get_sections();

        // La sezione service e solo per i servizi
        if ( 'service' !== $resource_type ) {
            unset( $all_sections['service'] );
        }

        // La sezione reference_system non e per i servizi
        if ( 'service' === $resource_type ) {
            unset( $all_sections['reference_system'] );
        }

        return $all_sections;
    }
}
