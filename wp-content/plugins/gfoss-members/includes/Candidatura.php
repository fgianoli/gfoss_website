<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Lifecycle e state machine della candidatura ad associato.
 *
 * Le due condizioni per diventare effettivo sono indipendenti e possono avvenire
 * in qualunque ordine:
 *   - delibera del CD (cd_decision = 'approved')
 *   - pagamento della prima quota (payment_status = 'paid')
 *
 * Quando entrambe sono vere, recompute() promuove la candidatura a 'effective',
 * crea l'utente WP con ruolo gfoss_socio, copia anagrafica nei meta, registra la
 * quota nell'anno solare in corso, invia l'email di benvenuto.
 */
class Candidatura {

    public const STATO_PENDING           = 'pending';
    public const STATO_AWAITING_CD       = 'awaiting_cd';
    public const STATO_AWAITING_PAYMENT  = 'awaiting_payment';
    public const STATO_EFFECTIVE         = 'effective';
    public const STATO_REJECTED          = 'rejected';
    public const STATO_WITHDRAWN         = 'withdrawn';

    public static function create( array $data ): int {
        global $wpdb;
        $table = Schema::table_candidatura();

        $row = wp_parse_args( $data, [
            'token'              => self::new_token(),
            'volontario'         => 0,
            'consenso_privacy'   => 0,
            'consenso_statuto'   => 0,
            'stato'              => self::STATO_PENDING,
            'payment_status'     => 'unpaid',
            'ip'                 => self::client_ip(),
        ] );
        $wpdb->insert( $table, $row );
        return (int) $wpdb->insert_id;
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_candidatura() . " WHERE id = %d", $id
        ), ARRAY_A );
        return $row ?: null;
    }

    public static function get_by_token( string $token ): ?array {
        global $wpdb;
        if ( $token === '' ) { return null; }
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_candidatura() . " WHERE token = %s", $token
        ), ARRAY_A );
        return $row ?: null;
    }

    public static function find_by_email_pending( string $email ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_candidatura()
            . " WHERE email = %s AND stato IN ('pending','awaiting_cd','awaiting_payment')
                ORDER BY created_at DESC LIMIT 1",
            $email
        ), ARRAY_A );
        return $row ?: null;
    }

    public static function list_filtered( ?string $stato = null ): array {
        global $wpdb;
        $sql = "SELECT * FROM " . Schema::table_candidatura();
        $args = [];
        if ( $stato ) { $sql .= " WHERE stato = %s"; $args[] = $stato; }
        $sql .= " ORDER BY created_at DESC LIMIT 500";
        $prepared = $args ? $wpdb->prepare( $sql, $args ) : $sql;
        return $wpdb->get_results( $prepared, ARRAY_A ) ?: [];
    }

    public static function approve( int $id, int $reviewer_user_id, string $note = '' ): bool {
        return self::set_cd_decision( $id, 'approved', $reviewer_user_id, $note );
    }

    public static function reject( int $id, int $reviewer_user_id, string $note = '' ): bool {
        return self::set_cd_decision( $id, 'rejected', $reviewer_user_id, $note );
    }

    private static function set_cd_decision( int $id, string $decision, int $reviewer, string $note ): bool {
        global $wpdb;
        $ok = (bool) $wpdb->update(
            Schema::table_candidatura(),
            [
                'cd_decision' => $decision,
                'reviewed_by' => $reviewer ?: null,
                'reviewed_at' => current_time( 'mysql', true ),
                'note_review' => $note,
            ],
            [ 'id' => $id ]
        );
        if ( $ok ) {
            self::recompute( $id );
        }
        return $ok;
    }

    public static function record_payment( int $id, float $amount, string $method, string $txn_ref ): bool {
        global $wpdb;
        // Idempotenza: se la stessa transazione è già registrata, non ripetere.
        $row = self::get( $id );
        if ( $row && $row['payment_txn_ref'] === $txn_ref && $row['payment_status'] === 'paid' ) {
            return true;
        }
        $ok = (bool) $wpdb->update(
            Schema::table_candidatura(),
            [
                'payment_status'  => 'paid',
                'payment_at'      => current_time( 'mysql', true ),
                'payment_method'  => sanitize_key( $method ),
                'payment_amount'  => $amount,
                'payment_txn_ref' => $txn_ref,
            ],
            [ 'id' => $id ]
        );
        if ( $ok ) {
            self::recompute( $id );
        }
        return $ok;
    }

    /** Cuore della state machine. */
    public static function recompute( int $id ): void {
        $row = self::get( $id );
        if ( ! $row ) { return; }

        // Già effettivo o in stato terminale → niente da fare.
        if ( in_array( $row['stato'], [ self::STATO_EFFECTIVE, self::STATO_WITHDRAWN ], true ) ) { return; }

        $cd   = $row['cd_decision'];
        $paid = $row['payment_status'] === 'paid';

        if ( $cd === 'rejected' ) {
            self::set_stato( $id, self::STATO_REJECTED );
            do_action( 'gfoss_members_candidatura_rejected', $row );
            Email::candidatura_rejected( $row );
            return;
        }

        if ( $cd === 'approved' && $paid ) {
            self::make_effective( $row );
            return;
        }

        if ( $cd === 'approved' && ! $paid ) {
            self::set_stato( $id, self::STATO_AWAITING_PAYMENT );
            // Notifica solo al primo passaggio (basata su transizione di stato).
            if ( $row['stato'] !== self::STATO_AWAITING_PAYMENT ) {
                Email::candidatura_approved_pay_now( self::get( $id ) );
            }
            return;
        }

        if ( $cd === null && $paid ) {
            self::set_stato( $id, self::STATO_AWAITING_CD );
            return;
        }

        // Ancora in attesa di entrambi.
        self::set_stato( $id, self::STATO_PENDING );
    }

    private static function set_stato( int $id, string $stato ): void {
        global $wpdb;
        $wpdb->update( Schema::table_candidatura(), [ 'stato' => $stato ], [ 'id' => $id ] );
    }

    /**
     * Promuove la candidatura: crea l'utente WP, copia anagrafica nei meta,
     * registra la quota dell'anno corrente, invia welcome email.
     */
    private static function make_effective( array $cand ): void {
        global $wpdb;

        // 1. Crea utente (se non esiste già). user_login = email.
        $user_id = (int) ( $cand['user_id'] ?: 0 );
        if ( ! $user_id ) {
            $existing = get_user_by( 'email', $cand['email'] );
            if ( $existing ) {
                $user_id = (int) $existing->ID;
                $existing->set_role( 'gfoss_socio' );
            } else {
                $user_id = wp_insert_user( [
                    'user_login'   => sanitize_user( $cand['email'], true ),
                    'user_email'   => $cand['email'],
                    'user_pass'    => wp_generate_password( 24, true, true ),
                    'first_name'   => $cand['nome'],
                    'last_name'    => $cand['cognome'],
                    'display_name' => trim( $cand['nome'] . ' ' . $cand['cognome'] ),
                    'role'         => 'gfoss_socio',
                ] );
                if ( is_wp_error( $user_id ) ) {
                    error_log( '[gfoss-members] make_effective: ' . $user_id->get_error_message() );
                    return;
                }
            }
        }

        // 2. Copia anagrafica nei user_meta.
        $map = [
            'codice_fiscale'  => 'gf_codice_fiscale',
            'data_nascita'    => 'gf_data_nascita',
            'comune_nascita'  => 'gf_comune_nascita',
            'indirizzo'       => 'gf_indirizzo',
            'cap'             => 'gf_cap',
            'citta'           => 'gf_citta',
            'provincia'       => 'gf_provincia',
            'telefono'        => 'gf_telefono',
            'professione'     => 'gf_professione',
            'competenze'      => 'gf_competenze',
            'volontario'      => 'gf_volontario',
        ];
        foreach ( $map as $src => $meta ) {
            update_user_meta( $user_id, $meta, (string) ( $cand[ $src ] ?? '' ) );
        }
        update_user_meta( $user_id, 'gf_data_ammissione', current_time( 'Y-m-d', true ) );
        update_user_meta( $user_id, 'gf_numero_socio', (string) self::next_numero_socio() );

        // 3. Registra quota dell'anno corrente.
        $year = (int) gmdate( 'Y' );
        Quote::mark_paid(
            $user_id, $year,
            $cand['payment_method'] ?: 'paypal',
            $cand['payment_txn_ref'] ?: null,
            'Prima quota da candidatura #' . $cand['id']
        );

        // 4. Aggiorna candidatura.
        $wpdb->update(
            Schema::table_candidatura(),
            [
                'user_id'       => $user_id,
                'stato'         => self::STATO_EFFECTIVE,
                'effective_at'  => current_time( 'mysql', true ),
            ],
            [ 'id' => (int) $cand['id'] ]
        );

        // 5. Email di benvenuto + reset-password link standard di WP.
        Email::candidatura_welcome( $user_id, self::get( (int) $cand['id'] ) );
        wp_new_user_notification( $user_id, null, 'user' );

        do_action( 'gfoss_members_candidatura_effective', $user_id, self::get( (int) $cand['id'] ) );
    }

    /**
     * Numero socio nel formato ANNO-NNNNN (es. 2026-00001): anno di iscrizione +
     * progressivo a 5 cifre che riparte ogni anno. Generato all'approvazione.
     */
    public static function next_numero_socio(): string {
        $year = (int) gmdate( 'Y' );
        $opt  = 'gfoss_numero_socio_seq_' . $year;
        $seq  = (int) get_option( $opt, 0 ) + 1;
        update_option( $opt, $seq, false );
        return sprintf( '%d-%05d', $year, $seq );
    }

    /** Il numero socio è già usato da un altro utente? */
    public static function numero_in_use( string $numero, int $exclude_uid = 0 ): bool {
        if ( $numero === '' ) { return false; }
        global $wpdb;
        $id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'gf_numero_socio' AND meta_value = %s AND user_id <> %d LIMIT 1",
            $numero, $exclude_uid
        ) );
        return $id > 0;
    }

    public static function new_token(): string {
        return bin2hex( random_bytes( 16 ) );
    }

    public static function client_ip(): ?string {
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $_SERVER['REMOTE_ADDR'] );
        }
        return null;
    }

    /** Costruisce l'URL del bottone PayPal hosted con custom field per IPN. */
    public static function paypal_url( array $cand ): string {
        $bid = defined( 'GFOSS_PAYPAL_BUTTON_ID' ) ? GFOSS_PAYPAL_BUTTON_ID : '';
        $amount = (float) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 );
        $year = (int) gmdate( 'Y' );
        $params = http_build_query( [
            'hosted_button_id' => $bid,
            'amount'           => number_format( $amount, 2, '.', '' ),
            'currency_code'    => 'EUR',
            'item_name'        => "Quota associativa GFOSS.it APS {$year}",
            'custom'           => 'cand_' . $cand['token'],
            'no_shipping'      => 1,
            'return'           => add_query_arg( [ 'gfoss_payment' => 'ok', 'token' => $cand['token'] ], home_url( '/' ) ),
            'cancel_return'    => add_query_arg( [ 'gfoss_payment' => 'cancel', 'token' => $cand['token'] ], home_url( '/' ) ),
            'notify_url'       => rest_url( 'gfoss/v1/paypal-ipn' ),
        ] );
        return 'https://www.paypal.com/donate/?' . $params;
    }
}
