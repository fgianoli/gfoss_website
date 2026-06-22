<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Campagne di crowdfunding per progetti (art. 5 Statuto: raccolta fondi per
 * progetti/comunità). Modello keep-it-all: i fondi sono acquisiti, niente
 * rimborsi automatici; se l'obiettivo non è raggiunto si usano per il progetto
 * o per finalità analoghe (dichiarato in pagina, art. 7 CTS).
 *
 *   - CPT 'gfoss_progetto' (obiettivo €, scadenza modificabile, stato, nota).
 *   - Donazioni aperte a tutti via PayPal (importo libero) o bonifico.
 *   - Consenso a mostrare il nome raccolto nel form, prima del pagamento.
 */
class Progetti {

    public const CPT = 'gfoss_progetto';

    public static function init(): void {
        add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',         [ __CLASS__, 'metaboxes' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_shortcode( 'gfoss_progetti',      [ __CLASS__, 'shortcode' ] );
        add_filter( 'the_content',            [ __CLASS__, 'single_content' ], 18 );
        add_action( 'admin_post_nopriv_gfoss_dona', [ __CLASS__, 'handle_donate' ] );
        add_action( 'admin_post_gfoss_dona',        [ __CLASS__, 'handle_donate' ] );
        add_action( 'admin_post_gfoss_dona_manual', [ __CLASS__, 'handle_manual' ] );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Progetti',
                'singular_name' => 'Progetto',
                'add_new'       => 'Aggiungi progetto',
                'add_new_item'  => 'Nuova campagna',
                'edit_item'     => 'Modifica progetto',
                'menu_name'     => 'Progetti (crowdfunding)',
            ],
            'public'          => true,
            'has_archive'     => false,
            'show_ui'         => true,
            'show_in_menu'    => 'gfoss-associazione',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'capabilities'    => [
                'edit_posts'        => Roles::CAP_MANAGE_SOCI,
                'edit_others_posts' => Roles::CAP_MANAGE_SOCI,
                'publish_posts'     => Roles::CAP_MANAGE_SOCI,
            ],
            'supports'        => [ 'title', 'editor', 'thumbnail' ],
            'rewrite'         => [ 'slug' => 'progetto' ],
            'menu_icon'       => 'dashicons-heart',
        ] );
    }

    // --- meta helpers -----------------------------------------------------
    public static function goal( int $id ): float    { return (float) get_post_meta( $id, '_gf_pr_obiettivo', true ); }
    public static function deadline( int $id ): string { return (string) get_post_meta( $id, '_gf_pr_scadenza', true ); }
    public static function status( int $id ): string { return (string) get_post_meta( $id, '_gf_pr_stato', true ) ?: 'attiva'; }

    public static function is_open( int $id ): bool {
        if ( self::status( $id ) !== 'attiva' ) { return false; }
        $d = self::deadline( $id );
        return ! $d || strtotime( $d . ' 23:59:59' ) >= time();
    }

    // --- admin metaboxes --------------------------------------------------
    public static function metaboxes(): void {
        add_meta_box( 'gfoss_pr_meta', 'Campagna', [ __CLASS__, 'render_meta' ], self::CPT, 'side', 'high' );
        add_meta_box( 'gfoss_pr_don',  'Donazioni', [ __CLASS__, 'render_don' ], self::CPT, 'normal', 'default' );
    }

    public static function render_meta( \WP_Post $post ): void {
        $goal = (string) get_post_meta( $post->ID, '_gf_pr_obiettivo', true );
        $scad = self::deadline( $post->ID );
        $stato= self::status( $post->ID );
        $nota = (string) get_post_meta( $post->ID, '_gf_pr_nota_mancato', true );
        wp_nonce_field( 'gfoss_pr_meta_' . $post->ID, '_gfoss_pr_nonce' );
        ?>
        <p><label><strong>Obiettivo (€)</strong></label><br>
            <input type="number" name="gf_pr_obiettivo" value="<?php echo esc_attr( $goal ); ?>" step="0.01" min="0" class="widefat"></p>
        <p><label><strong>Scadenza</strong> <small>(modificabile)</small></label><br>
            <input type="date" name="gf_pr_scadenza" value="<?php echo esc_attr( $scad ); ?>" class="widefat"></p>
        <p><label><strong>Stato</strong></label><br>
            <select name="gf_pr_stato" class="widefat">
                <option value="attiva" <?php selected( $stato, 'attiva' ); ?>>Attiva</option>
                <option value="chiusa" <?php selected( $stato, 'chiusa' ); ?>>Chiusa</option>
            </select></p>
        <p><label><strong>Se l'obiettivo non è raggiunto</strong></label><br>
            <textarea name="gf_pr_nota_mancato" rows="3" class="widefat" placeholder="Es. i fondi raccolti saranno destinati al progetto nei limiti del possibile o a finalità analoghe."><?php echo esc_textarea( $nota ); ?></textarea></p>
        <p><label><strong>Rendicontazione</strong> <small>(come sono stati usati i fondi)</small></label><br>
            <?php $rend = (string) get_post_meta( $post->ID, '_gf_pr_rendiconto', true ); ?>
            <textarea name="gf_pr_rendiconto" rows="4" class="widefat" placeholder="Pubblicato in pagina per trasparenza verso i donatori (art. 7 CTS). Es. dettaglio delle spese coperte dalla raccolta."><?php echo esc_textarea( $rend ); ?></textarea></p>
        <?php
    }

    public static function render_don( \WP_Post $post ): void {
        $raised = Donazioni::raised( $post->ID );
        $goal   = self::goal( $post->ID );
        echo '<p><strong>Raccolto: ' . esc_html( number_format_i18n( $raised, 2 ) ) . ' €</strong>'
           . ( $goal > 0 ? ' su ' . esc_html( number_format_i18n( $goal, 2 ) ) . ' € (' . (int) round( $raised / $goal * 100 ) . '%)' : '' )
           . ' · ' . (int) Donazioni::count_paid( $post->ID ) . ' donazioni</p>';

        echo '<h4>Registra donazione (bonifico/contanti)</h4>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:end">';
        wp_nonce_field( 'gfoss_dona_manual_' . $post->ID );
        echo '<input type="hidden" name="action" value="gfoss_dona_manual"><input type="hidden" name="progetto" value="' . (int) $post->ID . '">';
        echo '<label>Donatore<br><input type="text" name="nome" required></label>';
        echo '<label>Email<br><input type="email" name="email"></label>';
        echo '<label>Importo €<br><input type="number" name="importo" step="0.01" min="0" required style="width:90px"></label>';
        echo '<label>Metodo<br><select name="metodo"><option value="bonifico">Bonifico</option><option value="contanti">Contanti</option></select></label>';
        echo '<label><input type="checkbox" name="mostra_nome" value="1"> mostra nome</label>';
        echo '<button class="button">Aggiungi</button></form>';

        $list = Donazioni::list_for_project( $post->ID );
        if ( $list ) {
            echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>Data</th><th>Donatore</th><th>Importo</th><th>Metodo</th><th>Nome pubblico</th></tr></thead><tbody>';
            foreach ( $list as $d ) {
                echo '<tr><td>' . esc_html( $d['data_pagamento'] ) . '</td><td>' . esc_html( (string) $d['donatore_nome'] ) . '</td><td>'
                   . esc_html( number_format_i18n( (float) $d['importo'], 2 ) ) . ' €</td><td>' . esc_html( $d['metodo'] ) . '</td><td>'
                   . ( $d['mostra_nome'] ? '✓' : '—' ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_pr_nonce'] ) || ! wp_verify_nonce( $_POST['_gfoss_pr_nonce'], 'gfoss_pr_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return; }
        update_post_meta( $post_id, '_gf_pr_obiettivo',    round( (float) ( $_POST['gf_pr_obiettivo'] ?? 0 ), 2 ) );
        update_post_meta( $post_id, '_gf_pr_scadenza',     sanitize_text_field( wp_unslash( $_POST['gf_pr_scadenza'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_pr_stato',        sanitize_key( $_POST['gf_pr_stato'] ?? 'attiva' ) );
        update_post_meta( $post_id, '_gf_pr_nota_mancato', sanitize_textarea_field( wp_unslash( $_POST['gf_pr_nota_mancato'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_pr_rendiconto',   wp_kses_post( wp_unslash( $_POST['gf_pr_rendiconto'] ?? '' ) ) );
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) { $new['gf_pr_raised'] = 'Raccolto'; $new['gf_pr_scad'] = 'Scadenza'; }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gf_pr_raised' ) {
            $g = self::goal( $post_id );
            echo esc_html( number_format_i18n( Donazioni::raised( $post_id ), 2 ) ) . ' €' . ( $g > 0 ? ' / ' . esc_html( number_format_i18n( $g, 2 ) ) : '' );
        }
        if ( $col === 'gf_pr_scad' ) { echo esc_html( self::deadline( $post_id ) ?: '—' ); }
    }

    // --- frontend ---------------------------------------------------------

    private static function progress_bar( int $id ): string {
        $raised = Donazioni::raised( $id );
        $goal   = self::goal( $id );
        $pct    = $goal > 0 ? min( 100, (int) round( $raised / $goal * 100 ) ) : 0;
        $out  = '<div class="gf-cf__bar"><span style="width:' . $pct . '%"></span></div>';
        $out .= '<p class="gf-cf__stats"><strong>' . esc_html( number_format_i18n( $raised, 2 ) ) . ' €</strong>';
        if ( $goal > 0 ) { $out .= ' raccolti su ' . esc_html( number_format_i18n( $goal, 2 ) ) . ' € (' . $pct . '%)'; }
        $out .= ' · ' . (int) Donazioni::count_paid( $id ) . ' sostenitori</p>';
        return $out;
    }

    public static function shortcode( $atts = [] ): string {
        $atts = shortcode_atts( [ 'stato' => 'attiva' ], $atts, 'gfoss_progetti' );
        $q = get_posts( [ 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'menu_order date', 'order' => 'DESC' ] );
        if ( ! $q ) { return '<p class="gf-muted">Nessuna campagna al momento.</p>'; }

        ob_start();
        echo '<div class="gf-cf-grid">';
        foreach ( $q as $p ) {
            $open = self::is_open( $p->ID );
            echo '<article class="gf-cf-card">';
            if ( has_post_thumbnail( $p->ID ) ) {
                echo '<a href="' . esc_url( get_permalink( $p ) ) . '" class="gf-cf-card__img">' . get_the_post_thumbnail( $p->ID, 'medium_large' ) . '</a>';
            }
            echo '<div class="gf-cf-card__body">';
            echo '<h3><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $p->post_title ) . '</a></h3>';
            echo '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( $p->post_content ), 24 ) ) . '</p>';
            echo self::progress_bar( $p->ID );
            echo '<p><a class="gf-btn gf-btn--orange" href="' . esc_url( get_permalink( $p ) ) . '">' . ( $open ? 'Sostieni' : 'Vedi la campagna' ) . '</a></p>';
            echo '</div></article>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /** Sulla pagina singola del progetto: barra, form donazione, sostenitori, nota. */
    public static function single_content( string $content ): string {
        if ( ! is_singular( self::CPT ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
        $id   = (int) get_the_ID();
        $open = self::is_open( $id );
        $msg  = isset( $_GET['dona'] ) ? sanitize_key( (string) $_GET['dona'] ) : '';

        ob_start();
        echo '<div class="gf-cf">';
        if ( $msg === 'grazie' ) { echo '<div class="gf-card gf-card--ok">Grazie per il tuo sostegno! La donazione sarà visibile appena confermata.</div>'; }
        if ( $msg === 'errore' ) { echo '<div class="gf-card gf-card--warn">Qualcosa non ha funzionato. Riprova o scrivici a info@gfoss.it.</div>'; }

        echo self::progress_bar( $id );

        if ( $open ) {
            $iban = defined( 'GFOSS_ASSOC_IBAN' ) ? GFOSS_ASSOC_IBAN : '';
            echo '<div class="gf-cf__donate">';
            echo '<h3>Sostieni questo progetto</h3>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gf-form">';
            wp_nonce_field( 'gfoss_dona_' . $id );
            echo '<input type="hidden" name="action" value="gfoss_dona"><input type="hidden" name="progetto" value="' . $id . '">';
            echo '<div class="gf-grid">';
            echo '<label class="gf-field"><span class="gf-field__lbl">Nome</span><input type="text" name="nome" required></label>';
            echo '<label class="gf-field"><span class="gf-field__lbl">Email</span><input type="email" name="email" required></label>';
            echo '<label class="gf-field"><span class="gf-field__lbl">Importo €</span><input type="number" name="importo" min="1" step="1" value="20" required></label>';
            echo '</div>';
            echo '<label class="gf-check"><input type="checkbox" name="mostra_nome" value="1"> Mostra il mio nome tra i sostenitori</label>';
            echo '<label class="gf-check"><input type="checkbox" name="privacy" value="1" required> Ho letto l\'<a href="' . esc_url( home_url( '/privacy/' ) ) . '">informativa privacy</a></label>';
            echo '<p><button type="submit" class="gf-btn gf-btn--orange gf-btn--lg">Dona con PayPal</button></p>';
            echo '</form>';
            if ( $iban ) {
                echo '<details><summary>Preferisci il bonifico?</summary><p>IBAN <code>' . esc_html( $iban ) . '</code> — causale «Donazione ' . esc_html( get_the_title( $id ) ) . '». Inviaci la conferma a <a href="mailto:info@gfoss.it">info@gfoss.it</a>.</p></details>';
            }
            echo '</div>';
        } else {
            echo '<div class="gf-card">Questa campagna è chiusa. Grazie a chi l\'ha sostenuta!</div>';
        }

        // Nota "se non raggiunto".
        $nota = (string) get_post_meta( $id, '_gf_pr_nota_mancato', true );
        if ( $nota ) { echo '<p class="gf-muted" style="margin-top:1rem"><em>' . esc_html( $nota ) . '</em></p>'; }

        // Rendicontazione pubblica: come sono stati usati i fondi (trasparenza, art. 7 CTS).
        $rend = (string) get_post_meta( $id, '_gf_pr_rendiconto', true );
        if ( $rend ) {
            echo '<div class="gf-cf__rendiconto" style="margin-top:1.5rem"><h3>Rendicontazione</h3>' . wp_kses_post( wpautop( $rend ) ) . '</div>';
        }

        // Sostenitori (con consenso).
        $sup = Donazioni::supporters( $id );
        if ( $sup ) {
            echo '<div class="gf-cf__supporters"><h3>Sostenitori</h3><ul class="gf-doclist">';
            foreach ( $sup as $s ) {
                echo '<li><strong>' . esc_html( (string) $s['donatore_nome'] ) . '</strong>';
                if ( ! empty( $s['messaggio'] ) ) { echo ' <span class="gf-muted">— ' . esc_html( $s['messaggio'] ) . '</span>'; }
                echo '</li>';
            }
            echo '</ul></div>';
        }

        echo '</div>';
        return $content . ob_get_clean();
    }

    // --- handlers ---------------------------------------------------------

    public static function handle_donate(): void {
        $id = (int) ( $_POST['progetto'] ?? 0 );
        check_admin_referer( 'gfoss_dona_' . $id );
        $back = get_permalink( $id ) ?: home_url( '/' );

        if ( ! self::is_open( $id ) || empty( $_POST['privacy'] ) ) {
            wp_safe_redirect( add_query_arg( 'dona', 'errore', $back ) ); exit;
        }
        $importo = round( (float) ( $_POST['importo'] ?? 0 ), 2 );
        $nome    = sanitize_text_field( wp_unslash( $_POST['nome'] ?? '' ) );
        $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( $importo < 1 || $nome === '' ) {
            wp_safe_redirect( add_query_arg( 'dona', 'errore', $back ) ); exit;
        }
        $don = Donazioni::create_pending( $id, $importo, $nome, $email, ! empty( $_POST['mostra_nome'] ) );

        $bid = defined( 'GFOSS_PAYPAL_BUTTON_ID' ) ? GFOSS_PAYPAL_BUTTON_ID : '';
        $params = http_build_query( [
            'hosted_button_id' => $bid,
            'amount'           => number_format( $importo, 2, '.', '' ),
            'currency_code'    => 'EUR',
            'item_name'        => 'Donazione: ' . get_the_title( $id ),
            'custom'           => 'dona_' . $don['token'],
            'no_shipping'      => 1,
            'return'           => add_query_arg( 'dona', 'grazie', $back ),
            'cancel_return'    => add_query_arg( 'dona', 'annullata', $back ),
            'notify_url'       => rest_url( 'gfoss/v1/paypal-ipn' ),
        ] );
        wp_redirect( 'https://www.paypal.com/donate/?' . $params );
        exit;
    }

    public static function handle_manual(): void {
        $id = (int) ( $_POST['progetto'] ?? 0 );
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { wp_die( 'forbidden', 403 ); }
        check_admin_referer( 'gfoss_dona_manual_' . $id );
        $importo = round( (float) ( $_POST['importo'] ?? 0 ), 2 );
        if ( $id && $importo > 0 ) {
            Donazioni::record_manual(
                $id, $importo,
                sanitize_text_field( wp_unslash( $_POST['nome'] ?? '' ) ),
                sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
                ! empty( $_POST['mostra_nome'] ),
                sanitize_key( $_POST['metodo'] ?? 'bonifico' )
            );
        }
        wp_safe_redirect( get_edit_post_link( $id, '' ) ?: admin_url() );
        exit;
    }
}
