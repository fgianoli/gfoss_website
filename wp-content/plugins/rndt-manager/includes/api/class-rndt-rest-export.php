<?php
/**
 * REST API per esportazione metadati
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-rest-controller.php';

/**
 * Classe RNDT_REST_Export
 */
class RNDT_REST_Export extends RNDT_REST_Controller {

    /**
     * Resource name
     *
     * @var string
     */
    protected $rest_base = 'export';

    /**
     * Registra le routes
     */
    public function register_routes() {
        // GET /rndt/v1/export/{id}/xml
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/xml',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'export_xml' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'ID del metadato da esportare.', 'rndt-manager' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                        'download' => array(
                            'description' => __( 'Se true, restituisce come file scaricabile.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => false,
                        ),
                        'validate' => array(
                            'description' => __( 'Valida prima dell\'esportazione.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => false,
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/export/batch/xml - Export multiplo
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/batch/xml',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'export_batch_xml' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'ids' => array(
                            'description' => __( 'Array di ID da esportare.', 'rndt-manager' ),
                            'type'        => 'array',
                            'required'    => true,
                            'items'       => array( 'type' => 'integer' ),
                        ),
                        'format' => array(
                            'description' => __( 'Formato output: zip o json.', 'rndt-manager' ),
                            'type'        => 'string',
                            'default'     => 'json',
                            'enum'        => array( 'json', 'zip' ),
                        ),
                    ),
                ),
            )
        );

        // GET /rndt/v1/export/{id}/preview
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/preview',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'preview_xml' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'ID del metadato.', 'rndt-manager' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Esporta metadato in XML
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function export_xml( $request ) {
        $id = $request->get_param( 'id' );
        $download = $request->get_param( 'download' );
        $validate = $request->get_param( 'validate' );

        // Carica metadato
        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
        $repository = RNDT_Metadata_Repository::get_instance();
        $metadata = $repository->find( $id );

        if ( ! $metadata ) {
            return $this->error_response(
                'not_found',
                __( 'Metadato non trovato.', 'rndt-manager' ),
                404
            );
        }

        // Valida se richiesto
        if ( $validate ) {
            require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validator.php';
            $validator = new RNDT_Validator();
            $result = $validator->validate( $metadata, false );

            if ( ! $result['valid'] ) {
                return new WP_Error(
                    'validation_failed',
                    __( 'Il metadato non è valido.', 'rndt-manager' ),
                    array( 'status' => 400, 'errors' => $result['errors'] )
                );
            }
        }

        // Genera XML
        $xml_gen_path = RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-generator.php';
        if ( ! file_exists( $xml_gen_path ) ) {
            return $this->error_response(
                'xml_generator_missing',
                __( 'Il generatore XML non è ancora disponibile. Funzionalità in sviluppo.', 'rndt-manager' ),
                501
            );
        }
        require_once $xml_gen_path;
        $generator = new RNDT_XML_Generator();
        $xml = $generator->generate( $metadata );

        // Se download, restituisci come file
        if ( $download ) {
            // Filename = IPA:UUID (con : sostituito da _)
            $raw_identifier = $metadata->file_identifier ?? 'metadata_' . $id;
            $filename = sanitize_file_name( str_replace( ':', '_', $raw_identifier ) ) . '.xml';

            header( 'Content-Type: application/xml; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . strlen( $xml ) );
            echo $xml;
            exit;
        }

        return $this->success_response( array(
            'id'              => $id,
            'file_identifier' => $metadata->file_identifier,
            'title'           => $metadata->title,
            'xml'             => $xml,
        ) );
    }

    /**
     * Esporta multipli metadati
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function export_batch_xml( $request ) {
        $ids = $request->get_param( 'ids' );
        $format = $request->get_param( 'format' );

        if ( empty( $ids ) ) {
            return $this->error_response(
                'no_ids',
                __( 'Nessun ID specificato.', 'rndt-manager' ),
                400
            );
        }

        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';

        $xml_gen_path = RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-generator.php';
        if ( ! file_exists( $xml_gen_path ) ) {
            return $this->error_response(
                'xml_generator_missing',
                __( 'Il generatore XML non è ancora disponibile. Funzionalità in sviluppo.', 'rndt-manager' ),
                501
            );
        }
        require_once $xml_gen_path;

        $repository = RNDT_Metadata_Repository::get_instance();
        $generator = new RNDT_XML_Generator();

        $results = array();
        $errors = array();

        foreach ( $ids as $id ) {
            $metadata = $repository->find( $id );

            if ( ! $metadata ) {
                $errors[] = array(
                    'id'      => $id,
                    'message' => __( 'Metadato non trovato.', 'rndt-manager' ),
                );
                continue;
            }

            try {
                $xml = $generator->generate( $metadata );
                $results[] = array(
                    'id'              => $id,
                    'file_identifier' => $metadata->file_identifier,
                    'title'           => $metadata->title,
                    'xml'             => $xml,
                );
            } catch ( Exception $e ) {
                $errors[] = array(
                    'id'      => $id,
                    'message' => $e->getMessage(),
                );
            }
        }

        if ( $format === 'zip' ) {
            return $this->create_zip_response( $results );
        }

        return $this->success_response( array(
            'exported' => $results,
            'errors'   => $errors,
            'total'    => count( $ids ),
            'success'  => count( $results ),
            'failed'   => count( $errors ),
        ) );
    }

    /**
     * Preview XML (versione semplificata)
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function preview_xml( $request ) {
        $id = $request->get_param( 'id' );

        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
        $repository = RNDT_Metadata_Repository::get_instance();
        $metadata = $repository->find( $id );

        if ( ! $metadata ) {
            return $this->error_response(
                'not_found',
                __( 'Metadato non trovato.', 'rndt-manager' ),
                404
            );
        }

        $xml_gen_path = RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-generator.php';
        if ( ! file_exists( $xml_gen_path ) ) {
            return $this->error_response(
                'xml_generator_missing',
                __( 'Il generatore XML non è ancora disponibile. Funzionalità in sviluppo.', 'rndt-manager' ),
                501
            );
        }
        require_once $xml_gen_path;
        $generator = new RNDT_XML_Generator();
        $xml = $generator->generate( $metadata );

        // Formatta XML per preview (con indentazione) - LIBXML_NONET previene XXE
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML( $xml, LIBXML_NONET );
        $formatted_xml = $doc->saveXML();

        return $this->success_response( array(
            'id'              => $id,
            'file_identifier' => $metadata->file_identifier,
            'title'           => $metadata->title,
            'xml'             => $formatted_xml,
            'size'            => strlen( $formatted_xml ),
        ) );
    }

    /**
     * Crea risposta ZIP
     *
     * @param array $results Array risultati
     * @return WP_REST_Response|WP_Error
     */
    private function create_zip_response( $results ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return $this->error_response(
                'zip_not_available',
                __( 'ZipArchive non disponibile sul server.', 'rndt-manager' ),
                500
            );
        }

        $temp_file = wp_tempnam( 'rndt_export_' );
        $zip = new ZipArchive();

        if ( $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return $this->error_response(
                'zip_create_error',
                __( 'Impossibile creare file ZIP.', 'rndt-manager' ),
                500
            );
        }

        foreach ( $results as $result ) {
            $filename = sanitize_file_name( $result['file_identifier'] ?? 'metadata_' . $result['id'] ) . '.xml';
            $zip->addFromString( $filename, $result['xml'] );
        }

        $zip->close();

        // Leggi e invia file
        $content = file_get_contents( $temp_file );
        unlink( $temp_file );

        $filename = 'rndt_export_' . gmdate( 'Y-m-d_His' ) . '.zip';

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $content ) );
        echo $content;
        exit;
    }
}
