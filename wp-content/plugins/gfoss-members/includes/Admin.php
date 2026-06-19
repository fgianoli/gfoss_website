<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Menù admin "Associazione" — punto unico di accesso a soci, candidature, quote, export.
 * Le sotto-pagine sono incluse on-demand dalla cartella admin/views.
 */
class Admin {

    public static function init(): void {
        add_action( 'admin_menu',   [ __CLASS__, 'menu' ] );
        add_action( 'admin_init',   [ __CLASS__, 'maybe_handle_actions' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( GFOSS_MEMBERS_FILE ), [ __CLASS__, 'plugin_links' ] );
    }

    public static function menu(): void {
        $cap = Roles::CAP_MANAGE_SOCI;

        add_menu_page(
            __( 'Associazione', 'gfoss-members' ),
            __( 'Associazione', 'gfoss-members' ),
            $cap,
            'gfoss-associazione',
            [ __CLASS__, 'view_dashboard' ],
            'dashicons-groups',
            27
        );

        add_submenu_page( 'gfoss-associazione', __( 'Dashboard', 'gfoss-members' ),     __( 'Dashboard', 'gfoss-members' ),     $cap,                            'gfoss-associazione',           [ __CLASS__, 'view_dashboard' ] );
        add_submenu_page( 'gfoss-associazione', __( 'Soci', 'gfoss-members' ),          __( 'Soci', 'gfoss-members' ),          $cap,                            'gfoss-soci',                   [ __CLASS__, 'view_soci' ] );
        add_submenu_page( 'gfoss-associazione', __( 'Candidature nuovi soci', 'gfoss-members' ),   __( 'Candidature nuovi soci', 'gfoss-members' ),   Roles::CAP_REVIEW_CANDIDATURE,   'gfoss-candidature',            [ __CLASS__, 'view_candidature' ] );
        add_submenu_page( 'gfoss-associazione', __( 'Quote', 'gfoss-members' ),         __( 'Quote', 'gfoss-members' ),         Roles::CAP_MANAGE_QUOTE,         'gfoss-quote',                  [ __CLASS__, 'view_quote' ] );
        add_submenu_page( 'gfoss-associazione', __( 'Comunicazioni', 'gfoss-members' ), __( 'Comunicazioni', 'gfoss-members' ), Roles::CAP_MANAGE_SOCI,          'gfoss-comunicazioni',          [ __CLASS__, 'view_comunicazioni' ] );
        add_submenu_page( 'gfoss-associazione', __( 'Esporta registro', 'gfoss-members' ), __( 'Esporta registro', 'gfoss-members' ), Roles::CAP_EXPORT_REGISTRO, 'gfoss-export',                 [ __CLASS__, 'view_export' ] );
    }

    public static function plugin_links( array $links ): array {
        $url = admin_url( 'admin.php?page=gfoss-associazione' );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Apri', 'gfoss-members' ) . '</a>' );
        return $links;
    }

    public static function maybe_handle_actions(): void {
        // Le azioni POST/GET (approva candidatura, registra pagamento, ecc.) verranno gestite qui in fase 2.
    }

    public static function view_dashboard(): void { self::render( 'dashboard' ); }
    public static function view_soci(): void        { self::render( 'soci' ); }
    public static function view_candidature(): void { self::render( 'candidature' ); }
    public static function view_quote(): void       { self::render( 'quote' ); }
    public static function view_comunicazioni(): void { self::render( 'comunicazioni' ); }
    public static function view_export(): void      { self::render( 'export' ); }

    private static function render( string $view ): void {
        $file = GFOSS_MEMBERS_DIR . 'admin/views/' . $view . '.php';
        if ( is_file( $file ) ) {
            require $file;
        } else {
            echo '<div class="wrap"><h1>' . esc_html( ucfirst( $view ) ) . '</h1><p>'
                . esc_html__( 'Vista in sviluppo (fase 2).', 'gfoss-members' ) . '</p></div>';
        }
    }
}
