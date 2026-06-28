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
    public const EVENT_REMINDER_DAYS = 5; // giorni prima dell'evento per il promemoria al direttivo

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

        // Promemoria al direttivo per compilare il registro volontari prima degli eventi
        self::event_reminders();

        do_action( 'gfoss_members_daily_check_done', [ 'year' => $year, 'days_to_eoy' => $days ] );
    }

    /**
     * Per ogni evento imminente (entro EVENT_REMINDER_DAYS) invia una volta sola
     * un promemoria al direttivo: "ricordati di compilare il registro volontari".
     */
    private static function event_reminders(): void {
        if ( ! class_exists( __NAMESPACE__ . '\\Eventi' ) ) { return; }
        $tz    = wp_timezone();
        $today = new \DateTimeImmutable( 'today', $tz );

        $events = get_posts( [ 'post_type' => Eventi::CPT, 'numberposts' => 100, 'post_status' => 'publish', 'meta_key' => '_gf_ev_data' ] );
        foreach ( $events as $ev ) {
            $ini = (string) get_post_meta( $ev->ID, '_gf_ev_data', true );
            if ( ! $ini ) { continue; }
            if ( get_post_meta( $ev->ID, '_gfoss_vol_reminded', true ) ) { continue; }
            try { $evDay = new \DateTimeImmutable( substr( $ini, 0, 10 ), $tz ); } catch ( \Throwable $e ) { continue; }
            $days = (int) $today->diff( $evDay )->format( '%r%a' );
            if ( $days >= 1 && $days <= self::EVENT_REMINDER_DAYS ) {
                self::send_event_reminder( $ev, $days, $ini );
                update_post_meta( $ev->ID, '_gfoss_vol_reminded', '1' );
            }
        }
    }

    private static function send_event_reminder( \WP_Post $ev, int $days, string $ini ): void {
        $dest = get_users( [ 'role__in' => [ 'gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_segreteria','administrator' ] ] );
        if ( ! $dest ) { return; }

        $pg  = get_posts( [ 'post_type' => 'page', 'name' => 'registro-volontari', 'post_status' => 'publish', 'numberposts' => 1 ] );
        $url = $pg ? add_query_arg( 'ev', $ev->ID, get_permalink( $pg[0] ) ) : admin_url( 'admin.php?page=gfoss-volontari' );
        $when = date_i18n( 'd/m/Y', strtotime( $ini ) );

        $oggetto = sprintf( '[GFOSS] Tra %d giorni: «%s» — compila il registro volontari', $days, $ev->post_title );
        $body = '<p>Ciao,</p>'
            . '<p>Tra <strong>' . (int) $days . ' giorni</strong> (' . esc_html( $when ) . ') si terrà l\'evento <strong>' . esc_html( $ev->post_title ) . '</strong>.</p>'
            . '<p>Ricordati di <strong>compilare il registro dei volontari</strong> per la copertura assicurativa: aggiungi i presenti (soci e occasionali) e genera il PDF.</p>'
            . '<p><a href="' . esc_url( $url ) . '">Apri il registro volontari per questo evento →</a></p>'
            . '<p>Dopo l\'evento: genera il PDF con l\'impronta, mettilo a verbale del Direttivo e invialo via PEC per la data certa.</p>'
            . '<hr><p style="font-size:12px;color:#777">GFOSS.it APS — promemoria automatico per il Consiglio Direttivo.</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        foreach ( $dest as $u ) {
            if ( is_email( $u->user_email ) ) { wp_mail( $u->user_email, $oggetto, $body, $headers ); }
        }
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
