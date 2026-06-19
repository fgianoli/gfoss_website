<?php
/**
 * REST API per le codelist
 * @package RNDT_Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class RNDT_REST_Codelists extends RNDT_REST_Controller {
    protected $rest_base = 'codelists';

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<type>[a-z-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_codelist' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_codelist( $request ) {
        $type = $request['type'];
        $lang = isset( $_GET['lang'] ) ? sanitize_text_field( $_GET['lang'] ) : 'it';

        switch ( $type ) {
            case 'inspire-themes':
                return $this->success_response( RNDT_Inspire_Themes::get_options( $lang ) );
            case 'topic-categories':
                return $this->success_response( RNDT_Topic_Categories::get_options( $lang ) );
            case 'service-types':
                return $this->success_response( RNDT_Service_Types::get_options( $lang ) );
            case 'role-codes':
                return $this->success_response( RNDT_Role_Codes::get_options( $lang ) );
            case 'restriction-codes':
                return $this->success_response( RNDT_Restriction_Codes::get_options( $lang ) );
            case 'epsg-codes':
                return $this->success_response( RNDT_EPSG_Codes::get_options() );
            default:
                return $this->error_response( 'invalid_codelist', __( 'Codelist non valida.', 'rndt-manager' ), 404 );
        }
    }
}
