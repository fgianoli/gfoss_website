<?php
/**
 * REST API per gestione presets parti responsabili
 *
 * @package RNDT_Manager
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-rest-controller.php';

/**
 * Classe RNDT_REST_Responsible_Presets
 */
class RNDT_REST_Responsible_Presets extends RNDT_REST_Controller {

    /**
     * Resource name
     *
     * @var string
     */
    protected $rest_base = 'responsible-presets';

    /**
     * Registra le routes
     */
    public function register_routes() {
        // GET /rndt/v1/responsible-presets — Lista tutti i presets
        // POST /rndt/v1/responsible-presets — Crea nuovo preset
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_create_args(),
                ),
            )
        );

        // PUT /rndt/v1/responsible-presets/{id} — Aggiorna preset
        // DELETE /rndt/v1/responsible-presets/{id} — Elimina preset
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_create_args(),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'id' => array(
                            'type'     => 'integer',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Lista tutti i presets
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response
     */
    public function get_items( $request ) {
        try {
            $results = $this->db->get_results(
                'SELECT * FROM rndt_responsible_presets ORDER BY preset_name ASC',
                array()
            );

            return $this->success_response( $results ?: array() );
        } catch ( \Exception $e ) {
            // La tabella potrebbe non esistere ancora — ritorna array vuoto
            return $this->success_response( array() );
        }
    }

    /**
     * Crea un nuovo preset
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function create_item( $request ) {
        $data = $this->extract_preset_data( $request );

        if ( empty( $data['preset_name'] ) || empty( $data['organisation_name'] ) ) {
            return $this->error_response(
                'missing_required',
                __( 'Nome preset e organizzazione sono obbligatori.', 'rndt-manager' ),
                400
            );
        }

        try {
            $id = $this->db->insert( 'rndt_responsible_presets', $data );
        } catch ( \Exception $e ) {
            return $this->error_response(
                'table_not_found',
                __( 'Tabella presets non trovata. Vai nelle impostazioni e clicca "Crea tabelle".', 'rndt-manager' ),
                500
            );
        }

        if ( ! $id ) {
            $db_error = $this->db->get_last_error();
            return $this->error_response(
                'insert_failed',
                __( 'Errore nel salvataggio del preset.', 'rndt-manager' ) . ( $db_error ? ' DB: ' . $db_error : '' ),
                500
            );
        }

        $preset = $this->db->get_row(
            'SELECT * FROM rndt_responsible_presets WHERE id = :id',
            array( ':id' => $id )
        );

        return $this->success_response( $preset, 201 );
    }

    /**
     * Aggiorna un preset
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function update_item( $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $data = $this->extract_preset_data( $request );

        if ( empty( $data['preset_name'] ) || empty( $data['organisation_name'] ) ) {
            return $this->error_response(
                'missing_required',
                __( 'Nome preset e organizzazione sono obbligatori.', 'rndt-manager' ),
                400
            );
        }

        $existing = $this->db->get_row(
            'SELECT id FROM rndt_responsible_presets WHERE id = :id',
            array( ':id' => $id )
        );

        if ( ! $existing ) {
            return $this->error_response(
                'not_found',
                __( 'Preset non trovato.', 'rndt-manager' ),
                404
            );
        }

        $this->db->update( 'rndt_responsible_presets', $data, array( 'id' => $id ) );

        $preset = $this->db->get_row(
            'SELECT * FROM rndt_responsible_presets WHERE id = :id',
            array( ':id' => $id )
        );

        return $this->success_response( $preset );
    }

    /**
     * Elimina un preset
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );

        $existing = $this->db->get_row(
            'SELECT id FROM rndt_responsible_presets WHERE id = :id',
            array( ':id' => $id )
        );

        if ( ! $existing ) {
            return $this->error_response(
                'not_found',
                __( 'Preset non trovato.', 'rndt-manager' ),
                404
            );
        }

        $this->db->delete( 'rndt_responsible_presets', array( 'id' => $id ) );

        return $this->success_response( array( 'deleted' => true ) );
    }

    /**
     * Estrai dati preset dalla richiesta
     *
     * @param WP_REST_Request $request Richiesta
     * @return array
     */
    private function extract_preset_data( $request ) {
        return array(
            'preset_name'        => sanitize_text_field( $this->get_param( $request, 'preset_name', '' ) ),
            'organisation_name'  => sanitize_text_field( $this->get_param( $request, 'organisation_name', '' ) ),
            'individual_name'    => sanitize_text_field( $this->get_param( $request, 'individual_name', '' ) ) ?: null,
            'position_name'      => sanitize_text_field( $this->get_param( $request, 'position_name', '' ) ) ?: null,
            'role_code'          => sanitize_text_field( $this->get_param( $request, 'role_code', 'pointOfContact' ) ),
            'phone'              => sanitize_text_field( $this->get_param( $request, 'phone', '' ) ) ?: null,
            'fax'                => sanitize_text_field( $this->get_param( $request, 'fax', '' ) ) ?: null,
            'email'              => sanitize_email( $this->get_param( $request, 'email', '' ) ) ?: null,
            'delivery_point'     => sanitize_text_field( $this->get_param( $request, 'delivery_point', '' ) ) ?: null,
            'city'               => sanitize_text_field( $this->get_param( $request, 'city', '' ) ) ?: null,
            'admin_area'         => sanitize_text_field( $this->get_param( $request, 'admin_area', '' ) ) ?: null,
            'postal_code'        => sanitize_text_field( $this->get_param( $request, 'postal_code', '' ) ) ?: null,
            'country'            => sanitize_text_field( $this->get_param( $request, 'country', 'Italia' ) ),
            'online_resource_url' => esc_url_raw( $this->get_param( $request, 'url', '' ) ) ?: null,
            'ipa_code'           => sanitize_text_field( $this->get_param( $request, 'ipa_code', '' ) ) ?: null,
        );
    }

    /**
     * Argomenti per creazione/aggiornamento
     *
     * @return array
     */
    private function get_create_args() {
        return array(
            'preset_name' => array(
                'type'        => 'string',
                'required'    => true,
                'description' => __( 'Nome identificativo del preset.', 'rndt-manager' ),
            ),
            'organisation_name' => array(
                'type'        => 'string',
                'required'    => true,
                'description' => __( 'Nome organizzazione.', 'rndt-manager' ),
            ),
            'individual_name' => array( 'type' => 'string' ),
            'position_name'   => array( 'type' => 'string' ),
            'role_code'       => array( 'type' => 'string', 'default' => 'pointOfContact' ),
            'phone'           => array( 'type' => 'string' ),
            'fax'             => array( 'type' => 'string' ),
            'email'           => array( 'type' => 'string' ),
            'delivery_point'  => array( 'type' => 'string' ),
            'city'            => array( 'type' => 'string' ),
            'admin_area'      => array( 'type' => 'string' ),
            'postal_code'     => array( 'type' => 'string' ),
            'country'         => array( 'type' => 'string', 'default' => 'Italia' ),
            'url'             => array( 'type' => 'string' ),
            'ipa_code'        => array( 'type' => 'string' ),
        );
    }
}
