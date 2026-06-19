<?php
/**
 * Client CSW per pyCSW
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-http-client.php';

/**
 * Classe RNDT_CSW_Client
 *
 * Client CSW-T (Transactional) per pubblicazione su pyCSW
 */
class RNDT_CSW_Client extends RNDT_HTTP_Client {

    /**
     * Namespace CSW
     */
    const CSW_NS = 'http://www.opengis.net/cat/csw/2.0.2';

    /**
     * Namespace OWS
     */
    const OWS_NS = 'http://www.opengis.net/ows';

    /**
     * Versione CSW
     */
    const CSW_VERSION = '2.0.2';

    /**
     * Costruttore
     *
     * @param string $endpoint URL endpoint CSW
     * @param array  $options  Opzioni
     */
    public function __construct( $endpoint, $options = array() ) {
        parent::__construct( $endpoint, $options );

        // Headers predefiniti per CSW
        $this->default_headers['Content-Type'] = 'application/xml';
        $this->default_headers['Accept'] = 'application/xml';
    }

    /**
     * Test connessione (GetCapabilities)
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        $response = $this->get( '', array(
            'service' => 'CSW',
            'version' => self::CSW_VERSION,
            'request' => 'GetCapabilities',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return new WP_Error(
                'csw_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Errore CSW: codice HTTP %d', 'rndt-manager' ),
                    $response['status_code']
                )
            );
        }

        // Verifica che sia una risposta CSW valida
        $xml = simplexml_load_string( $response['body'] );
        if ( $xml === false ) {
            return new WP_Error( 'csw_invalid_response', __( 'Risposta non valida dal server CSW.', 'rndt-manager' ) );
        }

        // Verifica ExceptionReport
        if ( $xml->getName() === 'ExceptionReport' ) {
            $exception = (string) $xml->Exception->ExceptionText;
            return new WP_Error( 'csw_exception', $exception );
        }

        return array(
            'success' => true,
            'message' => __( 'Connessione riuscita.', 'rndt-manager' ),
            'capabilities' => $this->parse_capabilities( $xml ),
        );
    }

    /**
     * Inserisce un record (CSW-T Insert)
     *
     * @param string $xml_record Record XML ISO 19139
     * @return array|WP_Error
     */
    public function insert( $xml_record ) {
        $transaction = $this->build_transaction( 'Insert', $xml_record );

        $response = $this->post( '', $transaction, array(
            'Content-Type' => 'application/xml',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->parse_transaction_response( $response );
    }

    /**
     * Aggiorna un record (CSW-T Update)
     *
     * @param string $identifier  Identificativo record
     * @param string $xml_record  Record XML ISO 19139
     * @return array|WP_Error
     */
    public function update( $identifier, $xml_record ) {
        $transaction = $this->build_transaction( 'Update', $xml_record, $identifier );

        $response = $this->post( '', $transaction, array(
            'Content-Type' => 'application/xml',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->parse_transaction_response( $response );
    }

    /**
     * Elimina un record (CSW-T Delete)
     *
     * @param string $identifier Identificativo record
     * @return array|WP_Error
     */
    public function delete_record( $identifier ) {
        $transaction = $this->build_delete_transaction( $identifier );

        $response = $this->post( '', $transaction, array(
            'Content-Type' => 'application/xml',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->parse_transaction_response( $response );
    }

    /**
     * Ottieni record per ID (GetRecordById)
     *
     * @param string $identifier Identificativo record
     * @return array|WP_Error
     */
    public function get_record_by_id( $identifier ) {
        $response = $this->get( '', array(
            'service'        => 'CSW',
            'version'        => self::CSW_VERSION,
            'request'        => 'GetRecordById',
            'Id'             => $identifier,
            'ElementSetName' => 'full',
            'outputSchema'   => 'http://www.isotc211.org/2005/gmd',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return new WP_Error(
                'csw_error',
                sprintf( __( 'Errore CSW: codice HTTP %d', 'rndt-manager' ), $response['status_code'] )
            );
        }

        // Parse risposta
        $xml = simplexml_load_string( $response['body'] );
        if ( $xml === false ) {
            return new WP_Error( 'csw_parse_error', __( 'Errore nel parsing della risposta.', 'rndt-manager' ) );
        }

        // Verifica ExceptionReport
        if ( $xml->getName() === 'ExceptionReport' ) {
            $exception = (string) $xml->Exception->ExceptionText;
            return new WP_Error( 'csw_exception', $exception );
        }

        // Conta risultati
        $xml->registerXPathNamespace( 'csw', self::CSW_NS );
        $xml->registerXPathNamespace( 'gmd', 'http://www.isotc211.org/2005/gmd' );

        $records = $xml->xpath( '//gmd:MD_Metadata' );

        if ( empty( $records ) ) {
            return new WP_Error( 'not_found', __( 'Record non trovato.', 'rndt-manager' ) );
        }

        return array(
            'success' => true,
            'record'  => $records[0]->asXML(),
        );
    }

    /**
     * Cerca records (GetRecords)
     *
     * @param array $params Parametri ricerca
     * @return array|WP_Error
     */
    public function get_records( $params = array() ) {
        $defaults = array(
            'startPosition' => 1,
            'maxRecords'    => 10,
            'outputSchema'  => 'http://www.isotc211.org/2005/gmd',
            'typeNames'     => 'gmd:MD_Metadata',
        );

        $params = array_merge( $defaults, $params );

        $request_xml = $this->build_get_records_request( $params );

        $response = $this->post( '', $request_xml, array(
            'Content-Type' => 'application/xml',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->parse_get_records_response( $response );
    }

    /**
     * Costruisce richiesta Transaction
     *
     * @param string $operation  Operazione (Insert/Update)
     * @param string $xml_record Record XML
     * @param string $identifier Identificativo (per Update)
     * @return string
     */
    private function build_transaction( $operation, $xml_record, $identifier = null ) {
        $doc = new DOMDocument( '1.0', 'UTF-8' );

        // Root Transaction
        $transaction = $doc->createElementNS( self::CSW_NS, 'csw:Transaction' );
        $transaction->setAttribute( 'service', 'CSW' );
        $transaction->setAttribute( 'version', self::CSW_VERSION );
        $doc->appendChild( $transaction );

        // Operation element
        $op_element = $doc->createElementNS( self::CSW_NS, 'csw:' . $operation );

        if ( $operation === 'Update' && $identifier ) {
            // Per Update, aggiungi Constraint
            $constraint = $doc->createElementNS( self::CSW_NS, 'csw:Constraint' );
            $constraint->setAttribute( 'version', '1.1.0' );

            $filter = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:Filter' );
            $property = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:PropertyIsEqualTo' );

            $prop_name = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:PropertyName' );
            $prop_name->nodeValue = 'apiso:Identifier';
            $property->appendChild( $prop_name );

            $literal = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:Literal' );
            $literal->nodeValue = $identifier;
            $property->appendChild( $literal );

            $filter->appendChild( $property );
            $constraint->appendChild( $filter );
            $op_element->appendChild( $constraint );
        }

        // Importa record XML
        $record_doc = new DOMDocument();
        $record_doc->loadXML( $xml_record );
        $imported = $doc->importNode( $record_doc->documentElement, true );
        $op_element->appendChild( $imported );

        $transaction->appendChild( $op_element );

        return $doc->saveXML();
    }

    /**
     * Costruisce richiesta Delete Transaction
     *
     * @param string $identifier Identificativo
     * @return string
     */
    private function build_delete_transaction( $identifier ) {
        $doc = new DOMDocument( '1.0', 'UTF-8' );

        $transaction = $doc->createElementNS( self::CSW_NS, 'csw:Transaction' );
        $transaction->setAttribute( 'service', 'CSW' );
        $transaction->setAttribute( 'version', self::CSW_VERSION );
        $doc->appendChild( $transaction );

        $delete = $doc->createElementNS( self::CSW_NS, 'csw:Delete' );

        $constraint = $doc->createElementNS( self::CSW_NS, 'csw:Constraint' );
        $constraint->setAttribute( 'version', '1.1.0' );

        $filter = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:Filter' );
        $property = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:PropertyIsEqualTo' );

        $prop_name = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:PropertyName' );
        $prop_name->nodeValue = 'apiso:Identifier';
        $property->appendChild( $prop_name );

        $literal = $doc->createElementNS( 'http://www.opengis.net/ogc', 'ogc:Literal' );
        $literal->nodeValue = $identifier;
        $property->appendChild( $literal );

        $filter->appendChild( $property );
        $constraint->appendChild( $filter );
        $delete->appendChild( $constraint );
        $transaction->appendChild( $delete );

        return $doc->saveXML();
    }

    /**
     * Costruisce richiesta GetRecords
     *
     * @param array $params Parametri
     * @return string
     */
    private function build_get_records_request( $params ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <csw:GetRecords xmlns:csw="' . self::CSW_NS . '"
            service="CSW"
            version="' . self::CSW_VERSION . '"
            resultType="results"
            startPosition="' . $params['startPosition'] . '"
            maxRecords="' . $params['maxRecords'] . '"
            outputSchema="' . $params['outputSchema'] . '">
            <csw:Query typeNames="' . $params['typeNames'] . '">
                <csw:ElementSetName>full</csw:ElementSetName>
            </csw:Query>
        </csw:GetRecords>';

        return $xml;
    }

    /**
     * Parse risposta Transaction
     *
     * @param array $response Risposta HTTP
     * @return array|WP_Error
     */
    private function parse_transaction_response( $response ) {
        if ( ! $response['success'] ) {
            return new WP_Error(
                'csw_http_error',
                sprintf( __( 'Errore HTTP: %d', 'rndt-manager' ), $response['status_code'] )
            );
        }

        $xml = simplexml_load_string( $response['body'] );
        if ( $xml === false ) {
            return new WP_Error( 'csw_parse_error', __( 'Errore nel parsing della risposta.', 'rndt-manager' ) );
        }

        // Verifica ExceptionReport
        if ( $xml->getName() === 'ExceptionReport' ) {
            $exception = (string) $xml->Exception->ExceptionText;
            return new WP_Error( 'csw_exception', $exception );
        }

        // Parse TransactionResponse
        $xml->registerXPathNamespace( 'csw', self::CSW_NS );

        $total_inserted = (int) $xml->TransactionSummary->totalInserted;
        $total_updated = (int) $xml->TransactionSummary->totalUpdated;
        $total_deleted = (int) $xml->TransactionSummary->totalDeleted;

        return array(
            'success'        => true,
            'total_inserted' => $total_inserted,
            'total_updated'  => $total_updated,
            'total_deleted'  => $total_deleted,
        );
    }

    /**
     * Parse risposta GetRecords
     *
     * @param array $response Risposta HTTP
     * @return array|WP_Error
     */
    private function parse_get_records_response( $response ) {
        if ( ! $response['success'] ) {
            return new WP_Error( 'csw_http_error', sprintf( __( 'Errore HTTP: %d', 'rndt-manager' ), $response['status_code'] ) );
        }

        $xml = simplexml_load_string( $response['body'] );
        if ( $xml === false ) {
            return new WP_Error( 'csw_parse_error', __( 'Errore nel parsing della risposta.', 'rndt-manager' ) );
        }

        if ( $xml->getName() === 'ExceptionReport' ) {
            $exception = (string) $xml->Exception->ExceptionText;
            return new WP_Error( 'csw_exception', $exception );
        }

        $xml->registerXPathNamespace( 'csw', self::CSW_NS );

        $search_results = $xml->children( self::CSW_NS )->SearchResults;
        $total = (int) $search_results['numberOfRecordsMatched'];
        $returned = (int) $search_results['numberOfRecordsReturned'];

        return array(
            'success'         => true,
            'total_matched'   => $total,
            'total_returned'  => $returned,
            'records'         => $response['body'],
        );
    }

    /**
     * Parse Capabilities
     *
     * @param SimpleXMLElement $xml XML capabilities
     * @return array
     */
    private function parse_capabilities( $xml ) {
        $capabilities = array(
            'title'     => '',
            'abstract'  => '',
            'operations'=> array(),
        );

        $xml->registerXPathNamespace( 'ows', self::OWS_NS );
        $xml->registerXPathNamespace( 'csw', self::CSW_NS );

        $title = $xml->xpath( '//ows:ServiceIdentification/ows:Title' );
        if ( ! empty( $title ) ) {
            $capabilities['title'] = (string) $title[0];
        }

        $abstract = $xml->xpath( '//ows:ServiceIdentification/ows:Abstract' );
        if ( ! empty( $abstract ) ) {
            $capabilities['abstract'] = (string) $abstract[0];
        }

        $operations = $xml->xpath( '//ows:OperationsMetadata/ows:Operation/@name' );
        foreach ( $operations as $op ) {
            $capabilities['operations'][] = (string) $op;
        }

        return $capabilities;
    }
}
