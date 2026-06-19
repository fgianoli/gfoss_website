<?php
/**
 * Gestione area admin del plugin
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Admin
 */
class RNDT_Admin {

    /**
     * Nome del plugin
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Versione del plugin
     *
     * @var string
     */
    private $version;

    /**
     * Costruttore
     *
     * @param string $plugin_name Nome del plugin
     * @param string $version     Versione del plugin
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Registra gli stili CSS per l'area admin
     *
     * @param string $hook Hook della pagina corrente
     */
    public function enqueue_styles( $hook ) {
        // Carica solo nelle pagine del plugin
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            RNDT_MANAGER_PLUGIN_URL . 'admin/css/rndt-admin.css',
            array(),
            $this->version,
            'all'
        );

        // Se siamo nella pagina editor, carica anche gli stili del wizard
        if ( $this->is_editor_page( $hook ) ) {
            $asset_file = RNDT_MANAGER_PLUGIN_DIR . 'build/index.asset.php';
            $asset = file_exists( $asset_file ) ? require $asset_file : array( 'version' => $this->version );

            wp_enqueue_style(
                $this->plugin_name . '-wizard',
                RNDT_MANAGER_PLUGIN_URL . 'build/index.css',
                array( 'wp-components' ),
                $asset['version'] ?? $this->version,
                'all'
            );

            // Leaflet CSS
            wp_enqueue_style(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                array(),
                '1.9.4'
            );
        }
    }

    /**
     * Registra gli script JS per l'area admin
     *
     * @param string $hook Hook della pagina corrente
     */
    public function enqueue_scripts( $hook ) {
        // Carica solo nelle pagine del plugin
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            RNDT_MANAGER_PLUGIN_URL . 'admin/js/rndt-admin.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        // Se siamo nella pagina editor, carica il wizard React
        if ( $this->is_editor_page( $hook ) ) {
            // Leaflet JS
            wp_enqueue_script(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                array(),
                '1.9.4',
                true
            );

            // Wizard React (stessa build del frontend)
            $asset_file = RNDT_MANAGER_PLUGIN_DIR . 'build/index.asset.php';
            $asset      = file_exists( $asset_file )
                ? require $asset_file
                : array( 'dependencies' => array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ), 'version' => $this->version );

            wp_enqueue_script(
                $this->plugin_name . '-wizard',
                RNDT_MANAGER_PLUGIN_URL . 'build/index.js',
                array_merge( $asset['dependencies'], array( 'leaflet' ) ),
                $asset['version'],
                true
            );

            // Passa dati al JavaScript
            wp_localize_script(
                $this->plugin_name . '-wizard',
                'rndtManager',
                $this->get_wizard_data()
            );
        }

        // Localizzazione generale
        wp_localize_script(
            $this->plugin_name,
            'rndtAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'restUrl' => rest_url( 'rndt/v1/' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'i18n'    => $this->get_i18n_strings(),
            )
        );
    }

    /**
     * Aggiungi il menu admin
     */
    public function add_admin_menu() {
        // Menu principale
        add_menu_page(
            __( 'RNDT Manager', 'rndt-manager' ),
            __( 'RNDT Manager', 'rndt-manager' ),
            'manage_rndt_metadata',
            'rndt-manager',
            array( $this, 'render_main_page' ),
            'dashicons-location-alt',
            25
        );

        // Sottomenu: Aggiungi nuovo
        add_submenu_page(
            'rndt-manager',
            __( 'Aggiungi metadato', 'rndt-manager' ),
            __( 'Aggiungi nuovo', 'rndt-manager' ),
            'manage_rndt_metadata',
            'rndt-manager-new',
            array( $this, 'render_editor_page' )
        );

        // Sottomenu: Importa
        add_submenu_page(
            'rndt-manager',
            __( 'Importa metadati', 'rndt-manager' ),
            __( 'Importa', 'rndt-manager' ),
            'manage_rndt_metadata',
            'rndt-manager-import',
            array( $this, 'render_import_page' )
        );

        // Sottomenu: Impostazioni
        add_submenu_page(
            'rndt-manager',
            __( 'Impostazioni', 'rndt-manager' ),
            __( 'Impostazioni', 'rndt-manager' ),
            'manage_rndt_settings',
            'rndt-manager-settings',
            array( $this, 'render_settings_page' )
        );

        // Rimuovi il duplicato del menu principale
        remove_submenu_page( 'rndt-manager', 'rndt-manager' );
    }

    /**
     * Registra le impostazioni
     */
    public function register_settings() {
        $settings_page = new RNDT_Settings_Page();
        $settings_page->register();
    }

    /**
     * Renderizza la pagina principale (dashboard)
     */
    public function render_main_page() {
        // Redirect alla lista dei metadati
        wp_redirect( admin_url( 'edit.php?post_type=rndt_metadata' ) );
        exit;
    }

    /**
     * Renderizza la pagina editor
     */
    public function render_editor_page() {
        // Verifica permessi
        if ( ! current_user_can( 'manage_rndt_metadata' ) ) {
            wp_die( __( 'Non hai i permessi per accedere a questa pagina.', 'rndt-manager' ) );
        }

        // Ottieni ID metadato se in modifica
        $metadata_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        include RNDT_MANAGER_PLUGIN_DIR . 'admin/partials/rndt-editor-display.php';
    }

    /**
     * Renderizza la pagina importazione
     */
    public function render_import_page() {
        if ( ! current_user_can( 'manage_rndt_metadata' ) ) {
            wp_die( __( 'Non hai i permessi per accedere a questa pagina.', 'rndt-manager' ) );
        }

        include RNDT_MANAGER_PLUGIN_DIR . 'admin/partials/rndt-import-display.php';
    }

    /**
     * Renderizza la pagina impostazioni
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_rndt_settings' ) ) {
            wp_die( __( 'Non hai i permessi per accedere a questa pagina.', 'rndt-manager' ) );
        }

        include RNDT_MANAGER_PLUGIN_DIR . 'admin/partials/rndt-settings-display.php';
    }

    /**
     * Verifica se siamo in una pagina del plugin
     *
     * @param string $hook Hook della pagina
     * @return bool
     */
    private function is_plugin_page( $hook ) {
        $plugin_pages = array(
            'toplevel_page_rndt-manager',
            'rndt-manager_page_rndt-manager-new',
            'rndt-manager_page_rndt-manager-import',
            'rndt-manager_page_rndt-manager-settings',
            'edit.php',
            'post.php',
            'post-new.php',
        );

        if ( in_array( $hook, $plugin_pages, true ) ) {
            // Per le pagine post, verifica il post type
            if ( in_array( $hook, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
                $screen = get_current_screen();
                return $screen && 'rndt_metadata' === $screen->post_type;
            }
            return true;
        }

        return false;
    }

    /**
     * Verifica se siamo nella pagina editor
     *
     * @param string $hook Hook della pagina
     * @return bool
     */
    private function is_editor_page( $hook ) {
        return 'rndt-manager_page_rndt-manager-new' === $hook;
    }

    /**
     * Ottieni i dati per il wizard React
     *
     * @return array
     */
    private function get_wizard_data() {
        $metadata_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        return array(
            'restUrl'      => rest_url( 'rndt/v1/' ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'metadataId'   => $metadata_id,
            'locale'       => get_locale(),
            'language'     => RNDT_i18n::get_current_language(),
            'resourceTypes' => RNDT_Post_Type::get_resource_types(),
            'inspireThemes' => RNDT_Inspire_Themes::get_options( RNDT_i18n::get_current_language() ),
            'topicCategories' => RNDT_Topic_Categories::get_options( RNDT_i18n::get_current_language() ),
            'serviceTypes' => RNDT_Service_Types::get_options( RNDT_i18n::get_current_language() ),
            'roleCodes'    => RNDT_Role_Codes::get_options( RNDT_i18n::get_current_language() ),
            'restrictionCodes' => RNDT_Restriction_Codes::get_options( RNDT_i18n::get_current_language() ),
            'epsgCodes'    => RNDT_EPSG_Codes::get_options(),
            'languages'    => RNDT_i18n::get_supported_languages(),
            'charsets'     => RNDT_i18n::get_supported_charsets(),
            'settings'     => $this->get_public_settings(),
            'i18n'         => $this->get_wizard_i18n(),
        );
    }

    /**
     * Ottieni le impostazioni pubbliche (non sensibili)
     *
     * @return array
     */
    private function get_public_settings() {
        $settings = get_option( 'rndt_settings', array() );
        $general  = isset( $settings['general'] ) ? $settings['general'] : array();

        return array(
            'defaultLanguage'     => isset( $general['default_language'] ) ? $general['default_language'] : 'ita',
            'defaultIpaCode'      => isset( $general['default_ipa_code'] ) ? $general['default_ipa_code'] : '',
            'defaultOrganization' => isset( $general['default_organization'] ) ? $general['default_organization'] : '',
            'autoGenerateUuid'    => isset( $general['auto_generate_uuid'] ) ? $general['auto_generate_uuid'] : true,
            'identifierPrefix'    => isset( $general['identifier_prefix'] ) ? $general['identifier_prefix'] : '',
            'csw' => array(
                'enabled' => ! empty( $settings['csw']['enabled'] ),
                'url'     => isset( $settings['csw']['url'] ) ? $settings['csw']['url'] : '',
                'type'    => isset( $settings['csw']['catalog_type'] ) ? $settings['csw']['catalog_type'] : 'pycsw',
            ),
            'geoserver' => array(
                'enabled' => ! empty( $settings['geoserver']['enabled'] ),
                'url'     => isset( $settings['geoserver']['url'] ) ? $settings['geoserver']['url'] : '',
            ),
        );
    }

    /**
     * Ottieni le stringhe i18n per il wizard
     *
     * @return array
     */
    private function get_wizard_i18n() {
        return array(
            'steps' => array(
                'resourceType'    => __( 'Tipo risorsa', 'rndt-manager' ),
                'identification'  => __( 'Identificazione', 'rndt-manager' ),
                'classification'  => __( 'Classificazione', 'rndt-manager' ),
                'geographicExtent' => __( 'Estensione geografica', 'rndt-manager' ),
                'temporal'        => __( 'Riferimento temporale', 'rndt-manager' ),
                'quality'         => __( 'Qualità', 'rndt-manager' ),
                'constraints'     => __( 'Vincoli', 'rndt-manager' ),
                'distribution'    => __( 'Distribuzione', 'rndt-manager' ),
                'responsibleParty' => __( 'Parte responsabile', 'rndt-manager' ),
                'referenceSystem' => __( 'Sistema di riferimento', 'rndt-manager' ),
                'metadataInfo'    => __( 'Info metadato', 'rndt-manager' ),
                'serviceDetails'  => __( 'Dettagli servizio', 'rndt-manager' ),
            ),
            'buttons' => array(
                'next'     => __( 'Avanti', 'rndt-manager' ),
                'previous' => __( 'Indietro', 'rndt-manager' ),
                'save'     => __( 'Salva', 'rndt-manager' ),
                'saveDraft' => __( 'Salva bozza', 'rndt-manager' ),
                'validate' => __( 'Valida', 'rndt-manager' ),
                'publish'  => __( 'Pubblica', 'rndt-manager' ),
                'exportXml' => __( 'Esporta XML', 'rndt-manager' ),
                'cancel'   => __( 'Annulla', 'rndt-manager' ),
            ),
            'messages' => array(
                'saving'           => __( 'Salvataggio in corso...', 'rndt-manager' ),
                'saved'            => __( 'Metadato salvato con successo.', 'rndt-manager' ),
                'validating'       => __( 'Validazione in corso...', 'rndt-manager' ),
                'validationPassed' => __( 'Validazione completata con successo.', 'rndt-manager' ),
                'validationFailed' => __( 'Validazione fallita. Correggi gli errori.', 'rndt-manager' ),
                'publishing'       => __( 'Pubblicazione in corso...', 'rndt-manager' ),
                'published'        => __( 'Metadato pubblicato con successo.', 'rndt-manager' ),
                'error'            => __( 'Si e verificato un errore.', 'rndt-manager' ),
                'unsavedChanges'   => __( 'Ci sono modifiche non salvate. Vuoi uscire?', 'rndt-manager' ),
                'confirmDelete'    => __( 'Sei sicuro di voler eliminare questo metadato?', 'rndt-manager' ),
            ),
            'fields' => array(
                'required' => __( 'Campo obbligatorio', 'rndt-manager' ),
                'optional' => __( 'Opzionale', 'rndt-manager' ),
            ),
        );
    }

    /**
     * Ottieni le stringhe i18n generali
     *
     * @return array
     */
    private function get_i18n_strings() {
        return array(
            'testConnection' => __( 'Test connessione', 'rndt-manager' ),
            'testing'        => __( 'Test in corso...', 'rndt-manager' ),
            'success'        => __( 'Connessione riuscita!', 'rndt-manager' ),
            'failed'         => __( 'Connessione fallita:', 'rndt-manager' ),
            'confirm'        => __( 'Conferma', 'rndt-manager' ),
            'cancel'         => __( 'Annulla', 'rndt-manager' ),
        );
    }
}
