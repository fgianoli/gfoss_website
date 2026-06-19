<?php
/**
 * Gestione area pubblica del plugin
 *
 * Gestisce shortcode, routing e interfaccia frontend per utenti autorizzati.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Public
 */
class RNDT_Public {

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
     * Flag per indicare se siamo in una pagina RNDT
     *
     * @var bool
     */
    private $is_rndt_page = false;

    /**
     * Vista corrente (list, editor, view)
     *
     * @var string
     */
    private $current_view = '';

    /**
     * Costruttore
     *
     * @param string $plugin_name Nome del plugin
     * @param string $version     Versione del plugin
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;

        // Registra shortcodes
        add_shortcode( 'rndt_manager', array( $this, 'shortcode_manager' ) );
        add_shortcode( 'rndt_catalog', array( $this, 'shortcode_catalog' ) );
    }

    /**
     * Registra gli stili CSS pubblici
     */
    public function enqueue_styles() {
        if ( ! $this->is_rndt_page ) {
            return;
        }

        // Stili base WordPress components (necessari per React)
        wp_enqueue_style( 'wp-components' );

        // Leaflet CSS per la mappa
        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );

        // Stili del wizard React (compilati da wp-scripts)
        $asset_file = RNDT_MANAGER_PLUGIN_DIR . 'build/index.asset.php';
        $asset = file_exists( $asset_file ) ? require $asset_file : array( 'version' => $this->version );

        wp_enqueue_style(
            'rndt-manager-frontend',
            RNDT_MANAGER_PLUGIN_URL . 'build/index.css',
            array( 'wp-components' ),
            $asset['version'] ?? $this->version
        );

        // Stili aggiuntivi frontend-specific
        wp_enqueue_style(
            'rndt-manager-public',
            RNDT_MANAGER_PLUGIN_URL . 'public/css/rndt-public.css',
            array( 'rndt-manager-frontend' ),
            $this->version
        );
    }

    /**
     * Registra gli script JS pubblici
     */
    public function enqueue_scripts() {
        if ( ! $this->is_rndt_page ) {
            return;
        }

        // Leaflet JS
        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );

        // Script React del wizard (compilato da wp-scripts)
        $asset_file = RNDT_MANAGER_PLUGIN_DIR . 'build/index.asset.php';
        $asset = file_exists( $asset_file )
            ? require $asset_file
            : array(
                'dependencies' => array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
                'version' => $this->version
            );

        wp_enqueue_script(
            'rndt-manager-frontend',
            RNDT_MANAGER_PLUGIN_URL . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // Localizza script con dati necessari
        wp_localize_script(
            'rndt-manager-frontend',
            'rndtManager',
            $this->get_localized_data()
        );

        // Traduzioni
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'rndt-manager-frontend', 'rndt-manager', RNDT_MANAGER_PLUGIN_DIR . 'languages' );
        }
    }

    /**
     * Ottieni i dati localizzati per lo script
     *
     * @return array
     */
    private function get_localized_data() {
        return array(
            'restUrl'           => rest_url( 'rndt/v1/' ),
            'nonce'             => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'pluginUrl'         => RNDT_MANAGER_PLUGIN_URL,
            'resourceTypes'     => $this->get_resource_types(),
            'inspireThemes'     => RNDT_Inspire_Themes::get_all(),
            'topicCategories'   => RNDT_Topic_Categories::get_all(),
            'serviceTypes'      => RNDT_Service_Types::get_all(),
            'roleCodes'         => RNDT_Role_Codes::get_all(),
            'restrictionCodes'  => RNDT_Restriction_Codes::get_all(),
            'epsgCodes'         => RNDT_EPSG_Codes::get_options(),
            'languages'         => $this->get_languages(),
            'charsets'          => $this->get_charsets(),
            'settings'          => $this->get_public_settings(),
            'i18n'              => $this->get_i18n_strings(),
            'currentUser'       => $this->get_current_user_data(),
            'isFrontend'        => true,
        );
    }

    /**
     * Shortcode principale [rndt_manager]
     *
     * @param array $atts Attributi shortcode
     * @return string
     */
    public function shortcode_manager( $atts ) {
        $atts = shortcode_atts(
            array(
                'view' => 'list',  // list, editor
                'id'   => '',      // ID metadato per modifica
            ),
            $atts,
            'rndt_manager'
        );

        // Verifica permessi
        if ( ! $this->check_access() ) {
            return $this->render_access_denied();
        }

        $this->is_rndt_page = true;
        $this->current_view = $atts['view'];

        // Gestisci parametri URL
        $view = isset( $_GET['rndt_view'] ) ? sanitize_text_field( $_GET['rndt_view'] ) : $atts['view'];
        $metadata_id = isset( $_GET['rndt_id'] ) ? absint( $_GET['rndt_id'] ) : ( $atts['id'] ? absint( $atts['id'] ) : 0 );

        // Forza enqueue degli script
        $this->enqueue_styles();
        $this->enqueue_scripts();

        ob_start();

        switch ( $view ) {
            case 'editor':
                $this->render_editor( $metadata_id );
                break;
            case 'list':
            default:
                $this->render_list();
                break;
        }

        return ob_get_clean();
    }

    /**
     * Shortcode catalogo pubblico [rndt_catalog]
     *
     * @param array $atts Attributi shortcode
     * @return string
     */
    public function shortcode_catalog( $atts ) {
        $atts = shortcode_atts(
            array(
                'limit'    => 10,
                'category' => '',
                'theme'    => '',
            ),
            $atts,
            'rndt_catalog'
        );

        ob_start();
        $this->render_catalog( $atts );
        return ob_get_clean();
    }

    /**
     * Verifica accesso utente
     *
     * @return bool
     */
    private function check_access() {
        // Deve essere loggato
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Deve avere la capability per gestire metadati RNDT
        return RNDT_Capabilities::current_user_can_manage();
    }

    /**
     * Render accesso negato
     *
     * @return string
     */
    private function render_access_denied() {
        $output = '<div class="rndt-access-denied">';

        if ( ! is_user_logged_in() ) {
            $output .= '<div class="rndt-notice rndt-notice--warning">';
            $output .= '<h3>' . esc_html__( 'Accesso richiesto', 'rndt-manager' ) . '</h3>';
            $output .= '<p>' . esc_html__( 'Devi effettuare il login per accedere a questa area.', 'rndt-manager' ) . '</p>';
            $output .= '<p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="rndt-button rndt-button--primary">';
            $output .= esc_html__( 'Accedi', 'rndt-manager' );
            $output .= '</a></p>';
            $output .= '</div>';
        } else {
            $output .= '<div class="rndt-notice rndt-notice--error">';
            $output .= '<h3>' . esc_html__( 'Accesso negato', 'rndt-manager' ) . '</h3>';
            $output .= '<p>' . esc_html__( 'Non hai i permessi necessari per accedere a questa area.', 'rndt-manager' ) . '</p>';
            $output .= '<p>' . esc_html__( 'Contatta l\'amministratore se ritieni di dover avere accesso.', 'rndt-manager' ) . '</p>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render lista metadati
     */
    private function render_list() {
        $current_url = get_permalink();
        ?>
        <div class="rndt-frontend rndt-frontend--list">
            <div class="rndt-frontend__header">
                <h2><?php esc_html_e( 'I miei metadati', 'rndt-manager' ); ?></h2>
                <a href="<?php echo esc_url( add_query_arg( 'rndt_view', 'editor', $current_url ) ); ?>"
                   class="rndt-button rndt-button--primary">
                    <?php esc_html_e( 'Nuovo metadato', 'rndt-manager' ); ?>
                </a>
            </div>

            <div class="rndt-frontend__content">
                <div id="rndt-metadata-list"
                     data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
                     data-api-url="<?php echo esc_attr( rest_url( 'rndt/v1/' ) ); ?>"
                     data-edit-url="<?php echo esc_url( add_query_arg( 'rndt_view', 'editor', $current_url ) ); ?>">
                    <div class="rndt-loading">
                        <span class="rndt-spinner"></span>
                        <p><?php esc_html_e( 'Caricamento metadati...', 'rndt-manager' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var container = document.getElementById('rndt-metadata-list');
            if (!container) return;

            var apiUrl = container.dataset.apiUrl;
            var nonce = container.dataset.nonce;
            var editUrl = container.dataset.editUrl;

            // Escape HTML per prevenire XSS
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }

            // Fetch metadati utente corrente
            fetch(apiUrl + 'metadata?per_page=50', {
                headers: {
                    'X-WP-Nonce': nonce
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(function(data) {
                // Se la risposta e' un errore REST (oggetto con code/message)
                if (data && data.code && data.message) {
                    container.innerHTML = '<div class="rndt-notice rndt-notice--error">' +
                        '<p>' + escapeHtml(data.message) + '</p>' +
                        '</div>';
                    console.error('RNDT API Error:', data);
                    return;
                }

                if (!Array.isArray(data) || data.length === 0) {
                    container.innerHTML = '<div class="rndt-empty">' +
                        '<p><?php echo esc_js( __( 'Non hai ancora creato nessun metadato.', 'rndt-manager' ) ); ?></p>' +
                        '</div>';
                    return;
                }

                var html = '<table class="rndt-table">';
                html += '<thead><tr>';
                html += '<th><?php echo esc_js( __( 'Titolo', 'rndt-manager' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Tipo', 'rndt-manager' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Stato', 'rndt-manager' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Data', 'rndt-manager' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Azioni', 'rndt-manager' ) ); ?></th>';
                html += '</tr></thead><tbody>';

                data.forEach(function(item) {
                    var vs = item.validation_status || 'not_validated';
                    var statusClass = 'rndt-status--' + vs;
                    var statusLabel = vs === 'valid' ? '<?php echo esc_js( __( 'Validato', 'rndt-manager' ) ); ?>' :
                                     vs === 'invalid' ? '<?php echo esc_js( __( 'Non valido', 'rndt-manager' ) ); ?>' :
                                     '<?php echo esc_js( __( 'Da validare', 'rndt-manager' ) ); ?>';
                    if (item.csw_published_at) {
                        statusClass = 'rndt-status--published';
                        statusLabel = '<?php echo esc_js( __( 'Pubblicato CSW', 'rndt-manager' ) ); ?>';
                    }

                    var dateStr = item.updated_at || item.created_at || '-';
                    if (dateStr && dateStr !== '-') {
                        dateStr = dateStr.substring(0, 10);
                    }

                    html += '<tr>';
                    html += '<td><strong>' + escapeHtml(item.title || '<?php echo esc_js( __( '(Senza titolo)', 'rndt-manager' ) ); ?>') + '</strong></td>';
                    html += '<td><span class="rndt-type-badge">' + escapeHtml(item.resource_type || 'dataset') + '</span></td>';
                    html += '<td><span class="rndt-status ' + escapeHtml(statusClass) + '">' + escapeHtml(statusLabel) + '</span></td>';
                    html += '<td>' + escapeHtml(dateStr) + '</td>';
                    html += '<td class="rndt-actions">';
                    html += '<a href="' + escapeHtml(editUrl + '&rndt_id=' + parseInt(item.id, 10)) + '" class="rndt-button rndt-button--small"><?php echo esc_js( __( 'Modifica', 'rndt-manager' ) ); ?></a> ';
                    html += '<button type="button" class="rndt-button rndt-button--small rndt-button--danger" data-delete-id="' + parseInt(item.id, 10) + '"><?php echo esc_js( __( 'Elimina', 'rndt-manager' ) ); ?></button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;

                // Gestisci click su pulsanti Elimina
                container.addEventListener('click', function(e) {
                    var btn = e.target.closest('[data-delete-id]');
                    if (!btn) return;

                    var deleteId = btn.dataset.deleteId;
                    if (!confirm('<?php echo esc_js( __( 'Eliminare definitivamente questo metadato? L\'azione non è reversibile.', 'rndt-manager' ) ); ?>')) {
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = '<?php echo esc_js( __( 'Eliminazione...', 'rndt-manager' ) ); ?>';

                    fetch(apiUrl + 'metadata/' + deleteId, {
                        method: 'DELETE',
                        headers: { 'X-WP-Nonce': nonce }
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(result) {
                        if (result.deleted || result.success) {
                            var row = btn.closest('tr');
                            if (row) row.remove();
                            // Se tabella vuota, mostra messaggio
                            var tbody = container.querySelector('tbody');
                            if (tbody && tbody.children.length === 0) {
                                container.innerHTML = '<div class="rndt-empty">' +
                                    '<p><?php echo esc_js( __( 'Non hai ancora creato nessun metadato.', 'rndt-manager' ) ); ?></p>' +
                                    '</div>';
                            }
                        } else {
                            alert(result.message || '<?php echo esc_js( __( 'Errore nell\'eliminazione.', 'rndt-manager' ) ); ?>');
                            btn.disabled = false;
                            btn.textContent = '<?php echo esc_js( __( 'Elimina', 'rndt-manager' ) ); ?>';
                        }
                    })
                    .catch(function(err) {
                        alert('<?php echo esc_js( __( 'Errore nell\'eliminazione.', 'rndt-manager' ) ); ?>');
                        btn.disabled = false;
                        btn.textContent = '<?php echo esc_js( __( 'Elimina', 'rndt-manager' ) ); ?>';
                        console.error('RNDT Delete Error:', err);
                    });
                });
            })
            .catch(function(error) {
                container.innerHTML = '<div class="rndt-notice rndt-notice--error">' +
                    '<p><?php echo esc_js( __( 'Errore nel caricamento dei metadati.', 'rndt-manager' ) ); ?></p>' +
                    '</div>';
                console.error('RNDT Error:', error);
            });
        });
        </script>
        <?php
    }

    /**
     * Render editor metadati (wizard React)
     *
     * @param int $metadata_id ID metadato
     */
    private function render_editor( $metadata_id = 0 ) {
        $current_url = get_permalink();
        ?>
        <div class="rndt-frontend rndt-frontend--editor">
            <div class="rndt-frontend__header">
                <a href="<?php echo esc_url( remove_query_arg( array( 'rndt_view', 'rndt_id' ), $current_url ) ); ?>"
                   class="rndt-button rndt-button--text">
                    &larr; <?php esc_html_e( 'Torna alla lista', 'rndt-manager' ); ?>
                </a>
            </div>

            <div class="rndt-frontend__content">
                <!-- Mount point per il wizard React -->
                <div id="rndt-metadata-editor"
                     data-metadata-id="<?php echo $metadata_id ? esc_attr( $metadata_id ) : ''; ?>"
                     data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
                     data-api-url="<?php echo esc_attr( rest_url( 'rndt/v1/' ) ); ?>">
                    <div class="rndt-loading">
                        <span class="rndt-spinner"></span>
                        <p><?php esc_html_e( 'Caricamento editor...', 'rndt-manager' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render catalogo pubblico (sola lettura)
     *
     * @param array $atts Attributi
     */
    private function render_catalog( $atts ) {
        $limit = absint( $atts['limit'] );

        ?>
        <div class="rndt-catalog">
            <div id="rndt-catalog-container"
                 data-limit="<?php echo esc_attr( $limit ); ?>"
                 data-category="<?php echo esc_attr( $atts['category'] ); ?>"
                 data-theme="<?php echo esc_attr( $atts['theme'] ); ?>">
                <div class="rndt-loading">
                    <span class="rndt-spinner"></span>
                    <p><?php esc_html_e( 'Caricamento catalogo...', 'rndt-manager' ); ?></p>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var container = document.getElementById('rndt-catalog-container');
            if (!container) return;

            var limit = container.dataset.limit || 10;
            var apiUrl = '<?php echo esc_url( rest_url( 'rndt/v1/' ) ); ?>';

            fetch(apiUrl + 'metadata?status=publish&per_page=' + limit)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.length === 0) {
                    container.innerHTML = '<p class="rndt-empty"><?php echo esc_js( __( 'Nessun metadato pubblicato.', 'rndt-manager' ) ); ?></p>';
                    return;
                }

                var html = '<div class="rndt-catalog-grid">';
                data.forEach(function(item) {
                    html += '<div class="rndt-catalog-card">';
                    html += '<h3>' + (item.title || '<?php echo esc_js( __( '(Senza titolo)', 'rndt-manager' ) ); ?>') + '</h3>';
                    html += '<p class="rndt-catalog-abstract">' + (item.abstract ? item.abstract.substring(0, 200) + '...' : '') + '</p>';
                    html += '<div class="rndt-catalog-meta">';
                    html += '<span class="rndt-type-badge">' + (item.resource_type || 'dataset') + '</span>';
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';

                container.innerHTML = html;
            })
            .catch(function(error) {
                container.innerHTML = '<div class="rndt-notice rndt-notice--error">' +
                    '<p><?php echo esc_js( __( 'Errore nel caricamento del catalogo.', 'rndt-manager' ) ); ?></p>' +
                    '</div>';
            });
        });
        </script>
        <?php
    }

    /**
     * Ottieni tipi di risorsa
     *
     * @return array
     */
    private function get_resource_types() {
        return array(
            'dataset'     => __( 'Dataset', 'rndt-manager' ),
            'series'      => __( 'Serie', 'rndt-manager' ),
            'service'     => __( 'Servizio', 'rndt-manager' ),
            'application' => __( 'Applicazione', 'rndt-manager' ),
        );
    }

    /**
     * Ottieni lingue disponibili
     *
     * @return array
     */
    private function get_languages() {
        return array(
            array( 'value' => 'ita', 'label' => 'Italiano' ),
            array( 'value' => 'eng', 'label' => 'English' ),
            array( 'value' => 'deu', 'label' => 'Deutsch' ),
            array( 'value' => 'fra', 'label' => 'Français' ),
            array( 'value' => 'spa', 'label' => 'Español' ),
        );
    }

    /**
     * Ottieni charset disponibili
     *
     * @return array
     */
    private function get_charsets() {
        return array(
            array( 'value' => 'utf8',       'label' => 'UTF-8' ),
            array( 'value' => 'utf16',      'label' => 'UTF-16' ),
            array( 'value' => '8859part1',  'label' => 'ISO-8859-1' ),
            array( 'value' => '8859part2',  'label' => 'ISO-8859-2' ),
            array( 'value' => '8859part15', 'label' => 'ISO-8859-15' ),
        );
    }

    /**
     * Ottieni impostazioni pubbliche (senza credenziali)
     *
     * @return array
     */
    private function get_public_settings() {
        $settings = get_option( 'rndt_settings', array() );
        $general  = isset( $settings['general'] ) ? $settings['general'] : array();

        // Restituisci info pubbliche + defaults per il wizard
        return array(
            'defaultIpaCode'      => isset( $general['default_ipa_code'] ) ? $general['default_ipa_code'] : '',
            'defaultOrganization' => isset( $general['default_organization'] ) ? $general['default_organization'] : '',
            'defaultLanguage'     => isset( $general['default_language'] ) ? $general['default_language'] : 'ita',
            'autoGenerateUuid'    => ! empty( $general['auto_generate_uuid'] ),
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
     * Ottieni stringhe i18n
     *
     * @return array
     */
    private function get_i18n_strings() {
        return array(
            'save'      => __( 'Salva', 'rndt-manager' ),
            'cancel'    => __( 'Annulla', 'rndt-manager' ),
            'delete'    => __( 'Elimina', 'rndt-manager' ),
            'confirm'   => __( 'Conferma', 'rndt-manager' ),
            'loading'   => __( 'Caricamento...', 'rndt-manager' ),
            'error'     => __( 'Errore', 'rndt-manager' ),
            'success'   => __( 'Operazione completata', 'rndt-manager' ),
        );
    }

    /**
     * Ottieni dati utente corrente
     *
     * @return array
     */
    private function get_current_user_data() {
        $user = wp_get_current_user();

        if ( ! $user->exists() ) {
            return array();
        }

        return array(
            'id'          => $user->ID,
            'name'        => $user->display_name,
            'email'       => $user->user_email,
            'canPublish'  => RNDT_Capabilities::current_user_can_publish_csw(),
            'canManage'   => RNDT_Capabilities::current_user_can_manage(),
        );
    }
}
