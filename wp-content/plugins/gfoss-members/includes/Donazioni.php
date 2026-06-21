<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Donazioni alle campagne di crowdfunding (CPT gfoss_progetto).
 *
 *   - Donazione PayPal: si crea un record "pending" col token del donatore
 *     (nome, email, consenso a mostrare il nome), poi l'IPN lo conferma "paid".
 *   - Donazione manuale (bonifico): registrata direttamente come "paid".
 *   - Modello keep-it-all: i fondi sono acquisiti; nessun rimborso automatico.
 */
class Donazioni {

    public static function create_pending( int $progetto_id, float $importo, string $nome, string $email, bool $mostra_nome, string $messaggio = '' ): array {
        global $wpdb;
        $token = bin2hex( random_bytes( 16 ) );
        $wpdb->insert( Schema::table_donazioni(), [
            'progetto_id'      => $progetto_id,
            'token'            => $token,
            'importo'          => round( $importo, 2 ),
            'metodo'           => 'paypal',
            'stato'            => 'pending',
            'donatore_nome'    => $nome ?: null,
            'donatore_email'   => $email ?: null,
            'mostra_nome'      => $mostra_nome ? 1 : 0,
            'consenso_privacy' => 1,
            'messaggio'        => $messaggio ?: null,
        ] );
        return [ 'id' => (int) $wpdb->insert_id, 'token' => $token ];
    }

    public static function find_by_token( string $token ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table_donazioni() . ' WHERE token = %s', $token
        ), ARRAY_A );
        return $row ?: null;
    }

    /** Conferma una donazione pending (da IPN). Idempotente. */
    public static function mark_paid( int $id, float $importo, string $metodo, string $txn ): void {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table_donazioni() . ' WHERE id = %d', $id ), ARRAY_A );
        if ( ! $row || $row['stato'] === 'paid' ) { return; }
        $wpdb->update( Schema::table_donazioni(), [
            'stato'           => 'paid',
            'importo'         => round( $importo, 2 ),
            'metodo'          => sanitize_key( $metodo ),
            'transaction_ref' => $txn,
            'data_pagamento'  => gmdate( 'Y-m-d' ),
        ], [ 'id' => $id ] );
        do_action( 'gfoss_members_donazione_paid', $id, (int) $row['progetto_id'], round( $importo, 2 ), $metodo, $txn );
    }

    /** Donazione registrata a mano (bonifico/contanti), già pagata. */
    public static function record_manual( int $progetto_id, float $importo, string $nome, string $email, bool $mostra_nome, string $metodo = 'bonifico', string $txn = '' ): int {
        global $wpdb;
        $wpdb->insert( Schema::table_donazioni(), [
            'progetto_id'     => $progetto_id,
            'token'           => '',
            'importo'         => round( $importo, 2 ),
            'metodo'          => sanitize_key( $metodo ),
            'stato'           => 'paid',
            'donatore_nome'   => $nome ?: null,
            'donatore_email'  => $email ?: null,
            'mostra_nome'     => $mostra_nome ? 1 : 0,
            'transaction_ref' => $txn ?: null,
            'data_pagamento'  => gmdate( 'Y-m-d' ),
        ] );
        $id = (int) $wpdb->insert_id;
        do_action( 'gfoss_members_donazione_paid', $id, $progetto_id, round( $importo, 2 ), $metodo, $txn );
        return $id;
    }

    public static function raised( int $progetto_id ): float {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(importo),0) FROM " . Schema::table_donazioni() . " WHERE progetto_id = %d AND stato = 'paid'",
            $progetto_id
        ) );
    }

    public static function count_paid( int $progetto_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . Schema::table_donazioni() . " WHERE progetto_id = %d AND stato = 'paid'",
            $progetto_id
        ) );
    }

    /** Sostenitori che hanno acconsentito a mostrare il nome. */
    public static function supporters( int $progetto_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT donatore_nome, importo, messaggio, data_pagamento FROM " . Schema::table_donazioni() . "
             WHERE progetto_id = %d AND stato = 'paid' AND mostra_nome = 1 AND donatore_nome <> ''
             ORDER BY data_pagamento DESC, id DESC",
            $progetto_id
        ), ARRAY_A ) ?: [];
    }

    /** Tutte le donazioni pagate (per l'admin). */
    public static function list_for_project( int $progetto_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_donazioni() . " WHERE progetto_id = %d AND stato = 'paid' ORDER BY data_pagamento DESC, id DESC",
            $progetto_id
        ), ARRAY_A ) ?: [];
    }
}
