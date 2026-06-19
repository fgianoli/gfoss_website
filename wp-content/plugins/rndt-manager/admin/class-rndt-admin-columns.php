<?php
/**
 * Colonne personalizzate per la lista dei metadati
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Admin_Columns
 */
class RNDT_Admin_Columns {

    /**
     * Inizializza le colonne
     */
    public function __construct() {
        add_filter( 'manage_rndt_metadata_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_rndt_metadata_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-rndt_metadata_sortable_columns', array( $this, 'set_sortable_columns' ) );
        add_action( 'pre_get_posts', array( $this, 'handle_sorting' ) );
        add_filter( 'bulk_actions-edit-rndt_metadata', array( $this, 'add_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-rndt_metadata', array( $this, 'handle_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_filters' ) );
        add_filter( 'parse_query', array( $this, 'filter_query' ) );
        add_filter( 'post_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
        add_action( 'admin_init', array( $this, 'handle_row_delete' ) );
        add_action( 'admin_init', array( $this, 'redirect_post_edit_to_wizard' ) );
        add_filter( 'get_edit_post_link', array( $this, 'filter_edit_post_link' ), 10, 3 );
    }

    /**
     * Definisce le colonne
     *
     * @param array $columns Colonne esistenti.
     * @return array Colonne modificate.
     */
    public function set_columns( $columns ) {
        $new_columns = array(
            'cb'             => $columns['cb'],
            'title'          => __( 'Titolo', 'rndt-manager' ),
            'resource_type'  => __( 'Tipo risorsa', 'rndt-manager' ),
            'inspire_themes' => __( 'Temi INSPIRE', 'rndt-manager' ),
            'file_identifier'=> __( 'Identificativo', 'rndt-manager' ),
            'validation'     => __( 'Stato validazione', 'rndt-manager' ),
            'csw_status'     => __( 'CSW', 'rndt-manager' ),
            'date'           => __( 'Data', 'rndt-manager' ),
        );

        return $new_columns;
    }

    /**
     * Renderizza le colonne personalizzate
     *
     * @param string $column  Nome della colonna.
     * @param int    $post_id ID del post.
     */
    public function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'resource_type':
                $this->render_resource_type_column( $post_id );
                break;

            case 'inspire_themes':
                $this->render_inspire_themes_column( $post_id );
                break;

            case 'file_identifier':
                $this->render_file_identifier_column( $post_id );
                break;

            case 'validation':
                $this->render_validation_column( $post_id );
                break;

            case 'csw_status':
                $this->render_csw_status_column( $post_id );
                break;
        }
    }

    /**
     * Renderizza colonna tipo risorsa
     *
     * @param int $post_id ID del post.
     */
    private function render_resource_type_column( $post_id ) {
        $resource_type = get_post_meta( $post_id, '_rndt_resource_type', true );
        $types = RNDT_Post_Type::get_resource_types();

        if ( isset( $types[ $resource_type ] ) ) {
            $icons = array(
                'dataset' => 'dashicons-database',
                'series'  => 'dashicons-grid-view',
                'service' => 'dashicons-cloud',
                'application' => 'dashicons-desktop',
            );

            $icon = isset( $icons[ $resource_type ] ) ? $icons[ $resource_type ] : 'dashicons-media-default';

            printf(
                '<span class="dashicons %s" style="color:#666;"></span> %s',
                esc_attr( $icon ),
                esc_html( $types[ $resource_type ] )
            );
        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }

    /**
     * Renderizza colonna temi INSPIRE
     *
     * @param int $post_id ID del post.
     */
    private function render_inspire_themes_column( $post_id ) {
        $terms = wp_get_post_terms( $post_id, 'rndt_inspire_theme', array( 'fields' => 'names' ) );

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            $count = count( $terms );
            if ( $count <= 2 ) {
                echo esc_html( implode( ', ', $terms ) );
            } else {
                printf(
                    '%s <span title="%s" style="color:#666;cursor:help;">(+%d)</span>',
                    esc_html( implode( ', ', array_slice( $terms, 0, 2 ) ) ),
                    esc_attr( implode( ', ', array_slice( $terms, 2 ) ) ),
                    $count - 2
                );
            }
        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }

    /**
     * Renderizza colonna identificativo
     *
     * @param int $post_id ID del post.
     */
    private function render_file_identifier_column( $post_id ) {
        $file_id = get_post_meta( $post_id, '_rndt_file_identifier', true );

        if ( $file_id ) {
            printf(
                '<code style="font-size:11px;">%s</code>',
                esc_html( strlen( $file_id ) > 30 ? substr( $file_id, 0, 27 ) . '...' : $file_id )
            );
        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }

    /**
     * Renderizza colonna validazione
     *
     * @param int $post_id ID del post.
     */
    private function render_validation_column( $post_id ) {
        $validation = get_post_meta( $post_id, '_rndt_validation_result', true );

        if ( ! $validation ) {
            echo '<span class="dashicons dashicons-minus" style="color:#999;" title="' . esc_attr__( 'Non validato', 'rndt-manager' ) . '"></span>';
            return;
        }

        $is_valid = isset( $validation['valid'] ) && $validation['valid'];
        $errors   = isset( $validation['errors'] ) ? count( $validation['errors'] ) : 0;
        $warnings = isset( $validation['warnings'] ) ? count( $validation['warnings'] ) : 0;

        if ( $is_valid ) {
            echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" title="' . esc_attr__( 'Valido', 'rndt-manager' ) . '"></span>';
        } elseif ( $errors > 0 ) {
            printf(
                '<span class="dashicons dashicons-warning" style="color:#dc3232;" title="%s"></span> <span style="color:#dc3232;">%d</span>',
                esc_attr( sprintf( __( '%d errori', 'rndt-manager' ), $errors ) ),
                $errors
            );
        }

        if ( $warnings > 0 ) {
            printf(
                ' <span class="dashicons dashicons-info" style="color:#f0b849;" title="%s"></span>',
                esc_attr( sprintf( __( '%d avvisi', 'rndt-manager' ), $warnings ) )
            );
        }
    }

    /**
     * Renderizza colonna stato CSW
     *
     * @param int $post_id ID del post.
     */
    private function render_csw_status_column( $post_id ) {
        $csw_id    = get_post_meta( $post_id, '_rndt_csw_record_id', true );
        $csw_date  = get_post_meta( $post_id, '_rndt_csw_published_date', true );
        $csw_error = get_post_meta( $post_id, '_rndt_csw_last_error', true );

        if ( $csw_id ) {
            $title = sprintf(
                __( 'Pubblicato il %s', 'rndt-manager' ),
                wp_date( get_option( 'date_format' ), strtotime( $csw_date ) )
            );
            echo '<span class="dashicons dashicons-yes" style="color:#46b450;" title="' . esc_attr( $title ) . '"></span>';
        } elseif ( $csw_error ) {
            echo '<span class="dashicons dashicons-no" style="color:#dc3232;" title="' . esc_attr( $csw_error ) . '"></span>';
        } else {
            echo '<span class="dashicons dashicons-minus" style="color:#999;" title="' . esc_attr__( 'Non pubblicato', 'rndt-manager' ) . '"></span>';
        }
    }

    /**
     * Definisce le colonne ordinabili
     *
     * @param array $columns Colonne.
     * @return array
     */
    public function set_sortable_columns( $columns ) {
        $columns['resource_type']   = 'resource_type';
        $columns['file_identifier'] = 'file_identifier';
        $columns['validation']      = 'validation';
        return $columns;
    }

    /**
     * Gestisce l'ordinamento
     *
     * @param WP_Query $query Query.
     */
    public function handle_sorting( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'edit-rndt_metadata' !== $screen->id ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        switch ( $orderby ) {
            case 'resource_type':
                $query->set( 'meta_key', '_rndt_resource_type' );
                $query->set( 'orderby', 'meta_value' );
                break;

            case 'file_identifier':
                $query->set( 'meta_key', '_rndt_file_identifier' );
                $query->set( 'orderby', 'meta_value' );
                break;
        }
    }

    /**
     * Aggiunge azioni in blocco
     *
     * @param array $actions Azioni esistenti.
     * @return array
     */
    public function add_bulk_actions( $actions ) {
        $actions['rndt_validate']   = __( 'Valida', 'rndt-manager' );
        $actions['rndt_export_xml'] = __( 'Esporta XML', 'rndt-manager' );

        $settings = get_option( 'rndt_settings', array() );
        if ( ! empty( $settings['pycsw']['enabled'] ) ) {
            $actions['rndt_publish_csw'] = __( 'Pubblica su CSW', 'rndt-manager' );
        }

        $actions['rndt_delete'] = __( 'Elimina definitivamente', 'rndt-manager' );

        return $actions;
    }

    /**
     * Gestisce le azioni in blocco
     *
     * @param string $redirect_url URL di redirect.
     * @param string $action       Azione selezionata.
     * @param array  $post_ids     ID dei post selezionati.
     * @return string
     */
    public function handle_bulk_actions( $redirect_url, $action, $post_ids ) {
        if ( ! in_array( $action, array( 'rndt_validate', 'rndt_export_xml', 'rndt_publish_csw', 'rndt_delete' ), true ) ) {
            return $redirect_url;
        }

        $results = array(
            'success' => 0,
            'errors'  => 0,
        );

        foreach ( $post_ids as $post_id ) {
            switch ( $action ) {
                case 'rndt_validate':
                    $result = $this->validate_metadata( $post_id );
                    break;

                case 'rndt_export_xml':
                    // Export viene gestito separatamente con download ZIP
                    $results['success']++;
                    continue 2;

                case 'rndt_publish_csw':
                    $result = $this->publish_to_csw( $post_id );
                    break;

                case 'rndt_delete':
                    $result = $this->delete_metadata_by_post( $post_id );
                    break;
            }

            if ( $result ) {
                $results['success']++;
            } else {
                $results['errors']++;
            }
        }

        // Per export XML, genera ZIP
        if ( 'rndt_export_xml' === $action && ! empty( $post_ids ) ) {
            $this->export_xml_batch( $post_ids );
        }

        $redirect_url = add_query_arg(
            array(
                'rndt_bulk_action' => $action,
                'rndt_success'     => $results['success'],
                'rndt_errors'      => $results['errors'],
            ),
            $redirect_url
        );

        return $redirect_url;
    }

    /**
     * Valida un singolo metadato
     *
     * @param int $post_id ID del post.
     * @return bool
     */
    private function validate_metadata( $post_id ) {
        $repository = new RNDT_Metadata_Repository();
        $metadata   = $repository->get( $post_id );

        if ( ! $metadata ) {
            return false;
        }

        $validator = new RNDT_Validator();
        $result    = $validator->validate( $metadata );

        update_post_meta( $post_id, '_rndt_validation_result', $result );
        update_post_meta( $post_id, '_rndt_validation_date', current_time( 'mysql' ) );

        return $result['valid'];
    }

    /**
     * Pubblica su CSW
     *
     * @param int $post_id ID del post.
     * @return bool
     */
    private function publish_to_csw( $post_id ) {
        $repository = new RNDT_Metadata_Repository();
        $metadata   = $repository->get( $post_id );

        if ( ! $metadata ) {
            return false;
        }

        $settings = get_option( 'rndt_settings', array() );
        $csw_config = isset( $settings['pycsw'] ) ? $settings['pycsw'] : array();

        if ( empty( $csw_config['url'] ) ) {
            return false;
        }

        $csw_client = new RNDT_CSW_Client( $csw_config );
        $generator  = new RNDT_XML_Generator();
        $xml        = $generator->generate( $metadata );

        $existing_id = get_post_meta( $post_id, '_rndt_csw_record_id', true );

        if ( $existing_id ) {
            $result = $csw_client->update( $existing_id, $xml );
        } else {
            $result = $csw_client->insert( $xml );
        }

        if ( is_wp_error( $result ) ) {
            update_post_meta( $post_id, '_rndt_csw_last_error', $result->get_error_message() );
            return false;
        }

        update_post_meta( $post_id, '_rndt_csw_record_id', $result );
        update_post_meta( $post_id, '_rndt_csw_published_date', current_time( 'mysql' ) );
        delete_post_meta( $post_id, '_rndt_csw_last_error' );

        return true;
    }

    /**
     * Esporta XML in batch (ZIP)
     *
     * @param array $post_ids ID dei post.
     */
    private function export_xml_batch( $post_ids ) {
        // Questo crea un file temporaneo e imposta un transient per il download
        $repository = new RNDT_Metadata_Repository();
        $generator  = new RNDT_XML_Generator();
        $files      = array();

        foreach ( $post_ids as $post_id ) {
            $metadata = $repository->get( $post_id );
            if ( $metadata ) {
                $xml = $generator->generate( $metadata );
                $files[ $metadata->get_file_identifier() . '.xml' ] = $xml;
            }
        }

        // Salva per il download
        set_transient( 'rndt_batch_export_' . get_current_user_id(), $files, HOUR_IN_SECONDS );
    }

    /**
     * Mostra avvisi per azioni in blocco
     */
    public function bulk_action_notices() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-rndt_metadata' !== $screen->id ) {
            return;
        }

        if ( ! isset( $_GET['rndt_bulk_action'] ) ) {
            return;
        }

        $action  = sanitize_text_field( wp_unslash( $_GET['rndt_bulk_action'] ) );
        $success = isset( $_GET['rndt_success'] ) ? intval( $_GET['rndt_success'] ) : 0;
        $errors  = isset( $_GET['rndt_errors'] ) ? intval( $_GET['rndt_errors'] ) : 0;

        $messages = array(
            'rndt_validate'    => __( 'Validazione completata: %d successi, %d errori.', 'rndt-manager' ),
            'rndt_export_xml'  => __( 'Export completato: %d metadati esportati.', 'rndt-manager' ),
            'rndt_publish_csw' => __( 'Pubblicazione completata: %d successi, %d errori.', 'rndt-manager' ),
            'rndt_delete'      => __( 'Eliminazione completata: %d metadati eliminati, %d errori.', 'rndt-manager' ),
        );

        if ( isset( $messages[ $action ] ) ) {
            $class = $errors > 0 ? 'notice-warning' : 'notice-success';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr( $class ),
                esc_html( sprintf( $messages[ $action ], $success, $errors ) )
            );
        }
    }

    /**
     * Aggiunge filtri alla lista
     *
     * @param string $post_type Tipo di post.
     */
    public function add_filters( $post_type ) {
        if ( 'rndt_metadata' !== $post_type ) {
            return;
        }

        // Filtro tipo risorsa
        $current_type = isset( $_GET['resource_type'] ) ? sanitize_text_field( wp_unslash( $_GET['resource_type'] ) ) : '';
        $types = RNDT_Post_Type::get_resource_types();

        echo '<select name="resource_type">';
        echo '<option value="">' . esc_html__( 'Tutti i tipi', 'rndt-manager' ) . '</option>';
        foreach ( $types as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $current_type, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        // Filtro stato validazione
        $current_validation = isset( $_GET['validation_status'] ) ? sanitize_text_field( wp_unslash( $_GET['validation_status'] ) ) : '';

        echo '<select name="validation_status">';
        echo '<option value="">' . esc_html__( 'Tutti gli stati', 'rndt-manager' ) . '</option>';
        echo '<option value="valid" ' . selected( $current_validation, 'valid', false ) . '>' . esc_html__( 'Validi', 'rndt-manager' ) . '</option>';
        echo '<option value="invalid" ' . selected( $current_validation, 'invalid', false ) . '>' . esc_html__( 'Non validi', 'rndt-manager' ) . '</option>';
        echo '<option value="not_validated" ' . selected( $current_validation, 'not_validated', false ) . '>' . esc_html__( 'Non validati', 'rndt-manager' ) . '</option>';
        echo '</select>';

        // Filtro stato CSW
        $current_csw = isset( $_GET['csw_status'] ) ? sanitize_text_field( wp_unslash( $_GET['csw_status'] ) ) : '';

        echo '<select name="csw_status">';
        echo '<option value="">' . esc_html__( 'CSW: Tutti', 'rndt-manager' ) . '</option>';
        echo '<option value="published" ' . selected( $current_csw, 'published', false ) . '>' . esc_html__( 'Pubblicati', 'rndt-manager' ) . '</option>';
        echo '<option value="not_published" ' . selected( $current_csw, 'not_published', false ) . '>' . esc_html__( 'Non pubblicati', 'rndt-manager' ) . '</option>';
        echo '</select>';
    }

    /**
     * Filtra la query in base ai filtri
     *
     * @param WP_Query $query Query.
     */
    public function filter_query( $query ) {
        global $pagenow;

        if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
            return;
        }

        if ( 'rndt_metadata' !== $query->get( 'post_type' ) ) {
            return;
        }

        $meta_query = array();

        // Filtro tipo risorsa
        if ( ! empty( $_GET['resource_type'] ) ) {
            $meta_query[] = array(
                'key'   => '_rndt_resource_type',
                'value' => sanitize_text_field( wp_unslash( $_GET['resource_type'] ) ),
            );
        }

        // Filtro validazione
        if ( ! empty( $_GET['validation_status'] ) ) {
            $status = sanitize_text_field( wp_unslash( $_GET['validation_status'] ) );

            switch ( $status ) {
                case 'valid':
                    $meta_query[] = array(
                        'key'     => '_rndt_validation_result',
                        'value'   => '"valid";b:1',
                        'compare' => 'LIKE',
                    );
                    break;

                case 'invalid':
                    $meta_query[] = array(
                        'key'     => '_rndt_validation_result',
                        'value'   => '"valid";b:0',
                        'compare' => 'LIKE',
                    );
                    break;

                case 'not_validated':
                    $meta_query[] = array(
                        'key'     => '_rndt_validation_result',
                        'compare' => 'NOT EXISTS',
                    );
                    break;
            }
        }

        // Filtro CSW
        if ( ! empty( $_GET['csw_status'] ) ) {
            $csw_status = sanitize_text_field( wp_unslash( $_GET['csw_status'] ) );

            if ( 'published' === $csw_status ) {
                $meta_query[] = array(
                    'key'     => '_rndt_csw_record_id',
                    'compare' => 'EXISTS',
                );
            } elseif ( 'not_published' === $csw_status ) {
                $meta_query[] = array(
                    'key'     => '_rndt_csw_record_id',
                    'compare' => 'NOT EXISTS',
                );
            }
        }

        if ( ! empty( $meta_query ) ) {
            $meta_query['relation'] = 'AND';
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Aggiunge azioni alla riga del metadato
     *
     * @param array   $actions Azioni esistenti.
     * @param WP_Post $post    Post corrente.
     * @return array
     */
    public function add_row_actions( $actions, $post ) {
        if ( 'rndt_metadata' !== $post->post_type ) {
            return $actions;
        }

        // Sostituisci il link "Modifica" di default (post.php) con il wizard React
        $rndt_id = get_post_meta( $post->ID, '_rndt_metadata_id', true );
        if ( $rndt_id ) {
            $edit_url = admin_url( 'admin.php?page=rndt-manager-new&id=' . absint( $rndt_id ) );
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html__( 'Modifica', 'rndt-manager' )
            );
        }

        // Rimuovi Quick Edit (non ha senso per metadati RNDT)
        unset( $actions['inline hide-if-no-js'] );

        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?action=rndt_delete_metadata&post_id=' . $post->ID ),
            'rndt_delete_metadata_' . $post->ID
        );

        $actions['rndt_delete'] = sprintf(
            '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
            esc_url( $delete_url ),
            esc_js( __( 'Eliminare definitivamente questo metadato? L\'azione non è reversibile.', 'rndt-manager' ) ),
            esc_html__( 'Elimina', 'rndt-manager' )
        );

        return $actions;
    }

    /**
     * Redirect da post.php al wizard React per i post rndt_metadata
     */
    public function redirect_post_edit_to_wizard() {
        global $pagenow;

        if ( 'post.php' !== $pagenow ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        $action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

        if ( ! $post_id || 'edit' !== $action ) {
            return;
        }

        if ( get_post_type( $post_id ) !== 'rndt_metadata' ) {
            return;
        }

        $rndt_id = get_post_meta( $post_id, '_rndt_metadata_id', true );
        if ( $rndt_id ) {
            wp_redirect( admin_url( 'admin.php?page=rndt-manager-new&id=' . absint( $rndt_id ) ) );
            exit;
        }
    }

    /**
     * Filtra il link "Modifica" per i post rndt_metadata
     *
     * @param string $url     URL di modifica.
     * @param int    $post_id ID del post.
     * @param string $context Contesto.
     * @return string
     */
    public function filter_edit_post_link( $url, $post_id, $context ) {
        if ( get_post_type( $post_id ) !== 'rndt_metadata' ) {
            return $url;
        }

        $rndt_id = get_post_meta( $post_id, '_rndt_metadata_id', true );
        if ( $rndt_id ) {
            return admin_url( 'admin.php?page=rndt-manager-new&id=' . absint( $rndt_id ) );
        }

        return $url;
    }

    /**
     * Gestisce l'eliminazione singola da row action
     */
    public function handle_row_delete() {
        if ( ! isset( $_GET['action'] ) || 'rndt_delete_metadata' !== $_GET['action'] ) {
            return;
        }

        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            return;
        }

        check_admin_referer( 'rndt_delete_metadata_' . $post_id );

        if ( ! current_user_can( 'delete_rndt_metadata', $post_id ) ) {
            wp_die( __( 'Non hai i permessi per eliminare questo metadato.', 'rndt-manager' ) );
        }

        $success = $this->delete_metadata_by_post( $post_id );

        $redirect_url = admin_url( 'edit.php?post_type=rndt_metadata' );
        if ( $success ) {
            $redirect_url = add_query_arg( array(
                'rndt_bulk_action' => 'rndt_delete',
                'rndt_success'     => 1,
                'rndt_errors'      => 0,
            ), $redirect_url );
        } else {
            $redirect_url = add_query_arg( array(
                'rndt_bulk_action' => 'rndt_delete',
                'rndt_success'     => 0,
                'rndt_errors'      => 1,
            ), $redirect_url );
        }

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Elimina un metadato dal DB RNDT e dal post WordPress
     *
     * @param int $post_id ID del post WordPress.
     * @return bool
     */
    private function delete_metadata_by_post( $post_id ) {
        // Trova l'ID RNDT dal post meta
        $rndt_id = get_post_meta( $post_id, '_rndt_metadata_id', true );

        if ( $rndt_id ) {
            // Elimina dal DB RNDT via repository
            try {
                $repository = new RNDT_Metadata_Repository();
                $repository->delete( $rndt_id );
            } catch ( \Exception $e ) {
                // Se il DB RNDT non è disponibile, elimina almeno il post WP
                RNDT_Debug::log( 'Delete from RNDT DB failed for ID ' . $rndt_id . ': ' . $e->getMessage(), 'warning' );
            }
        }

        // Elimina il post WordPress (force delete, no trash)
        $result = wp_delete_post( $post_id, true );

        return (bool) $result;
    }
}
