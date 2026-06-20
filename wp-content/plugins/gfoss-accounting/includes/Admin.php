<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {

    public static function init(): void {
        add_action( 'admin_menu',         [ __CLASS__, 'menu' ] );
        add_action( 'admin_post_gfoss_movement_save',   [ __CLASS__, 'save_movement' ] );
        add_action( 'admin_post_gfoss_movement_delete', [ __CLASS__, 'delete_movement' ] );
        add_action( 'admin_post_gfoss_acc_reconcile',   [ __CLASS__, 'reconcile' ] );
    }

    public static function menu(): void {
        $cap_view = \GFOSS_Members\Roles::CAP_VIEW_ACCOUNTING;
        $cap_man  = \GFOSS_Members\Roles::CAP_MANAGE_ACCOUNTING;

        add_menu_page( 'Contabilità', 'Contabilità', $cap_view, 'gfoss-contabilita',
            [ __CLASS__, 'view_dashboard' ], 'dashicons-chart-line', 28 );
        add_submenu_page( 'gfoss-contabilita', 'Dashboard',     'Dashboard',          $cap_view, 'gfoss-contabilita',           [ __CLASS__, 'view_dashboard' ] );
        add_submenu_page( 'gfoss-contabilita', 'Movimenti',     'Movimenti',          $cap_view, 'gfoss-movimenti',             [ __CLASS__, 'view_movements' ] );
        add_submenu_page( 'gfoss-contabilita', 'Aggiungi',      'Aggiungi movimento', $cap_man,  'gfoss-movimento-edit',        [ __CLASS__, 'view_edit' ] );
        add_submenu_page( 'gfoss-contabilita', 'Rendiconto',    'Rendiconto',         $cap_view, 'gfoss-rendiconto',            [ __CLASS__, 'view_rendiconto' ] );
        add_submenu_page( 'gfoss-contabilita', 'Riconciliazione quote', 'Riconciliazione', $cap_man, 'gfoss-riconciliazione',  [ __CLASS__, 'view_riconciliazione' ] );
        add_submenu_page( 'gfoss-contabilita', 'Esporta CSV',   'Esporta CSV',        $cap_view, 'gfoss-contabilita-export',    [ __CLASS__, 'view_export' ] );
    }

    public static function view_dashboard(): void { self::render( 'dashboard' ); }
    public static function view_movements(): void { self::render( 'movements' ); }
    public static function view_edit(): void      { self::render( 'movement-edit' ); }
    public static function view_rendiconto(): void { self::render( 'rendiconto' ); }
    public static function view_riconciliazione(): void { self::render( 'riconciliazione' ); }
    public static function view_export(): void    { self::render( 'export' ); }

    private static function render( string $view ): void {
        $file = GFOSS_ACCOUNTING_DIR . 'admin/views/' . $view . '.php';
        if ( is_file( $file ) ) { require $file; }
        else { echo '<div class="wrap"><h1>' . esc_html( ucfirst( $view ) ) . '</h1><p>vista mancante</p></div>'; }
    }

    public static function save_movement(): void {
        if ( ! current_user_can( \GFOSS_Members\Roles::CAP_MANAGE_ACCOUNTING ) ) { wp_die( 'forbidden', 403 ); }
        check_admin_referer( 'gfoss_movement_save' );

        $id   = (int) ( $_POST['id'] ?? 0 );
        $data = wp_unslash( $_POST );
        if ( $id ) { Movement::update( $id, $data ); }
        else      { $id = Movement::create( $data ); }

        wp_safe_redirect( admin_url( 'admin.php?page=gfoss-movimenti&msg=saved' ) );
        exit;
    }

    /** Riconciliazione: crea i movimenti mancanti per le quote pagate dell'anno. */
    public static function reconcile(): void {
        if ( ! current_user_can( \GFOSS_Members\Roles::CAP_MANAGE_ACCOUNTING ) ) { wp_die( 'forbidden', 403 ); }
        check_admin_referer( 'gfoss_acc_reconcile' );
        $year    = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );
        $created = 0;
        if ( class_exists( '\\GFOSS_Members\\Quote' ) ) {
            foreach ( \GFOSS_Members\Quote::all_for_year( $year ) as $uid => $q ) {
                if ( ( $q['stato'] ?? '' ) !== 'paid' ) { continue; }
                $mid = Hooks::ensure_movement_for_quota( (int) $q['id'], (int) $uid, $year, (string) ( $q['metodo'] ?? '' ), $q['transaction_ref'] ?? null );
                if ( $mid ) { $created++; }
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=gfoss-riconciliazione&anno=' . $year . '&msg=reconciled&n=' . $created ) );
        exit;
    }

    public static function delete_movement(): void {
        if ( ! current_user_can( \GFOSS_Members\Roles::CAP_MANAGE_ACCOUNTING ) ) { wp_die( 'forbidden', 403 ); }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'gfoss_movement_delete_' . $id );
        Movement::delete( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=gfoss-movimenti&msg=deleted' ) );
        exit;
    }
}
