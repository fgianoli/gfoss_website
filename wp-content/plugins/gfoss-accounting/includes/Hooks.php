<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Hooks cross-plugin: ogni quota associativa pagata diventa automaticamente
 * un movimento di entrata in categoria 'quota_associativa'.
 *
 * Idempotente: se esiste già un movimento per quel quota_id non lo duplica.
 */
class Hooks {

    public static function init(): void {
        add_action( 'gfoss_members_quota_paid', [ __CLASS__, 'on_quota_paid' ], 10, 5 );
    }

    public static function on_quota_paid( int $quota_id, int $user_id, int $anno, string $metodo, ?string $txn ): void {
        global $wpdb;
        $exists = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . Schema::table_movement() . " WHERE quota_id = %d", $quota_id
        ) );
        if ( $exists ) { return; }

        $u    = get_userdata( $user_id );
        $name = $u ? $u->display_name : "Socio #{$user_id}";

        Movement::create( [
            'data'           => current_time( 'Y-m-d', true ),
            'tipo'           => 'entrata',
            'categoria_slug' => 'quota_associativa',
            'importo'        => (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ),
            'descrizione'    => "Quota {$anno} — {$name}",
            'socio_id'       => $user_id,
            'quota_id'       => $quota_id,
            'metodo'         => $metodo,
            'note'           => $txn ? "txn: {$txn}" : null,
        ] );
    }
}
