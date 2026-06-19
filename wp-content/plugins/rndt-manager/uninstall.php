<?php
/**
 * Disinstallazione del plugin
 *
 * Questo file viene eseguito quando l'utente elimina il plugin.
 * Pulisce tutte le opzioni, CPT e dati creati dal plugin.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

// Se non chiamato da WordPress, esci
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Verifica che l'utente abbia i permessi
if ( ! current_user_can( 'delete_plugins' ) ) {
    exit;
}

/**
 * Pulisci i dati del plugin
 */
function rndt_manager_uninstall() {
    global $wpdb;

    // Opzione per mantenere i dati dopo la disinstallazione
    $settings = get_option( 'rndt_settings', array() );
    $keep_data = isset( $settings['general']['keep_data_on_uninstall'] )
                 && $settings['general']['keep_data_on_uninstall'];

    if ( $keep_data ) {
        return;
    }

    // 1. Elimina tutti i post del CPT rndt_metadata
    $posts = get_posts( array(
        'post_type'      => 'rndt_metadata',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }

    // 2. Elimina i termini delle tassonomie
    $taxonomies = array( 'rndt_inspire_theme', 'rndt_topic_category' );
    foreach ( $taxonomies as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        ) );

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term_id ) {
                wp_delete_term( $term_id, $taxonomy );
            }
        }
    }

    // 3. Elimina le opzioni
    $options = array(
        'rndt_settings',
        'rndt_manager_version',
        'rndt_manager_db_version',
        'rndt_manager_activation_complete',
        'rndt_manager_db_error',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // 4. Elimina i transient
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_rndt_manager_%'
         OR option_name LIKE '_transient_timeout_rndt_manager_%'"
    );

    // 5. Rimuovi le capabilities
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-rndt-capabilities.php';
    RNDT_Capabilities::remove_capabilities();

    // 6. Elimina le tabelle database
    $db_type = isset( $settings['database']['type'] ) ? $settings['database']['type'] : 'postgresql';

    if ( 'wordpress' === $db_type ) {
        // Elimina le tabelle WordPress (MariaDB/MySQL)
        $rndt_tables = array(
            'rndt_coupled_resources',
            'rndt_service_operations',
            'rndt_conformity',
            'rndt_distribution_formats',
            'rndt_online_resources',
            'rndt_responsible_parties',
            'rndt_keywords',
            'rndt_metadata',
            'rndt_responsible_presets',
            'rndt_inspire_themes',
            'rndt_topic_categories',
        );
        foreach ( $rndt_tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }
    // Per PostgreSQL: la connessione esterna potrebbe non essere disponibile,
    // l'utente dovrà eliminare le tabelle manualmente se necessario.

    // 7. Pulisci i cron jobs
    wp_clear_scheduled_hook( 'rndt_manager_daily_validation' );
    wp_clear_scheduled_hook( 'rndt_manager_csw_sync' );

    // 8. Flush rewrite rules
    flush_rewrite_rules();
}

// Esegui la disinstallazione
rndt_manager_uninstall();
