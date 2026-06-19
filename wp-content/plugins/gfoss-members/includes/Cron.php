<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Promemoria scadenza quota.
 *
 * Trigger giornaliero alle 06:00. Soglie:
 *   - 30 giorni prima del 31/12 → primo sollecito
 *   -  7 giorni prima del 31/12 → secondo sollecito
 *   - giorno dopo (1 gennaio anno successivo) → notifica scadenza
 *   - 1 marzo → ultima chiamata
 *
 * Idempotente: ogni promemoria registra un user_meta per evitare invii doppi.
 * Riepilogo amministrativo settimanale al CD ogni lunedì.
 */
class Cron {

    public const HOOK_DAILY = 'gfoss_members_daily_check';

    public static function init(): void {
        add_action( self::HOOK_DAILY, [ __CLASS__, 'run_daily' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            wp_schedule_event( strtotime( 'tomorrow 06:00' ), 'daily', self::HOOK_DAILY );
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled( self::HOOK_DAILY );
        if ( $ts ) { wp_unschedule_event( $ts, self::HOOK_DAILY ); }
    }

    public static function run_daily(): void {
        $tz    = wp_timezone();
        $today = new \DateTimeImmutable( 'today', $tz );
        $year  = (int) $today->format( 'Y' );
        $eoy   = new \DateTimeImmutable( $year . '-12-31', $tz );
        $days  = (int) $today->diff( $eoy )->format( '%r%a' );

        // Promemoria pre-scadenza (anno corrente)
        if ( in_array( $days, [ 30, 7 ], true ) ) {
            self::remind_unpaid( $year, "preEoy_{$year}_{$days}", "renewal_reminder", $days );
        }

        // Subito dopo Capodanno (anno appena iniziato): primo sollecito sul nuovo anno
        if ( $today->format( 'm-d' ) === '01-08' ) {
            self::remind_unpaid( $year, "newYear_{$year}", "renewal_reminder", -7 );
        }

        // Marzo: chi ancora non ha pagato la quota dell'anno corrente perde diritto di voto
        if ( $today->format( 'm-d' ) === '03-15' ) {
            self::remind_unpaid( $year, "march_{$year}", "renewal_last_call", 0 );
        }

        // Riepilogo settimanale al CD ogni lunedì
        if ( (int) $today->format( 'N' ) === 1 ) {
            Email::admin_quota_summary( $year, Quote::unpaid_for_year( $year ) );
        }

        do_action( 'gfoss_members_daily_check_done', [ 'year' => $year, 'days_to_eoy' => $days ] );
    }

    private static function remind_unpaid( int $year, string $reminder_key, string $template, int $days_to_eoy ): void {
        $unpaid = Quote::unpaid_for_year( $year );
        foreach ( $unpaid as $u ) {
            $user_id = (int) $u['ID'];
            $meta_key = 'gfoss_reminder_' . $reminder_key;
            if ( get_user_meta( $user_id, $meta_key, true ) ) { continue; }

            if ( $template === 'renewal_last_call' ) {
                Email::quota_last_call( $user_id, $year );
            } else {
                Email::quota_renewal_reminder( $user_id, $year, $days_to_eoy );
            }
            update_user_meta( $user_id, $meta_key, current_time( 'mysql', true ) );
        }
    }
}
