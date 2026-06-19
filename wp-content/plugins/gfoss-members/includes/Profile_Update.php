<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handler POST per il form "I tuoi dati" dell'area personale.
 * Solo l'utente loggato può aggiornare i propri campi.
 */
class Profile_Update {

    public const ACTION = 'gfoss_update_profile';

    private const META_FIELDS = [
        'gf_telefono', 'gf_indirizzo', 'gf_cap', 'gf_citta', 'gf_provincia',
        'gf_professione', 'gf_competenze',
    ];

    public static function init(): void {
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle' ] );
    }

    public static function handle(): void {
        if ( ! is_user_logged_in() ) { wp_safe_redirect( home_url() ); exit; }

        $user_id = get_current_user_id();
        check_admin_referer( 'gfoss_profile_' . $user_id );

        // First/last name + display name
        $first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last  = sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) );
        if ( $first !== '' || $last !== '' ) {
            wp_update_user( [
                'ID'           => $user_id,
                'first_name'   => $first,
                'last_name'    => $last,
                'display_name' => trim( $first . ' ' . $last ) ?: get_userdata( $user_id )->display_name,
            ] );
        }

        // Meta editabili dall'utente
        foreach ( self::META_FIELDS as $key ) {
            if ( ! array_key_exists( $key, $_POST ) ) { continue; }
            $val = wp_unslash( $_POST[ $key ] );

            switch ( $key ) {
                case 'gf_cap':
                    if ( $val !== '' && ! preg_match( '/^\d{5}$/', (string) $val ) ) { continue 2; }
                    break;
                case 'gf_provincia':
                    $val = strtoupper( (string) $val );
                    if ( $val !== '' && ! preg_match( '/^[A-Z]{2}$/', $val ) ) { continue 2; }
                    break;
                case 'gf_competenze':
                    $val = sanitize_textarea_field( (string) $val );
                    break;
                default:
                    $val = sanitize_text_field( (string) $val );
            }
            update_user_meta( $user_id, $key, $val );
        }

        update_user_meta( $user_id, 'gf_volontario', empty( $_POST['gf_volontario'] ) ? '0' : '1' );

        // Consenso mappa soci (opt-in) + geocodifica città.
        update_user_meta( $user_id, 'gf_mappa_consenso', empty( $_POST['gf_mappa_consenso'] ) ? '0' : '1' );
        if ( class_exists( __NAMESPACE__ . '\\Mappa_Soci' ) ) {
            Mappa_Soci::maybe_geocode( $user_id );
        }

        $back = wp_get_referer() ?: home_url( '/area-soci/' );
        wp_safe_redirect( add_query_arg( 'msg', 'profile_saved', $back ) );
        exit;
    }
}
