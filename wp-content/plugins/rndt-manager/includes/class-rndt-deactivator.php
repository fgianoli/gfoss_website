<?php
/**
 * Gestione disattivazione plugin
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Deactivator
 */
class RNDT_Deactivator {

    /**
     * Esegui le operazioni di disattivazione
     *
     * Nota: Non elimina i dati. Per l'eliminazione completa si usa uninstall.php
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Pulisci eventuali transient
        delete_transient( 'rndt_manager_pycsw_capabilities' );
        delete_transient( 'rndt_manager_geoserver_info' );

        // Rimuovi cron jobs schedulati
        wp_clear_scheduled_hook( 'rndt_manager_daily_validation' );
        wp_clear_scheduled_hook( 'rndt_manager_csw_sync' );
    }
}
