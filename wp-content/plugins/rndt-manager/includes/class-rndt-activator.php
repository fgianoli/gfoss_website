<?php
/**
 * Gestione attivazione plugin
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Activator
 */
class RNDT_Activator {

    /**
     * Esegui le operazioni di attivazione
     */
    public static function activate() {
        // Registra la versione del plugin
        update_option( 'rndt_manager_version', RNDT_MANAGER_VERSION );

        // Crea le opzioni di default se non esistono
        self::create_default_options();

        // Registra il CPT per flush rewrite rules
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-post-type.php';
        $post_type = new RNDT_Post_Type();
        $post_type->register_post_type();

        // Registra le tassonomie
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-taxonomies.php';
        $taxonomies = new RNDT_Taxonomies();
        $taxonomies->register_taxonomies();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Crea i ruoli e le capabilities
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-capabilities.php';
        $capabilities = new RNDT_Capabilities();
        $capabilities->add_capabilities();

        // Prova a creare le tabelle PostgreSQL se la connessione e configurata
        self::maybe_create_database_tables();

        // Segna che l'attivazione e stata completata
        update_option( 'rndt_manager_activation_complete', true );
    }

    /**
     * Crea le opzioni di default
     */
    private static function create_default_options() {
        $default_settings = array(
            'general' => array(
                'default_language'     => 'ita',
                'default_ipa_code'     => '',
                'default_organization' => '',
                'auto_generate_uuid'   => true,
                'identifier_prefix'    => '',
            ),
            'database' => array(
                'type'     => 'postgresql',
                'host'     => 'localhost',
                'port'     => '5432',
                'dbname'   => 'rndt_metadata',
                'user'     => '',
                'password' => '',
                'schema'   => 'public',
            ),
            'pycsw' => array(
                'enabled'       => false,
                'url'           => '',
                'auth_type'     => 'none',
                'username'      => '',
                'password'      => '',
                'bearer_token'  => '',
                'csw_version'   => '2.0.2',
                'output_schema' => 'http://www.isotc211.org/2005/gmd',
            ),
            'geoserver' => array(
                'enabled'           => false,
                'url'               => '',
                'username'          => '',
                'password'          => '',
                'default_workspace' => '',
                'default_datastore' => '',
            ),
            'validation' => array(
                'validate_on_save'       => true,
                'require_validation'     => true,
                'xsd_validation'         => true,
                'schematron_validation'  => false,
            ),
        );

        // Non sovrascrivere le impostazioni esistenti
        if ( false === get_option( 'rndt_settings' ) ) {
            add_option( 'rndt_settings', $default_settings );
        }
    }

    /**
     * Crea le tabelle se la connessione e configurata
     */
    private static function maybe_create_database_tables() {
        $settings = get_option( 'rndt_settings', array() );
        $db_settings = isset( $settings['database'] ) ? $settings['database'] : array();
        $db_type = isset( $db_settings['type'] ) ? $db_settings['type'] : 'postgresql';

        // Per PostgreSQL: skip se non c'e un utente configurato
        // Per WordPress: può sempre procedere (usa $wpdb)
        if ( 'postgresql' === $db_type && empty( $db_settings['user'] ) ) {
            return;
        }

        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/database/interface-rndt-database.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/database/class-rndt-database-postgresql.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/database/class-rndt-database-wordpress.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-database.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-inspire-themes.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-topic-categories.php';

        $db = RNDT_Database::get_instance();

        // Test connessione (per WordPress DB è sempre ok)
        $test = $db->test_connection();
        if ( true !== $test ) {
            update_option( 'rndt_manager_db_error', $test );
            return;
        }

        // Crea le tabelle
        if ( $db->create_tables() ) {
            $db->seed_lookup_tables();
            delete_option( 'rndt_manager_db_error' );
        } else {
            update_option( 'rndt_manager_db_error', $db->get_last_error() );
        }
    }
}
