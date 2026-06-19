<?php
/**
 * REST API per pubblicazione su CSW/GeoServer
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-rest-controller.php';

/**
 * Classe RNDT_REST_Publish
 */
class RNDT_REST_Publish extends RNDT_REST_Controller {

    /**
     * Resource name
     *
     * @var string
     */
    protected $rest_base = 'publish';

    /**
     * Registra le routes
     */
    public function register_routes() {
        // POST /rndt/v1/publish/{id}/csw - Pubblica su CSW
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/csw',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'publish_to_csw' ),
                    'permission_callback' => array( $this, 'check_publish_permission' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'ID del metadato da pubblicare.', 'rndt-manager' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                        'action' => array(
                            'description' => __( 'Azione: insert, update, delete.', 'rndt-manager' ),
                            'type'        => 'string',
                            'default'     => 'insert',
                            'enum'        => array( 'insert', 'update', 'delete' ),
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/publish/{id}/geoserver - Associa layer GeoServer
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/geoserver',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'publish_to_geoserver' ),
                    'permission_callback' => array( $this, 'check_publish_permission' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'ID del metadato.', 'rndt-manager' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                        'layer_name' => array(
                            'description' => __( 'Nome layer GeoServer da associare.', 'rndt-manager' ),
                            'type'        => 'string',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );

        // POST /rndt/v1/publish/test-connection - Test connessione
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/test-connection',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'test_connection' ),
                    'permission_callback' => array( $this, 'check_settings_permission' ),
                ),
            )
        );

        // POST /rndt/v1/publish/create-tables - Crea tabelle PostgreSQL
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/create-tables',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_database_tables' ),
                    'permission_callback' => array( $this, 'check_settings_permission' ),
                ),
            )
        );

        // GET /rndt/v1/publish/{id}/status - Stato pubblicazione
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/status',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_publish_status' ),
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

        // GET /rndt/v1/publish/geoserver/layers - Lista layer GeoServer
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/geoserver/layers',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_geoserver_layers' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        // DELETE /rndt/v1/publish/{id}/csw - Rimuovi da CSW
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/csw',
            array(
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'unpublish_from_csw' ),
                    'permission_callback' => array( $this, 'check_publish_permission' ),
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
     * Pubblica su CSW (pyCSW)
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function publish_to_csw( $request ) {
        $id = $request->get_param( 'id' );
        $action = $request->get_param( 'action' );

        // Carica metadato
        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
        $repository = RNDT_Metadata_Repository::get_instance();
        $metadata = $repository->find( $id );

        if ( ! $metadata ) {
            return $this->error_response( 'not_found', __( 'Metadato non trovato.', 'rndt-manager' ), 404 );
        }

        // Valida prima della pubblicazione
        require_once RNDT_MANAGER_PATH . 'includes/validation/class-rndt-validator.php';
        $validator = new RNDT_Validator();
        $validation = $validator->validate( $metadata, false );

        if ( ! $validation['valid'] ) {
            return $this->error_response(
                'validation_failed',
                __( 'Il metadato non è valido. Correggere gli errori prima della pubblicazione.', 'rndt-manager' ),
                400
            );
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

        // Ottieni configurazione CSW
        $csw_config = $this->get_csw_config();
        if ( is_wp_error( $csw_config ) ) {
            return $csw_config;
        }

        // Crea client CSW
        $csw_client_path = RNDT_MANAGER_PATH . 'includes/connectors/class-rndt-csw-client.php';
        if ( ! file_exists( $csw_client_path ) ) {
            return $this->error_response(
                'csw_client_missing',
                __( 'Il client CSW non è ancora disponibile. Funzionalità in sviluppo.', 'rndt-manager' ),
                501
            );
        }
        require_once $csw_client_path;
        $csw_client = new RNDT_CSW_Client( $csw_config['endpoint'], array(
            'auth' => $csw_config['auth'] ?? null,
        ) );

        // Esegui operazione
        $result = null;
        switch ( $action ) {
            case 'insert':
                $result = $csw_client->insert( $xml );
                break;

            case 'update':
                $result = $csw_client->update( $metadata->file_identifier, $xml );
                break;

            case 'delete':
                $result = $csw_client->delete_record( $metadata->file_identifier );
                break;
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Aggiorna stato pubblicazione
        $this->update_publish_status( $id, 'csw', array(
            'published_at' => current_time( 'mysql' ),
            'action'       => $action,
            'success'      => true,
        ) );

        // Aggiorna stato post se insert/update
        if ( in_array( $action, array( 'insert', 'update' ), true ) ) {
            wp_update_post( array(
                'ID'          => $metadata->post_id,
                'post_status' => 'publish',
            ) );
        }

        return $this->success_response( array(
            'success'  => true,
            'action'   => $action,
            'id'       => $id,
            'file_identifier' => $metadata->file_identifier,
            'result'   => $result,
        ) );
    }

    /**
     * Pubblica su GeoServer
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function publish_to_geoserver( $request ) {
        $id         = $request->get_param( 'id' );
        $layer_name = $request->get_param( 'layer_name' );

        if ( empty( $layer_name ) ) {
            return $this->error_response(
                'layer_missing',
                __( 'Seleziona un layer GeoServer da associare.', 'rndt-manager' ),
                400
            );
        }

        // Carica metadato
        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
        $repository = RNDT_Metadata_Repository::get_instance();
        $metadata = $repository->find( $id );

        if ( ! $metadata ) {
            return $this->error_response( 'not_found', __( 'Metadato non trovato.', 'rndt-manager' ), 404 );
        }

        // Ottieni configurazione GeoServer
        $gs_config = $this->get_geoserver_config();
        if ( is_wp_error( $gs_config ) ) {
            return $gs_config;
        }

        $gs_client = $this->create_geoserver_client( $gs_config );
        if ( is_wp_error( $gs_client ) ) {
            return $gs_client;
        }

        $workspace = $gs_config['workspace'];
        $gs_url    = rtrim( $gs_config['url'], '/' );
        $qualified = $workspace . ':' . $layer_name;

        // Aggiorna metadati del layer su GeoServer (best-effort)
        $gs_client->update_layer_metadata( $layer_name, array(
            'title'    => $metadata->title,
            'abstract' => $metadata->abstract ?? '',
            'keywords' => $this->get_metadata_keywords( $metadata ),
        ) );

        // Componi online resources WMS + WFS
        $existing_resources = $repository->get_online_resources( $id );
        $new_resources = array();

        // Mantieni risorse non-GeoServer
        foreach ( $existing_resources as $res ) {
            $res_array = (array) $res;
            $url = $res_array['linkage_url'] ?? '';
            if ( strpos( $url, $gs_url ) === false ) {
                $new_resources[] = $res_array;
            }
        }

        // Aggiungi WMS
        $new_resources[] = array(
            'linkage_url'         => $gs_url . '/' . $workspace . '/wms',
            'protocol'            => 'OGC:WMS',
            'name'                => $qualified,
            'description'         => sprintf( __( 'Servizio WMS - %s', 'rndt-manager' ), $metadata->title ),
            'function'            => 'information',
            'application_profile' => 'WMS 1.3.0',
        );

        // Aggiungi WFS
        $new_resources[] = array(
            'linkage_url'         => $gs_url . '/' . $workspace . '/wfs',
            'protocol'            => 'OGC:WFS',
            'name'                => $qualified,
            'description'         => sprintf( __( 'Servizio WFS - %s', 'rndt-manager' ), $metadata->title ),
            'function'            => 'download',
            'application_profile' => 'WFS 2.0.0',
        );

        // Salva online resources
        $repository->update_online_resources( $id, $new_resources );

        // Aggiorna stato pubblicazione
        $this->update_publish_status( $id, 'geoserver', array(
            'published_at' => current_time( 'mysql' ),
            'layer_name'   => $layer_name,
            'workspace'    => $workspace,
            'success'      => true,
        ) );

        return $this->success_response( array(
            'success'          => true,
            'id'               => $id,
            'layer_name'       => $layer_name,
            'workspace'        => $workspace,
            'resources_added'  => 2,
            'message'          => sprintf(
                __( 'Layer "%s" associato. Aggiunte risorse WMS e WFS alla distribuzione.', 'rndt-manager' ),
                $qualified
            ),
        ) );
    }

    /**
     * Lista layer GeoServer
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function get_geoserver_layers( $request ) {
        $gs_config = $this->get_geoserver_config();
        if ( is_wp_error( $gs_config ) ) {
            return $gs_config;
        }

        $gs_client = $this->create_geoserver_client( $gs_config );
        if ( is_wp_error( $gs_client ) ) {
            return $gs_client;
        }

        $workspace = $gs_config['workspace'];
        $layers    = $gs_client->get_layers( $workspace );

        if ( is_wp_error( $layers ) ) {
            return $layers;
        }

        $gs_url = rtrim( $gs_config['url'], '/' );

        return $this->success_response( array(
            'layers'    => $layers['layers'],
            'workspace' => $workspace,
            'wms_url'   => $gs_url . '/' . $workspace . '/wms',
            'wfs_url'   => $gs_url . '/' . $workspace . '/wfs',
            'wmts_url'  => $gs_url . '/gwc/service/wmts',
        ) );
    }

    /**
     * Crea client GeoServer dalla configurazione
     *
     * @param array $gs_config Configurazione GeoServer
     * @return RNDT_GeoServer_Client|WP_Error
     */
    private function create_geoserver_client( $gs_config ) {
        $gs_client_path = RNDT_MANAGER_PATH . 'includes/connectors/class-rndt-geoserver-client.php';
        if ( ! file_exists( $gs_client_path ) ) {
            return new WP_Error(
                'geoserver_client_missing',
                __( 'Il client GeoServer non è disponibile.', 'rndt-manager' )
            );
        }
        require_once $gs_client_path;

        return new RNDT_GeoServer_Client( $gs_config['url'], array(
            'auth' => array(
                'type'     => 'basic',
                'username' => $gs_config['username'],
                'password' => $gs_config['password'],
            ),
            'workspace' => $gs_config['workspace'],
        ) );
    }

    /**
     * Test connessione
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function test_connection( $request ) {
        $params = $request->get_json_params();
        $service = isset( $params['service'] ) ? $params['service'] : ( isset( $params['type'] ) ? $params['type'] : '' );
        $config = isset( $params['config'] ) ? $params['config'] : $params;

        if ( $service === 'database' ) {
            // Determina il tipo di database configurato
            $settings = get_option( 'rndt_settings', array() );
            $db_type  = isset( $settings['database']['type'] ) ? $settings['database']['type'] : 'postgresql';

            if ( 'wordpress' === $db_type ) {
                // Test connessione WordPress DB ($wpdb)
                global $wpdb;
                $result = $wpdb->query( 'SELECT 1' );
                if ( false === $result ) {
                    return $this->success_response( array(
                        'success' => false,
                        'message' => $wpdb->last_error ?: __( 'Errore connessione al database WordPress.', 'rndt-manager' ),
                    ) );
                }
                return $this->success_response( array(
                    'success' => true,
                    'message' => __( 'Connessione al database WordPress riuscita.', 'rndt-manager' ),
                ) );
            }

            // Test connessione PostgreSQL
            $db_config = array(
                'host'     => $config['host'] ?? 'localhost',
                'port'     => $config['port'] ?? '5432',
                'dbname'   => $config['dbname'] ?? '',
                'user'     => $config['user'] ?? '',
                'password' => $config['password'] ?? '',
                'schema'   => $config['schema'] ?? 'public',
            );

            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $db_config['host'],
                    $db_config['port'],
                    $db_config['dbname']
                );
                $pdo = new PDO( $dsn, $db_config['user'], $db_config['password'] );
                $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

                return $this->success_response( array(
                    'success' => true,
                    'message' => __( 'Connessione al database PostgreSQL riuscita.', 'rndt-manager' ),
                ) );
            } catch ( PDOException $e ) {
                return $this->success_response( array(
                    'success' => false,
                    'message' => $e->getMessage(),
                ) );
            }
        } elseif ( $service === 'csw' ) {
            $csw_path = RNDT_MANAGER_PATH . 'includes/connectors/class-rndt-csw-client.php';
            if ( ! file_exists( $csw_path ) ) {
                return $this->success_response( array( 'success' => false, 'message' => __( 'Client CSW non ancora disponibile.', 'rndt-manager' ) ) );
            }
            require_once $csw_path;

            $endpoint = isset( $config['endpoint'] ) ? $config['endpoint'] : ( isset( $config['url'] ) ? $config['url'] : '' );
            $auth = null;
            if ( ! empty( $config['username'] ) ) {
                $auth = array(
                    'type'     => 'basic',
                    'username' => $config['username'],
                    'password' => $config['password'] ?? '',
                );
            }

            $client = new RNDT_CSW_Client( $endpoint, array( 'auth' => $auth ) );
            $result = $client->test_connection();

        } elseif ( $service === 'geoserver' ) {
            $gs_path = RNDT_MANAGER_PATH . 'includes/connectors/class-rndt-geoserver-client.php';
            if ( ! file_exists( $gs_path ) ) {
                return $this->success_response( array( 'success' => false, 'message' => __( 'Client GeoServer non ancora disponibile.', 'rndt-manager' ) ) );
            }
            require_once $gs_path;

            $client = new RNDT_GeoServer_Client( $config['url'], array(
                'auth' => array(
                    'type'     => 'basic',
                    'username' => $config['username'],
                    'password' => $config['password'],
                ),
            ) );
            $result = $client->test_connection();

        } else {
            return $this->error_response( 'invalid_type', __( 'Tipo connessione non valido.', 'rndt-manager' ), 400 );
        }

        if ( is_wp_error( $result ) ) {
            return $this->success_response( array(
                'success' => false,
                'message' => $result->get_error_message(),
            ) );
        }

        return $this->success_response( $result );
    }

    /**
     * Crea le tabelle nel database
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function create_database_tables( $request ) {
        // Reset singleton per caricare config aggiornata
        RNDT_Database::reset_instance();
        $db = RNDT_Database::get_instance();

        // Test connessione prima
        $pdo = $db->get_connection();
        if ( ! $pdo ) {
            return $this->success_response( array(
                'success' => false,
                'message' => $db->get_last_error() ?: __( 'Impossibile connettersi al database.', 'rndt-manager' ),
            ) );
        }

        // Crea tabelle
        if ( ! $db->create_tables() ) {
            return $this->success_response( array(
                'success' => false,
                'message' => __( 'Errore creazione tabelle: ', 'rndt-manager' ) . $db->get_last_error(),
            ) );
        }

        // Popola lookup tables
        if ( ! $db->seed_lookup_tables() ) {
            return $this->success_response( array(
                'success' => true,
                'message' => __( 'Tabelle create, ma errore nel popolamento lookup: ', 'rndt-manager' ) . $db->get_last_error(),
                'tables_created' => true,
                'lookup_seeded'  => false,
            ) );
        }

        delete_option( 'rndt_manager_db_error' );

        return $this->success_response( array(
            'success' => true,
            'message' => __( 'Tabelle create e popolate con successo!', 'rndt-manager' ),
            'tables_created' => true,
            'lookup_seeded'  => true,
        ) );
    }

    /**
     * Ottieni stato pubblicazione
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function get_publish_status( $request ) {
        $id = $request->get_param( 'id' );

        $status = get_post_meta( $id, '_rndt_publish_status', true );

        return $this->success_response( array(
            'id'     => $id,
            'status' => $status ?: array(
                'csw'       => null,
                'geoserver' => null,
            ),
        ) );
    }

    /**
     * Rimuovi da CSW
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function unpublish_from_csw( $request ) {
        $id = $request->get_param( 'id' );

        require_once RNDT_MANAGER_PATH . 'includes/metadata/class-rndt-metadata-repository.php';
        $repository = RNDT_Metadata_Repository::get_instance();
        $metadata = $repository->find( $id );

        if ( ! $metadata ) {
            return $this->error_response( 'not_found', __( 'Metadato non trovato.', 'rndt-manager' ), 404 );
        }

        $csw_config = $this->get_csw_config();
        if ( is_wp_error( $csw_config ) ) {
            return $csw_config;
        }

        $csw_client_path = RNDT_MANAGER_PATH . 'includes/connectors/class-rndt-csw-client.php';
        if ( ! file_exists( $csw_client_path ) ) {
            return $this->error_response(
                'csw_client_missing',
                __( 'Il client CSW non è ancora disponibile. Funzionalità in sviluppo.', 'rndt-manager' ),
                501
            );
        }
        require_once $csw_client_path;
        $csw_client = new RNDT_CSW_Client( $csw_config['endpoint'], array(
            'auth' => $csw_config['auth'] ?? null,
        ) );

        $result = $csw_client->delete_record( $metadata->file_identifier );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Aggiorna stato
        $this->update_publish_status( $id, 'csw', null );

        wp_update_post( array(
            'ID'          => $metadata->post_id,
            'post_status' => 'pending',
        ) );

        return $this->success_response( array(
            'success' => true,
            'message' => __( 'Metadato rimosso dal catalogo CSW.', 'rndt-manager' ),
        ) );
    }

    /**
     * Ottieni configurazione CSW
     *
     * @return array|WP_Error
     */
    private function get_csw_config() {
        $options = get_option( 'rndt_settings', array() );
        $csw = isset( $options['csw'] ) ? $options['csw'] : array();

        if ( empty( $csw['url'] ) ) {
            return new WP_Error(
                'csw_not_configured',
                __( 'Endpoint CSW non configurato. Vai alle impostazioni.', 'rndt-manager' )
            );
        }

        if ( empty( $csw['enabled'] ) ) {
            return new WP_Error(
                'csw_disabled',
                __( 'La pubblicazione CSW non è abilitata. Vai alle impostazioni.', 'rndt-manager' )
            );
        }

        $config = array(
            'endpoint'     => $csw['url'],
            'catalog_type' => $csw['catalog_type'] ?? 'pycsw',
            'csw_version'  => $csw['csw_version'] ?? '2.0.2',
            'output_schema' => $csw['output_schema'] ?? 'http://www.isotc211.org/2005/gmd',
        );

        // Configura autenticazione
        $auth_type = $csw['auth_type'] ?? 'none';
        if ( $auth_type === 'basic' && ! empty( $csw['username'] ) ) {
            $config['auth'] = array(
                'type'     => 'basic',
                'username' => $csw['username'],
                'password' => $csw['password'] ?? '',
            );
        } elseif ( $auth_type === 'bearer' && ! empty( $csw['bearer_token'] ) ) {
            $config['auth'] = array(
                'type'  => 'bearer',
                'token' => $csw['bearer_token'],
            );
        }

        return $config;
    }

    /**
     * Ottieni configurazione GeoServer
     *
     * @return array|WP_Error
     */
    private function get_geoserver_config() {
        $options = get_option( 'rndt_settings', array() );
        $geoserver = isset( $options['geoserver'] ) ? $options['geoserver'] : array();

        if ( empty( $geoserver['url'] ) ) {
            return new WP_Error(
                'geoserver_not_configured',
                __( 'URL GeoServer non configurato. Vai alle impostazioni.', 'rndt-manager' )
            );
        }

        if ( empty( $geoserver['enabled'] ) ) {
            return new WP_Error(
                'geoserver_disabled',
                __( 'La pubblicazione GeoServer non è abilitata. Vai alle impostazioni.', 'rndt-manager' )
            );
        }

        return array(
            'url'       => $geoserver['url'],
            'username'  => $geoserver['username'] ?? 'admin',
            'password'  => $geoserver['password'] ?? '',
            'workspace' => $geoserver['default_workspace'] ?? 'rndt',
            'datastore' => $geoserver['default_datastore'] ?? '',
        );
    }

    /**
     * Aggiorna stato pubblicazione
     *
     * @param int    $id       ID metadato
     * @param string $type     Tipo (csw/geoserver)
     * @param mixed  $status   Stato
     */
    private function update_publish_status( $id, $type, $status ) {
        $current = get_post_meta( $id, '_rndt_publish_status', true ) ?: array();
        $current[ $type ] = $status;
        update_post_meta( $id, '_rndt_publish_status', $current );
    }

    /**
     * Ottieni keywords dal metadato
     *
     * @param object $metadata Metadato
     * @return array
     */
    private function get_metadata_keywords( $metadata ) {
        $keywords = array();

        if ( method_exists( $metadata, 'get_keywords' ) ) {
            foreach ( $metadata->get_keywords() as $kw ) {
                $keywords[] = $kw['keyword'];
            }
        }

        return $keywords;
    }
}
