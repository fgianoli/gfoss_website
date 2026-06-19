<?php
/**
 * Pagina impostazioni del plugin
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Settings_Page
 */
class RNDT_Settings_Page {

    /**
     * Slug della pagina impostazioni
     */
    const PAGE_SLUG = 'rndt-manager-settings';

    /**
     * Nome dell'opzione
     */
    const OPTION_NAME = 'rndt_settings';

    /**
     * Registra le impostazioni
     */
    public function register() {
        register_setting(
            'rndt_settings_group',
            self::OPTION_NAME,
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => $this->get_defaults(),
            )
        );

        $this->add_sections();
        $this->add_fields();
    }

    /**
     * Aggiungi le sezioni
     */
    private function add_sections() {
        // Sezione Generale
        add_settings_section(
            'rndt_general_section',
            __( 'Impostazioni generali', 'rndt-manager' ),
            array( $this, 'render_general_section' ),
            self::PAGE_SLUG
        );

        // Sezione Database
        add_settings_section(
            'rndt_database_section',
            __( 'Database', 'rndt-manager' ),
            array( $this, 'render_database_section' ),
            self::PAGE_SLUG
        );

        // Sezione Catalogo CSW
        add_settings_section(
            'rndt_csw_section',
            __( 'Catalogo CSW (Metadati)', 'rndt-manager' ),
            array( $this, 'render_csw_section' ),
            self::PAGE_SLUG
        );

        // Sezione GeoServer (dati)
        add_settings_section(
            'rndt_geoserver_section',
            __( 'GeoServer (Servizi dati WMS/WFS)', 'rndt-manager' ),
            array( $this, 'render_geoserver_section' ),
            self::PAGE_SLUG
        );

        // Sezione Validazione
        add_settings_section(
            'rndt_validation_section',
            __( 'Validazione', 'rndt-manager' ),
            array( $this, 'render_validation_section' ),
            self::PAGE_SLUG
        );
    }

    /**
     * Aggiungi i campi
     */
    private function add_fields() {
        // -- Campi Generali --
        add_settings_field(
            'default_language',
            __( 'Lingua predefinita', 'rndt-manager' ),
            array( $this, 'render_select_field' ),
            self::PAGE_SLUG,
            'rndt_general_section',
            array(
                'id'      => 'default_language',
                'section' => 'general',
                'options' => RNDT_i18n::get_supported_languages(),
            )
        );

        add_settings_field(
            'default_ipa_code',
            __( 'Codice IPA predefinito', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_general_section',
            array(
                'id'          => 'default_ipa_code',
                'section'     => 'general',
                'description' => __( 'Codice dell\'ente nel registro IndicePA', 'rndt-manager' ),
            )
        );

        add_settings_field(
            'default_organization',
            __( 'Organizzazione predefinita', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_general_section',
            array(
                'id'      => 'default_organization',
                'section' => 'general',
            )
        );

        add_settings_field(
            'auto_generate_uuid',
            __( 'Genera UUID automaticamente', 'rndt-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'rndt_general_section',
            array(
                'id'          => 'auto_generate_uuid',
                'section'     => 'general',
                'description' => __( 'Genera automaticamente l\'identificativo del metadato', 'rndt-manager' ),
            )
        );

        // -- Campi Database --
        add_settings_field(
            'db_type',
            __( 'Tipo database', 'rndt-manager' ),
            array( $this, 'render_radio_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'type',
                'section' => 'database',
                'options' => array(
                    'postgresql' => __( 'PostgreSQL (database esterno)', 'rndt-manager' ),
                    'wordpress'  => __( 'WordPress DB (MariaDB/MySQL)', 'rndt-manager' ),
                ),
                'description' => __( 'Seleziona dove memorizzare i metadati RNDT. Il default è PostgreSQL per retrocompatibilità.', 'rndt-manager' ),
            )
        );

        add_settings_field(
            'db_host',
            __( 'Host', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'host',
                'section' => 'database',
                'default' => 'localhost',
            )
        );

        add_settings_field(
            'db_port',
            __( 'Porta', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'port',
                'section' => 'database',
                'default' => '5432',
            )
        );

        add_settings_field(
            'db_name',
            __( 'Nome database', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'dbname',
                'section' => 'database',
                'default' => 'rndt_metadata',
            )
        );

        add_settings_field(
            'db_schema',
            __( 'Schema', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'schema',
                'section' => 'database',
                'default' => 'public',
            )
        );

        add_settings_field(
            'db_user',
            __( 'Utente', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'user',
                'section' => 'database',
            )
        );

        add_settings_field(
            'db_password',
            __( 'Password', 'rndt-manager' ),
            array( $this, 'render_password_field' ),
            self::PAGE_SLUG,
            'rndt_database_section',
            array(
                'id'      => 'password',
                'section' => 'database',
            )
        );

        // -- Campi Catalogo CSW --
        add_settings_field(
            'csw_enabled',
            __( 'Abilita pubblicazione CSW', 'rndt-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'          => 'enabled',
                'section'     => 'csw',
                'description' => __( 'Abilita la pubblicazione dei metadati su un catalogo CSW', 'rndt-manager' ),
            )
        );

        add_settings_field(
            'csw_type',
            __( 'Tipo catalogo', 'rndt-manager' ),
            array( $this, 'render_select_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'      => 'catalog_type',
                'section' => 'csw',
                'options' => array(
                    'pycsw'      => __( 'pyCSW', 'rndt-manager' ),
                    'geoserver'  => __( 'GeoServer CSW', 'rndt-manager' ),
                    'geonetwork' => __( 'GeoNetwork', 'rndt-manager' ),
                    'other'      => __( 'Altro (CSW-T compatibile)', 'rndt-manager' ),
                ),
            )
        );

        add_settings_field(
            'csw_url',
            __( 'URL endpoint CSW', 'rndt-manager' ),
            array( $this, 'render_url_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'          => 'url',
                'section'     => 'csw',
                'placeholder' => 'https://example.com/csw',
                'description' => __( 'pyCSW: /pycsw/csw | GeoServer: /geoserver/csw | GeoNetwork: /geonetwork/srv/ita/csw', 'rndt-manager' ),
            )
        );

        add_settings_field(
            'csw_auth_type',
            __( 'Tipo autenticazione', 'rndt-manager' ),
            array( $this, 'render_select_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'      => 'auth_type',
                'section' => 'csw',
                'options' => array(
                    'none'   => __( 'Nessuna', 'rndt-manager' ),
                    'basic'  => __( 'HTTP Basic', 'rndt-manager' ),
                    'bearer' => __( 'Bearer Token', 'rndt-manager' ),
                ),
            )
        );

        add_settings_field(
            'csw_username',
            __( 'Username', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'      => 'username',
                'section' => 'csw',
                'class'   => 'csw-auth-field',
            )
        );

        add_settings_field(
            'csw_password',
            __( 'Password', 'rndt-manager' ),
            array( $this, 'render_password_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'      => 'password',
                'section' => 'csw',
                'class'   => 'csw-auth-field',
            )
        );

        add_settings_field(
            'csw_output_schema',
            __( 'Output Schema', 'rndt-manager' ),
            array( $this, 'render_select_field' ),
            self::PAGE_SLUG,
            'rndt_csw_section',
            array(
                'id'      => 'output_schema',
                'section' => 'csw',
                'options' => array(
                    'http://www.isotc211.org/2005/gmd' => 'ISO 19139 (GMD)',
                    'http://www.opengis.net/cat/csw/2.0.2' => 'CSW Core',
                ),
            )
        );

        // -- Campi GeoServer --
        add_settings_field(
            'geoserver_enabled',
            __( 'Abilita GeoServer', 'rndt-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'rndt_geoserver_section',
            array(
                'id'      => 'enabled',
                'section' => 'geoserver',
            )
        );

        add_settings_field(
            'geoserver_url',
            __( 'URL GeoServer', 'rndt-manager' ),
            array( $this, 'render_url_field' ),
            self::PAGE_SLUG,
            'rndt_geoserver_section',
            array(
                'id'          => 'url',
                'section'     => 'geoserver',
                'placeholder' => 'https://example.com/geoserver',
            )
        );

        add_settings_field(
            'geoserver_username',
            __( 'Username', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_geoserver_section',
            array(
                'id'      => 'username',
                'section' => 'geoserver',
            )
        );

        add_settings_field(
            'geoserver_password',
            __( 'Password', 'rndt-manager' ),
            array( $this, 'render_password_field' ),
            self::PAGE_SLUG,
            'rndt_geoserver_section',
            array(
                'id'      => 'password',
                'section' => 'geoserver',
            )
        );

        add_settings_field(
            'geoserver_workspace',
            __( 'Workspace predefinito', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_geoserver_section',
            array(
                'id'      => 'default_workspace',
                'section' => 'geoserver',
            )
        );

        add_settings_field(
            'geoserver_datastore',
            __( 'Datastore predefinito', 'rndt-manager' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'rndt_geoserver_section',
            array(
                'id'          => 'default_datastore',
                'section'     => 'geoserver',
                'description' => __( 'Nome del datastore PostGIS su GeoServer (es: sitivi, sitvi_servizio).', 'rndt-manager' ),
            )
        );

        // -- Campi Validazione --
        add_settings_field(
            'validate_on_save',
            __( 'Valida al salvataggio', 'rndt-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'rndt_validation_section',
            array(
                'id'          => 'validate_on_save',
                'section'     => 'validation',
                'description' => __( 'Esegui la validazione automaticamente quando si salva', 'rndt-manager' ),
            )
        );

        add_settings_field(
            'require_validation',
            __( 'Richiedi validazione per pubblicare', 'rndt-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'rndt_validation_section',
            array(
                'id'          => 'require_validation',
                'section'     => 'validation',
                'description' => __( 'Il metadato deve essere validato prima della pubblicazione su CSW', 'rndt-manager' ),
            )
        );

        add_settings_field(
            'xsd_validation',
            __( 'Validazione XSD', 'rndt-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'rndt_validation_section',
            array(
                'id'          => 'xsd_validation',
                'section'     => 'validation',
                'description' => __( 'Valida l\'XML generato contro lo schema ISO 19139', 'rndt-manager' ),
            )
        );
    }

    /**
     * Renderizza la sezione generale
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configura le impostazioni generali del plugin.', 'rndt-manager' ) . '</p>';
    }

    /**
     * Renderizza la sezione database
     */
    public function render_database_section() {
        echo '<p>' . esc_html__( 'Configura il database per i metadati RNDT. Puoi usare PostgreSQL (database esterno) o il database WordPress (MariaDB/MySQL).', 'rndt-manager' ) . '</p>';

        // Mostra errore connessione se presente
        $db_error = get_option( 'rndt_manager_db_error' );
        if ( $db_error ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Errore connessione:', 'rndt-manager' ) . '</strong> ' . esc_html( $db_error ) . '</p></div>';
        }

        // Pulsanti test connessione e crea tabelle
        echo '<p class="rndt-db-actions">';
        echo '<button type="button" id="rndt-test-db-connection" class="button">' . esc_html__( 'Test connessione', 'rndt-manager' ) . '</button> ';
        echo '<button type="button" id="rndt-create-tables" class="button button-secondary">' . esc_html__( 'Crea tabelle', 'rndt-manager' ) . '</button> ';
        echo '<span id="rndt-db-test-result"></span>';
        echo '</p>';
        echo '<p class="description">' . esc_html__( 'Clicca "Salva modifiche" prima di testare la connessione o creare le tabelle.', 'rndt-manager' ) . '</p>';
    }

    /**
     * Renderizza la sezione Catalogo CSW
     */
    public function render_csw_section() {
        echo '<p>' . esc_html__( 'Configura la connessione al catalogo CSW per la pubblicazione dei metadati.', 'rndt-manager' ) . '</p>';
        echo '<p>' . esc_html__( 'Supporta: pyCSW, GeoServer CSW Extension, GeoNetwork, e altri server CSW-T compatibili.', 'rndt-manager' ) . '</p>';
        echo '<p><button type="button" id="rndt-test-csw-connection" class="button">' . esc_html__( 'Test connessione', 'rndt-manager' ) . '</button> <span id="rndt-csw-test-result"></span></p>';
    }

    /**
     * Renderizza la sezione GeoServer
     */
    public function render_geoserver_section() {
        echo '<p>' . esc_html__( 'Configura la connessione a GeoServer per la gestione dei layer (WMS, WFS, WCS).', 'rndt-manager' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Nota: se usi GeoServer anche come catalogo CSW, configuralo nella sezione "Catalogo CSW" sopra.', 'rndt-manager' ) . '</p>';
        echo '<p><button type="button" id="rndt-test-geoserver-connection" class="button">' . esc_html__( 'Test connessione', 'rndt-manager' ) . '</button> <span id="rndt-geoserver-test-result"></span></p>';
    }

    /**
     * Renderizza la sezione validazione
     */
    public function render_validation_section() {
        echo '<p>' . esc_html__( 'Configura le opzioni di validazione dei metadati.', 'rndt-manager' ) . '</p>';
    }

    /**
     * Renderizza un campo testo
     */
    public function render_text_field( $args ) {
        $settings = get_option( self::OPTION_NAME, $this->get_defaults() );
        $section  = $args['section'];
        $id       = $args['id'];
        $value    = isset( $settings[ $section ][ $id ] ) ? $settings[ $section ][ $id ] : '';
        $default  = isset( $args['default'] ) ? $args['default'] : '';
        $class    = isset( $args['class'] ) ? $args['class'] : '';

        if ( empty( $value ) && ! empty( $default ) ) {
            $value = $default;
        }

        printf(
            '<input type="text" id="%s_%s" name="%s[%s][%s]" value="%s" class="regular-text %s" />',
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( $value ),
            esc_attr( $class )
        );

        if ( isset( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    /**
     * Renderizza un campo password
     */
    public function render_password_field( $args ) {
        $settings = get_option( self::OPTION_NAME, $this->get_defaults() );
        $section  = $args['section'];
        $id       = $args['id'];
        $value    = isset( $settings[ $section ][ $id ] ) ? $settings[ $section ][ $id ] : '';
        $class    = isset( $args['class'] ) ? $args['class'] : '';

        printf(
            '<input type="password" id="%s_%s" name="%s[%s][%s]" value="%s" class="regular-text %s" autocomplete="new-password" />',
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( $value ),
            esc_attr( $class )
        );
    }

    /**
     * Renderizza un campo URL
     */
    public function render_url_field( $args ) {
        $settings    = get_option( self::OPTION_NAME, $this->get_defaults() );
        $section     = $args['section'];
        $id          = $args['id'];
        $value       = isset( $settings[ $section ][ $id ] ) ? $settings[ $section ][ $id ] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

        printf(
            '<input type="url" id="%s_%s" name="%s[%s][%s]" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( $value ),
            esc_attr( $placeholder )
        );

        if ( isset( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    /**
     * Renderizza un campo select
     */
    public function render_select_field( $args ) {
        $settings = get_option( self::OPTION_NAME, $this->get_defaults() );
        $section  = $args['section'];
        $id       = $args['id'];
        $value    = isset( $settings[ $section ][ $id ] ) ? $settings[ $section ][ $id ] : '';
        $options  = isset( $args['options'] ) ? $args['options'] : array();

        printf(
            '<select id="%s_%s" name="%s[%s][%s]">',
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $id )
        );

        foreach ( $options as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $value, $key, false ),
                esc_html( $label )
            );
        }

        echo '</select>';
    }

    /**
     * Renderizza un campo checkbox
     */
    public function render_checkbox_field( $args ) {
        $settings = get_option( self::OPTION_NAME, $this->get_defaults() );
        $section  = $args['section'];
        $id       = $args['id'];
        $checked  = isset( $settings[ $section ][ $id ] ) ? (bool) $settings[ $section ][ $id ] : false;

        printf(
            '<input type="checkbox" id="%s_%s" name="%s[%s][%s]" value="1" %s />',
            esc_attr( $section ),
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $id ),
            checked( $checked, true, false )
        );

        if ( isset( $args['description'] ) ) {
            echo '<label for="' . esc_attr( $section ) . '_' . esc_attr( $id ) . '">' . esc_html( $args['description'] ) . '</label>';
        }
    }

    /**
     * Renderizza un campo radio
     */
    public function render_radio_field( $args ) {
        $settings = get_option( self::OPTION_NAME, $this->get_defaults() );
        $section  = $args['section'];
        $id       = $args['id'];
        $value    = isset( $settings[ $section ][ $id ] ) ? $settings[ $section ][ $id ] : '';
        $options  = isset( $args['options'] ) ? $args['options'] : array();

        echo '<fieldset>';
        foreach ( $options as $key => $label ) {
            printf(
                '<label><input type="radio" name="%s[%s][%s]" value="%s" %s /> %s</label><br>',
                esc_attr( self::OPTION_NAME ),
                esc_attr( $section ),
                esc_attr( $id ),
                esc_attr( $key ),
                checked( $value, $key, false ),
                esc_html( $label )
            );
        }
        echo '</fieldset>';

        if ( isset( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    /**
     * Sanifica le impostazioni
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $defaults  = $this->get_defaults();

        foreach ( $defaults as $section => $fields ) {
            $sanitized[ $section ] = array();

            foreach ( $fields as $key => $default ) {
                if ( isset( $input[ $section ][ $key ] ) ) {
                    $value = $input[ $section ][ $key ];

                    // Sanifica in base al tipo
                    if ( is_bool( $default ) ) {
                        $sanitized[ $section ][ $key ] = (bool) $value;
                    } elseif ( $key === 'url' || strpos( $key, 'url' ) !== false ) {
                        $sanitized[ $section ][ $key ] = esc_url_raw( $value );
                    } elseif ( $key === 'password' ) {
                        // Non sanificare troppo le password
                        $sanitized[ $section ][ $key ] = $value;
                    } else {
                        $sanitized[ $section ][ $key ] = sanitize_text_field( $value );
                    }
                } else {
                    // Checkbox non selezionati
                    if ( is_bool( $default ) ) {
                        $sanitized[ $section ][ $key ] = false;
                    } else {
                        $sanitized[ $section ][ $key ] = $default;
                    }
                }
            }
        }

        // NON creare tabelle qui - causa timeout
        // La creazione tabelle è gestita via AJAX separatamente

        return $sanitized;
    }

    /**
     * Ottieni i valori di default
     */
    public function get_defaults() {
        return array(
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
            'csw' => array(
                'enabled'       => false,
                'catalog_type'  => 'pycsw',
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
    }
}
