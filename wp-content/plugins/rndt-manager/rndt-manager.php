<?php
/**
 * Plugin Name: RNDT Manager
 * Plugin URI: https://github.com/your-repo/rndt-manager
 * Description: Editor e validatore di metadati secondo il profilo italiano RNDT 2020 (INSPIRE TG v2.0.1, ISO 19115/19139). Supporta dataset, serie, servizi OGC e applicazioni con esportazione XML e pubblicazione su pyCSW/GeoServer.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: rndt-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package RNDT_Manager
 */

// Impedisci accesso diretto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Costanti del plugin
 */
define( 'RNDT_MANAGER_VERSION', '1.0.0' );
define( 'RNDT_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RNDT_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RNDT_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RNDT_MANAGER_PLUGIN_FILE', __FILE__ );
define( 'RNDT_MANAGER_PATH', plugin_dir_path( __FILE__ ) ); // Alias per compatibilità

/**
 * Verifica requisiti minimi
 */
function rndt_manager_check_requirements() {
    $errors = array();

    // Verifica versione PHP
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        $errors[] = sprintf(
            /* translators: %s: PHP version required */
            __( 'RNDT Manager richiede PHP %s o superiore.', 'rndt-manager' ),
            '7.4'
        );
    }

    // Verifica estensione PDO per PostgreSQL (solo se configurato per PostgreSQL)
    $rndt_settings = get_option( 'rndt_settings', array() );
    $rndt_db_type  = isset( $rndt_settings['database']['type'] ) ? $rndt_settings['database']['type'] : 'postgresql';
    if ( 'postgresql' === $rndt_db_type && ! extension_loaded( 'pdo_pgsql' ) ) {
        $errors[] = __( 'RNDT Manager richiede l\'estensione PHP PDO PostgreSQL (pdo_pgsql) per il backend PostgreSQL.', 'rndt-manager' );
    }

    // Verifica estensione DOM per generazione XML
    if ( ! extension_loaded( 'dom' ) ) {
        $errors[] = __( 'RNDT Manager richiede l\'estensione PHP DOM per la generazione XML.', 'rndt-manager' );
    }

    // Verifica estensione libxml
    if ( ! extension_loaded( 'libxml' ) ) {
        $errors[] = __( 'RNDT Manager richiede l\'estensione PHP libxml.', 'rndt-manager' );
    }

    return $errors;
}

/**
 * Mostra errori di requisiti mancanti
 */
function rndt_manager_requirements_notice() {
    $errors = rndt_manager_check_requirements();
    if ( ! empty( $errors ) ) {
        echo '<div class="notice notice-error"><p><strong>RNDT Manager:</strong></p><ul>';
        foreach ( $errors as $error ) {
            echo '<li>' . esc_html( $error ) . '</li>';
        }
        echo '</ul></div>';
    }
}

/**
 * Inizializza il plugin
 */
function rndt_manager_init() {
    // Verifica requisiti
    $errors = rndt_manager_check_requirements();
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'rndt_manager_requirements_notice' );
        return;
    }

    // Carica le dipendenze
    require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-manager.php';

    // Avvia il plugin
    $plugin = RNDT_Manager::get_instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'rndt_manager_init' );

/**
 * Hook di attivazione
 */
function rndt_manager_activate() {
    // Verifica requisiti prima dell'attivazione
    $errors = rndt_manager_check_requirements();
    if ( ! empty( $errors ) ) {
        wp_die(
            implode( '<br>', array_map( 'esc_html', $errors ) ),
            __( 'Errore attivazione plugin', 'rndt-manager' ),
            array( 'back_link' => true )
        );
    }

    require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-activator.php';
    RNDT_Activator::activate();
}
register_activation_hook( __FILE__, 'rndt_manager_activate' );

/**
 * Hook di disattivazione
 */
function rndt_manager_deactivate() {
    require_once RNDT_MANAGER_PLUGIN_DIR . 'includes/class-rndt-deactivator.php';
    RNDT_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'rndt_manager_deactivate' );
