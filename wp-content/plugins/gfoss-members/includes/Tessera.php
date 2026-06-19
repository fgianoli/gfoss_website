<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tessera digitale del socio.
 *
 *   Layout 85.6 × 54 mm (carta di credito ISO/IEC 7810 ID-1), due facciate.
 *   Front: logo + nome + numero socio + anno + QR di verifica.
 *   Back : dati associazione + nota verifica.
 *
 *   Generazione: mPDF (ottima resa CSS) + endroid/qr-code per il QR.
 *   Endpoint: GET /wp-json/gfoss/v1/tessera (richiede capability + matching user_id).
 *   Verifica: il QR punta alla pagina /verifica-tessera/?t=<token> (vedi Verify).
 */
class Tessera {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'gfoss/v1', '/tessera', [
            'methods'             => 'GET',
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can( Roles::CAP_DOWNLOAD_TESSERA ),
            'callback'            => [ __CLASS__, 'rest_download' ],
            'args'                => [
                'user' => [ 'sanitize_callback' => 'absint', 'default' => 0 ],
                'year' => [ 'sanitize_callback' => 'absint', 'default' => 0 ],
            ],
        ] );
    }

    public static function rest_download( \WP_REST_Request $req ) {
        $current = get_current_user_id();
        $target  = (int) ( $req['user'] ?: $current );
        $year    = (int) ( $req['year'] ?: gmdate( 'Y' ) );

        // Solo la propria tessera, salvo manage_soci.
        if ( $target !== $current && ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
            return new \WP_REST_Response( 'forbidden', 403 );
        }

        $status = Quote::status_for( $target, $year );
        if ( ! in_array( $status, [ 'paid', 'expiring' ], true ) && ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
            return new \WP_REST_Response( 'quota non in regola', 402 );
        }

        $pdf = self::generate_pdf( $target, $year );
        if ( $pdf instanceof \WP_Error ) {
            return new \WP_REST_Response( $pdf->get_error_message(), 500 );
        }

        $filename = sprintf( 'tessera-gfoss-%d-%d.pdf', $target, $year );
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );
        echo $pdf;
        exit;
    }

    public static function download_url( int $user_id ): string {
        return rest_url( 'gfoss/v1/tessera?user=' . $user_id );
    }

    /** PDF binario (string) o WP_Error. */
    public static function generate_pdf( int $user_id, int $year ): string|\WP_Error {
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            return new \WP_Error( 'no_mpdf', 'mPDF non installato. Esegui composer install nel plugin gfoss-members.' );
        }

        $html = self::render_html( $user_id, $year );

        try {
            $mpdf = new \Mpdf\Mpdf( [
                'mode'              => 'utf-8',
                'format'            => [ 85.6, 108 ],   // due facciate sopra/sotto su singola pagina
                'orientation'       => 'P',
                'margin_left'       => 0,
                'margin_right'      => 0,
                'margin_top'        => 0,
                'margin_bottom'     => 0,
                'tempDir'           => WP_CONTENT_DIR . '/uploads/gfoss-tmp',
                'fontDir'           => array_merge( ( new \Mpdf\Config\ConfigVariables() )->getDefaults()['fontDir'], [] ),
            ] );
            $mpdf->SetTitle( 'Tessera GFOSS.it APS' );
            $mpdf->SetAuthor( 'GFOSS.it APS' );
            $mpdf->WriteHTML( $html );
            return $mpdf->Output( '', 'S' );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'mpdf_fail', 'Errore generazione PDF: ' . $e->getMessage() );
        }
    }

    /** HTML per il PDF (renderizzato anche inline come anteprima nell'area personale). */
    public static function render_html( int $user_id, int $year ): string {
        $user = get_userdata( $user_id );
        if ( ! $user ) { return ''; }

        $data = [
            'user'         => $user,
            'year'         => $year,
            'numero_socio' => (string) get_user_meta( $user_id, 'gf_numero_socio', true ),
            'data_iscr'    => (string) get_user_meta( $user_id, 'gf_data_ammissione', true ),
            'qr_data_uri'  => self::qr_data_uri_for( $user_id ),
            'verify_url'   => Verify::url_for( $user_id ),
            'logo_uri'     => self::logo_data_uri(),
            'logo_svg'     => self::logo_svg(),
            'palette'      => self::year_palette( $year ),
        ];

        ob_start();
        $template = GFOSS_MEMBERS_DIR . 'templates/tessera.html.php';
        if ( is_file( $template ) ) {
            extract( $data, EXTR_SKIP );
            include $template;
        }
        return ob_get_clean();
    }

    /** Versione "ridotta" per anteprima HTML nell'area personale (no PDF). */
    public static function render_inline( int $user_id, int $year ): string {
        $user = get_userdata( $user_id );
        if ( ! $user ) { return ''; }
        $numero = (string) get_user_meta( $user_id, 'gf_numero_socio', true );
        $qr     = self::qr_data_uri_for( $user_id, 140 );
        $logo   = self::logo_data_uri();
        $pal    = self::year_palette( $year );
        $brand  = $logo
            ? '<img class="gf-tessera__logo" src="' . esc_attr( $logo ) . '" alt="GFOSS.it APS">'
            : self::logo_svg();
        $style  = 'background:linear-gradient(135deg,' . $pal['a'] . ',' . $pal['b'] . ')';
        return '<div class="gf-tessera" style="' . esc_attr( $style ) . '">'
            . '<div class="gf-tessera__brand">' . $brand . '</div>'
            . ( $qr ? '<img class="gf-tessera__qr" src="' . esc_attr( $qr ) . '" alt="QR di verifica">' : '' )
            . '<div class="gf-tessera__body">'
            .   '<p class="gf-tessera__name">' . esc_html( $user->display_name ) . '</p>'
            .   '<p class="gf-tessera__num">socio n° <strong>' . esc_html( $numero ?: '—' ) . '</strong> · ' . esc_html( (string) $year ) . '</p>'
            . '</div>'
            . '</div>';
    }

    /**
     * Colore della tessera in funzione dell'anno: ogni anno una tinta diversa
     * (deterministica). [a] = tinta principale, [b] = variante scura.
     */
    public static function year_palette( int $year ): array {
        // Anno base = 2026 → blu istituzionale GFOSS; gli anni successivi ruotano.
        $base     = 2026;
        $palettes = [
            [ 'a' => '#1A6FA0', 'b' => '#103D58' ], // blu (anno base)
            [ 'a' => '#3E7C3A', 'b' => '#244A22' ], // verde
            [ 'a' => '#0E7C86', 'b' => '#073E43' ], // teal
            [ 'a' => '#3A3F8F', 'b' => '#21244F' ], // indaco
            [ 'a' => '#8A3B4B', 'b' => '#4E2129' ], // vinaccia
            [ 'a' => '#3A4A57', 'b' => '#202A31' ], // ardesia
            [ 'a' => '#5B4B8A', 'b' => '#332A4E' ], // prugna
            [ 'a' => '#9A5A1E', 'b' => '#5A3411' ], // bronzo
        ];
        $n = count( $palettes );
        return $palettes[ ( ( ( $year - $base ) % $n ) + $n ) % $n ];
    }

    private static function qr_data_uri_for( int $user_id, int $size = 220 ): string {
        $url = Verify::url_for( $user_id );
        if ( ! class_exists( '\\Endroid\\QrCode\\Builder\\Builder' ) ) {
            $autoload = GFOSS_MEMBERS_DIR . 'vendor/autoload.php';
            if ( is_file( $autoload ) ) { require_once $autoload; }
        }
        if ( ! class_exists( '\\Endroid\\QrCode\\Builder\\Builder' ) ) {
            return ''; // fallback: nessun QR (la tessera resta valida visivamente)
        }
        try {
            $result = \Endroid\QrCode\Builder\Builder::create()
                ->writer( new \Endroid\QrCode\Writer\PngWriter() )
                ->data( $url )
                ->size( $size )
                ->margin( 6 )
                ->build();
            return $result->getDataUri();
        } catch ( \Throwable $e ) {
            error_log( '[gfoss-members] QR build failed: ' . $e->getMessage() );
            return '';
        }
    }

    /**
     * Logo dell'associazione come data-URI base64 (per mPDF e anteprima HTML).
     * Usa il logo configurato in WordPress (Aspetto → Personalizza → Logo); in
     * fallback l'asset del tema. Stringa vuota se nessuno disponibile → si usa
     * il placeholder SVG.
     */
    private static function logo_data_uri(): string {
        $paths   = [];
        $logo_id = (int) get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $p = get_attached_file( $logo_id );
            if ( $p ) { $paths[] = $p; }
        }
        $paths[] = get_theme_file_path( 'assets/img/logo.png' );

        foreach ( $paths as $path ) {
            if ( $path && is_readable( $path ) ) {
                $type = wp_check_filetype( $path )['type'] ?: 'image/png';
                $bin  = @file_get_contents( $path );
                if ( $bin !== false ) {
                    return 'data:' . $type . ';base64,' . base64_encode( $bin );
                }
            }
        }
        return '';
    }

    /** Mini logo inline per la tessera (placeholder se manca il logo associazione). */
    private static function logo_svg(): string {
        return '<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
             . '<circle cx="20" cy="20" r="14" fill="#F39200"/>'
             . '<rect x="0" y="22" width="40" height="18" fill="#FAFBFC"/>'
             . '<path d="M0 22h40" stroke="#1A6FA0" stroke-width="1.4"/>'
             . '</svg>';
    }
}
