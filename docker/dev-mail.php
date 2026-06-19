<?php
/**
 * Plugin Name: Dev Mail Catcher
 * Description: Reindirizza wp_mail() su Mailpit (SMTP locale) — caricato solo in sviluppo.
 * Version: 1.0.0
 * Author: GFOSS.it dev
 *
 * Mu-plugin montato dal docker-compose.override.yml. NON arriva in produzione
 * (in prod questo file non è montato).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'phpmailer_init', static function ( $phpmailer ) {
    $phpmailer->isSMTP();
    $phpmailer->Host        = 'mailpit';
    $phpmailer->Port        = 1025;
    $phpmailer->SMTPAuth    = false;
    $phpmailer->SMTPAutoTLS = false;
    $phpmailer->From        = 'dev@gfoss.local';
    $phpmailer->FromName    = 'GFOSS.it (dev)';
} );

// Filtro per by-passare le verifiche dominio sui da: in dev
add_filter( 'wp_mail_from',      static fn() => 'dev@gfoss.local' );
add_filter( 'wp_mail_from_name', static fn() => 'GFOSS.it (dev)' );
