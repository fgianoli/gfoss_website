<?php
/**
 * Registrazione Custom Post Type per i metadati RNDT
 *
 * Il CPT e usato principalmente per l'interfaccia admin di WordPress.
 * I dati effettivi sono salvati nel database PostgreSQL esterno.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Post_Type
 */
class RNDT_Post_Type {

    /**
     * Nome del post type
     */
    const POST_TYPE = 'rndt_metadata';

    /**
     * Registra il Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Metadati RNDT', 'Post type general name', 'rndt-manager' ),
            'singular_name'         => _x( 'Metadato RNDT', 'Post type singular name', 'rndt-manager' ),
            'menu_name'             => _x( 'RNDT Manager', 'Admin menu text', 'rndt-manager' ),
            'name_admin_bar'        => _x( 'Metadato RNDT', 'Add new on toolbar', 'rndt-manager' ),
            'add_new'               => __( 'Aggiungi nuovo', 'rndt-manager' ),
            'add_new_item'          => __( 'Aggiungi nuovo metadato', 'rndt-manager' ),
            'new_item'              => __( 'Nuovo metadato', 'rndt-manager' ),
            'edit_item'             => __( 'Modifica metadato', 'rndt-manager' ),
            'view_item'             => __( 'Visualizza metadato', 'rndt-manager' ),
            'all_items'             => __( 'Tutti i metadati', 'rndt-manager' ),
            'search_items'          => __( 'Cerca metadati', 'rndt-manager' ),
            'parent_item_colon'     => __( 'Metadato padre:', 'rndt-manager' ),
            'not_found'             => __( 'Nessun metadato trovato.', 'rndt-manager' ),
            'not_found_in_trash'    => __( 'Nessun metadato nel cestino.', 'rndt-manager' ),
            'featured_image'        => __( 'Immagine di copertina', 'rndt-manager' ),
            'set_featured_image'    => __( 'Imposta immagine di copertina', 'rndt-manager' ),
            'remove_featured_image' => __( 'Rimuovi immagine di copertina', 'rndt-manager' ),
            'use_featured_image'    => __( 'Usa come immagine di copertina', 'rndt-manager' ),
            'archives'              => __( 'Archivio metadati', 'rndt-manager' ),
            'insert_into_item'      => __( 'Inserisci nel metadato', 'rndt-manager' ),
            'uploaded_to_this_item' => __( 'Caricato in questo metadato', 'rndt-manager' ),
            'filter_items_list'     => __( 'Filtra lista metadati', 'rndt-manager' ),
            'items_list_navigation' => __( 'Navigazione lista metadati', 'rndt-manager' ),
            'items_list'            => __( 'Lista metadati', 'rndt-manager' ),
        );

        $args = array(
            'labels'              => $labels,
            'description'         => __( 'Metadati territoriali secondo il profilo RNDT 2020', 'rndt-manager' ),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'rndt-manager',
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => array( 'rndt_metadata', 'rndt_metadata_items' ),
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-location-alt',
            'supports'            => array( 'title', 'author' ),
            'show_in_rest'        => true,
            'rest_base'           => 'rndt-metadata',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Ottieni gli stati personalizzati per il post type
     *
     * @return array
     */
    public static function get_custom_statuses() {
        return array(
            'draft'     => __( 'Bozza', 'rndt-manager' ),
            'pending'   => __( 'Validato', 'rndt-manager' ),
            'publish'   => __( 'Pubblicato su CSW', 'rndt-manager' ),
        );
    }

    /**
     * Ottieni i tipi di risorsa supportati
     *
     * @return array
     */
    public static function get_resource_types() {
        return array(
            'dataset'     => __( 'Dataset', 'rndt-manager' ),
            'series'      => __( 'Serie', 'rndt-manager' ),
            'service'     => __( 'Servizio', 'rndt-manager' ),
            'application' => __( 'Applicazione', 'rndt-manager' ),
        );
    }

    /**
     * Ottieni il codice hierarchy level ISO per il tipo di risorsa
     *
     * @param string $resource_type Tipo di risorsa
     * @return string
     */
    public static function get_hierarchy_level( $resource_type ) {
        $levels = array(
            'dataset'     => 'dataset',
            'series'      => 'series',
            'service'     => 'service',
            'application' => 'application',
        );
        return isset( $levels[ $resource_type ] ) ? $levels[ $resource_type ] : 'dataset';
    }

    /**
     * Ottieni il codice MD_ScopeCode per il tipo di risorsa
     *
     * @param string $resource_type Tipo di risorsa
     * @return string
     */
    public static function get_scope_code( $resource_type ) {
        // Per RNDT/INSPIRE, i codici sono gli stessi dei hierarchy levels
        return self::get_hierarchy_level( $resource_type );
    }
}
