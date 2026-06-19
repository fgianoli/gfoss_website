<?php
/**
 * Validatore XSD ISO 19139
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_XSD_Validator
 *
 * Valida documenti XML contro lo schema XSD ISO 19139
 */
class RNDT_XSD_Validator {

    /**
     * Percorso base schemi XSD
     *
     * @var string
     */
    private $schema_path;

    /**
     * Errori di validazione
     *
     * @var array
     */
    private $errors = array();

    /**
     * Costruttore
     */
    public function __construct() {
        $this->schema_path = RNDT_MANAGER_PATH . 'schemas/iso19139/';
    }

    /**
     * Valida XML contro schema XSD
     *
     * @param string $xml_string XML da validare
     * @return bool True se valido, false altrimenti
     */
    public function validate( $xml_string ) {
        $this->errors = array();

        $doc = new DOMDocument();
        libxml_use_internal_errors( true );

        // Carica XML
        if ( ! $doc->loadXML( $xml_string ) ) {
            $this->errors = $this->get_libxml_errors();
            return false;
        }

        // Verifica disponibilità schema
        $schema_file = $this->get_schema_file();
        if ( ! $schema_file ) {
            // Se schema non disponibile, valida solo struttura XML
            $this->errors[] = array(
                'type'    => 'warning',
                'message' => __( 'Schema XSD non disponibile localmente. Validazione strutturale saltata.', 'rndt-manager' ),
            );
            return true;
        }

        // Valida contro schema
        if ( ! $doc->schemaValidate( $schema_file ) ) {
            $this->errors = $this->get_libxml_errors();
            return false;
        }

        libxml_clear_errors();
        return true;
    }

    /**
     * Valida XML contro schema remoto
     *
     * @param string $xml_string   XML da validare
     * @param string $schema_url   URL schema XSD
     * @param int    $timeout      Timeout in secondi
     * @return bool
     */
    public function validate_remote( $xml_string, $schema_url = null, $timeout = 30 ) {
        $this->errors = array();

        if ( ! $schema_url ) {
            $schema_url = 'http://schemas.opengis.net/csw/2.0.2/profiles/apiso/1.0.0/apiso.xsd';
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors( true );

        // Imposta timeout per fetch remoto
        $context = stream_context_create( array(
            'http' => array( 'timeout' => $timeout ),
            'https' => array( 'timeout' => $timeout ),
        ) );
        libxml_set_streams_context( $context );

        if ( ! $doc->loadXML( $xml_string ) ) {
            $this->errors = $this->get_libxml_errors();
            return false;
        }

        if ( ! @$doc->schemaValidate( $schema_url ) ) {
            $this->errors = $this->get_libxml_errors();
            return false;
        }

        libxml_clear_errors();
        return true;
    }

    /**
     * Ottieni file schema locale
     *
     * @return string|false Percorso file o false se non disponibile
     */
    private function get_schema_file() {
        $schema_file = $this->schema_path . 'gmd/gmd.xsd';

        if ( file_exists( $schema_file ) ) {
            return $schema_file;
        }

        // Prova percorso alternativo
        $alt_schema = $this->schema_path . 'iso19139.xsd';
        if ( file_exists( $alt_schema ) ) {
            return $alt_schema;
        }

        return false;
    }

    /**
     * Ottieni errori libxml
     *
     * @return array
     */
    private function get_libxml_errors() {
        $errors = array();
        $xml_errors = libxml_get_errors();

        foreach ( $xml_errors as $error ) {
            $type = 'error';
            switch ( $error->level ) {
                case LIBXML_ERR_WARNING:
                    $type = 'warning';
                    break;
                case LIBXML_ERR_ERROR:
                    $type = 'error';
                    break;
                case LIBXML_ERR_FATAL:
                    $type = 'fatal';
                    break;
            }

            $errors[] = array(
                'type'    => $type,
                'message' => trim( $error->message ),
                'line'    => $error->line,
                'column'  => $error->column,
            );
        }

        libxml_clear_errors();
        return $errors;
    }

    /**
     * Ottieni errori di validazione
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Ottieni errori formattati come stringa
     *
     * @return string
     */
    public function get_errors_string() {
        $messages = array();
        foreach ( $this->errors as $error ) {
            $msg = sprintf( '[%s]', strtoupper( $error['type'] ) );
            if ( ! empty( $error['line'] ) ) {
                $msg .= sprintf( ' Linea %d', $error['line'] );
            }
            $msg .= ': ' . $error['message'];
            $messages[] = $msg;
        }
        return implode( "\n", $messages );
    }

    /**
     * Verifica se ci sono errori fatali
     *
     * @return bool
     */
    public function has_fatal_errors() {
        foreach ( $this->errors as $error ) {
            if ( $error['type'] === 'fatal' || $error['type'] === 'error' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Download e salva schema XSD localmente
     *
     * @return bool|WP_Error
     */
    public function download_schemas() {
        $base_url = 'http://schemas.opengis.net/';
        $schemas = array(
            'csw/2.0.2/profiles/apiso/1.0.0/apiso.xsd',
            'iso/19139/20070417/gmd/gmd.xsd',
            'iso/19139/20070417/gco/gco.xsd',
            'iso/19139/20070417/gsr/gsr.xsd',
            'iso/19139/20070417/gss/gss.xsd',
            'iso/19139/20070417/gts/gts.xsd',
            'iso/19139/20070417/srv/srv.xsd',
            'gml/3.2.1/gml.xsd',
        );

        // Crea directory se non esiste
        if ( ! is_dir( $this->schema_path ) ) {
            wp_mkdir_p( $this->schema_path );
        }

        foreach ( $schemas as $schema ) {
            $url = $base_url . $schema;
            $local_path = $this->schema_path . basename( $schema );

            $response = wp_remote_get( $url, array( 'timeout' => 60 ) );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $body = wp_remote_retrieve_body( $response );
            if ( empty( $body ) ) {
                continue;
            }

            file_put_contents( $local_path, $body );
        }

        return true;
    }
}
