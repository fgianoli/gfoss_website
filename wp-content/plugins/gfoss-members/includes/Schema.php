<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tabelle custom.
 *
 * gfoss_quote        — un record per (socio, anno solare). UNIQUE.
 * gfoss_candidatura  — domanda di ammissione (art. 6 Statuto). State machine:
 *
 *      cd_decision        payment_status        stato derivato
 *      ─────────────────────────────────────────────────────
 *      NULL               unpaid                pending
 *      NULL               paid                  awaiting_cd
 *      approved           unpaid                awaiting_payment
 *      approved           paid                  effective    ← crea utente WP
 *      rejected           *                     rejected
 */
class Schema {

    public static function table_quote(): string       { global $wpdb; return $wpdb->prefix . 'gfoss_quote'; }
    public static function table_candidatura(): string { global $wpdb; return $wpdb->prefix . 'gfoss_candidatura'; }
    public static function table_donazioni(): string   { global $wpdb; return $wpdb->prefix . 'gfoss_donazioni'; }

    public static function maybe_upgrade(): void {
        if ( get_option( 'gfoss_members_db_version' ) !== GFOSS_MEMBERS_DB_VER ) {
            self::install();
        }
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $t_quote = self::table_quote();
        $t_cand  = self::table_candidatura();

        $sql_quote = "CREATE TABLE $t_quote (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            anno SMALLINT UNSIGNED NOT NULL,
            importo DECIMAL(10,2) NOT NULL,
            metodo VARCHAR(32) NOT NULL DEFAULT 'bonifico',
            stato VARCHAR(16) NOT NULL DEFAULT 'pending',
            data_pagamento DATE NULL DEFAULT NULL,
            transaction_ref VARCHAR(190) NULL DEFAULT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_user_anno (user_id, anno),
            KEY anno (anno),
            KEY stato (stato),
            KEY transaction_ref (transaction_ref)
        ) $charset;";

        $sql_cand = "CREATE TABLE $t_cand (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            token VARCHAR(64) NOT NULL DEFAULT '',
            nome VARCHAR(120) NOT NULL,
            cognome VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            codice_fiscale VARCHAR(16) NULL DEFAULT NULL,
            data_nascita DATE NULL DEFAULT NULL,
            comune_nascita VARCHAR(120) NULL DEFAULT NULL,
            indirizzo VARCHAR(255) NULL DEFAULT NULL,
            cap VARCHAR(10) NULL DEFAULT NULL,
            citta VARCHAR(120) NULL DEFAULT NULL,
            provincia VARCHAR(2) NULL DEFAULT NULL,
            telefono VARCHAR(40) NULL DEFAULT NULL,
            professione VARCHAR(120) NULL DEFAULT NULL,
            competenze TEXT NULL,
            motivazione TEXT NULL,
            volontario TINYINT(1) NOT NULL DEFAULT 0,
            consenso_privacy TINYINT(1) NOT NULL DEFAULT 0,
            consenso_statuto TINYINT(1) NOT NULL DEFAULT 0,
            stato VARCHAR(20) NOT NULL DEFAULT 'pending',
            cd_decision VARCHAR(16) NULL DEFAULT NULL,
            reviewed_by BIGINT UNSIGNED NULL DEFAULT NULL,
            reviewed_at DATETIME NULL DEFAULT NULL,
            note_review TEXT NULL,
            payment_status VARCHAR(16) NOT NULL DEFAULT 'unpaid',
            payment_at DATETIME NULL DEFAULT NULL,
            payment_method VARCHAR(32) NULL DEFAULT NULL,
            payment_amount DECIMAL(10,2) NULL DEFAULT NULL,
            payment_txn_ref VARCHAR(190) NULL DEFAULT NULL,
            effective_at DATETIME NULL DEFAULT NULL,
            ip VARCHAR(45) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY stato (stato),
            KEY user_id (user_id),
            KEY email (email),
            KEY token (token),
            KEY payment_txn_ref (payment_txn_ref)
        ) $charset;";

        $t_don = self::table_donazioni();
        $sql_don = "CREATE TABLE $t_don (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            progetto_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL DEFAULT '',
            importo DECIMAL(10,2) NOT NULL DEFAULT 0,
            metodo VARCHAR(32) NOT NULL DEFAULT 'paypal',
            stato VARCHAR(16) NOT NULL DEFAULT 'pending',
            donatore_nome VARCHAR(160) NULL DEFAULT NULL,
            donatore_email VARCHAR(190) NULL DEFAULT NULL,
            mostra_nome TINYINT(1) NOT NULL DEFAULT 0,
            consenso_privacy TINYINT(1) NOT NULL DEFAULT 0,
            messaggio VARCHAR(255) NULL DEFAULT NULL,
            transaction_ref VARCHAR(190) NULL DEFAULT NULL,
            data_pagamento DATE NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY progetto_id (progetto_id),
            KEY stato (stato),
            KEY token (token),
            KEY transaction_ref (transaction_ref)
        ) $charset;";

        dbDelta( $sql_quote );
        dbDelta( $sql_cand );
        dbDelta( $sql_don );

        update_option( 'gfoss_members_db_version', GFOSS_MEMBERS_DB_VER, false );
    }
}
