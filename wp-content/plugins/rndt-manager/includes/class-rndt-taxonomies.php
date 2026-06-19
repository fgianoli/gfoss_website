<?php
/**
 * Registrazione tassonomie personalizzate
 *
 * Le tassonomie sono usate per l'interfaccia WordPress (filtri, ricerca).
 * I dati effettivi sono nel database PostgreSQL.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Taxonomies
 */
class RNDT_Taxonomies {

    /**
     * Tassonomia per temi INSPIRE
     */
    const TAXONOMY_INSPIRE_THEME = 'rndt_inspire_theme';

    /**
     * Tassonomia per categorie ISO
     */
    const TAXONOMY_TOPIC_CATEGORY = 'rndt_topic_category';

    /**
     * Registra le tassonomie
     */
    public function register_taxonomies() {
        $this->register_inspire_theme_taxonomy();
        $this->register_topic_category_taxonomy();
    }

    /**
     * Registra la tassonomia per i temi INSPIRE
     */
    private function register_inspire_theme_taxonomy() {
        $labels = array(
            'name'              => _x( 'Temi INSPIRE', 'taxonomy general name', 'rndt-manager' ),
            'singular_name'     => _x( 'Tema INSPIRE', 'taxonomy singular name', 'rndt-manager' ),
            'search_items'      => __( 'Cerca temi', 'rndt-manager' ),
            'all_items'         => __( 'Tutti i temi', 'rndt-manager' ),
            'parent_item'       => __( 'Tema padre', 'rndt-manager' ),
            'parent_item_colon' => __( 'Tema padre:', 'rndt-manager' ),
            'edit_item'         => __( 'Modifica tema', 'rndt-manager' ),
            'update_item'       => __( 'Aggiorna tema', 'rndt-manager' ),
            'add_new_item'      => __( 'Aggiungi nuovo tema', 'rndt-manager' ),
            'new_item_name'     => __( 'Nome nuovo tema', 'rndt-manager' ),
            'menu_name'         => __( 'Temi INSPIRE', 'rndt-manager' ),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
            'rest_base'         => 'rndt-inspire-themes',
        );

        register_taxonomy(
            self::TAXONOMY_INSPIRE_THEME,
            array( RNDT_Post_Type::POST_TYPE ),
            $args
        );
    }

    /**
     * Registra la tassonomia per le categorie ISO 19115
     */
    private function register_topic_category_taxonomy() {
        $labels = array(
            'name'              => _x( 'Categorie ISO', 'taxonomy general name', 'rndt-manager' ),
            'singular_name'     => _x( 'Categoria ISO', 'taxonomy singular name', 'rndt-manager' ),
            'search_items'      => __( 'Cerca categorie', 'rndt-manager' ),
            'all_items'         => __( 'Tutte le categorie', 'rndt-manager' ),
            'parent_item'       => __( 'Categoria padre', 'rndt-manager' ),
            'parent_item_colon' => __( 'Categoria padre:', 'rndt-manager' ),
            'edit_item'         => __( 'Modifica categoria', 'rndt-manager' ),
            'update_item'       => __( 'Aggiorna categoria', 'rndt-manager' ),
            'add_new_item'      => __( 'Aggiungi nuova categoria', 'rndt-manager' ),
            'new_item_name'     => __( 'Nome nuova categoria', 'rndt-manager' ),
            'menu_name'         => __( 'Categorie ISO', 'rndt-manager' ),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
            'rest_base'         => 'rndt-topic-categories',
        );

        register_taxonomy(
            self::TAXONOMY_TOPIC_CATEGORY,
            array( RNDT_Post_Type::POST_TYPE ),
            $args
        );
    }

    /**
     * Popola le tassonomie con i termini predefiniti
     * Chiamato durante l'attivazione
     */
    public static function populate_taxonomies() {
        self::populate_inspire_themes();
        self::populate_topic_categories();
    }

    /**
     * Popola i temi INSPIRE
     */
    private static function populate_inspire_themes() {
        $themes = RNDT_Inspire_Themes::get_all();

        foreach ( $themes as $code => $theme ) {
            if ( ! term_exists( $code, self::TAXONOMY_INSPIRE_THEME ) ) {
                wp_insert_term(
                    $theme['it'], // Nome visualizzato
                    self::TAXONOMY_INSPIRE_THEME,
                    array(
                        'slug'        => $code,
                        'description' => $theme['en'], // Traduzione inglese nella descrizione
                    )
                );
            }
        }
    }

    /**
     * Popola le categorie ISO
     */
    private static function populate_topic_categories() {
        $categories = RNDT_Topic_Categories::get_all();

        foreach ( $categories as $code => $category ) {
            if ( ! term_exists( $code, self::TAXONOMY_TOPIC_CATEGORY ) ) {
                wp_insert_term(
                    $category['it'],
                    self::TAXONOMY_TOPIC_CATEGORY,
                    array(
                        'slug'        => $code,
                        'description' => $category['en'],
                    )
                );
            }
        }
    }
}
