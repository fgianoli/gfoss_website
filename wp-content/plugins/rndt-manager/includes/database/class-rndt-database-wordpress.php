<?php
/**
 * Driver database WordPress (MariaDB/MySQL) per RNDT Manager
 *
 * Implementazione basata su $wpdb. Traduce automaticamente le query
 * PostgreSQL-style (named params, ILIKE, ::text) in MySQL-compatible.
 *
 * @package RNDT_Manager
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Database_WordPress
 */
class RNDT_Database_WordPress implements RNDT_Database_Interface {

    /**
     * Ultimo errore
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Lista tabelle RNDT
     *
     * @var array
     */
    private static $table_names = array(
        'rndt_metadata',
        'rndt_keywords',
        'rndt_responsible_parties',
        'rndt_online_resources',
        'rndt_distribution_formats',
        'rndt_conformity',
        'rndt_service_operations',
        'rndt_coupled_resources',
        'rndt_responsible_presets',
        'rndt_inspire_themes',
        'rndt_topic_categories',
    );

    /**
     * Costruttore
     */
    public function __construct() {
        // Nessuna configurazione necessaria: usa il DB WordPress esistente
    }

    /**
     * Risolvi il nome della tabella (senza prefisso WordPress)
     *
     * Le tabelle RNDT usano nomi diretti: rndt_metadata, rndt_keywords, etc.
     *
     * @param string $table Nome tabella (es. "public.rndt_metadata" o "rndt_metadata")
     * @return string Nome pulito (es. "rndt_metadata")
     */
    private function resolve_table( $table ) {
        // Rimuovi eventuale prefisso schema PostgreSQL (es. "public.rndt_metadata")
        if ( strpos( $table, '.' ) !== false ) {
            $table = substr( $table, strpos( $table, '.' ) + 1 );
        }

        return $table;
    }

    /**
     * Traduci una query PostgreSQL-style in MySQL-compatible
     *
     * Gestisce:
     * - Named params (:name) -> positional (%s/%d/%f)
     * - ILIKE -> LIKE
     * - ::text casts -> rimossi
     * - Nomi tabella -> ripuliti dallo schema PostgreSQL
     * - Valori NULL -> literal NULL nella query
     *
     * @param string $sql    Query SQL con placeholder :named
     * @param array  $params Parametri associativi (:name => value)
     * @return array [ sql_tradotta, valori_ordinati ]
     */
    private function translate_query( $sql, $params = array() ) {
        // Rimuovi sintassi PostgreSQL
        $sql = preg_replace( '/::text/', '', $sql );
        $sql = preg_replace( '/\bILIKE\b/i', 'LIKE', $sql );

        // Prefissa nomi tabella
        $sql = $this->prefix_table_names( $sql );

        if ( empty( $params ) ) {
            return array( $sql, array() );
        }

        // Step 1: Trova tutte le occorrenze dei parametri e le loro posizioni nel SQL
        // Serve per costruire ordered_values nell'ordine di apparizione nel SQL,
        // non nell'ordine di sostituzione (che e' per lunghezza nome).
        $occurrences = array();
        foreach ( $params as $name => $value ) {
            $escaped_name = preg_quote( $name, '/' );
            if ( preg_match_all( '/' . $escaped_name . '\b/', $sql, $matches, PREG_OFFSET_CAPTURE ) ) {
                foreach ( $matches[0] as $match ) {
                    $occurrences[] = array(
                        'pos'   => $match[1],
                        'name'  => $name,
                        'value' => $value,
                    );
                }
            }
        }

        // Ordina per posizione nel SQL (ascendente)
        usort( $occurrences, function( $a, $b ) {
            return $a['pos'] - $b['pos'];
        } );

        // Step 2: Sostituisci named params con placeholder posizionali
        // Ordina per lunghezza nome DESC per evitare sostituzioni parziali (es. :label_it vs :label)
        $param_names = array_keys( $params );
        usort( $param_names, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        } );

        foreach ( $param_names as $name ) {
            $value = $params[ $name ];
            $escaped_name = preg_quote( $name, '/' );

            if ( null === $value ) {
                // NULL: sostituisci con literal NULL
                $sql = preg_replace( '/' . $escaped_name . '\b/', 'NULL', $sql );
            } else {
                // Determina placeholder in base al tipo
                $placeholder = '%s';
                if ( is_int( $value ) ) {
                    $placeholder = '%d';
                } elseif ( is_float( $value ) ) {
                    $placeholder = '%f';
                }

                // Sostituisci tutte le occorrenze
                $sql = preg_replace( '/' . $escaped_name . '\b/', $placeholder, $sql );
            }
        }

        // Step 3: Costruisci ordered_values dall'ordine di posizione nel SQL (non dall'ordine di sostituzione)
        $ordered_values = array();
        foreach ( $occurrences as $occ ) {
            if ( null !== $occ['value'] ) {
                $ordered_values[] = $occ['value'];
            }
        }

        return array( $sql, $ordered_values );
    }

    /**
     * Ripulisci i nomi delle tabelle RNDT nella query SQL
     *
     * Rimuove prefissi schema PostgreSQL (es. "public.rndt_metadata" -> "rndt_metadata").
     * Le tabelle RNDT usano nomi diretti senza prefisso WordPress.
     *
     * @param string $sql Query SQL
     * @return string Query con tabelle ripulite
     */
    private function prefix_table_names( $sql ) {
        // Rimuovi prefissi schema PostgreSQL (es. "public.")
        $sql = preg_replace( '/\bpublic\./', '', $sql );

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function get_connection() {
        global $wpdb;
        return $wpdb;
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection( $config = null ) {
        global $wpdb;
        $result = $wpdb->get_var( 'SELECT 1' );
        if ( $wpdb->last_error ) {
            return $wpdb->last_error;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * {@inheritdoc}
     */
    public function get_schema() {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function query( $sql, $params = array() ) {
        global $wpdb;

        list( $sql, $values ) = $this->translate_query( $sql, $params );

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $wpdb->prepare( $sql, $values );
        } else {
            $prepared = $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query( $prepared );
        if ( false === $result ) {
            $this->last_error = $wpdb->last_error;
            return false;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function insert( $table, $data ) {
        global $wpdb;

        $table = $this->resolve_table( $table );

        // Genera UUID per file_identifier se mancante (in PostgreSQL lo fa gen_random_uuid())
        if ( strpos( $table, 'rndt_metadata' ) !== false && empty( $data['file_identifier'] ) ) {
            $data['file_identifier'] = wp_generate_uuid4();
        }

        // Rimuovi campi con valore NULL: $wpdb->insert() non gestisce bene i NULL,
        // li converte in stringhe vuote. Rimuovendoli, il DB usa il DEFAULT NULL.
        $clean_data = array();
        foreach ( $data as $key => $value ) {
            if ( null !== $value ) {
                $clean_data[ $key ] = $value;
            }
        }

        // Costruisci array formati per $wpdb->insert()
        $formats = array();
        foreach ( $clean_data as $value ) {
            if ( is_int( $value ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $value ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table, $clean_data, $formats );
        if ( false === $result ) {
            $this->last_error = $wpdb->last_error;
            return false;
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * {@inheritdoc}
     */
    public function update( $table, $data, $where ) {
        global $wpdb;

        $table = $this->resolve_table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update( $table, $data, $where );
        if ( false === $result ) {
            $this->last_error = $wpdb->last_error;
            return false;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete( $table, $where ) {
        global $wpdb;

        $table = $this->resolve_table( $table );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete( $table, $where );
        if ( false === $result ) {
            $this->last_error = $wpdb->last_error;
            return false;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function get_row( $sql, $params = array() ) {
        global $wpdb;

        list( $sql, $values ) = $this->translate_query( $sql, $params );

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $wpdb->prepare( $sql, $values );
        } else {
            $prepared = $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $prepared, ARRAY_A );
        if ( $wpdb->last_error ) {
            $this->last_error = $wpdb->last_error;
        }
        return $row ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function get_results( $sql, $params = array() ) {
        global $wpdb;

        list( $sql, $values ) = $this->translate_query( $sql, $params );

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $wpdb->prepare( $sql, $values );
        } else {
            $prepared = $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $prepared, ARRAY_A );
        if ( $wpdb->last_error ) {
            $this->last_error = $wpdb->last_error;
        }
        return $results ?: array();
    }

    /**
     * {@inheritdoc}
     */
    public function get_var( $sql, $params = array() ) {
        global $wpdb;

        list( $sql, $values ) = $this->translate_query( $sql, $params );

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $wpdb->prepare( $sql, $values );
        } else {
            $prepared = $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $var = $wpdb->get_var( $prepared );
        if ( $wpdb->last_error ) {
            $this->last_error = $wpdb->last_error;
        }
        return $var;
    }

    /**
     * {@inheritdoc}
     */
    public function begin_transaction() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return false !== $wpdb->query( 'START TRANSACTION' );
    }

    /**
     * {@inheritdoc}
     */
    public function commit() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return false !== $wpdb->query( 'COMMIT' );
    }

    /**
     * {@inheritdoc}
     */
    public function rollback() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return false !== $wpdb->query( 'ROLLBACK' );
    }

    /**
     * {@inheritdoc}
     */
    public function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array();

        // Tabella principale metadati
        $tables[] = "CREATE TABLE rndt_metadata (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wp_post_id bigint(20) unsigned DEFAULT NULL,
            resource_type varchar(20) NOT NULL DEFAULT 'dataset',
            file_identifier varchar(36) NOT NULL DEFAULT '',
            resource_identifier varchar(255) DEFAULT NULL,
            resource_identifier_codespace varchar(255) DEFAULT NULL,
            parent_identifier varchar(255) DEFAULT NULL,
            title text NOT NULL,
            abstract text,
            resource_language varchar(10) DEFAULT 'ita',
            character_set varchar(20) DEFAULT 'utf8',
            hierarchy_level_name varchar(100) DEFAULT NULL,
            spatial_representation_type varchar(50) DEFAULT NULL,
            date_creation date DEFAULT NULL,
            date_publication date DEFAULT NULL,
            date_revision date DEFAULT NULL,
            temporal_extent_begin date DEFAULT NULL,
            temporal_extent_end date DEFAULT NULL,
            bbox_west decimal(10,6) DEFAULT NULL,
            bbox_east decimal(10,6) DEFAULT NULL,
            bbox_south decimal(10,6) DEFAULT NULL,
            bbox_north decimal(10,6) DEFAULT NULL,
            geographic_description text,
            lineage_statement text,
            spatial_resolution_scale int(11) DEFAULT NULL,
            spatial_resolution_distance decimal(10,4) DEFAULT NULL,
            spatial_resolution_units varchar(20) DEFAULT NULL,
            use_limitation text,
            access_constraints varchar(50) DEFAULT 'otherRestrictions',
            use_constraints varchar(50) DEFAULT 'otherRestrictions',
            other_constraints text,
            classification varchar(50) DEFAULT NULL,
            reference_system_code varchar(50) DEFAULT NULL,
            reference_system_codespace varchar(255) DEFAULT NULL,
            metadata_language varchar(10) DEFAULT 'ita',
            metadata_character_set varchar(20) DEFAULT 'utf8',
            metadata_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            metadata_standard_name varchar(255) DEFAULT 'DM - Regole tecniche RNDT',
            metadata_standard_version varchar(50) DEFAULT '10 novembre 2011',
            service_type varchar(50) DEFAULT NULL,
            service_type_version varchar(100) DEFAULT NULL,
            coupling_type varchar(20) DEFAULT NULL,
            maintenance_frequency varchar(50) DEFAULT NULL,
            validation_status varchar(20) DEFAULT 'not_validated',
            validation_errors longtext,
            last_validated_at datetime DEFAULT NULL,
            csw_published_at datetime DEFAULT NULL,
            csw_record_id varchar(255) DEFAULT NULL,
            geoserver_published_at datetime DEFAULT NULL,
            xml_cache longtext,
            xml_cache_date datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY wp_post_id (wp_post_id),
            KEY resource_type (resource_type),
            KEY file_identifier (file_identifier),
            KEY validation_status (validation_status)
        ) $charset_collate;";

        // Tabella keywords
        $tables[] = "CREATE TABLE rndt_keywords (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            keyword varchar(500) NOT NULL,
            keyword_type varchar(50) DEFAULT 'theme',
            thesaurus_name varchar(500) DEFAULT NULL,
            thesaurus_date date DEFAULT NULL,
            thesaurus_date_type varchar(20) DEFAULT 'publication',
            anchor_href varchar(1000) DEFAULT NULL,
            language varchar(10) DEFAULT 'ita',
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella responsible parties
        $tables[] = "CREATE TABLE rndt_responsible_parties (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            context varchar(50) NOT NULL,
            individual_name varchar(255) DEFAULT NULL,
            organisation_name varchar(500) NOT NULL,
            position_name varchar(255) DEFAULT NULL,
            role varchar(50) NOT NULL,
            phone_voice varchar(100) DEFAULT NULL,
            phone_fax varchar(100) DEFAULT NULL,
            delivery_point varchar(500) DEFAULT NULL,
            city varchar(255) DEFAULT NULL,
            admin_area varchar(255) DEFAULT NULL,
            postal_code varchar(20) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            online_resource_url varchar(1000) DEFAULT NULL,
            ipa_code varchar(50) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella online resources
        $tables[] = "CREATE TABLE rndt_online_resources (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            linkage_url varchar(2000) NOT NULL,
            protocol varchar(100) DEFAULT NULL,
            name varchar(500) DEFAULT NULL,
            description text,
            function varchar(50) DEFAULT NULL,
            application_profile varchar(500) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella distribution formats
        $tables[] = "CREATE TABLE rndt_distribution_formats (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            format_name varchar(255) NOT NULL,
            format_version varchar(100) DEFAULT NULL,
            format_spec varchar(500) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella conformity
        $tables[] = "CREATE TABLE rndt_conformity (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            specification_title varchar(1000) NOT NULL,
            specification_date date NOT NULL,
            specification_date_type varchar(20) DEFAULT 'publication',
            degree varchar(20) DEFAULT NULL,
            explanation text,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella service operations
        $tables[] = "CREATE TABLE rndt_service_operations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            operation_name varchar(255) NOT NULL,
            dcp varchar(50) DEFAULT 'WebServices',
            connect_point_url varchar(2000) DEFAULT NULL,
            operation_description text,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella coupled resources
        $tables[] = "CREATE TABLE rndt_coupled_resources (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metadata_id bigint(20) unsigned NOT NULL,
            identifier varchar(255) NOT NULL,
            resource_title varchar(500) DEFAULT NULL,
            resource_url varchar(2000) DEFAULT NULL,
            linked_metadata_id bigint(20) unsigned DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY metadata_id (metadata_id)
        ) $charset_collate;";

        // Tabella presets parti responsabili (standalone, no FK)
        $tables[] = "CREATE TABLE rndt_responsible_presets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            preset_name varchar(255) NOT NULL,
            organisation_name varchar(500) NOT NULL,
            individual_name varchar(255) DEFAULT NULL,
            position_name varchar(255) DEFAULT NULL,
            role_code varchar(50) DEFAULT 'pointOfContact',
            phone varchar(100) DEFAULT NULL,
            fax varchar(100) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            delivery_point varchar(500) DEFAULT NULL,
            city varchar(255) DEFAULT NULL,
            admin_area varchar(255) DEFAULT NULL,
            postal_code varchar(20) DEFAULT NULL,
            country varchar(100) DEFAULT 'Italia',
            online_resource_url varchar(1000) DEFAULT NULL,
            ipa_code varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Tabella INSPIRE themes (lookup)
        $tables[] = "CREATE TABLE rndt_inspire_themes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            label_it varchar(255) NOT NULL,
            label_en varchar(255) NOT NULL,
            annex varchar(10) DEFAULT NULL,
            uri varchar(500) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";

        // Tabella topic categories (lookup)
        $tables[] = "CREATE TABLE rndt_topic_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            label_it varchar(255) NOT NULL,
            label_en varchar(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";

        // Esegui dbDelta per tutte le tabelle
        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        if ( $wpdb->last_error ) {
            $this->last_error = $wpdb->last_error;
        }

        // Aggiungi FK con CASCADE (dbDelta non le supporta)
        $this->add_foreign_keys();

        return empty( $this->last_error );
    }

    /**
     * Aggiungi foreign keys con ON DELETE CASCADE
     *
     * dbDelta() non supporta FK, quindi le aggiungiamo separatamente.
     */
    private function add_foreign_keys() {
        global $wpdb;

        $fk_definitions = array(
            'fk_rndt_keywords_metadata'          => array( 'rndt_keywords', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_parties_metadata'           => array( 'rndt_responsible_parties', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_resources_metadata'         => array( 'rndt_online_resources', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_formats_metadata'           => array( 'rndt_distribution_formats', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_conformity_metadata'        => array( 'rndt_conformity', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_operations_metadata'        => array( 'rndt_service_operations', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_coupled_metadata'           => array( 'rndt_coupled_resources', 'metadata_id', 'rndt_metadata', 'id', 'CASCADE' ),
            'fk_rndt_coupled_linked'             => array( 'rndt_coupled_resources', 'linked_metadata_id', 'rndt_metadata', 'id', 'SET NULL' ),
        );

        foreach ( $fk_definitions as $fk_name => $def ) {
            list( $child_table, $child_col, $parent_table, $parent_col, $on_delete ) = $def;

            // Controlla se la FK esiste gia'
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = %s AND CONSTRAINT_NAME = %s AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                DB_NAME,
                $fk_name
            ) );

            if ( ! $exists ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query(
                    "ALTER TABLE {$child_table}
                     ADD CONSTRAINT {$fk_name}
                     FOREIGN KEY ({$child_col}) REFERENCES {$parent_table}({$parent_col})
                     ON DELETE {$on_delete}"
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seed_lookup_tables() {
        global $wpdb;

        // Popola INSPIRE themes
        $themes = RNDT_Inspire_Themes::get_all();
        foreach ( $themes as $code => $theme ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO rndt_inspire_themes (code, label_it, label_en, annex, uri)
                 VALUES (%s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    label_it = VALUES(label_it),
                    label_en = VALUES(label_en),
                    annex = VALUES(annex),
                    uri = VALUES(uri)",
                $code,
                $theme['it'],
                $theme['en'],
                isset( $theme['annex'] ) ? $theme['annex'] : null,
                isset( $theme['uri'] ) ? $theme['uri'] : null
            ) );
        }

        // Popola Topic categories
        $categories = RNDT_Topic_Categories::get_all();
        foreach ( $categories as $code => $category ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO rndt_topic_categories (code, label_it, label_en)
                 VALUES (%s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    label_it = VALUES(label_it),
                    label_en = VALUES(label_en)",
                $code,
                $category['it'],
                $category['en']
            ) );
        }

        return empty( $wpdb->last_error );
    }

    /**
     * {@inheritdoc}
     */
    public function drop_tables() {
        global $wpdb;

        // Ordine: prima le tabelle child (per FK), poi le parent, poi standalone
        $tables = array(
            'rndt_coupled_resources',
            'rndt_service_operations',
            'rndt_conformity',
            'rndt_distribution_formats',
            'rndt_online_resources',
            'rndt_responsible_parties',
            'rndt_keywords',
            'rndt_metadata',
            'rndt_responsible_presets',
            'rndt_inspire_themes',
            'rndt_topic_categories',
        );

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        return true;
    }
}
