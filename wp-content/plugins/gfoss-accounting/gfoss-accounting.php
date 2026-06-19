<?php
/**
 * Plugin Name:       GFOSS Accounting
 * Plugin URI:        https://gfoss.it
 * Description:       Contabilità entrate/uscite per GFOSS.it APS — accessibile a Tesoriere, Revisore e Amministratori. Dipende da "GFOSS Members".
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            GFOSS.it APS
 * License:           GPL-2.0-or-later
 * Text Domain:       gfoss-accounting
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GFOSS_ACCOUNTING_VERSION', '1.0.0' );
define( 'GFOSS_ACCOUNTING_FILE',    __FILE__ );
define( 'GFOSS_ACCOUNTING_DIR',     plugin_dir_path( __FILE__ ) );
define( 'GFOSS_ACCOUNTING_URL',     plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, static function () {
    if ( ! class_exists( '\\GFOSS_Members\\Roles' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'GFOSS Accounting richiede il plugin "GFOSS Members" attivo.', 'gfoss-accounting' ) );
    }
    require_once __DIR__ . '/includes/Schema.php';
    \GFOSS_Accounting\Schema::install();
    flush_rewrite_rules();
} );

spl_autoload_register( static function ( $class ) {
    if ( ! str_starts_with( $class, 'GFOSS_Accounting\\' ) ) { return; }
    $rel  = str_replace( [ 'GFOSS_Accounting\\', '\\' ], [ '', '/' ], $class );
    $file = GFOSS_ACCOUNTING_DIR . 'includes/' . $rel . '.php';
    if ( is_file( $file ) ) { require_once $file; }
} );

add_action( 'plugins_loaded', static function () {
    if ( ! class_exists( '\\GFOSS_Members\\Roles' ) ) { return; }
    \GFOSS_Accounting\Schema::maybe_upgrade();
    \GFOSS_Accounting\Hooks::init();
    if ( is_admin() ) {
        \GFOSS_Accounting\Admin::init();
        \GFOSS_Accounting\Export::init();
    }
} );
