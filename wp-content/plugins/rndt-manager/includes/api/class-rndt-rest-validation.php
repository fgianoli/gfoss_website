<?php
/**
 * REST API per validazione metadati
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-rest-controller.php';

/**
 * Classe RNDT_REST_Validation
 */
class RNDT_REST_Validation extends RNDT_REST_Controller {

    /**
     * Resource name
     *
     * @var string
     */
    protected $rest_base = 'validate';

    /**
     * Registra le routes
     */
    public function register_routes() {
        // POST /rndt/v1/validate/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'validate_metadata' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'ID del metadato da validare.', 'rndt-manager' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                        'include_xsd' => array(
                            'description' => __( 'Includi validazione XSD.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => false,
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/validate - Valida dati inline
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'validate_inline' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'data' => array(
                            'description' => __( 'Dati metadato da validare.', 'rndt-manager' ),
                            'type'        => 'object',
                            'required'    => true,
                        ),
                        'resource_type' => array(
                            'description' => __( 'Tipo di risorsa.', 'rndt-manager' ),
                            'type'        => 'string',
                            'default'     => 'dataset',
                            'enum'        => array( 'dataset', 'series', 'service', 'application' ),
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/validate/xml - Valida XML
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/xml',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'validate_xml' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'xml' => array(
                            'description' => __( 'XML da validare.', 'rndt-manager' ),
                            'type'        => 'string',
                            'required'    => true,
                        ),
                        'include_xsd' => array(
                            'description' => __( 'Includi validazione XSD.', 'rndt-manager' ),
                            'type'        => 'boolean',
                            'default'     => true,
                        ),
                    ),
                ),
            )
        );

        // GET /rndt/v1/validate/rules - Ottieni regole validazione
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/rules',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_validation_rules' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'resource_type' => array(
                            'description' => __( 'Tipo di risorsa.', 'rndt-manager' ),
                            'type'        => 'string',
                            'default'     => 'dataset',
                            'enum'        => array( 'dataset', 'series', 'service', 'application' ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Valida metadato esistente
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function validate_metadata( $request ) {
        $id = $request->get_param( 'id' );
        $include_xsd = $request->get_param( 'include_xsd' );

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

        // Valida
        require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validator.php';
        $validator = new RNDT_Validator();
        $result = $validator->validate( $metadata, $include_xsd );

        // Aggiorna stato nel DB PostgreSQL
        $new_status = $result['valid'] ? 'valid' : 'invalid';
        $this->db->update(
            'rndt_metadata',
            array(
                'validation_status' => $new_status,
                'last_validated_at' => current_time( 'mysql', true ),
            ),
            array( 'id' => $id )
        );

        // Aggiorna stato post WordPress se valido
        if ( $result['valid'] && ! empty( $metadata->post_id ) ) {
            wp_update_post( array(
                'ID'          => $metadata->post_id,
                'post_status' => 'pending',
            ) );
        }

        return $this->success_response( array(
            'id'                => $id,
            'valid'             => $result['valid'],
            'errors'            => $result['errors'],
            'warnings'          => $result['warnings'],
            'validation_status' => $new_status,
        ) );
    }

    /**
     * Valida dati inline
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function validate_inline( $request ) {
        $data = $request->get_param( 'data' );
        $resource_type = $request->get_param( 'resource_type' );

        if ( ! is_array( $data ) ) {
            return $this->error_response(
                'invalid_data',
                __( 'I dati devono essere un oggetto.', 'rndt-manager' ),
                400
            );
        }

        require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validator.php';
        $validator = new RNDT_Validator();
        $result = $validator->validate_array( $data, $resource_type );

        return $this->success_response( array(
            'valid'    => $result['valid'],
            'errors'   => $result['errors'],
            'warnings' => $result['warnings'],
        ) );
    }

    /**
     * Valida XML
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function validate_xml( $request ) {
        $xml = $request->get_param( 'xml' );
        $include_xsd = $request->get_param( 'include_xsd' );

        $results = array(
            'valid'    => true,
            'errors'   => array(),
            'warnings' => array(),
        );

        // 1. Parse XML
        require_once RNDT_MANAGER_PATH . 'includes/xml/class-rndt-xml-parser.php';
        $parser = new RNDT_XML_Parser();
        $parsed = $parser->parse( $xml );

        if ( is_wp_error( $parsed ) ) {
            return $this->success_response( array(
                'valid'    => false,
                'errors'   => array( array(
                    'field'   => 'xml',
                    'message' => $parsed->get_error_message(),
                ) ),
                'warnings' => array(),
            ) );
        }

        // 2. Valida dati estratti
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
        $content_result = $validator->validate_array( $data, $resource_type );

        $results['errors'] = array_merge( $results['errors'], $content_result['errors'] );
        $results['warnings'] = array_merge( $results['warnings'], $content_result['warnings'] );

        // 3. Valida XSD
        if ( $include_xsd ) {
            require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-xsd-validator.php';
            $xsd_validator = new RNDT_XSD_Validator();

            if ( ! $xsd_validator->validate( $xml ) ) {
                foreach ( $xsd_validator->get_errors() as $error ) {
                    if ( $error['type'] === 'warning' ) {
                        $results['warnings'][] = array(
                            'field'   => 'xsd',
                            'message' => $error['message'],
                        );
                    } else {
                        $results['errors'][] = array(
                            'field'   => 'xsd',
                            'message' => $error['message'],
                        );
                    }
                }
            }
        }

        $results['valid'] = empty( $results['errors'] );

        return $this->success_response( $results );
    }

    /**
     * Ottieni regole validazione
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response
     */
    public function get_validation_rules( $request ) {
        $resource_type = $request->get_param( 'resource_type' );

        require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validation-rules.php';
        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-fields.php';

        $required = RNDT_Validation_Rules::get_required_fields();
        $fields = RNDT_Metadata_Fields::get_fields_config( $resource_type );

        // Combina campi comuni e specifici
        $required_fields = array_merge(
            $required['common'],
            $required[ $resource_type ] ?? array()
        );

        // Costruisci lista completa
        $rules = array();
        foreach ( $fields as $section => $section_fields ) {
            foreach ( $section_fields as $field_id => $field_config ) {
                $rules[ $field_id ] = array(
                    'label'       => $field_config['label'] ?? $field_id,
                    'required'    => isset( $required_fields[ $field_id ] ),
                    'type'        => $field_config['type'] ?? 'text',
                    'section'     => $section,
                    'help'        => $field_config['help'] ?? '',
                    'options'     => $field_config['options'] ?? null,
                    'validations' => array(),
                );

                // Aggiungi validazioni formato
                if ( in_array( $field_id, array( 'metadata_date', 'date_creation', 'date_publication', 'date_revision' ), true ) ) {
                    $rules[ $field_id ]['validations'][] = 'date_iso8601';
                }
                if ( $field_id === 'reference_system_code' ) {
                    $rules[ $field_id ]['validations'][] = 'epsg_code';
                }
                if ( strpos( $field_id, 'email' ) !== false ) {
                    $rules[ $field_id ]['validations'][] = 'email';
                }
                if ( strpos( $field_id, 'url' ) !== false || $field_id === 'linkage' ) {
                    $rules[ $field_id ]['validations'][] = 'url';
                }
            }
        }

        return $this->success_response( array(
            'resource_type'   => $resource_type,
            'required_fields' => $required_fields,
            'field_rules'     => $rules,
            'cross_field'     => array(
                'at_least_one_date' => __( 'Almeno una data (creazione, pubblicazione o revisione) è obbligatoria.', 'rndt-manager' ),
                'bbox_complete'     => __( 'Il bounding box deve essere completo.', 'rndt-manager' ),
                'inspire_keyword'   => __( 'Almeno una parola chiave INSPIRE è obbligatoria.', 'rndt-manager' ),
                'metadata_contact'  => __( 'È obbligatorio un contatto per il metadato.', 'rndt-manager' ),
                'resource_poc'      => __( 'È obbligatorio un punto di contatto per la risorsa.', 'rndt-manager' ),
            ),
        ) );
    }
}
