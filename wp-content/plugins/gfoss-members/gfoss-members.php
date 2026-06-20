<?php
/**
 * Plugin Name:       GFOSS Members
 * Plugin URI:        https://gfoss.it
 * Description:       Gestione soci, quote associative, area personale, tessera digitale e pagamenti per GFOSS.it APS.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            GFOSS.it APS
 * License:           GPL-2.0-or-later
 * Text Domain:       gfoss-members
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GFOSS_MEMBERS_VERSION',  '1.4.0' );
define( 'GFOSS_MEMBERS_FILE',     __FILE__ );
define( 'GFOSS_MEMBERS_DIR',      plugin_dir_path( __FILE__ ) );
define( 'GFOSS_MEMBERS_URL',      plugin_dir_url( __FILE__ ) );
define( 'GFOSS_MEMBERS_DB_VER',   '2' ); // bump to trigger dbDelta on next load

/** Minimal PSR-4-ish autoloader for our classes. */
spl_autoload_register( static function ( $class ) {
    if ( ! str_starts_with( $class, 'GFOSS_Members\\' ) ) { return; }
    $rel = str_replace( [ 'GFOSS_Members\\', '\\' ], [ '', '/' ], $class );
    $file = GFOSS_MEMBERS_DIR . 'includes/' . $rel . '.php';
    if ( is_file( $file ) ) { require_once $file; }
} );

// Lifecycle ----------------------------------------------------------------

register_activation_hook( __FILE__, [ \GFOSS_Members\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \GFOSS_Members\Deactivator::class, 'deactivate' ] );

// Boot --------------------------------------------------------------------

add_action( 'plugins_loaded', static function () {
    \GFOSS_Members\Roles::register();
    \GFOSS_Members\Schema::maybe_upgrade();
    \GFOSS_Members\User_Fields::init();
    \GFOSS_Members\Quote::init();
    \GFOSS_Members\Form::init();
    \GFOSS_Members\Submission::init();
    \GFOSS_Members\Paypal::init();
    \GFOSS_Members\Email::init();
    \GFOSS_Members\Area_Personale::init();
    \GFOSS_Members\Tessera::init();
    \GFOSS_Members\Ricevuta::init();
    \GFOSS_Members\Verify::init();
    \GFOSS_Members\Rinnovo::init();
    \GFOSS_Members\Profile_Update::init();
    \GFOSS_Members\Export::init();
    \GFOSS_Members\Doc_Riservato::init();
    \GFOSS_Members\Trasparenza::init();
    \GFOSS_Members\Eventi::init();
    \GFOSS_Members\Materiali::init();
    \GFOSS_Members\Mappa_Soci::init();
    \GFOSS_Members\Convocazioni::init();
    \GFOSS_Members\Newsletter::init();

    if ( is_admin() ) {
        \GFOSS_Members\Admin::init();
    }

    \GFOSS_Members\Cron::init();

    load_plugin_textdomain( 'gfoss-members', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/** Helper: URL della pagina pubblica "Iscriviti". */
function gfoss_members_iscrizione_url(): string {
    $page_id = (int) get_option( 'gfoss_page_iscriviti' );
    return $page_id ? get_permalink( $page_id ) : home_url( '/iscriviti/' );
}

// Public helpers used by the theme ----------------------------------------

/**
 * Status of the current-year membership fee for a user.
 *
 * @return string one of: 'paid' | 'pending' | 'expired' | 'expiring' | 'unknown'
 */
function gfoss_members_quota_status( int $user_id, ?int $year = null ): string {
    return \GFOSS_Members\Quote::status_for( $user_id, $year ?? (int) gmdate( 'Y' ) );
}

function gfoss_members_is_socio( int $user_id ): bool {
    $u = get_userdata( $user_id );
    return $u && in_array( 'gfoss_socio', (array) $u->roles, true );
}
