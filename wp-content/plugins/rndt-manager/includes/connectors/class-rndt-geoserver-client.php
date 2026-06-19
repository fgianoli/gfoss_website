<?php
/**
 * Client REST API GeoServer
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-http-client.php';

/**
 * Classe RNDT_GeoServer_Client
 *
 * Client per GeoServer REST API
 */
class RNDT_GeoServer_Client extends RNDT_HTTP_Client {

    /**
     * Workspace predefinito
     *
     * @var string
     */
    protected $workspace;

    /**
     * Costruttore
     *
     * @param string $base_url  URL base GeoServer (es: http://localhost:8080/geoserver)
     * @param array  $options   Opzioni
     */
    public function __construct( $base_url, $options = array() ) {
        // Aggiungi /rest al base URL se non presente
        if ( strpos( $base_url, '/rest' ) === false ) {
            $base_url = rtrim( $base_url, '/' ) . '/rest';
        }

        parent::__construct( $base_url, $options );

        $this->workspace = $options['workspace'] ?? 'rndt';

        // Headers predefiniti per GeoServer REST
        $this->default_headers['Accept'] = 'application/json';
    }

    /**
     * Test connessione
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        $response = $this->get( 'about/version.json' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            if ( $response['status_code'] === 401 ) {
                return new WP_Error( 'auth_error', __( 'Autenticazione fallita. Verifica username e password.', 'rndt-manager' ) );
            }
            return new WP_Error(
                'geoserver_error',
                sprintf( __( 'Errore GeoServer: codice HTTP %d', 'rndt-manager' ), $response['status_code'] )
            );
        }

        $data = json_decode( $response['body'], true );

        return array(
            'success' => true,
            'message' => __( 'Connessione riuscita.', 'rndt-manager' ),
            'version' => $data['about']['resource'][0]['Version'] ?? 'unknown',
        );
    }

    /**
     * Ottieni lista workspaces
     *
     * @return array|WP_Error
     */
    public function get_workspaces() {
        $response = $this->get( 'workspaces.json' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return $this->handle_error( $response );
        }

        $data = json_decode( $response['body'], true );
        $workspaces = array();

        if ( isset( $data['workspaces']['workspace'] ) ) {
            foreach ( $data['workspaces']['workspace'] as $ws ) {
                $workspaces[] = $ws['name'];
            }
        }

        return array(
            'success'    => true,
            'workspaces' => $workspaces,
        );
    }

    /**
     * Crea workspace
     *
     * @param string $name Nome workspace
     * @return array|WP_Error
     */
    public function create_workspace( $name ) {
        $response = $this->post(
            'workspaces',
            json_encode( array(
                'workspace' => array( 'name' => $name ),
            ) ),
            array( 'Content-Type' => 'application/json' )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( $response['success'] ) {
            return array(
                'success' => true,
                'message' => sprintf( __( 'Workspace "%s" creato.', 'rndt-manager' ), $name ),
            );
        }

        return $this->handle_error( $response );
    }

    /**
     * Verifica esistenza workspace
     *
     * @param string $name Nome workspace
     * @return bool
     */
    public function workspace_exists( $name ) {
        $response = $this->get( "workspaces/{$name}.json" );
        return ! is_wp_error( $response ) && $response['success'];
    }

    /**
     * Ottieni lista datastore in workspace
     *
     * @param string $workspace Nome workspace
     * @return array|WP_Error
     */
    public function get_datastores( $workspace = null ) {
        $ws = $workspace ?? $this->workspace;
        $response = $this->get( "workspaces/{$ws}/datastores.json" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return $this->handle_error( $response );
        }

        $data = json_decode( $response['body'], true );
        $stores = array();

        if ( isset( $data['dataStores']['dataStore'] ) ) {
            foreach ( $data['dataStores']['dataStore'] as $ds ) {
                $stores[] = $ds['name'];
            }
        }

        return array(
            'success'    => true,
            'datastores' => $stores,
        );
    }

    /**
     * Crea datastore PostGIS
     *
     * @param string $name       Nome datastore
     * @param array  $connection Parametri connessione
     * @param string $workspace  Workspace
     * @return array|WP_Error
     */
    public function create_postgis_datastore( $name, $connection, $workspace = null ) {
        $ws = $workspace ?? $this->workspace;

        $datastore = array(
            'dataStore' => array(
                'name'                  => $name,
                'type'                  => 'PostGIS',
                'enabled'               => true,
                'connectionParameters'  => array(
                    'entry' => array(
                        array( '@key' => 'host', '$' => $connection['host'] ),
                        array( '@key' => 'port', '$' => $connection['port'] ?? '5432' ),
                        array( '@key' => 'database', '$' => $connection['database'] ),
                        array( '@key' => 'user', '$' => $connection['user'] ),
                        array( '@key' => 'passwd', '$' => $connection['password'] ),
                        array( '@key' => 'dbtype', '$' => 'postgis' ),
                        array( '@key' => 'schema', '$' => $connection['schema'] ?? 'public' ),
                    ),
                ),
            ),
        );

        $response = $this->post(
            "workspaces/{$ws}/datastores",
            json_encode( $datastore ),
            array( 'Content-Type' => 'application/json' )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( $response['success'] ) {
            return array(
                'success' => true,
                'message' => sprintf( __( 'Datastore "%s" creato.', 'rndt-manager' ), $name ),
            );
        }

        return $this->handle_error( $response );
    }

    /**
     * Ottieni lista layer
     *
     * @param string $workspace Workspace
     * @return array|WP_Error
     */
    public function get_layers( $workspace = null ) {
        $ws = $workspace ?? $this->workspace;
        $response = $this->get( "workspaces/{$ws}/layers.json" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return $this->handle_error( $response );
        }

        $data = json_decode( $response['body'], true );
        $layers = array();

        if ( isset( $data['layers']['layer'] ) ) {
            foreach ( $data['layers']['layer'] as $layer ) {
                $layers[] = $layer['name'];
            }
        }

        return array(
            'success' => true,
            'layers'  => $layers,
        );
    }

    /**
     * Pubblica layer da feature type
     *
     * @param string $datastore   Nome datastore
     * @param string $table_name  Nome tabella
     * @param array  $options     Opzioni layer
     * @param string $workspace   Workspace
     * @return array|WP_Error
     */
    public function publish_layer( $datastore, $table_name, $options = array(), $workspace = null ) {
        $ws = $workspace ?? $this->workspace;

        $feature_type = array(
            'featureType' => array(
                'name'       => $options['name'] ?? $table_name,
                'nativeName' => $table_name,
                'title'      => $options['title'] ?? $table_name,
                'abstract'   => $options['abstract'] ?? '',
                'srs'        => $options['srs'] ?? 'EPSG:4326',
                'enabled'    => true,
            ),
        );

        if ( isset( $options['keywords'] ) ) {
            $feature_type['featureType']['keywords'] = array(
                'string' => $options['keywords'],
            );
        }

        $response = $this->post(
            "workspaces/{$ws}/datastores/{$datastore}/featuretypes",
            json_encode( $feature_type ),
            array( 'Content-Type' => 'application/json' )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( $response['success'] ) {
            return array(
                'success'    => true,
                'message'    => sprintf( __( 'Layer "%s" pubblicato.', 'rndt-manager' ), $options['name'] ?? $table_name ),
                'layer_name' => $options['name'] ?? $table_name,
            );
        }

        return $this->handle_error( $response );
    }

    /**
     * Elimina layer
     *
     * @param string $layer_name Nome layer
     * @param string $workspace  Workspace
     * @return array|WP_Error
     */
    public function delete_layer( $layer_name, $workspace = null ) {
        $ws = $workspace ?? $this->workspace;
        $response = $this->delete( "workspaces/{$ws}/layers/{$layer_name}?recurse=true" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( $response['success'] ) {
            return array(
                'success' => true,
                'message' => sprintf( __( 'Layer "%s" eliminato.', 'rndt-manager' ), $layer_name ),
            );
        }

        return $this->handle_error( $response );
    }

    /**
     * Ottieni dettagli layer
     *
     * @param string $layer_name Nome layer
     * @param string $workspace  Workspace
     * @return array|WP_Error
     */
    public function get_layer( $layer_name, $workspace = null ) {
        $ws = $workspace ?? $this->workspace;
        $response = $this->get( "workspaces/{$ws}/layers/{$layer_name}.json" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return $this->handle_error( $response );
        }

        $data = json_decode( $response['body'], true );

        return array(
            'success' => true,
            'layer'   => $data['layer'] ?? null,
        );
    }

    /**
     * Aggiorna metadati layer
     *
     * @param string $layer_name Nome layer
     * @param array  $metadata   Metadati da aggiornare
     * @param string $workspace  Workspace
     * @return array|WP_Error
     */
    public function update_layer_metadata( $layer_name, $metadata, $workspace = null ) {
        $ws = $workspace ?? $this->workspace;

        // Ottieni feature type attuale
        $response = $this->get( "workspaces/{$ws}/layers/{$layer_name}.json" );

        if ( is_wp_error( $response ) || ! $response['success'] ) {
            return is_wp_error( $response ) ? $response : $this->handle_error( $response );
        }

        $layer_data = json_decode( $response['body'], true );
        $resource_url = $layer_data['layer']['resource']['href'] ?? null;

        if ( ! $resource_url ) {
            return new WP_Error( 'resource_not_found', __( 'Risorsa layer non trovata.', 'rndt-manager' ) );
        }

        // Ottieni feature type
        $ft_response = wp_remote_get( $resource_url, array(
            'headers' => $this->apply_auth( array( 'Accept' => 'application/json' ) ),
            'timeout' => $this->timeout,
        ) );

        if ( is_wp_error( $ft_response ) ) {
            return $ft_response;
        }

        $ft_data = json_decode( wp_remote_retrieve_body( $ft_response ), true );

        // Aggiorna metadati
        if ( isset( $metadata['title'] ) ) {
            $ft_data['featureType']['title'] = $metadata['title'];
        }
        if ( isset( $metadata['abstract'] ) ) {
            $ft_data['featureType']['abstract'] = $metadata['abstract'];
        }
        if ( isset( $metadata['keywords'] ) ) {
            $ft_data['featureType']['keywords'] = array( 'string' => $metadata['keywords'] );
        }

        // Invia aggiornamento
        $update_response = $this->put(
            str_replace( $this->base_url . '/', '', $resource_url ),
            json_encode( $ft_data ),
            array( 'Content-Type' => 'application/json' )
        );

        if ( is_wp_error( $update_response ) ) {
            return $update_response;
        }

        if ( $update_response['success'] ) {
            return array(
                'success' => true,
                'message' => __( 'Metadati layer aggiornati.', 'rndt-manager' ),
            );
        }

        return $this->handle_error( $update_response );
    }

    /**
     * Gestisce errori HTTP
     *
     * @param array $response Risposta HTTP
     * @return WP_Error
     */
    private function handle_error( $response ) {
        $message = __( 'Errore GeoServer sconosciuto.', 'rndt-manager' );
        $parsed = false;

        if ( ! empty( $response['body'] ) ) {
            // Prova a decodificare JSON
            $data = json_decode( $response['body'], true );
            if ( isset( $data['message'] ) ) {
                $message = $data['message'];
                $parsed = true;
            } elseif ( isset( $data['error'] ) ) {
                $message = $data['error'];
                $parsed = true;
            } else {
                // Potrebbe essere XML
                $xml = @simplexml_load_string( $response['body'] );
                if ( $xml && isset( $xml->ServiceException ) ) {
                    $message = (string) $xml->ServiceException;
                    $parsed = true;
                }
            }

            // Se non è stato possibile parsare, includi un frammento del body
            if ( ! $parsed ) {
                $snippet = wp_strip_all_tags( substr( $response['body'], 0, 200 ) );
                if ( ! empty( trim( $snippet ) ) ) {
                    $message = sprintf( __( 'Risposta GeoServer non riconosciuta: %s', 'rndt-manager' ), $snippet );
                }
            }
        }

        return new WP_Error(
            'geoserver_error',
            sprintf( '%s (HTTP %d)', $message, $response['status_code'] )
        );
    }
}
