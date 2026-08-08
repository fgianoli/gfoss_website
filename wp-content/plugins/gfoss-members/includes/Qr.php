<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Generatore QR riusabile (PNG data-URI), basato su endroid/qr-code già presente
 * nel vendor del plugin. Nessuna dipendenza esterna a runtime.
 */
class Qr {

    public static function data_uri( string $data, int $size = 220 ): string {
        if ( ! class_exists( '\\Endroid\\QrCode\\Builder\\Builder' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Endroid\\QrCode\\Builder\\Builder' ) ) {
            return '';
        }
        try {
            return \Endroid\QrCode\Builder\Builder::create()
                ->writer( new \Endroid\QrCode\Writer\PngWriter() )
                ->data( $data )
                ->size( max( 80, $size ) )
                ->margin( 6 )
                ->build()
                ->getDataUri();
        } catch ( \Throwable $e ) {
            error_log( '[gfoss-members] QR build failed: ' . $e->getMessage() );
            return '';
        }
    }
}
