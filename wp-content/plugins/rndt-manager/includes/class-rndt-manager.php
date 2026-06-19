<?php
/**
 * Classe principale del plugin RNDT Manager
 *
 * Orchestratore singleton che coordina tutte le funzionalita del plugin.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Manager
 */
class RNDT_Manager {

    /**
     * Istanza singleton
     *
     * @var RNDT_Manager
     */
    private static $instance = null;

    /**
     * Versione del plugin
     *
     * @var string
     */
    protected $version;

    /**
     * Nome del plugin (slug)
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * Loader per hooks
     *
     * @var RNDT_Loader
     */
    protected $loader;

    /**
     * Connessione database (PostgreSQL o WordPress)
     *
     * @var RNDT_Database
     */
    protected $database;

    /**
     * Ottieni l'istanza singleton
     *
     * @return RNDT_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore privato (singleton)
     */
    private function __construct() {
        $this->version     = RNDT_MANAGER_VERSION;
        $this->plugin_name = 'rndt-manager';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    /**
     * Carica le dipendenze del plugin
     */
    private function load_dependencies() {
        // Core
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-loader.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-i18n.php';

        // Database: interface + drivers + facade
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/database/interface-rndt-database.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/database/class-rndt-database-postgresql.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/database/class-rndt-database-wordpress.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-database.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-post-type.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-taxonomies.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-capabilities.php';

        // Codelists
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-inspire-themes.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-topic-categories.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-service-types.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-role-codes.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-restriction-codes.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/codelists/class-rndt-epsg-codes.php';

        // Metadata
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/metadata/class-rndt-metadata-model.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/metadata/class-rndt-metadata-repository.php';

        // XML
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/xml/class-rndt-xml-namespaces.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/xml/class-rndt-xml-codelists.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/xml/class-rndt-xml-generator.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/xml/class-rndt-xml-parser.php';

        // Validation
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/validation/class-rndt-validation-result.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/validation/class-rndt-validation-rules.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/validation/class-rndt-validator.php';

        // Connectors
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/connectors/class-rndt-http-client.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/connectors/class-rndt-csw-client.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/connectors/class-rndt-geoserver-client.php';

        // REST API
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-controller.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-metadata.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-validation.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-export.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-publish.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-import.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-codelists.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/api/class-rndt-rest-responsible-presets.php';

        // Admin
        require_once RNDT_MANAGER_PLUGIN_DIR . 'admin/class-rndt-admin.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'admin/class-rndt-settings-page.php';
        require_once RNDT_MANAGER_PLUGIN_DIR . 'admin/class-rndt-admin-columns.php';

        // Public
        require_once RNDT_MANAGER_PLUGIN_DIR . 'public/class-rndt-public.php';

        $this->loader   = new RNDT_Loader();
        $this->database = RNDT_Database::get_instance();
    }

    /**
     * Configura l'internazionalizzazione
     */
    private function set_locale() {
        $i18n = new RNDT_i18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    /**
     * Registra gli hook per l'area admin
     */
    private function define_admin_hooks() {
        $admin = new RNDT_Admin( $this->get_plugin_name(), $this->get_version() );

        // Menu e pagine admin
        $this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init', $admin, 'register_settings' );

        // Assets admin
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

        // Custom Post Type e Tassonomie
        $post_type  = new RNDT_Post_Type();
        $taxonomies = new RNDT_Taxonomies();

        $this->loader->add_action( 'init', $post_type, 'register_post_type' );
        $this->loader->add_action( 'init', $taxonomies, 'register_taxonomies' );

        // Capabilities
        $capabilities = new RNDT_Capabilities();
        $this->loader->add_action( 'admin_init', $capabilities, 'add_capabilities' );

        // Admin columns for metadata list
        new RNDT_Admin_Columns();
    }

    /**
     * Registra gli hook per l'area pubblica
     */
    private function define_public_hooks() {
        $public = new RNDT_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
    }

    /**
     * Registra gli endpoint REST API
     */
    private function define_api_hooks() {
        $metadata_api   = new RNDT_REST_Metadata();
        $validation_api = new RNDT_REST_Validation();
        $export_api     = new RNDT_REST_Export();
        $publish_api    = new RNDT_REST_Publish();
        $import_api     = new RNDT_REST_Import();
        $codelists_api  = new RNDT_REST_Codelists();
        $presets_api    = new RNDT_REST_Responsible_Presets();

        $this->loader->add_action( 'rest_api_init', $metadata_api, 'register_routes' );
        $this->loader->add_action( 'rest_api_init', $validation_api, 'register_routes' );
        $this->loader->add_action( 'rest_api_init', $export_api, 'register_routes' );
        $this->loader->add_action( 'rest_api_init', $publish_api, 'register_routes' );
        $this->loader->add_action( 'rest_api_init', $import_api, 'register_routes' );
        $this->loader->add_action( 'rest_api_init', $codelists_api, 'register_routes' );
        $this->loader->add_action( 'rest_api_init', $presets_api, 'register_routes' );
    }

    /**
     * Esegue il plugin registrando tutti gli hooks
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Ottieni il nome del plugin
     *
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Ottieni la versione del plugin
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Ottieni il loader
     *
     * @return RNDT_Loader
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Ottieni l'istanza del database
     *
     * @return RNDT_Database
     */
    public function get_database() {
        return $this->database;
    }
}
