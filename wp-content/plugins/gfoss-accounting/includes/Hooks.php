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
        self::ensure_movement_for_quota( $quota_id, $user_id, $anno, $metodo, $txn );
    }

    /**
     * Crea (se manca) il movimento di entrata per una quota pagata. Idempotente.
     * Usato sia dall'hook sia dalla riconciliazione. Ritorna l'id del movimento.
     */
    public static function ensure_movement_for_quota( int $quota_id, int $user_id, int $anno, string $metodo = '', ?string $txn = null ): int {
        if ( Movement::exists_for_quota( $quota_id ) ) { return 0; }

        // Importo reale della quota (può differire dalla quota standard: ridotta/onoraria).
        global $wpdb;
        $importo = 0.0;
        if ( class_exists( '\\GFOSS_Members\\Schema' ) ) {
            $importo = (float) $wpdb->get_var( $wpdb->prepare(
                'SELECT importo FROM ' . \GFOSS_Members\Schema::table_quote() . ' WHERE id = %d', $quota_id
            ) );
        }
        if ( $importo <= 0 ) {
            $importo = (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 );
        }

        $u    = get_userdata( $user_id );
        $name = $u ? $u->display_name : "Socio #{$user_id}";

        return Movement::create( [
            'data'           => current_time( 'Y-m-d', true ),
            'tipo'           => 'entrata',
            'categoria_slug' => 'quota_associativa',
            'importo'        => $importo,
            'descrizione'    => "Quota {$anno} — {$name}",
            'socio_id'       => $user_id,
            'quota_id'       => $quota_id,
            'metodo'         => $metodo,
            'note'           => $txn ? "txn: {$txn}" : null,
        ] );
    }
}
