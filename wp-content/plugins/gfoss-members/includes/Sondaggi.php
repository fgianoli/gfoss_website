<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Sondaggi informali tra i soci (NON il voto d'assemblea statutario).
 *
 *   - CPT 'gfoss_sondaggio': domanda (titolo) + opzioni + stato + scadenza.
 *   - Solo soci in regola, un voto per socio (UNIQUE a DB), risultati anonimi.
 *   - Shortcode [gfoss_sondaggi].
 */
class Sondaggi {

    public const CPT = 'gfoss_sondaggio';

    public static function init(): void {
        add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',         [ __CLASS__, 'metabox' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_shortcode( 'gfoss_sondaggi',      [ __CLASS__, 'shortcode' ] );
        add_action( 'admin_post_gfoss_sondaggio_vota', [ __CLASS__, 'handle_vote' ] );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Sondaggi',
                'singular_name' => 'Sondaggio',
                'add_new'       => 'Aggiungi sondaggio',
                'add_new_item'  => 'Nuovo sondaggio',
                'edit_item'     => 'Modifica sondaggio',
                'menu_name'     => 'Sondaggi',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'gfoss-associazione',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'capabilities'    => [
                'edit_posts'        => Roles::CAP_MANAGE_SOCI,
                'edit_others_posts' => Roles::CAP_MANAGE_SOCI,
                'publish_posts'     => Roles::CAP_MANAGE_SOCI,
            ],
            'supports'        => [ 'title', 'editor' ],
            'rewrite'         => false,
            'menu_icon'       => 'dashicons-chart-pie',
        ] );
    }

    /** @return string[] opzioni del sondaggio */
    public static function options( int $id ): array {
        $raw = (string) get_post_meta( $id, '_gf_sond_opzioni', true );
        $arr = array_filter( array_map( 'trim', explode( "\n", $raw ) ), static fn( $s ) => $s !== '' );
        return array_values( $arr );
    }

    public static function is_open( int $id ): bool {
        if ( (string) get_post_meta( $id, '_gf_sond_stato', true ) !== 'aperto' ) { return false; }
        $d = (string) get_post_meta( $id, '_gf_sond_scadenza', true );
        return ! $d || strtotime( $d . ' 23:59:59' ) >= time();
    }

    public static function has_voted( int $id, int $uid ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . Schema::table_voti() . ' WHERE sondaggio_id = %d AND user_id = %d', $id, $uid
        ) );
    }

    /** @return array<int,int> opzione_idx => conteggio */
    public static function counts( int $id ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT opzione, COUNT(*) AS n FROM ' . Schema::table_voti() . ' WHERE sondaggio_id = %d GROUP BY opzione', $id
        ), ARRAY_A ) ?: [];
        $out = [];
        foreach ( $rows as $r ) { $out[ (int) $r['opzione'] ] = (int) $r['n']; }
        return $out;
    }

    // --- admin ------------------------------------------------------------
    public static function metabox(): void {
        add_meta_box( 'gfoss_sond_meta', 'Opzioni e stato', [ __CLASS__, 'render_meta' ], self::CPT, 'normal', 'high' );
    }

    public static function render_meta( \WP_Post $post ): void {
        $opz  = (string) get_post_meta( $post->ID, '_gf_sond_opzioni', true );
        $stato= (string) get_post_meta( $post->ID, '_gf_sond_stato', true ) ?: 'aperto';
        $scad = (string) get_post_meta( $post->ID, '_gf_sond_scadenza', true );
        wp_nonce_field( 'gfoss_sond_meta_' . $post->ID, '_gfoss_sond_nonce' );
        ?>
        <p class="description">La <strong>domanda</strong> è il titolo qui sopra. Inserisci le <strong>opzioni</strong>, una per riga.</p>
        <p><label><strong>Opzioni di risposta</strong> (una per riga)</label><br>
            <textarea name="gf_sond_opzioni" rows="5" class="large-text" placeholder="Sì&#10;No&#10;Non so"><?php echo esc_textarea( $opz ); ?></textarea></p>
        <p style="display:flex;gap:1.5rem;flex-wrap:wrap">
            <label><strong>Stato</strong><br>
                <select name="gf_sond_stato">
                    <option value="aperto" <?php selected( $stato, 'aperto' ); ?>>Aperto</option>
                    <option value="chiuso" <?php selected( $stato, 'chiuso' ); ?>>Chiuso</option>
                </select></label>
            <label><strong>Scadenza</strong> <small>(facoltativa)</small><br>
                <input type="date" name="gf_sond_scadenza" value="<?php echo esc_attr( $scad ); ?>"></label>
        </p>
        <?php
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_sond_nonce'] ) || ! wp_verify_nonce( $_POST['_gfoss_sond_nonce'], 'gfoss_sond_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return; }
        update_post_meta( $post_id, '_gf_sond_opzioni',  sanitize_textarea_field( wp_unslash( $_POST['gf_sond_opzioni'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_sond_stato',    sanitize_key( $_POST['gf_sond_stato'] ?? 'aperto' ) );
        update_post_meta( $post_id, '_gf_sond_scadenza', sanitize_text_field( wp_unslash( $_POST['gf_sond_scadenza'] ?? '' ) ) );
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) { $new['gf_sond_stato'] = 'Stato'; $new['gf_sond_voti'] = 'Voti'; }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gf_sond_stato' ) { echo esc_html( self::is_open( $post_id ) ? 'Aperto' : 'Chiuso' ); }
        if ( $col === 'gf_sond_voti' )  { echo (int) array_sum( self::counts( $post_id ) ); }
    }

    // --- voto -------------------------------------------------------------
    public static function handle_vote(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'login richiesto', 403 ); }
        $id = (int) ( $_POST['sondaggio'] ?? 0 );
        check_admin_referer( 'gfoss_sondaggio_' . $id );
        $uid  = get_current_user_id();
        $back = wp_get_referer() ?: home_url( '/area-soci/' );

        $in_regola = gfoss_members_is_socio( $uid )
            && in_array( Quote::status_for( $uid, (int) gmdate( 'Y' ) ), [ 'paid', 'expiring' ], true );
        $opz = (int) ( $_POST['opzione'] ?? -1 );
        $valid = $opz >= 0 && $opz < count( self::options( $id ) );

        if ( ! $in_regola || ! self::is_open( $id ) || ! $valid ) {
            wp_safe_redirect( add_query_arg( 'sond', 'errore', $back ) ); exit;
        }
        global $wpdb;
        // UNIQUE(sondaggio_id,user_id) garantisce un solo voto: l'insert duplicato fallisce.
        $wpdb->query( $wpdb->prepare(
            'INSERT IGNORE INTO ' . Schema::table_voti() . ' (sondaggio_id, user_id, opzione) VALUES (%d, %d, %d)',
            $id, $uid, $opz
        ) );
        wp_safe_redirect( add_query_arg( 'sond', 'votato', $back ) ); exit;
    }

    // --- frontend ---------------------------------------------------------
    public static function shortcode( $atts = [] ): string {
        if ( ! is_user_logged_in() || ! gfoss_members_is_socio( get_current_user_id() ) ) {
            return '<div class="gf-card gf-card--warn">I sondaggi sono riservati ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }
        $uid = get_current_user_id();
        $msg = isset( $_GET['sond'] ) ? sanitize_key( (string) $_GET['sond'] ) : '';
        $polls = get_posts( [ 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ] );

        ob_start();
        echo '<div class="gf-sondaggi">';
        if ( $msg === 'votato' ) { echo '<div class="gf-card gf-card--ok">Grazie, voto registrato!</div>'; }
        if ( $msg === 'errore' ) { echo '<div class="gf-card gf-card--warn">Voto non registrato (sondaggio chiuso, già votato o opzione non valida).</div>'; }

        if ( ! $polls ) { echo '<p class="gf-muted">Nessun sondaggio al momento.</p></div>'; return ob_get_clean(); }

        foreach ( $polls as $p ) {
            $opts   = self::options( $p->ID );
            if ( ! $opts ) { continue; }
            $open   = self::is_open( $p->ID );
            $voted  = self::has_voted( $p->ID, $uid );
            $counts = self::counts( $p->ID );
            $total  = array_sum( $counts );

            echo '<article class="gf-evento"><h3>' . esc_html( $p->post_title ) . '</h3>';
            if ( $p->post_content ) { echo '<div class="gf-evento__desc">' . wp_kses_post( wpautop( $p->post_content ) ) . '</div>'; }

            if ( $open && ! $voted ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'gfoss_sondaggio_' . $p->ID );
                echo '<input type="hidden" name="action" value="gfoss_sondaggio_vota"><input type="hidden" name="sondaggio" value="' . (int) $p->ID . '">';
                foreach ( $opts as $i => $label ) {
                    echo '<label class="gf-check"><input type="radio" name="opzione" value="' . (int) $i . '" required> ' . esc_html( $label ) . '</label>';
                }
                echo '<p><button type="submit" class="gf-btn gf-btn--orange">Vota</button></p></form>';
            } else {
                // Risultati.
                echo '<div class="gf-poll-results">';
                foreach ( $opts as $i => $label ) {
                    $n   = $counts[ $i ] ?? 0;
                    $pct = $total > 0 ? (int) round( $n / $total * 100 ) : 0;
                    echo '<div class="gf-poll-row"><div class="gf-poll-row__top"><span>' . esc_html( $label ) . '</span><strong>' . $pct . '% (' . (int) $n . ')</strong></div>';
                    echo '<div class="gf-cf__bar"><span style="width:' . $pct . '%"></span></div></div>';
                }
                echo '<p class="gf-muted">' . (int) $total . ' voti' . ( $voted ? ' · hai già votato' : '' ) . ( $open ? '' : ' · sondaggio chiuso' ) . '</p>';
                echo '</div>';
            }
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
