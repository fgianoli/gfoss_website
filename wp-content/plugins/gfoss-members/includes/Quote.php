<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * API quote associative.
 * - 1 record per (socio, anno solare) — UNIQUE constraint
 * - Stato: pending | paid | refunded
 * - Validità: anno solare (1 gen → 31 dic), come Statuto art. 22
 */
class Quote {

    public static function init(): void {
        // Hook futuri (callback PayPal IPN, REST endpoints) verranno registrati qui.
    }

    public static function default_amount(): float {
        return defined( 'GFOSS_QUOTA_AMOUNT' ) ? (float) GFOSS_QUOTA_AMOUNT : 30.0;
    }

    /** @return string 'paid'|'pending'|'expiring'|'expired'|'unknown' */
    public static function status_for( int $user_id, int $year ): string {
        global $wpdb;
        $table = Schema::table_quote();
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT stato FROM $table WHERE user_id = %d AND anno = %d",
            $user_id, $year
        ), ARRAY_A );

        if ( $row && $row['stato'] === 'paid' ) {
            // se siamo nelle ultime 6 settimane dell'anno → 'expiring'
            $cutoff = mktime( 0, 0, 0, 11, 20, $year );
            return ( time() >= $cutoff ) ? 'expiring' : 'paid';
        }
        if ( $row && $row['stato'] === 'pending' ) { return 'pending'; }
        if ( ! $row && $year < (int) gmdate( 'Y' ) ) { return 'expired'; }
        return $row ? 'unknown' : 'pending';
    }

    /**
     * Crea o aggiorna la quota di un socio per un dato anno.
     * Restituisce l'id riga.
     */
    public static function upsert( int $user_id, int $anno, array $data ): int {
        global $wpdb;
        $table = Schema::table_quote();

        $defaults = [
            'importo'         => self::default_amount(),
            'metodo'          => 'bonifico',
            'stato'           => 'pending',
            'data_pagamento'  => null,
            'transaction_ref' => null,
            'note'            => null,
        ];
        $row = wp_parse_args( $data, $defaults );

        $existing_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND anno = %d",
            $user_id, $anno
        ) );

        $payload = [
            'user_id'         => $user_id,
            'anno'            => $anno,
            'importo'         => (float) $row['importo'],
            'metodo'          => sanitize_key( $row['metodo'] ),
            'stato'           => sanitize_key( $row['stato'] ),
            'data_pagamento'  => $row['data_pagamento'] ?: null,
            'transaction_ref' => $row['transaction_ref'] ?: null,
            'note'            => $row['note'] ?: null,
        ];

        if ( $existing_id ) {
            $wpdb->update( $table, $payload, [ 'id' => $existing_id ] );
            return $existing_id;
        }
        $payload['created_by'] = get_current_user_id() ?: null;
        $wpdb->insert( $table, $payload );
        return (int) $wpdb->insert_id;
    }

    public static function mark_paid( int $user_id, int $anno, string $metodo, ?string $transaction_ref = null, ?string $note = null, ?float $amount = null ): int {
        $data = [
            'stato'           => 'paid',
            'metodo'          => $metodo,
            'transaction_ref' => $transaction_ref,
            'data_pagamento'  => gmdate( 'Y-m-d' ),
            'note'            => $note,
        ];
        if ( $amount !== null ) { $data['importo'] = $amount; }
        $id = self::upsert( $user_id, $anno, $data );
        // Reset reminder flags per il nuovo anno (così l'anno prossimo riceveranno i promemoria).
        delete_user_meta( $user_id, 'gfoss_reminder_preEoy_' . $anno . '_30' );
        delete_user_meta( $user_id, 'gfoss_reminder_preEoy_' . $anno . '_7' );
        delete_user_meta( $user_id, 'gfoss_reminder_newYear_' . $anno );
        delete_user_meta( $user_id, 'gfoss_reminder_march_' . $anno );

        do_action( 'gfoss_members_quota_paid', $id, $user_id, $anno, $metodo, $transaction_ref );
        return $id;
    }

    /** Segna come NON pagata (pending) la quota di un anno, se esiste. */
    public static function mark_unpaid( int $user_id, int $anno ): void {
        global $wpdb;
        $wpdb->update(
            Schema::table_quote(),
            [ 'stato' => 'pending', 'data_pagamento' => null, 'transaction_ref' => null ],
            [ 'user_id' => $user_id, 'anno' => $anno ]
        );
    }

    /** Riga quota (user, anno) o null. */
    public static function get( int $user_id, int $anno ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_quote() . " WHERE user_id = %d AND anno = %d",
            $user_id, $anno
        ), ARRAY_A );
        return $row ?: null;
    }

    /** Prossimo numero ricevuta dell'anno (progressivo che riparte ogni anno). */
    public static function next_ricevuta_numero( int $anno ): int {
        global $wpdb;
        return 1 + (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(ricevuta_numero) FROM " . Schema::table_quote() . " WHERE anno = %d", $anno
        ) );
    }

    /** Verifica che un numero ricevuta non sia già usato per l'anno (esclude la riga stessa). */
    public static function ricevuta_numero_in_use( int $anno, int $numero, int $exclude_user = 0 ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . Schema::table_quote() . " WHERE anno = %d AND ricevuta_numero = %d AND user_id <> %d",
            $anno, $numero, $exclude_user
        ) );
    }

    /**
     * Aggiorna solo i campi legati alla ricevuta (numero, data pagamento, verbale,
     * pagatore) senza toccare stato/importo. La riga quota deve già esistere.
     */
    public static function save_ricevuta( int $user_id, int $anno, array $f ): bool {
        global $wpdb;
        $data = [];
        foreach ( [ 'ricevuta_numero', 'data_pagamento', 'verbale_data', 'pagatore_nome', 'pagatore_sede', 'pagatore_cf', 'pagatore_piva' ] as $k ) {
            if ( ! array_key_exists( $k, $f ) ) { continue; }
            $v = $f[ $k ];
            if ( $k === 'ricevuta_numero' ) {
                $data[ $k ] = ( $v === '' || $v === null ) ? null : (int) $v;
            } elseif ( $k === 'data_pagamento' || $k === 'verbale_data' ) {
                $data[ $k ] = $v ?: null;
            } else {
                $data[ $k ] = ( $v !== '' && $v !== null ) ? sanitize_text_field( (string) $v ) : null;
            }
        }
        if ( ! $data ) { return false; }
        return (bool) $wpdb->update( Schema::table_quote(), $data, [ 'user_id' => $user_id, 'anno' => $anno ] );
    }

    /** Assegna il prossimo numero ricevuta se non già presente (usato per i rinnovi PayPal). */
    public static function assign_ricevuta_if_missing( int $user_id, int $anno ): void {
        $row = self::get( $user_id, $anno );
        if ( $row && empty( $row['ricevuta_numero'] ) ) {
            self::save_ricevuta( $user_id, $anno, [ 'ricevuta_numero' => self::next_ricevuta_numero( $anno ) ] );
        }
    }

    /** Ci sono i dati minimi per emettere la ricevuta? (numero + data pagamento). */
    public static function has_ricevuta( array $row ): bool {
        return ! empty( $row['ricevuta_numero'] ) && ! empty( $row['data_pagamento'] );
    }

    /** Ricevute emesse in un anno (quote con numero ricevuta), ordinate per numero. */
    public static function ricevute_for_year( int $anno ): array {
        global $wpdb;
        $t = Schema::table_quote();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT q.*, u.display_name, u.user_email
             FROM $t q LEFT JOIN {$wpdb->users} u ON u.ID = q.user_id
             WHERE q.anno = %d AND q.ricevuta_numero IS NOT NULL
             ORDER BY q.ricevuta_numero ASC",
            $anno
        ), ARRAY_A ) ?: [];
    }

    /** Lista quote di un singolo socio (storico). */
    public static function for_user( int $user_id ): array {
        global $wpdb;
        $table = Schema::table_quote();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY anno DESC", $user_id
        ), ARRAY_A ) ?: [];
    }

    /** Tutte le quote di un anno, mappate per user_id (per la console tesoriere). */
    public static function all_for_year( int $anno ): array {
        global $wpdb;
        $table = Schema::table_quote();
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE anno = %d", $anno
        ), ARRAY_A ) ?: [];
        $map = [];
        foreach ( $rows as $r ) { $map[ (int) $r['user_id'] ] = $r; }
        return $map;
    }

    /** Lista soci che NON hanno pagato la quota di un anno. */
    public static function unpaid_for_year( int $anno ): array {
        global $wpdb;
        $t_quote = Schema::table_quote();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.user_email, u.display_name
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = '{$wpdb->prefix}capabilities'
             LEFT JOIN $t_quote q ON q.user_id = u.ID AND q.anno = %d AND q.stato = 'paid'
             WHERE um.meta_value LIKE %s
               AND q.id IS NULL",
            $anno,
            '%' . $wpdb->esc_like( '"gfoss_socio"' ) . '%'
        ), ARRAY_A ) ?: [];
    }
}
