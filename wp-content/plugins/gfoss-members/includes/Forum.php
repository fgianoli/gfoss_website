<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Forum privato: il forum bbPress è accessibile SOLO ai soci in regola.
 * Tutto guardato da function_exists, così è innocuo se bbPress non è attivo.
 */
class Forum {

    public static function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'gate' ] );
        // Niente forum nei feed/sitemap pubblici.
        add_filter( 'bbp_register_forum_post_type', [ __CLASS__, 'hide_from_rest' ] );
    }

    public static function is_active(): bool {
        return function_exists( 'is_bbpress' );
    }

    /** Può accedere al forum? Soci in regola + amministratori. */
    public static function can_access(): bool {
        if ( current_user_can( 'manage_options' ) ) { return true; }
        $uid = get_current_user_id();
        return $uid && gfoss_members_is_socio( $uid );
    }

    public static function gate(): void {
        if ( ! self::is_active() || ! is_bbpress() ) { return; }
        if ( self::can_access() ) { return; }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( home_url( '/forums/' ) ) );
            exit;
        }
        wp_die(
            esc_html__( 'Il forum è riservato ai soci di GFOSS.it APS in regola con la quota.', 'gfoss-members' ),
            esc_html__( 'Area riservata', 'gfoss-members' ),
            [ 'response' => 403, 'back_link' => true ]
        );
    }

    public static function hide_from_rest( array $args ): array {
        $args['show_in_rest'] = false;
        return $args;
    }
}
