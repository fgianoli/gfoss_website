<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Convocazioni d'assemblea + gestione deleghe (propedeutico al voto online).
 *
 *   - CPT 'gfoss_convocazione' gestito da admin/CD (data, luogo, tipo, modalità, o.d.g.)
 *   - Shortcode [gfoss_convocazioni] per i soci loggati: dettagli + gestione delega
 *   - Delega: un socio in regola può delegare un altro socio per una convocazione.
 *     Vincolo statutario (art. 11): ogni socio può rappresentare al massimo 3 deleghe.
 */
class Convocazioni {

    public const CPT = 'gfoss_convocazione';
    public const MAX_DELEGHE = 3;

    public static function init(): void {
        add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',         [ __CLASS__, 'metabox' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_shortcode( 'gfoss_convocazioni',  [ __CLASS__, 'shortcode' ] );
        add_action( 'admin_post_gfoss_delega', [ __CLASS__, 'handle_delega' ] );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Convocazioni',
                'singular_name' => 'Convocazione',
                'add_new'       => 'Aggiungi convocazione',
                'add_new_item'  => 'Nuova convocazione',
                'edit_item'     => 'Modifica convocazione',
                'menu_name'     => 'Convocazioni',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'gfoss-associazione',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'capabilities'    => [
                'edit_posts'        => Roles::CAP_MANAGE_ASSEMBLEE,
                'edit_others_posts' => Roles::CAP_MANAGE_ASSEMBLEE,
                'publish_posts'     => Roles::CAP_MANAGE_ASSEMBLEE,
            ],
            'supports'        => [ 'title' ],
            'rewrite'         => false,
            'menu_icon'       => 'dashicons-bank',
        ] );
    }

    public static function metabox(): void {
        add_meta_box( 'gfoss_conv_meta', 'Dettagli convocazione', [ __CLASS__, 'render_metabox' ], self::CPT, 'normal', 'high' );
        add_meta_box( 'gfoss_conv_deleghe', 'Deleghe ricevute', [ __CLASS__, 'render_deleghe_admin' ], self::CPT, 'side', 'default' );
    }

    public static function render_metabox( \WP_Post $post ): void {
        $data  = (string) get_post_meta( $post->ID, '_gf_cv_data', true );
        $luogo = (string) get_post_meta( $post->ID, '_gf_cv_luogo', true );
        $tipo  = (string) get_post_meta( $post->ID, '_gf_cv_tipo', true ) ?: 'ordinaria';
        $mod   = (string) get_post_meta( $post->ID, '_gf_cv_modalita', true ) ?: 'presenza';
        $odg   = (string) get_post_meta( $post->ID, '_gf_cv_odg', true );
        wp_nonce_field( 'gfoss_conv_meta_' . $post->ID, '_gfoss_conv_nonce' );
        ?>
        <p><label><strong>Data e ora</strong></label><br>
            <input type="datetime-local" name="gf_cv_data" value="<?php echo esc_attr( $data ); ?>"></p>
        <p><label><strong>Luogo</strong></label>
            <input type="text" name="gf_cv_luogo" value="<?php echo esc_attr( $luogo ); ?>" class="widefat" placeholder="es. Padova / piattaforma online"></p>
        <p><label><strong>Tipo</strong></label>
            <select name="gf_cv_tipo">
                <option value="ordinaria" <?php selected( $tipo, 'ordinaria' ); ?>>Ordinaria</option>
                <option value="straordinaria" <?php selected( $tipo, 'straordinaria' ); ?>>Straordinaria</option>
            </select>
            <label style="margin-left:1rem"><strong>Modalità</strong></label>
            <select name="gf_cv_modalita">
                <option value="presenza" <?php selected( $mod, 'presenza' ); ?>>In presenza</option>
                <option value="online" <?php selected( $mod, 'online' ); ?>>Online</option>
                <option value="mista" <?php selected( $mod, 'mista' ); ?>>Mista</option>
            </select>
        </p>
        <p><label><strong>Ordine del giorno</strong></label><br>
            <textarea name="gf_cv_odg" rows="6" class="widefat" placeholder="Un punto per riga"><?php echo esc_textarea( $odg ); ?></textarea></p>
        <?php
    }

    public static function render_deleghe_admin( \WP_Post $post ): void {
        $deleghe = self::deleghe( $post->ID );
        if ( ! $deleghe ) { echo '<p>Nessuna delega.</p>'; return; }
        echo '<ul>';
        foreach ( $deleghe as $delegante => $delegato ) {
            $a = get_userdata( (int) $delegante ); $b = get_userdata( (int) $delegato );
            echo '<li>' . esc_html( $a ? $a->display_name : "#$delegante" ) . ' → <strong>' . esc_html( $b ? $b->display_name : "#$delegato" ) . '</strong></li>';
        }
        echo '</ul>';
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_conv_nonce'] )
             || ! wp_verify_nonce( $_POST['_gfoss_conv_nonce'], 'gfoss_conv_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_ASSEMBLEE ) ) { return; }
        update_post_meta( $post_id, '_gf_cv_data',     sanitize_text_field( wp_unslash( $_POST['gf_cv_data'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_cv_luogo',    sanitize_text_field( wp_unslash( $_POST['gf_cv_luogo'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_cv_tipo',     sanitize_key( $_POST['gf_cv_tipo'] ?? 'ordinaria' ) );
        update_post_meta( $post_id, '_gf_cv_modalita', sanitize_key( $_POST['gf_cv_modalita'] ?? 'presenza' ) );
        update_post_meta( $post_id, '_gf_cv_odg',      sanitize_textarea_field( wp_unslash( $_POST['gf_cv_odg'] ?? '' ) ) );
    }

    /** @return array<int,int> delegante_id => delegato_id */
    public static function deleghe( int $conv_id ): array {
        $v = get_post_meta( $conv_id, '_gfoss_deleghe', true );
        return is_array( $v ) ? $v : [];
    }

    private static function count_deleghe_for( int $conv_id, int $delegato_id ): int {
        return count( array_filter( self::deleghe( $conv_id ), static fn( $d ) => (int) $d === $delegato_id ) );
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) { $new['gf_cv_data'] = 'Data'; $new['gf_cv_deleghe'] = 'Deleghe'; }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gf_cv_data' ) {
            $d = (string) get_post_meta( $post_id, '_gf_cv_data', true );
            echo $d ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $d ) ) ) : '—';
        }
        if ( $col === 'gf_cv_deleghe' ) { echo esc_html( (string) count( self::deleghe( $post_id ) ) ); }
    }

    // ---------------------------------------------------------------------

    public static function handle_delega(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'login richiesto', 403 ); }
        $conv_id = (int) ( $_POST['convocazione'] ?? 0 );
        $azione  = sanitize_key( (string) ( $_POST['azione'] ?? 'delega' ) );
        check_admin_referer( 'gfoss_delega_' . $conv_id );

        $me   = get_current_user_id();
        $back = wp_get_referer() ?: home_url( '/area-soci/' );
        $post = get_post( $conv_id );
        if ( ! $post || $post->post_type !== self::CPT || ! self::in_regola( $me ) ) {
            wp_safe_redirect( add_query_arg( 'dlg', 'errore', $back ) ); exit;
        }

        $deleghe = self::deleghe( $conv_id );

        if ( $azione === 'revoca' ) {
            unset( $deleghe[ $me ] );
            update_post_meta( $conv_id, '_gfoss_deleghe', $deleghe );
            wp_safe_redirect( add_query_arg( 'dlg', 'revocata', $back ) ); exit;
        }

        $delegato = (int) ( $_POST['delegato'] ?? 0 );
        if ( ! $delegato || $delegato === $me || ! self::in_regola( $delegato ) ) {
            wp_safe_redirect( add_query_arg( 'dlg', 'invalido', $back ) ); exit;
        }
        // Vincolo: max 3 deleghe per delegato (art. 11).
        $current = self::count_deleghe_for( $conv_id, $delegato ) - ( ( isset( $deleghe[ $me ] ) && (int) $deleghe[ $me ] === $delegato ) ? 1 : 0 );
        if ( $current >= self::MAX_DELEGHE ) {
            wp_safe_redirect( add_query_arg( 'dlg', 'limite', $back ) ); exit;
        }
        $deleghe[ $me ] = $delegato;
        update_post_meta( $conv_id, '_gfoss_deleghe', $deleghe );
        wp_safe_redirect( add_query_arg( 'dlg', 'ok', $back ) ); exit;
    }

    private static function in_regola( int $uid ): bool {
        return gfoss_members_is_socio( $uid )
            && in_array( Quote::status_for( $uid, (int) gmdate( 'Y' ) ), [ 'paid', 'expiring' ], true );
    }

    public static function shortcode( $atts = [] ): string {
        if ( ! is_user_logged_in() || ! gfoss_members_is_socio( get_current_user_id() ) ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }
        $me     = get_current_user_id();
        $regola = self::in_regola( $me );
        $now    = time();
        $msg    = isset( $_GET['dlg'] ) ? sanitize_key( (string) $_GET['dlg'] ) : '';

        $convs = get_posts( [ 'post_type' => self::CPT, 'posts_per_page' => -1, 'post_status' => 'publish' ] );
        // Solo future (o senza data).
        $convs = array_filter( $convs, static function ( $c ) use ( $now ) {
            $d = (string) get_post_meta( $c->ID, '_gf_cv_data', true );
            return ! $d || strtotime( $d ) >= $now - DAY_IN_SECONDS;
        } );

        ob_start();
        echo '<div class="gf-convocazioni">';

        $notes = [
            'ok' => [ 'ok', 'Delega registrata.' ], 'revocata' => [ 'warn', 'Delega revocata.' ],
            'limite' => [ 'warn', 'Il socio scelto ha già 3 deleghe (massimo statutario).' ],
            'invalido' => [ 'warn', 'Delega non valida.' ], 'errore' => [ 'warn', 'Operazione non riuscita.' ],
        ];
        if ( $msg && isset( $notes[ $msg ] ) ) {
            echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>';
        }

        if ( ! $convs ) { echo '<p class="gf-muted">Nessuna convocazione in programma.</p></div>'; return ob_get_clean(); }

        // Elenco soci in regola per la delega (escluso me).
        $soci = get_users( [ 'role__in' => [ 'gfoss_socio','gfoss_consigliere','gfoss_presidente','gfoss_tesoriere','gfoss_revisore','gfoss_comunicazione','gfoss_segreteria' ], 'orderby' => 'display_name' ] );

        foreach ( $convs as $c ) {
            $d    = (string) get_post_meta( $c->ID, '_gf_cv_data', true );
            $luo  = (string) get_post_meta( $c->ID, '_gf_cv_luogo', true );
            $tipo = (string) get_post_meta( $c->ID, '_gf_cv_tipo', true );
            $mod  = (string) get_post_meta( $c->ID, '_gf_cv_modalita', true );
            $odg  = (string) get_post_meta( $c->ID, '_gf_cv_odg', true );
            $deleghe = self::deleghe( $c->ID );
            $mia  = $deleghe[ $me ] ?? 0;

            echo '<article class="gf-evento">';
            echo '<h3>' . esc_html( $c->post_title ) . ' <small class="gf-muted">(' . esc_html( ucfirst( $tipo ) ) . ')</small></h3>';
            echo '<p class="gf-evento__meta">';
            if ( $d )   { echo '📅 ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $d ) ) ) . ' '; }
            if ( $luo ) { echo '📍 ' . esc_html( $luo ) . ' '; }
            echo '· ' . esc_html( ucfirst( $mod ) ) . '</p>';
            if ( $odg ) {
                echo '<details><summary>Ordine del giorno</summary><div>' . nl2br( esc_html( $odg ) ) . '</div></details>';
            }

            // Gestione delega.
            if ( ! $regola ) {
                echo '<p class="gf-muted">Per delegare devi essere in regola con la quota.</p>';
            } elseif ( $mia ) {
                $b = get_userdata( (int) $mia );
                echo '<p>Hai delegato: <strong>' . esc_html( $b ? $b->display_name : '' ) . '</strong></p>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'gfoss_delega_' . $c->ID );
                echo '<input type="hidden" name="action" value="gfoss_delega"><input type="hidden" name="convocazione" value="' . (int) $c->ID . '"><input type="hidden" name="azione" value="revoca">';
                echo '<button class="gf-btn gf-btn--ghost" type="submit">Revoca delega</button></form>';
            } else {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gf-delega-form">';
                wp_nonce_field( 'gfoss_delega_' . $c->ID );
                echo '<input type="hidden" name="action" value="gfoss_delega"><input type="hidden" name="convocazione" value="' . (int) $c->ID . '"><input type="hidden" name="azione" value="delega">';
                echo '<label>Delega un altro socio: <select name="delegato"><option value="">— scegli —</option>';
                foreach ( $soci as $s ) {
                    if ( (int) $s->ID === $me ) { continue; }
                    echo '<option value="' . (int) $s->ID . '">' . esc_html( $s->display_name ) . '</option>';
                }
                echo '</select></label> <button class="gf-btn gf-btn--orange" type="submit">Delega</button>';
                echo '<p class="gf-muted">Ogni socio può rappresentare al massimo ' . (int) self::MAX_DELEGHE . ' deleghe (art. 11 Statuto).</p>';
                echo '</form>';
            }
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
