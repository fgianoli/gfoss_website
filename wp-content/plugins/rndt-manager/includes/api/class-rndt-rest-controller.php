<?php
/**
 * Controller base per le REST API
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_REST_Controller
 */
abstract class RNDT_REST_Controller extends WP_REST_Controller {

    /**
     * Namespace delle API
     *
     * @var string
     */
    protected $namespace = 'rndt/v1';

    /**
     * Istanza database
     *
     * @var RNDT_Database
     */
    protected $db;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->db = RNDT_Database::get_instance();
    }

    /**
     * Registra le routes - da implementare nelle classi figlie
     */
    public function register_routes() {
        // Da implementare nelle classi figlie
    }

    /**
     * Verifica i permessi per gestire i metadati
     *
     * @param WP_REST_Request $request Richiesta
     * @return bool|WP_Error
     */
    public function check_manage_permission( $request ) {
        if ( ! current_user_can( 'manage_rndt_metadata' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Non hai i permessi per eseguire questa operazione.', 'rndt-manager' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Verifica i permessi per pubblicare su CSW
     *
     * @param WP_REST_Request $request Richiesta
     * @return bool|WP_Error
     */
    public function check_publish_permission( $request ) {
        if ( ! current_user_can( 'publish_rndt_csw' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Non hai i permessi per pubblicare su CSW.', 'rndt-manager' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Verifica i permessi per le impostazioni
     *
     * @param WP_REST_Request $request Richiesta
     * @return bool|WP_Error
     */
    public function check_settings_permission( $request ) {
        if ( ! current_user_can( 'manage_rndt_settings' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Non hai i permessi per gestire le impostazioni.', 'rndt-manager' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Verifica la connessione al database
     *
     * @return bool|WP_Error
     */
    protected function check_db_connection() {
        if ( ! $this->db->get_connection() ) {
            return new WP_Error(
                'db_connection_error',
                __( 'Impossibile connettersi al database PostgreSQL.', 'rndt-manager' ),
                array( 'status' => 500 )
            );
        }
        return true;
    }

    /**
     * Prepara una risposta di successo
     *
     * @param mixed $data    Dati della risposta
     * @param int   $status  Codice HTTP (default 200)
     * @return WP_REST_Response
     */
    protected function success_response( $data, $status = 200 ) {
        return new WP_REST_Response( $data, $status );
    }

    /**
     * Prepara una risposta di errore
     *
     * @param string $code    Codice errore
     * @param string $message Messaggio errore
     * @param int    $status  Codice HTTP (default 400)
     * @return WP_Error
     */
    protected function error_response( $code, $message, $status = 400 ) {
        return new WP_Error( $code, $message, array( 'status' => $status ) );
    }

    /**
     * Ottieni un parametro dalla richiesta con valore default
     *
     * @param WP_REST_Request $request Richiesta
     * @param string          $param   Nome parametro
     * @param mixed           $default Valore default
     * @return mixed
     */
    protected function get_param( $request, $param, $default = null ) {
        $value = $request->get_param( $param );
        return null !== $value ? $value : $default;
    }

    /**
     * Valida un ID numerico
     *
     * @param mixed $id ID da validare
     * @return int|WP_Error
     */
    protected function validate_id( $id ) {
        $id = absint( $id );
        if ( ! $id ) {
            return $this->error_response(
                'invalid_id',
                __( 'ID non valido.', 'rndt-manager' ),
                400
            );
        }
        return $id;
    }

    /**
     * Schema base per la paginazione
     *
     * @return array
     */
    protected function get_pagination_params() {
        return array(
            'page' => array(
                'description' => __( 'Numero pagina corrente.', 'rndt-manager' ),
                'type'        => 'integer',
                'default'     => 1,
                'minimum'     => 1,
            ),
            'per_page' => array(
                'description' => __( 'Numero di elementi per pagina.', 'rndt-manager' ),
                'type'        => 'integer',
                'default'     => 10,
                'minimum'     => 1,
                'maximum'     => 100,
            ),
        );
    }

    /**
     * Aggiungi header di paginazione alla risposta
     *
     * @param WP_REST_Response $response    Risposta
     * @param int              $total       Totale elementi
     * @param int              $total_pages Totale pagine
     * @return WP_REST_Response
     */
    protected function add_pagination_headers( $response, $total, $total_pages ) {
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', $total_pages );
        return $response;
    }
}
