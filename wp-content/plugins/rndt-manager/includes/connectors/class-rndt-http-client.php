<?php
/**
 * Client HTTP base
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_HTTP_Client
 *
 * Client HTTP base per connessioni esterne
 */
class RNDT_HTTP_Client {

    /**
     * URL base
     *
     * @var string
     */
    protected $base_url;

    /**
     * Timeout in secondi
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Headers predefiniti
     *
     * @var array
     */
    protected $default_headers = array();

    /**
     * Autenticazione
     *
     * @var array
     */
    protected $auth = null;

    /**
     * Ultimo errore
     *
     * @var WP_Error|null
     */
    protected $last_error = null;

    /**
     * Costruttore
     *
     * @param string $base_url URL base
     * @param array  $options  Opzioni
     */
    public function __construct( $base_url, $options = array() ) {
        $this->base_url = rtrim( $base_url, '/' );

        if ( isset( $options['timeout'] ) ) {
            $this->timeout = (int) $options['timeout'];
        }

        if ( isset( $options['headers'] ) ) {
            $this->default_headers = $options['headers'];
        }

        if ( isset( $options['auth'] ) ) {
            $this->set_auth( $options['auth'] );
        }
    }

    /**
     * Imposta autenticazione
     *
     * @param array $auth Array con type, username, password/token
     */
    public function set_auth( $auth ) {
        $this->auth = $auth;
    }

    /**
     * Richiesta GET
     *
     * @param string $endpoint Endpoint
     * @param array  $params   Parametri query
     * @param array  $headers  Headers aggiuntivi
     * @return array|WP_Error
     */
    public function get( $endpoint, $params = array(), $headers = array() ) {
        $url = $this->build_url( $endpoint, $params );
        return $this->request( 'GET', $url, null, $headers );
    }

    /**
     * Richiesta POST
     *
     * @param string $endpoint Endpoint
     * @param mixed  $body     Body richiesta
     * @param array  $headers  Headers aggiuntivi
     * @return array|WP_Error
     */
    public function post( $endpoint, $body = null, $headers = array() ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'POST', $url, $body, $headers );
    }

    /**
     * Richiesta PUT
     *
     * @param string $endpoint Endpoint
     * @param mixed  $body     Body richiesta
     * @param array  $headers  Headers aggiuntivi
     * @return array|WP_Error
     */
    public function put( $endpoint, $body = null, $headers = array() ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'PUT', $url, $body, $headers );
    }

    /**
     * Richiesta DELETE
     *
     * @param string $endpoint Endpoint
     * @param array  $headers  Headers aggiuntivi
     * @return array|WP_Error
     */
    public function delete( $endpoint, $headers = array() ) {
        $url = $this->build_url( $endpoint );
        return $this->request( 'DELETE', $url, null, $headers );
    }

    /**
     * Esegue richiesta HTTP
     *
     * @param string $method  Metodo HTTP
     * @param string $url     URL completo
     * @param mixed  $body    Body richiesta
     * @param array  $headers Headers aggiuntivi
     * @return array|WP_Error
     */
    protected function request( $method, $url, $body = null, $headers = array() ) {
        $this->last_error = null;

        // Costruisci headers
        $all_headers = array_merge( $this->default_headers, $headers );

        // Aggiungi autenticazione
        $all_headers = $this->apply_auth( $all_headers );

        // Opzioni richiesta
        $args = array(
            'method'  => $method,
            'timeout' => $this->timeout,
            'headers' => $all_headers,
        );

        // Body per POST/PUT
        if ( $body !== null && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
            if ( is_array( $body ) ) {
                $args['body'] = wp_json_encode( $body );
                if ( ! isset( $all_headers['Content-Type'] ) ) {
                    $args['headers']['Content-Type'] = 'application/json';
                }
            } else {
                $args['body'] = $body;
            }
        }

        // Esegui richiesta
        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->last_error = $response;
            return $response;
        }

        // Analizza risposta
        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_headers = wp_remote_retrieve_headers( $response );

        return array(
            'status_code' => $status_code,
            'body'        => $response_body,
            'headers'     => $response_headers,
            'success'     => $status_code >= 200 && $status_code < 300,
        );
    }

    /**
     * Costruisce URL completo
     *
     * @param string $endpoint Endpoint
     * @param array  $params   Parametri query
     * @return string
     */
    protected function build_url( $endpoint, $params = array() ) {
        $url = $this->base_url;

        if ( ! empty( $endpoint ) ) {
            $url .= '/' . ltrim( $endpoint, '/' );
        }

        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        return $url;
    }

    /**
     * Applica autenticazione agli headers
     *
     * @param array $headers Headers
     * @return array
     */
    protected function apply_auth( $headers ) {
        if ( empty( $this->auth ) ) {
            return $headers;
        }

        $type = $this->auth['type'] ?? 'basic';

        switch ( $type ) {
            case 'basic':
                $credentials = base64_encode(
                    $this->auth['username'] . ':' . $this->auth['password']
                );
                $headers['Authorization'] = 'Basic ' . $credentials;
                break;

            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $this->auth['token'];
                break;

            case 'api_key':
                $header_name = $this->auth['header'] ?? 'X-Api-Key';
                $headers[ $header_name ] = $this->auth['key'];
                break;
        }

        return $headers;
    }

    /**
     * Ottieni ultimo errore
     *
     * @return WP_Error|null
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Imposta timeout
     *
     * @param int $seconds Secondi
     */
    public function set_timeout( $seconds ) {
        $this->timeout = (int) $seconds;
    }

    /**
     * Aggiungi header predefinito
     *
     * @param string $name  Nome header
     * @param string $value Valore
     */
    public function add_header( $name, $value ) {
        $this->default_headers[ $name ] = $value;
    }
}
