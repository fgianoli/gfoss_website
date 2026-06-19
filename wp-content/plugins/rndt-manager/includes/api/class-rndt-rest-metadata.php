<?php
/**
 * REST API per i metadati RNDT
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_REST_Metadata
 */
class RNDT_REST_Metadata extends RNDT_REST_Controller {

    /**
     * Base della route
     *
     * @var string
     */
    protected $rest_base = 'metadata';

    /**
     * Registra le routes
     */
    public function register_routes() {
        // Lista metadati
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_create_params(),
                ),
            )
        );

        // Singolo metadato
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'ID del metadato.', 'rndt-manager' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_update_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        // Test connessione database
        register_rest_route(
            $this->namespace,
            '/test-db-connection',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'test_db_connection' ),
                'permission_callback' => array( $this, 'check_settings_permission' ),
            )
        );
    }

    /**
     * Ottieni lista metadati
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function get_items( $request ) {
        $db_check = $this->check_db_connection();
        if ( is_wp_error( $db_check ) ) {
            return $db_check;
        }

        $page     = max( 1, absint( $this->get_param( $request, 'page', 1 ) ) );
        $per_page = min( 100, max( 1, absint( $this->get_param( $request, 'per_page', 10 ) ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $resource_type = $this->get_param( $request, 'resource_type' );
        $search        = $this->get_param( $request, 'search' );

        // Costruisci query
        $where  = array();
        $params = array();

        if ( $resource_type ) {
            $where[]  = 'resource_type = :resource_type';
            $params[':resource_type'] = $resource_type;
        }

        if ( $search ) {
            $where[]  = '(title ILIKE :search OR abstract ILIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // Conta totale
        $total = $this->db->get_var(
            "SELECT COUNT(*) FROM rndt_metadata {$where_sql}",
            $params
        );

        // Ottieni risultati - LIMIT/OFFSET parametrizzati per prevenire SQL injection
        $params[':limit']  = $per_page;
        $params[':offset'] = $offset;
        $results = $this->db->get_results(
            "SELECT * FROM rndt_metadata {$where_sql} ORDER BY updated_at DESC LIMIT :limit OFFSET :offset",
            $params
        );

        $items = array();
        foreach ( $results as $row ) {
            $items[] = $this->prepare_item_for_response( $row );
        }

        $response = $this->success_response( $items );
        $total_pages = ceil( $total / $per_page );

        return $this->add_pagination_headers( $response, $total, $total_pages );
    }

    /**
     * Ottieni singolo metadato
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function get_item( $request ) {
        $db_check = $this->check_db_connection();
        if ( is_wp_error( $db_check ) ) {
            return $db_check;
        }

        $id = $this->validate_id( $request['id'] );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $row = $this->db->get_row(
            'SELECT * FROM rndt_metadata WHERE id = :id',
            array( ':id' => $id )
        );

        if ( ! $row ) {
            return $this->error_response(
                'not_found',
                __( 'Metadato non trovato.', 'rndt-manager' ),
                404
            );
        }

        $item = $this->prepare_item_for_response( $row );

        // Aggiungi dati correlati
        $all_keywords = $this->get_keywords( $id );

        // Separa keywords normali, INSPIRE themes e topic categories
        $item['keywords'] = array();
        $item['inspire_themes'] = array();
        $item['topic_categories'] = array();

        foreach ( $all_keywords as $kw ) {
            if ( isset( $kw['thesaurus_name'] ) && strpos( $kw['thesaurus_name'], 'GEMET - INSPIRE' ) !== false ) {
                // Ricostruisci il codice tema INSPIRE dalla label
                $theme_code = $this->find_inspire_theme_code( $kw['keyword'] );
                if ( $theme_code ) {
                    $item['inspire_themes'][] = $theme_code;
                }
            } elseif ( isset( $kw['keyword_type'] ) && 'topicCategory' === $kw['keyword_type'] ) {
                // Ricostruisci il codice topic category dalla label
                $cat_code = $this->find_topic_category_code( $kw['keyword'] );
                if ( $cat_code ) {
                    $item['topic_categories'][] = $cat_code;
                }
            } else {
                $item['keywords'][] = $kw;
            }
        }

        $item['responsible_parties'] = $this->get_responsible_parties( $id );
        $item['online_resources'] = $this->get_online_resources( $id );
        $item['distribution_formats'] = $this->get_distribution_formats( $id );
        $item['conformity'] = $this->get_conformity( $id );

        return $this->success_response( $item );
    }

    /**
     * Crea nuovo metadato
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function create_item( $request ) {
        $db_check = $this->check_db_connection();
        if ( is_wp_error( $db_check ) ) {
            return $db_check;
        }

        $data = $this->prepare_item_for_database( $request );

        // Aggiungi campi di audit
        $data['created_by'] = get_current_user_id();
        $data['updated_by'] = get_current_user_id();

        $id = $this->db->insert( 'rndt_metadata', $data );

        if ( ! $id ) {
            return $this->error_response(
                'create_failed',
                __( 'Errore durante la creazione del metadato.', 'rndt-manager' ) . ' ' . $this->db->get_last_error(),
                500
            );
        }

        // Salva dati correlati
        $this->save_related_data( $id, $request );

        // Crea anche il post WordPress per l'interfaccia admin
        $this->create_wp_post( $id, $data );

        // Ottieni il record creato
        $request['id'] = $id;
        return $this->get_item( $request );
    }

    /**
     * Aggiorna metadato
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function update_item( $request ) {
        $db_check = $this->check_db_connection();
        if ( is_wp_error( $db_check ) ) {
            return $db_check;
        }

        $id = $this->validate_id( $request['id'] );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        // Verifica esistenza
        $exists = $this->db->get_var(
            'SELECT id FROM rndt_metadata WHERE id = :id',
            array( ':id' => $id )
        );

        if ( ! $exists ) {
            return $this->error_response(
                'not_found',
                __( 'Metadato non trovato.', 'rndt-manager' ),
                404
            );
        }

        $data = $this->prepare_item_for_database( $request );
        $data['updated_by'] = get_current_user_id();
        $data['xml_cache'] = null; // Invalida cache XML

        $result = $this->db->update( 'rndt_metadata', $data, array( 'id' => $id ) );

        if ( false === $result ) {
            return $this->error_response(
                'update_failed',
                __( 'Errore durante l\'aggiornamento del metadato.', 'rndt-manager' ),
                500
            );
        }

        // Aggiorna dati correlati
        $this->save_related_data( $id, $request );

        // Aggiorna post WordPress
        $this->update_wp_post( $id, $data );

        return $this->get_item( $request );
    }

    /**
     * Elimina metadato
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item( $request ) {
        $db_check = $this->check_db_connection();
        if ( is_wp_error( $db_check ) ) {
            return $db_check;
        }

        $id = $this->validate_id( $request['id'] );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        // Ottieni wp_post_id prima di eliminare
        $wp_post_id = $this->db->get_var(
            'SELECT wp_post_id FROM rndt_metadata WHERE id = :id',
            array( ':id' => $id )
        );

        $result = $this->db->delete( 'rndt_metadata', array( 'id' => $id ) );

        if ( ! $result ) {
            return $this->error_response(
                'delete_failed',
                __( 'Errore durante l\'eliminazione del metadato.', 'rndt-manager' ),
                500
            );
        }

        // Elimina post WordPress
        if ( $wp_post_id ) {
            wp_delete_post( $wp_post_id, true );
        }

        return $this->success_response( array( 'deleted' => true ) );
    }

    /**
     * Test connessione database
     *
     * @param WP_REST_Request $request Richiesta
     * @return WP_REST_Response
     */
    public function test_db_connection( $request ) {
        $config = array(
            'host'     => $this->get_param( $request, 'host', 'localhost' ),
            'port'     => $this->get_param( $request, 'port', '5432' ),
            'dbname'   => $this->get_param( $request, 'dbname', 'rndt_metadata' ),
            'user'     => $this->get_param( $request, 'user', '' ),
            'password' => $this->get_param( $request, 'password', '' ),
            'schema'   => $this->get_param( $request, 'schema', 'public' ),
        );

        $result = $this->db->test_connection( $config );

        if ( true === $result ) {
            return $this->success_response( array( 'success' => true ) );
        }

        return $this->success_response( array(
            'success' => false,
            'message' => $result,
        ) );
    }

    /**
     * Prepara item per la risposta
     *
     * @param array           $row     Riga database
     * @param WP_REST_Request $request Richiesta (opzionale)
     * @return array
     */
    public function prepare_item_for_response( $row, $request = null ) {
        return array(
            // Core
            'id'                          => (int) $row['id'],
            'wp_post_id'                  => $row['wp_post_id'] ? (int) $row['wp_post_id'] : null,
            'resource_type'               => $row['resource_type'],
            'file_identifier'             => $row['file_identifier'],
            // Identification
            'title'                       => $row['title'],
            'alternate_title'             => $row['alternate_title'] ?? null,
            'abstract'                    => $row['abstract'],
            'purpose'                     => $row['purpose'] ?? null,
            'resource_identifier'         => $row['resource_identifier'] ?? null,
            'resource_identifier_codespace' => $row['resource_identifier_codespace'] ?? null,
            'resource_language'           => $row['resource_language'],
            'character_set'               => $row['character_set'] ?? null,
            'status'                      => $row['status'] ?? null,
            'edition'                     => $row['edition'] ?? null,
            'series_name'                 => $row['series_name'] ?? null,
            'series_issue'                => $row['series_issue'] ?? null,
            'hierarchy_level_name'        => $row['hierarchy_level_name'] ?? null,
            'spatial_representation_type' => $row['spatial_representation_type'] ?? null,
            // Temporal
            'date_creation'               => $row['date_creation'],
            'date_publication'            => $row['date_publication'],
            'date_revision'               => $row['date_revision'],
            'temporal_extent_begin'       => $row['temporal_extent_begin'] ?? null,
            'temporal_extent_end'         => $row['temporal_extent_end'] ?? null,
            'maintenance_frequency'       => $row['maintenance_frequency'] ?? null,
            // Geographic
            'bbox_west'                   => $row['bbox_west'] ? (float) $row['bbox_west'] : null,
            'bbox_east'                   => $row['bbox_east'] ? (float) $row['bbox_east'] : null,
            'bbox_south'                  => $row['bbox_south'] ? (float) $row['bbox_south'] : null,
            'bbox_north'                  => $row['bbox_north'] ? (float) $row['bbox_north'] : null,
            'geographic_description'      => $row['geographic_description'] ?? null,
            // Quality — map DB names to frontend names
            'lineage'                     => $row['lineage_statement'] ?? null,
            'equivalent_scale'            => $row['spatial_resolution_scale'] ?? null,
            'distance_value'              => $row['spatial_resolution_distance'] ?? null,
            'distance_uom'                => $row['spatial_resolution_units'] ?? null,
            // Constraints
            'use_limitation'              => $row['use_limitation'] ?? null,
            'access_constraints'          => $row['access_constraints'] ?? null,
            'use_constraints'             => $row['use_constraints'] ?? null,
            'other_constraints'           => $row['other_constraints'] ?? null,
            'classification'              => $row['classification'] ?? null,
            // Reference system — map codespace to frontend name
            'reference_system_code'       => $row['reference_system_code'] ?? null,
            'reference_system_code_space' => $row['reference_system_codespace'] ?? null,
            // Metadata info
            'metadata_language'           => $row['metadata_language'] ?? null,
            'metadata_character_set'      => $row['metadata_character_set'] ?? null,
            'metadata_date'               => ! empty( $row['metadata_date'] ) ? substr( $row['metadata_date'], 0, 10 ) : null,
            'metadata_standard_name'      => $row['metadata_standard_name'] ?? null,
            'metadata_standard_version'   => $row['metadata_standard_version'] ?? null,
            'parent_identifier'           => $row['parent_identifier'] ?? null,
            // Service
            'service_type'                => $row['service_type'] ?? null,
            'service_type_version'        => $row['service_type_version'] ?? null,
            'coupling_type'               => $row['coupling_type'] ?? null,
            // Audit
            'validation_status'           => $row['validation_status'],
            'csw_published_at'            => $row['csw_published_at'],
            'created_at'                  => $row['created_at'],
            'updated_at'                  => $row['updated_at'],
        );
    }

    /**
     * Prepara item per il database
     *
     * @param WP_REST_Request $request Richiesta
     * @return array
     */
    protected function prepare_item_for_database( $request ) {
        $fields = array(
            'resource_type', 'title', 'abstract', 'resource_identifier',
            'resource_identifier_codespace', 'parent_identifier', 'resource_language',
            'character_set', 'hierarchy_level_name', 'spatial_representation_type',
            'date_creation', 'date_publication', 'date_revision',
            'temporal_extent_begin', 'temporal_extent_end',
            'bbox_west', 'bbox_east', 'bbox_south', 'bbox_north', 'geographic_description',
            'lineage_statement', 'spatial_resolution_scale', 'spatial_resolution_distance',
            'spatial_resolution_units', 'use_limitation', 'access_constraints',
            'use_constraints', 'other_constraints', 'classification',
            'reference_system_code', 'reference_system_codespace',
            'metadata_language', 'metadata_character_set', 'metadata_date',
            'service_type', 'service_type_version', 'coupling_type', 'maintenance_frequency',
        );

        $data = array();
        foreach ( $fields as $field ) {
            $value = $this->get_param( $request, $field );
            if ( null !== $value ) {
                $data[ $field ] = $value;
            }
        }

        // Frontend → DB field name aliases
        $aliases = array(
            'lineage'                     => 'lineage_statement',
            'equivalent_scale'            => 'spatial_resolution_scale',
            'distance_value'              => 'spatial_resolution_distance',
            'distance_uom'                => 'spatial_resolution_units',
            'reference_system_code_space' => 'reference_system_codespace',
        );
        foreach ( $aliases as $frontend_name => $db_name ) {
            if ( ! isset( $data[ $db_name ] ) ) {
                $value = $this->get_param( $request, $frontend_name );
                if ( null !== $value ) {
                    $data[ $db_name ] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Ottieni keywords per un metadato
     */
    private function get_keywords( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_keywords WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => $metadata_id )
        );
    }

    /**
     * Ottieni responsible parties per un metadato
     * Mappa colonne DB → nomi frontend
     */
    private function get_responsible_parties( $metadata_id ) {
        $rows = $this->db->get_results(
            'SELECT * FROM rndt_responsible_parties WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => $metadata_id )
        );
        return array_map( function( $row ) {
            return array(
                'role_type'         => $row['context'] ?? 'resource_poc',
                'organisation_name' => $row['organisation_name'] ?? '',
                'individual_name'   => $row['individual_name'] ?? '',
                'position_name'     => $row['position_name'] ?? '',
                'role_code'         => $row['role'] ?? 'pointOfContact',
                'phone'             => $row['phone_voice'] ?? '',
                'fax'               => $row['phone_fax'] ?? '',
                'email'             => $row['email'] ?? '',
                'delivery_point'    => $row['delivery_point'] ?? '',
                'city'              => $row['city'] ?? '',
                'admin_area'        => $row['admin_area'] ?? '',
                'postal_code'       => $row['postal_code'] ?? '',
                'country'           => $row['country'] ?? 'Italia',
                'url'               => $row['online_resource_url'] ?? '',
            );
        }, $rows );
    }

    /**
     * Ottieni online resources per un metadato
     * Mappa linkage_url → url per il frontend
     */
    private function get_online_resources( $metadata_id ) {
        $rows = $this->db->get_results(
            'SELECT * FROM rndt_online_resources WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => $metadata_id )
        );
        return array_map( function( $row ) {
            return array(
                'url'                => $row['linkage_url'] ?? '',
                'protocol'           => $row['protocol'] ?? '',
                'name'               => $row['name'] ?? '',
                'description'        => $row['description'] ?? '',
                'function'           => $row['function'] ?? '',
                'application_profile' => $row['application_profile'] ?? '',
            );
        }, $rows );
    }

    /**
     * Ottieni distribution formats per un metadato
     * Mappa format_name → name, format_version → version, format_spec → specification
     */
    private function get_distribution_formats( $metadata_id ) {
        $rows = $this->db->get_results(
            'SELECT * FROM rndt_distribution_formats WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => $metadata_id )
        );
        return array_map( function( $row ) {
            return array(
                'name'          => $row['format_name'] ?? '',
                'version'       => $row['format_version'] ?? '',
                'specification' => $row['format_spec'] ?? '',
            );
        }, $rows );
    }

    /**
     * Ottieni conformity per un metadato
     * Mappa colonna DB degree → frontend pass (boolean)
     */
    private function get_conformity( $metadata_id ) {
        $rows = $this->db->get_results(
            'SELECT * FROM rndt_conformity WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => $metadata_id )
        );
        return array_map( function( $row ) {
            return array(
                'specification_title'     => $row['specification_title'] ?? '',
                'specification_date'      => $row['specification_date'] ?? '',
                'specification_date_type' => $row['specification_date_type'] ?? 'publication',
                'explanation'             => $row['explanation'] ?? '',
                'pass'                    => ! empty( $row['degree'] ),
            );
        }, $rows );
    }

    /**
     * Salva dati correlati
     *
     * Mappa i nomi dei campi frontend ai nomi delle colonne DB.
     */
    private function save_related_data( $metadata_id, $request ) {
        // Keywords
        $keywords = $this->get_param( $request, 'keywords' );
        if ( is_array( $keywords ) ) {
            $this->db->delete( 'rndt_keywords', array( 'metadata_id' => $metadata_id ) );
            foreach ( $keywords as $i => $kw ) {
                $this->db->insert( 'rndt_keywords', array(
                    'metadata_id'         => $metadata_id,
                    'keyword'             => $kw['keyword'] ?? '',
                    'keyword_type'        => $kw['keyword_type'] ?? 'theme',
                    'thesaurus_name'      => $kw['thesaurus_name'] ?? null,
                    'thesaurus_date'      => $kw['thesaurus_date'] ?? null,
                    'thesaurus_date_type' => $kw['thesaurus_date_type'] ?? 'publication',
                    'anchor_href'         => $kw['anchor_href'] ?? null,
                    'language'            => $kw['language'] ?? 'ita',
                    'sort_order'          => $i,
                ) );
            }
        }

        // INSPIRE themes → salva come keywords con thesaurus GEMET
        $inspire_themes = $this->get_param( $request, 'inspire_themes' );
        if ( is_array( $inspire_themes ) && ! empty( $inspire_themes ) ) {
            $all_themes = array();
            $codelists_file = RNDT_MANAGER_PATH . 'includes/codelists/class-rndt-inspire-themes.php';
            if ( file_exists( $codelists_file ) ) {
                require_once $codelists_file;
                $all_themes = RNDT_Inspire_Themes::get_all();
            }
            $sort_start = is_array( $keywords ) ? count( $keywords ) : 0;

            foreach ( $inspire_themes as $i => $theme_code ) {
                $theme_data = isset( $all_themes[ $theme_code ] ) ? $all_themes[ $theme_code ] : null;
                $theme_label = $theme_data ? $theme_data['it'] : $theme_code;
                $this->db->insert( 'rndt_keywords', array(
                    'metadata_id'         => $metadata_id,
                    'keyword'             => $theme_label,
                    'keyword_type'        => 'theme',
                    'thesaurus_name'      => 'GEMET - INSPIRE themes, version 1.0',
                    'thesaurus_date'      => '2008-06-01',
                    'thesaurus_date_type' => 'publication',
                    'anchor_href'         => $theme_data ? ( $theme_data['uri'] ?? null ) : null,
                    'language'            => 'ita',
                    'sort_order'          => $sort_start + $i,
                ) );
            }
        }

        // Topic categories → salva come keywords con thesaurus ISO
        $topic_categories = $this->get_param( $request, 'topic_categories' );
        if ( is_array( $topic_categories ) && ! empty( $topic_categories ) ) {
            $all_categories = array();
            $codelists_file = RNDT_MANAGER_PATH . 'includes/codelists/class-rndt-topic-categories.php';
            if ( file_exists( $codelists_file ) ) {
                require_once $codelists_file;
                $all_categories = RNDT_Topic_Categories::get_all();
            }
            // Conteggio sort_order dopo keywords + inspire_themes
            $sort_start = ( is_array( $keywords ) ? count( $keywords ) : 0 )
                        + ( is_array( $inspire_themes ) ? count( $inspire_themes ) : 0 );

            foreach ( $topic_categories as $i => $cat_code ) {
                $cat_data = isset( $all_categories[ $cat_code ] ) ? $all_categories[ $cat_code ] : null;
                $cat_label = $cat_data ? $cat_data['it'] : $cat_code;
                $this->db->insert( 'rndt_keywords', array(
                    'metadata_id'         => $metadata_id,
                    'keyword'             => $cat_label,
                    'keyword_type'        => 'topicCategory',
                    'thesaurus_name'      => 'ISO 19115 Topic Categories',
                    'thesaurus_date'      => null,
                    'thesaurus_date_type' => null,
                    'anchor_href'         => null,
                    'language'            => 'ita',
                    'sort_order'          => $sort_start + $i,
                ) );
            }
        }

        // Responsible parties (frontend: role_type/role_code/phone/fax/url → DB: context/role/phone_voice/phone_fax/online_resource_url)
        $parties = $this->get_param( $request, 'responsible_parties' );
        if ( is_array( $parties ) ) {
            $this->db->delete( 'rndt_responsible_parties', array( 'metadata_id' => $metadata_id ) );
            foreach ( $parties as $i => $party ) {
                $this->db->insert( 'rndt_responsible_parties', array(
                    'metadata_id'         => $metadata_id,
                    'context'             => $party['context'] ?? $party['role_type'] ?? 'resource_poc',
                    'individual_name'     => $party['individual_name'] ?? null,
                    'organisation_name'   => $party['organisation_name'] ?? '',
                    'position_name'       => $party['position_name'] ?? null,
                    'role'                => $party['role'] ?? $party['role_code'] ?? 'pointOfContact',
                    'phone_voice'         => $party['phone_voice'] ?? $party['phone'] ?? null,
                    'phone_fax'           => $party['phone_fax'] ?? $party['fax'] ?? null,
                    'delivery_point'      => $party['delivery_point'] ?? null,
                    'city'                => $party['city'] ?? null,
                    'admin_area'          => $party['admin_area'] ?? null,
                    'postal_code'         => $party['postal_code'] ?? null,
                    'country'             => $party['country'] ?? null,
                    'email'               => $party['email'] ?? null,
                    'online_resource_url' => $party['online_resource_url'] ?? $party['url'] ?? null,
                    'ipa_code'            => $party['ipa_code'] ?? null,
                    'sort_order'          => $i,
                ) );
            }
        }

        // Online resources - frontend: url → DB: linkage_url
        $resources = $this->get_param( $request, 'online_resources' );
        if ( is_array( $resources ) ) {
            $this->db->delete( 'rndt_online_resources', array( 'metadata_id' => $metadata_id ) );
            foreach ( $resources as $i => $res ) {
                $this->db->insert( 'rndt_online_resources', array(
                    'metadata_id'         => $metadata_id,
                    'linkage_url'         => $res['url'] ?? $res['linkage_url'] ?? '',
                    'protocol'            => $res['protocol'] ?? null,
                    'name'                => $res['name'] ?? null,
                    'description'         => $res['description'] ?? null,
                    'function'            => $res['function'] ?? null,
                    'application_profile' => $res['application_profile'] ?? null,
                    'sort_order'          => $i,
                ) );
            }
        }

        // Distribution formats - frontend: name/version/specification → DB: format_name/format_version/format_spec
        $formats = $this->get_param( $request, 'distribution_formats' );
        if ( is_array( $formats ) ) {
            $this->db->delete( 'rndt_distribution_formats', array( 'metadata_id' => $metadata_id ) );
            foreach ( $formats as $i => $fmt ) {
                $this->db->insert( 'rndt_distribution_formats', array(
                    'metadata_id'    => $metadata_id,
                    'format_name'    => $fmt['name'] ?? $fmt['format_name'] ?? '',
                    'format_version' => $fmt['version'] ?? $fmt['format_version'] ?? null,
                    'format_spec'    => $fmt['specification'] ?? $fmt['format_spec'] ?? null,
                    'sort_order'     => $i,
                ) );
            }
        }

        // Conformity
        $conformity = $this->get_param( $request, 'conformity' );
        if ( is_array( $conformity ) ) {
            $this->db->delete( 'rndt_conformity', array( 'metadata_id' => $metadata_id ) );
            foreach ( $conformity as $i => $conf ) {
                $this->db->insert( 'rndt_conformity', array(
                    'metadata_id'             => $metadata_id,
                    'specification_title'     => $conf['specification_title'] ?? '',
                    'specification_date'      => $conf['specification_date'] ?? null,
                    'specification_date_type' => $conf['specification_date_type'] ?? 'publication',
                    'degree'                  => $conf['degree'] ?? ( isset( $conf['pass'] ) ? ( $conf['pass'] ? 'true' : 'false' ) : null ),
                    'explanation'             => $conf['explanation'] ?? null,
                    'sort_order'              => $i,
                ) );
            }
        }
    }

    /**
     * Crea post WordPress associato
     */
    private function create_wp_post( $metadata_id, $data ) {
        if ( ! post_type_exists( 'rndt_metadata' ) ) {
            return;
        }

        $post_id = wp_insert_post( array(
            'post_type'    => 'rndt_metadata',
            'post_title'   => isset( $data['title'] ) ? $data['title'] : __( 'Metadato senza titolo', 'rndt-manager' ),
            'post_content' => isset( $data['abstract'] ) ? $data['abstract'] : '',
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ) );

        if ( $post_id && ! is_wp_error( $post_id ) ) {
            // Salva il metadata_id come post meta per collegamento
            update_post_meta( $post_id, '_rndt_metadata_id', $metadata_id );
            update_post_meta( $post_id, '_rndt_resource_type', isset( $data['resource_type'] ) ? $data['resource_type'] : 'dataset' );

            $this->db->update(
                'rndt_metadata',
                array( 'wp_post_id' => $post_id ),
                array( 'id' => $metadata_id )
            );
        }
    }

    /**
     * Aggiorna post WordPress associato
     */
    private function update_wp_post( $metadata_id, $data ) {
        $wp_post_id = $this->db->get_var(
            'SELECT wp_post_id FROM rndt_metadata WHERE id = :id',
            array( ':id' => $metadata_id )
        );

        if ( $wp_post_id ) {
            $post_data = array( 'ID' => $wp_post_id );
            if ( isset( $data['title'] ) ) {
                $post_data['post_title'] = $data['title'];
            }
            if ( isset( $data['abstract'] ) ) {
                $post_data['post_content'] = $data['abstract'];
            }
            wp_update_post( $post_data );

            // Aggiorna meta
            update_post_meta( $wp_post_id, '_rndt_metadata_id', $metadata_id );
            if ( isset( $data['resource_type'] ) ) {
                update_post_meta( $wp_post_id, '_rndt_resource_type', $data['resource_type'] );
            }
        }
    }

    /**
     * Parametri per la collezione
     */
    public function get_collection_params() {
        return array_merge(
            $this->get_pagination_params(),
            array(
                'resource_type' => array(
                    'description' => __( 'Filtra per tipo di risorsa.', 'rndt-manager' ),
                    'type'        => 'string',
                    'enum'        => array( 'dataset', 'series', 'service', 'application' ),
                ),
                'search' => array(
                    'description' => __( 'Cerca nel titolo e abstract.', 'rndt-manager' ),
                    'type'        => 'string',
                ),
            )
        );
    }

    /**
     * Parametri per la creazione
     */
    public function get_create_params() {
        return array(
            'resource_type' => array(
                'required'    => true,
                'type'        => 'string',
                'enum'        => array( 'dataset', 'series', 'service', 'application' ),
            ),
            'title' => array(
                'required' => true,
                'type'     => 'string',
            ),
            'abstract' => array(
                'type' => 'string',
            ),
        );
    }

    /**
     * Parametri per l'aggiornamento
     */
    public function get_update_params() {
        return array(
            'id' => array(
                'required' => true,
                'type'     => 'integer',
            ),
        );
    }

    /**
     * Cerca il codice tema INSPIRE dalla label italiana
     *
     * @param string $label Label del tema
     * @return string|null Codice tema o null
     */
    private function find_inspire_theme_code( $label ) {
        static $themes = null;
        if ( null === $themes ) {
            $file = RNDT_MANAGER_PATH . 'includes/codelists/class-rndt-inspire-themes.php';
            if ( file_exists( $file ) ) {
                require_once $file;
                $themes = RNDT_Inspire_Themes::get_all();
            } else {
                $themes = array();
            }
        }
        foreach ( $themes as $code => $data ) {
            if ( ( isset( $data['it'] ) && $data['it'] === $label ) ||
                 ( isset( $data['en'] ) && $data['en'] === $label ) ) {
                return $code;
            }
        }
        return null;
    }

    /**
     * Cerca il codice topic category dalla label italiana
     *
     * @param string $label Label della categoria
     * @return string|null Codice categoria o null
     */
    private function find_topic_category_code( $label ) {
        static $categories = null;
        if ( null === $categories ) {
            $file = RNDT_MANAGER_PATH . 'includes/codelists/class-rndt-topic-categories.php';
            if ( file_exists( $file ) ) {
                require_once $file;
                $categories = RNDT_Topic_Categories::get_all();
            } else {
                $categories = array();
            }
        }
        foreach ( $categories as $code => $data ) {
            if ( ( isset( $data['it'] ) && $data['it'] === $label ) ||
                 ( isset( $data['en'] ) && $data['en'] === $label ) ) {
                return $code;
            }
        }
        return null;
    }
}
