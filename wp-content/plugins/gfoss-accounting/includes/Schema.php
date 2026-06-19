<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Schema {

    public const DB_VERSION = '1';

    public static function table_movement(): string { global $wpdb; return $wpdb->prefix . 'gfoss_movement'; }
    public static function table_category(): string { global $wpdb; return $wpdb->prefix . 'gfoss_acc_cat'; }

    public static function maybe_upgrade(): void {
        if ( get_option( 'gfoss_accounting_db_version' ) !== self::DB_VERSION ) {
            self::install();
        }
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t_mov = self::table_movement();
        $t_cat = self::table_category();

        $sql_cat = "CREATE TABLE $t_cat (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(60) NOT NULL,
            label VARCHAR(120) NOT NULL,
            tipo VARCHAR(8) NOT NULL DEFAULT 'entrata',
            attivo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset;";

        $sql_mov = "CREATE TABLE $t_mov (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            data DATE NOT NULL,
            tipo VARCHAR(8) NOT NULL,
            categoria_slug VARCHAR(60) NOT NULL,
            importo DECIMAL(10,2) NOT NULL,
            descrizione VARCHAR(255) NOT NULL,
            socio_id BIGINT UNSIGNED NULL DEFAULT NULL,
            quota_id BIGINT UNSIGNED NULL DEFAULT NULL,
            documento_url VARCHAR(255) NULL DEFAULT NULL,
            metodo VARCHAR(32) NULL DEFAULT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY data (data),
            KEY tipo (tipo),
            KEY categoria_slug (categoria_slug),
            KEY socio_id (socio_id)
        ) $charset;";

        dbDelta( $sql_cat );
        dbDelta( $sql_mov );

        // Categorie iniziali coerenti con art. 19 dello Statuto.
        $defaults = [
            [ 'slug' => 'quota_associativa',    'label' => 'Quota associativa',                     'tipo' => 'entrata' ],
            [ 'slug' => 'donazione',            'label' => 'Donazione',                             'tipo' => 'entrata' ],
            [ 'slug' => 'contributo_pubblico',  'label' => 'Contributo pubblico',                   'tipo' => 'entrata' ],
            [ 'slug' => 'raccolta_fondi',       'label' => 'Raccolta fondi',                        'tipo' => 'entrata' ],
            [ 'slug' => 'cinque_per_mille',     'label' => '5 per mille',                            'tipo' => 'entrata' ],
            [ 'slug' => 'spesa_eventi',         'label' => 'Eventi e workshop',                      'tipo' => 'uscita' ],
            [ 'slug' => 'rimborso_volontario',  'label' => 'Rimborso spese volontari (art. 8)',     'tipo' => 'uscita' ],
            [ 'slug' => 'hosting_servizi',      'label' => 'Hosting e servizi web',                  'tipo' => 'uscita' ],
            [ 'slug' => 'commissioni_bancarie', 'label' => 'Commissioni bancarie / PayPal',          'tipo' => 'uscita' ],
            [ 'slug' => 'commercialista',       'label' => 'Commercialista / consulenze',            'tipo' => 'uscita' ],
            [ 'slug' => 'assicurazione',        'label' => 'Assicurazione (art. 26)',                'tipo' => 'uscita' ],
        ];
        foreach ( $defaults as $row ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO $t_cat (slug, label, tipo) VALUES (%s, %s, %s)",
                $row['slug'], $row['label'], $row['tipo']
            ) );
        }

        update_option( 'gfoss_accounting_db_version', self::DB_VERSION, false );
    }
}
