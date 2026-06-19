<?php
/**
 * Repository per le operazioni CRUD sui metadati
 *
 * Gestisce tutte le interazioni con il database PostgreSQL
 * per i metadati e le tabelle correlate.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Metadata_Repository
 */
class RNDT_Metadata_Repository {

    /**
     * Istanza singleton
     *
     * @var RNDT_Metadata_Repository
     */
    private static $instance = null;

    /**
     * Database
     *
     * @var RNDT_Database
     */
    private $db;

    /**
     * Ottieni l'istanza singleton
     *
     * @return RNDT_Metadata_Repository
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore privato
     */
    private function __construct() {
        $this->db = RNDT_Database::get_instance();
    }

    /**
     * Trova un metadato per ID
     *
     * @param int $id ID del metadato
     * @return RNDT_Metadata_Model|null
     */
    public function find( $id ) {
        $row = $this->db->get_row(
            'SELECT * FROM rndt_metadata WHERE id = :id',
            array( ':id' => (int) $id )
        );

        if ( ! $row ) {
            return null;
        }

        return new RNDT_Metadata_Model( $row );
    }

    /**
     * Trova un metadato per file_identifier (UUID)
     *
     * @param string $file_identifier UUID del metadato
     * @return RNDT_Metadata_Model|null
     */
    public function find_by_file_identifier( $file_identifier ) {
        $row = $this->db->get_row(
            'SELECT * FROM rndt_metadata WHERE file_identifier = :file_identifier',
            array( ':file_identifier' => $file_identifier )
        );

        if ( ! $row ) {
            return null;
        }

        return new RNDT_Metadata_Model( $row );
    }

    /**
     * Trova un metadato per wp_post_id
     *
     * @param int $wp_post_id ID del post WordPress
     * @return RNDT_Metadata_Model|null
     */
    public function find_by_wp_post_id( $wp_post_id ) {
        $row = $this->db->get_row(
            'SELECT * FROM rndt_metadata WHERE wp_post_id = :wp_post_id',
            array( ':wp_post_id' => (int) $wp_post_id )
        );

        if ( ! $row ) {
            return null;
        }

        return new RNDT_Metadata_Model( $row );
    }

    /**
     * Ottieni tutti i metadati con paginazione
     *
     * @param array $args Argomenti di ricerca
     * @return array ['items' => array, 'total' => int, 'pages' => int]
     */
    public function find_all( $args = array() ) {
        $defaults = array(
            'page'          => 1,
            'per_page'      => 10,
            'resource_type' => '',
            'search'        => '',
            'validation_status' => '',
            'orderby'       => 'updated_at',
            'order'         => 'DESC',
        );

        $args   = wp_parse_args( $args, $defaults );
        $where  = array();
        $params = array();

        // Filtro per tipo risorsa
        if ( ! empty( $args['resource_type'] ) ) {
            $where[] = 'resource_type = :resource_type';
            $params[':resource_type'] = $args['resource_type'];
        }

        // Filtro per stato validazione
        if ( ! empty( $args['validation_status'] ) ) {
            $where[] = 'validation_status = :validation_status';
            $params[':validation_status'] = $args['validation_status'];
        }

        // Ricerca testuale
        if ( ! empty( $args['search'] ) ) {
            $where[] = '(title ILIKE :search OR abstract ILIKE :search OR file_identifier::text ILIKE :search)';
            $params[':search'] = '%' . $args['search'] . '%';
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // Conta totale
        $total = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM rndt_metadata {$where_sql}",
            $params
        );

        // Calcola paginazione
        $per_page = max( 1, min( 100, (int) $args['per_page'] ) );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $pages    = ceil( $total / $per_page );

        // Ordina
        $orderby = in_array( $args['orderby'], array( 'title', 'resource_type', 'created_at', 'updated_at', 'validation_status' ), true )
            ? $args['orderby']
            : 'updated_at';
        $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        // Ottieni risultati
        $rows = $this->db->get_results(
            "SELECT * FROM rndt_metadata {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}",
            $params
        );

        $items = array();
        foreach ( $rows as $row ) {
            $items[] = new RNDT_Metadata_Model( $row );
        }

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
        );
    }

    /**
     * Salva un metadato (insert o update)
     *
     * @param RNDT_Metadata_Model $model Modello da salvare
     * @return int|false ID del record o false in caso di errore
     */
    public function save( RNDT_Metadata_Model $model ) {
        $this->db->begin_transaction();

        try {
            if ( $model->is_new() ) {
                $id = $this->insert( $model );
            } else {
                $id = $this->update( $model );
            }

            if ( false === $id ) {
                throw new Exception( $this->db->get_last_error() );
            }

            // Salva relazioni
            $this->save_keywords( $id, $model->get_keywords() );
            $this->save_responsible_parties( $id, $model->get_responsible_parties() );
            $this->save_online_resources( $id, $model->get_online_resources() );
            $this->save_distribution_formats( $id, $model->get_distribution_formats() );
            $this->save_conformity( $id, $model->get_conformity() );

            if ( $model->is_service() ) {
                $this->save_service_operations( $id, $model->get_service_operations() );
                $this->save_coupled_resources( $id, $model->get_coupled_resources() );
            }

            $this->db->commit();

            // Aggiorna l'ID nel modello
            $model->id = $id;

            // Sincronizza con WordPress
            $this->sync_wp_post( $model );

            return $id;

        } catch ( Exception $e ) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Inserisci un nuovo metadato
     *
     * @param RNDT_Metadata_Model $model Modello
     * @return int|false
     */
    private function insert( RNDT_Metadata_Model $model ) {
        // Genera UUID se mancante
        if ( empty( $model->file_identifier ) ) {
            $model->generate_file_identifier();
        }

        // Imposta data metadato
        if ( empty( $model->metadata_date ) ) {
            $model->metadata_date = current_time( 'mysql' );
        }

        $data = $this->model_to_db_array( $model );
        $data['created_by'] = get_current_user_id();
        $data['updated_by'] = get_current_user_id();

        return $this->db->insert( 'rndt_metadata', $data );
    }

    /**
     * Aggiorna un metadato esistente
     *
     * @param RNDT_Metadata_Model $model Modello
     * @return int|false
     */
    private function update( RNDT_Metadata_Model $model ) {
        $data = $this->model_to_db_array( $model );
        $data['updated_by'] = get_current_user_id();
        $data['xml_cache']  = null; // Invalida cache

        $result = $this->db->update(
            'rndt_metadata',
            $data,
            array( 'id' => $model->id )
        );

        return false !== $result ? $model->id : false;
    }

    /**
     * Elimina un metadato
     *
     * @param int $id ID del metadato
     * @return bool
     */
    public function delete( $id ) {
        // Ottieni wp_post_id prima di eliminare
        $model = $this->find( $id );
        if ( ! $model ) {
            return false;
        }

        $result = $this->db->delete( 'rndt_metadata', array( 'id' => $id ) );

        if ( $result && $model->wp_post_id ) {
            wp_delete_post( $model->wp_post_id, true );
        }

        return (bool) $result;
    }

    /**
     * Converti modello in array per database
     *
     * @param RNDT_Metadata_Model $model Modello
     * @return array
     */
    private function model_to_db_array( RNDT_Metadata_Model $model ) {
        return array(
            'resource_type'               => $model->resource_type,
            'file_identifier'             => $model->file_identifier,
            'resource_identifier'         => $model->resource_identifier,
            'resource_identifier_codespace' => $model->resource_identifier_codespace,
            'parent_identifier'           => $model->parent_identifier,
            'title'                       => $model->title,
            'abstract'                    => $model->abstract,
            'resource_language'           => $model->resource_language,
            'character_set'               => $model->character_set,
            'hierarchy_level_name'        => $model->hierarchy_level_name,
            'spatial_representation_type' => $model->spatial_representation_type,
            'date_creation'               => $model->date_creation ?: null,
            'date_publication'            => $model->date_publication ?: null,
            'date_revision'               => $model->date_revision ?: null,
            'temporal_extent_begin'       => $model->temporal_extent_begin ?: null,
            'temporal_extent_end'         => $model->temporal_extent_end ?: null,
            'bbox_west'                   => $model->bbox_west,
            'bbox_east'                   => $model->bbox_east,
            'bbox_south'                  => $model->bbox_south,
            'bbox_north'                  => $model->bbox_north,
            'geographic_description'      => $model->geographic_description,
            'lineage_statement'           => $model->lineage_statement,
            'spatial_resolution_scale'    => $model->spatial_resolution_scale,
            'spatial_resolution_distance' => $model->spatial_resolution_distance,
            'spatial_resolution_units'    => $model->spatial_resolution_units,
            'use_limitation'              => $model->use_limitation,
            'access_constraints'          => $model->access_constraints,
            'use_constraints'             => $model->use_constraints,
            'other_constraints'           => $model->other_constraints,
            'classification'              => $model->classification,
            'reference_system_code'       => $model->reference_system_code,
            'reference_system_codespace'  => $model->reference_system_codespace,
            'metadata_language'           => $model->metadata_language,
            'metadata_character_set'      => $model->metadata_character_set,
            'metadata_date'               => $model->metadata_date,
            'metadata_standard_name'      => $model->metadata_standard_name,
            'metadata_standard_version'   => $model->metadata_standard_version,
            'service_type'                => $model->service_type,
            'service_type_version'        => $model->service_type_version,
            'coupling_type'               => $model->coupling_type,
            'maintenance_frequency'       => $model->maintenance_frequency,
            'validation_status'           => $model->validation_status,
            'validation_errors'           => wp_json_encode( $model->validation_errors ),
            'last_validated_at'           => $model->last_validated_at,
            'csw_published_at'            => $model->csw_published_at,
            'csw_record_id'               => $model->csw_record_id,
            'geoserver_published_at'      => $model->geoserver_published_at,
        );
    }

    /**
     * Sincronizza con il post WordPress
     *
     * @param RNDT_Metadata_Model $model Modello
     */
    private function sync_wp_post( RNDT_Metadata_Model $model ) {
        $post_data = array(
            'post_type'    => RNDT_Post_Type::POST_TYPE,
            'post_title'   => $model->title,
            'post_content' => $model->abstract,
            'post_status'  => $this->get_wp_post_status( $model ),
            'post_author'  => $model->created_by ?: get_current_user_id(),
        );

        if ( $model->wp_post_id ) {
            $post_data['ID'] = $model->wp_post_id;
            wp_update_post( $post_data );
        } else {
            $wp_post_id = wp_insert_post( $post_data );
            if ( $wp_post_id && ! is_wp_error( $wp_post_id ) ) {
                $this->db->update(
                    'rndt_metadata',
                    array( 'wp_post_id' => $wp_post_id ),
                    array( 'id' => $model->id )
                );
                $model->wp_post_id = $wp_post_id;
            }
        }
    }

    /**
     * Ottieni lo status del post WordPress
     *
     * @param RNDT_Metadata_Model $model Modello
     * @return string
     */
    private function get_wp_post_status( RNDT_Metadata_Model $model ) {
        if ( $model->is_published_csw() ) {
            return 'publish';
        }
        if ( $model->is_validated() ) {
            return 'pending';
        }
        return 'draft';
    }

    // ========== METODI PER RELAZIONI ==========

    /**
     * Ottieni keywords per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_keywords( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_keywords WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Salva keywords
     *
     * @param int   $metadata_id ID metadato
     * @param array $keywords    Keywords
     */
    private function save_keywords( $metadata_id, $keywords ) {
        $this->db->delete( 'rndt_keywords', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $keywords ) ) {
            return;
        }

        foreach ( $keywords as $i => $kw ) {
            $this->db->insert( 'rndt_keywords', array(
                'metadata_id'        => $metadata_id,
                'keyword'            => $kw['keyword'] ?? '',
                'keyword_type'       => $kw['keyword_type'] ?? 'theme',
                'thesaurus_name'     => $kw['thesaurus_name'] ?? null,
                'thesaurus_date'     => $kw['thesaurus_date'] ?? null,
                'thesaurus_date_type' => $kw['thesaurus_date_type'] ?? 'publication',
                'anchor_href'        => $kw['anchor_href'] ?? null,
                'language'           => $kw['language'] ?? 'ita',
                'sort_order'         => $i,
            ) );
        }
    }

    /**
     * Ottieni responsible parties per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_responsible_parties( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_responsible_parties WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Salva responsible parties
     *
     * @param int   $metadata_id ID metadato
     * @param array $parties     Parti responsabili
     */
    private function save_responsible_parties( $metadata_id, $parties ) {
        $this->db->delete( 'rndt_responsible_parties', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $parties ) ) {
            return;
        }

        foreach ( $parties as $i => $party ) {
            $this->db->insert( 'rndt_responsible_parties', array(
                'metadata_id'        => $metadata_id,
                'context'            => $party['context'] ?? 'resource_poc',
                'individual_name'    => $party['individual_name'] ?? null,
                'organisation_name'  => $party['organisation_name'] ?? '',
                'position_name'      => $party['position_name'] ?? null,
                'role'               => $party['role'] ?? 'pointOfContact',
                'phone_voice'        => $party['phone_voice'] ?? null,
                'phone_fax'          => $party['phone_fax'] ?? null,
                'delivery_point'     => $party['delivery_point'] ?? null,
                'city'               => $party['city'] ?? null,
                'admin_area'         => $party['admin_area'] ?? null,
                'postal_code'        => $party['postal_code'] ?? null,
                'country'            => $party['country'] ?? null,
                'email'              => $party['email'] ?? null,
                'online_resource_url' => $party['online_resource_url'] ?? null,
                'ipa_code'           => $party['ipa_code'] ?? null,
                'sort_order'         => $i,
            ) );
        }
    }

    /**
     * Ottieni online resources per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_online_resources( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_online_resources WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Aggiorna online resources (API pubblica)
     *
     * @param int   $metadata_id ID metadato
     * @param array $resources   Risorse
     */
    public function update_online_resources( $metadata_id, $resources ) {
        $this->save_online_resources( $metadata_id, $resources );
        // Invalida cache XML
        $this->db->update(
            'rndt_metadata',
            array( 'xml_cache' => null ),
            array( 'id' => (int) $metadata_id )
        );
    }

    /**
     * Salva online resources
     *
     * @param int   $metadata_id ID metadato
     * @param array $resources   Risorse
     */
    private function save_online_resources( $metadata_id, $resources ) {
        $this->db->delete( 'rndt_online_resources', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $resources ) ) {
            return;
        }

        foreach ( $resources as $i => $res ) {
            $this->db->insert( 'rndt_online_resources', array(
                'metadata_id'         => $metadata_id,
                'linkage_url'         => $res['linkage_url'] ?? '',
                'protocol'            => $res['protocol'] ?? null,
                'name'                => $res['name'] ?? null,
                'description'         => $res['description'] ?? null,
                'function'            => $res['function'] ?? null,
                'application_profile' => $res['application_profile'] ?? null,
                'sort_order'          => $i,
            ) );
        }
    }

    /**
     * Ottieni distribution formats per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_distribution_formats( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_distribution_formats WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Salva distribution formats
     *
     * @param int   $metadata_id ID metadato
     * @param array $formats     Formati
     */
    private function save_distribution_formats( $metadata_id, $formats ) {
        $this->db->delete( 'rndt_distribution_formats', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $formats ) ) {
            return;
        }

        foreach ( $formats as $i => $fmt ) {
            $this->db->insert( 'rndt_distribution_formats', array(
                'metadata_id'    => $metadata_id,
                'format_name'    => $fmt['format_name'] ?? '',
                'format_version' => $fmt['format_version'] ?? null,
                'format_spec'    => $fmt['format_spec'] ?? null,
                'sort_order'     => $i,
            ) );
        }
    }

    /**
     * Ottieni conformity per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_conformity( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_conformity WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Salva conformity
     *
     * @param int   $metadata_id ID metadato
     * @param array $conformity  Conformita
     */
    private function save_conformity( $metadata_id, $conformity ) {
        $this->db->delete( 'rndt_conformity', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $conformity ) ) {
            return;
        }

        foreach ( $conformity as $i => $conf ) {
            $this->db->insert( 'rndt_conformity', array(
                'metadata_id'           => $metadata_id,
                'specification_title'   => $conf['specification_title'] ?? '',
                'specification_date'    => $conf['specification_date'] ?? null,
                'specification_date_type' => $conf['specification_date_type'] ?? 'publication',
                'degree'                => $conf['degree'] ?? null,
                'explanation'           => $conf['explanation'] ?? null,
                'sort_order'            => $i,
            ) );
        }
    }

    /**
     * Ottieni service operations per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_service_operations( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_service_operations WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Salva service operations
     *
     * @param int   $metadata_id ID metadato
     * @param array $operations  Operazioni
     */
    private function save_service_operations( $metadata_id, $operations ) {
        $this->db->delete( 'rndt_service_operations', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $operations ) ) {
            return;
        }

        foreach ( $operations as $i => $op ) {
            $this->db->insert( 'rndt_service_operations', array(
                'metadata_id'           => $metadata_id,
                'operation_name'        => $op['operation_name'] ?? '',
                'dcp'                   => $op['dcp'] ?? 'WebServices',
                'connect_point_url'     => $op['connect_point_url'] ?? null,
                'operation_description' => $op['operation_description'] ?? null,
                'sort_order'            => $i,
            ) );
        }
    }

    /**
     * Ottieni coupled resources per un metadato
     *
     * @param int $metadata_id ID metadato
     * @return array
     */
    public function get_coupled_resources( $metadata_id ) {
        return $this->db->get_results(
            'SELECT * FROM rndt_coupled_resources WHERE metadata_id = :id ORDER BY sort_order',
            array( ':id' => (int) $metadata_id )
        );
    }

    /**
     * Salva coupled resources
     *
     * @param int   $metadata_id ID metadato
     * @param array $resources   Risorse accoppiate
     */
    private function save_coupled_resources( $metadata_id, $resources ) {
        $this->db->delete( 'rndt_coupled_resources', array( 'metadata_id' => $metadata_id ) );

        if ( empty( $resources ) ) {
            return;
        }

        foreach ( $resources as $i => $res ) {
            $this->db->insert( 'rndt_coupled_resources', array(
                'metadata_id'        => $metadata_id,
                'identifier'         => $res['identifier'] ?? '',
                'resource_title'     => $res['resource_title'] ?? null,
                'resource_url'       => $res['resource_url'] ?? null,
                'linked_metadata_id' => $res['linked_metadata_id'] ?? null,
                'sort_order'         => $i,
            ) );
        }
    }

    // ========== METODI DI UTILITA ==========

    /**
     * Aggiorna lo stato di validazione
     *
     * @param int    $id     ID metadato
     * @param string $status Stato (not_validated, valid, invalid)
     * @param array  $errors Errori di validazione
     * @return bool
     */
    public function update_validation_status( $id, $status, $errors = array() ) {
        $result = $this->db->update(
            'rndt_metadata',
            array(
                'validation_status' => $status,
                'validation_errors' => wp_json_encode( $errors ),
                'last_validated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id )
        );

        return false !== $result;
    }

    /**
     * Aggiorna lo stato di pubblicazione CSW
     *
     * @param int    $id        ID metadato
     * @param string $record_id ID nel catalogo CSW
     * @return bool
     */
    public function update_csw_published( $id, $record_id = '' ) {
        $result = $this->db->update(
            'rndt_metadata',
            array(
                'csw_published_at' => current_time( 'mysql' ),
                'csw_record_id'    => $record_id,
            ),
            array( 'id' => $id )
        );

        if ( $result ) {
            $model = $this->find( $id );
            if ( $model && $model->wp_post_id ) {
                wp_update_post( array(
                    'ID'          => $model->wp_post_id,
                    'post_status' => 'publish',
                ) );
            }
        }

        return false !== $result;
    }

    /**
     * Salva la cache XML
     *
     * @param int    $id  ID metadato
     * @param string $xml XML generato
     * @return bool
     */
    public function save_xml_cache( $id, $xml ) {
        $result = $this->db->update(
            'rndt_metadata',
            array(
                'xml_cache'      => $xml,
                'xml_cache_date' => current_time( 'mysql' ),
            ),
            array( 'id' => $id )
        );

        return false !== $result;
    }

    /**
     * Conta metadati per tipo
     *
     * @return array
     */
    public function count_by_type() {
        $results = $this->db->get_results(
            'SELECT resource_type, COUNT(*) as count FROM rndt_metadata GROUP BY resource_type'
        );

        $counts = array(
            'dataset'     => 0,
            'series'      => 0,
            'service'     => 0,
            'application' => 0,
        );

        foreach ( $results as $row ) {
            $counts[ $row['resource_type'] ] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Conta metadati per stato validazione
     *
     * @return array
     */
    public function count_by_validation_status() {
        $results = $this->db->get_results(
            'SELECT validation_status, COUNT(*) as count FROM rndt_metadata GROUP BY validation_status'
        );

        $counts = array(
            'not_validated' => 0,
            'valid'         => 0,
            'invalid'       => 0,
        );

        foreach ( $results as $row ) {
            $counts[ $row['validation_status'] ] = (int) $row['count'];
        }

        return $counts;
    }
}
