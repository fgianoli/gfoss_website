<?php
/**
 * REST API per importazione metadati
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-rest-controller.php';

/**
 * Classe RNDT_REST_Import
 */
class RNDT_REST_Import extends RNDT_REST_Controller {

    /**
     * Resource name
     *
     * @var string
     */
    protected $rest_base = 'import';

    /**
     * Registra le routes
     */
    public function register_routes() {
        // POST /rndt/v1/import/xml - Importa XML
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/xml',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'import_xml' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'xml' => array(
                            'description' => __( 'XML da importare.', 'rndt-manager' ),
                            'type'        => 'string',
                            'required'    => false,
                        ),
                        'validate' => array(
                            'description' => __( 'Valida dopo l\'importazione.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => true,
                        ),
                        'overwrite' => array(
                            'description' => __( 'Sovrascrivi se esiste con stesso file_identifier.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => false,
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/import/file - Importa file caricato
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/file',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'import_file' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'validate' => array(
                            'description' => __( 'Valida dopo l\'importazione.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => true,
                        ),
                        'overwrite' => array(
                            'description' => __( 'Sovrascrivi se esiste.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => false,
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/import/csw - Importa da CSW
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/csw',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'import_from_csw' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'csw_url' => array(
                            'description' => __( 'URL endpoint CSW.', 'rndt-manager' ),
                            'type'        => 'string',
                            'required'    => true,
                            'format'      => 'uri',
                        ),
                        'identifier' => array(
                            'description' => __( 'Identificativo record da importare.', 'rndt-manager' ),
                            'type'        => 'string',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/import/preview - Preview importazione
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/preview',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'preview_import' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'xml' => array(
                            'description' => __( 'XML da analizzare.', 'rndt-manager' ),
                            'type'        => 'string',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Importa XML
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function import_xml( $request ) {
        $xml = $request->get_param( 'xml' );
        $validate = $request->get_param( 'validate' );
        $overwrite = $request->get_param( 'overwrite' );

        // Se non c'è XML nel body, controlla il raw body
        if ( empty( $xml ) ) {
            $xml = $request->get_body();
        }

        if ( empty( $xml ) ) {
            return $this->error_response(
                'no_xml',
                __( 'Nessun XML fornito.', 'rndt-manager' ),
                400
            );
        }

        return $this->process_xml_import( $xml, $validate, $overwrite );
    }

    /**
     * Importa file caricato
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function import_file( $request ) {
        $files = $request->get_file_params();
        $validate = $request->get_param( 'validate' );
        $overwrite = $request->get_param( 'overwrite' );

        if ( empty( $files['file'] ) ) {
            return $this->error_response(
                'no_file',
                __( 'Nessun file caricato.', 'rndt-manager' ),
                400
            );
        }

        $file = $files['file'];

        // Verifica errori upload
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return $this->error_response(
                'upload_error',
                __( 'Errore durante l\'upload del file.', 'rndt-manager' ),
                400
            );
        }

        // Verifica tipo file
        $allowed_types = array( 'text/xml', 'application/xml' );
        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            // Verifica anche l'estensione
            $ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
            if ( strtolower( $ext ) !== 'xml' ) {
                return $this->error_response(
                    'invalid_file_type',
                    __( 'Il file deve essere in formato XML.', 'rndt-manager' ),
                    400
                );
            }
        }

        $xml = file_get_contents( $file['tmp_name'] );

        if ( empty( $xml ) ) {
            return $this->error_response(
                'empty_file',
                __( 'Il file è vuoto.', 'rndt-manager' ),
                400
            );
        }

        return $this->process_xml_import( $xml, $validate, $overwrite );
    }

    /**
     * Importa da CSW
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function import_from_csw( $request ) {
        $csw_url = $request->get_param( 'csw_url' );
        $identifier = $request->get_param( 'identifier' );

        // Costruisci richiesta GetRecordById
        $params = array(
            'service'       => 'CSW',
            'version'       => '2.0.2',
            'request'       => 'GetRecordById',
            'Id'            => $identifier,
            'ElementSetName'=> 'full',
            'outputSchema'  => 'http://www.isotc211.org/2005/gmd',
        );

        $url = add_query_arg( $params, $csw_url );

        $response = wp_remote_get( $url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/xml',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->error_response(
                'csw_error',
                sprintf(
                    /* translators: %s: Messaggio errore */
                    __( 'Errore nella richiesta CSW: %s', 'rndt-manager' ),
                    $response->get_error_message()
                ),
                500
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return $this->error_response(
                'csw_http_error',
                sprintf(
                    /* translators: %d: Codice HTTP */
                    __( 'Il server CSW ha risposto con codice %d.', 'rndt-manager' ),
                    $status_code
                ),
                500
            );
        }

        $body = wp_remote_retrieve_body( $response );

        // Estrai MD_Metadata dalla risposta CSW
        $xml = $this->extract_metadata_from_csw_response( $body );

        if ( is_wp_error( $xml ) ) {
            return $xml;
        }

        return $this->process_xml_import( $xml, true, false );
    }

    /**
     * Preview importazione
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function preview_import( $request ) {
        $xml = $request->get_param( 'xml' );

        require_once RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-parser.php';
        $parser = new RNDT_XML_Parser();
        $parsed = $parser->parse( $xml );

        if ( is_wp_error( $parsed ) ) {
            return $this->error_response(
                'parse_error',
                $parsed->get_error_message(),
                400
            );
        }

        // Verifica se esiste già
        $file_identifier = $parsed['fields']['file_identifier'] ?? '';
        $existing = null;

        if ( ! empty( $file_identifier ) ) {
            require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
            $repository = RNDT_Metadata_Repository::get_instance();
            $existing = $repository->find_by_file_identifier( $file_identifier );
        }

        // Valida
        require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validator.php';
        $validator = new RNDT_Validator();

        $data = array_merge(
            $parsed['fields'],
            array(
                'keywords'            => $parsed['keywords'],
                'responsible_parties' => $parsed['responsible_parties'],
                'conformity'          => $parsed['conformity'],
                'service_operations'  => $parsed['service_operations'],
                'coupled_resources'   => $parsed['coupled_resources'],
            )
        );

        $resource_type = $parsed['fields']['resource_type'] ?? 'dataset';
        $validation = $validator->validate_array( $data, $resource_type );

        return $this->success_response( array(
            'preview'         => true,
            'file_identifier' => $file_identifier,
            'title'           => $parsed['fields']['title'] ?? '',
            'resource_type'   => $resource_type,
            'existing'        => $existing ? array(
                'id'    => $existing->id,
                'title' => $existing->title,
            ) : null,
            'fields'          => $parsed['fields'],
            'keywords_count'  => count( $parsed['keywords'] ),
            'contacts_count'  => count( $parsed['responsible_parties'] ),
            'validation'      => $validation,
        ) );
    }

    /**
     * Processa importazione XML
     *
     * @param string $xml       XML da importare
     * @param bool   $validate  Valida dopo importazione
     * @param bool   $overwrite Sovrascrivi se esiste
     * @return WP_REST_Response|WP_Error
     */
    private function process_xml_import( $xml, $validate, $overwrite ) {
        // Parse XML
        require_once RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-parser.php';
        $parser = new RNDT_XML_Parser();
        $parsed = $parser->parse( $xml );

        if ( is_wp_error( $parsed ) ) {
            return $this->error_response(
                'parse_error',
                $parsed->get_error_message(),
                400
            );
        }

        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
        $repository = RNDT_Metadata_Repository::get_instance();

        // Verifica se esiste già
        $file_identifier = $parsed['fields']['file_identifier'] ?? '';
        $existing = null;

        if ( ! empty( $file_identifier ) ) {
            $existing = $repository->find_by_file_identifier( $file_identifier );
        }

        if ( $existing && ! $overwrite ) {
            return $this->error_response(
                'already_exists',
                sprintf(
                    /* translators: %s: File identifier */
                    __( 'Un metadato con identificativo "%s" esiste già.', 'rndt-manager' ),
                    $file_identifier
                ),
                409
            );
        }

        // Prepara dati per salvataggio
        $data = $parsed['fields'];
        $data['keywords'] = $parsed['keywords'];
        $data['responsible_parties'] = $parsed['responsible_parties'];
        $data['online_resources'] = $parsed['online_resources'];
        $data['distribution_formats'] = $parsed['distribution_formats'];
        $data['conformity'] = $parsed['conformity'];
        $data['service_operations'] = $parsed['service_operations'];
        $data['coupled_resources'] = $parsed['coupled_resources'];

        // Salva o aggiorna
        if ( $existing && $overwrite ) {
            $result = $repository->update( $existing->id, $data );
            $action = 'updated';
            $id = $existing->id;
        } else {
            $result = $repository->create( $data );
            $action = 'created';
            $id = $result;
        }

        if ( is_wp_error( $result ) ) {
            return $this->error_response(
                'save_error',
                $result->get_error_message(),
                500
            );
        }

        // Valida se richiesto
        $validation = null;
        if ( $validate ) {
            $metadata = $repository->find( $id );
            if ( $metadata ) {
                require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validator.php';
                $validator = new RNDT_Validator();
                $validation = $validator->validate( $metadata, false );

                // Aggiorna stato post
                $status = $validation['valid'] ? 'pending' : 'draft';
                wp_update_post( array(
                    'ID'          => $metadata->post_id,
                    'post_status' => $status,
                ) );
            }
        }

        return $this->success_response( array(
            'success'         => true,
            'action'          => $action,
            'id'              => $id,
            'file_identifier' => $file_identifier,
            'title'           => $data['title'] ?? '',
            'resource_type'   => $data['resource_type'] ?? 'dataset',
            'validation'      => $validation,
        ) );
    }

    /**
     * Estrai MD_Metadata dalla risposta CSW
     *
     * @param string $csw_response Risposta CSW
     * @return string|WP_Error XML estratto o errore
     */
    private function extract_metadata_from_csw_response( $csw_response ) {
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );

        if ( ! $doc->loadXML( $csw_response ) ) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            return new WP_Error(
                'invalid_csw_response',
                __( 'Risposta CSW non valida.', 'rndt-manager' )
            );
        }

        $xpath = new DOMXPath( $doc );
        $xpath->registerNamespace( 'csw', 'http://www.opengis.net/cat/csw/2.0.2' );
        $xpath->registerNamespace( 'gmd', 'http://www.isotc211.org/2005/gmd' );

        // Cerca MD_Metadata
        $metadata_nodes = $xpath->query( '//gmd:MD_Metadata' );

        if ( $metadata_nodes->length === 0 ) {
            // Potrebbe essere un errore CSW
            $exception = $xpath->query( '//ows:ExceptionText' );
            if ( $exception->length > 0 ) {
                return new WP_Error(
                    'csw_exception',
                    $exception->item( 0 )->nodeValue
                );
            }

            return new WP_Error(
                'no_metadata_found',
                __( 'Nessun metadato trovato nella risposta CSW.', 'rndt-manager' )
            );
        }

        // Estrai primo MD_Metadata
        $metadata_node = $metadata_nodes->item( 0 );

        // Crea nuovo documento con solo MD_Metadata
        $new_doc = new DOMDocument( '1.0', 'UTF-8' );
        $new_doc->formatOutput = true;

        $imported = $new_doc->importNode( $metadata_node, true );
        $new_doc->appendChild( $imported );

        return $new_doc->saveXML();
    }
}
