<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Eventi / workshop con iscrizione dei soci.
 *
 *   - CPT 'gfoss_evento' gestito da admin/CD
 *   - Meta: data/ora, luogo, scadenza iscrizioni, posti massimi
 *   - Iscrizione: i soci loggati si iscrivono/cancellano (post meta _gfoss_iscritti)
 *   - Shortcode [gfoss_eventi] elenca i prossimi eventi con pulsante iscrizione
 */
class Eventi {

    public const CPT = 'gfoss_evento';

    public static function init(): void {
        add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',         [ __CLASS__, 'metabox' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_shortcode( 'gfoss_eventi',        [ __CLASS__, 'shortcode' ] );
        add_action( 'admin_post_gfoss_evento_iscrizione', [ __CLASS__, 'handle_iscrizione' ] );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Eventi',
                'singular_name' => 'Evento',
                'add_new'       => 'Aggiungi evento',
                'add_new_item'  => 'Nuovo evento',
                'edit_item'     => 'Modifica evento',
                'menu_name'     => 'Eventi',
            ],
            'public'             => true,
            'has_archive'        => false,
            'publicly_queryable' => false, // niente single: il dettaglio è nello shortcode
            'show_ui'            => true,
            'show_in_menu'       => 'gfoss-associazione',
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'capabilities'       => [
                'edit_posts'        => Roles::CAP_MANAGE_SOCI,
                'edit_others_posts' => Roles::CAP_MANAGE_SOCI,
                'publish_posts'     => Roles::CAP_MANAGE_SOCI,
            ],
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'menu_icon'          => 'dashicons-calendar-alt',
        ] );
    }

    public static function metabox(): void {
        add_meta_box( 'gfoss_evento_meta', 'Dettagli evento', [ __CLASS__, 'render_metabox' ], self::CPT, 'side', 'high' );
        add_meta_box( 'gfoss_evento_iscritti', 'Iscritti', [ __CLASS__, 'render_iscritti' ], self::CPT, 'normal', 'default' );
    }

    public static function render_metabox( \WP_Post $post ): void {
        $data    = (string) get_post_meta( $post->ID, '_gf_ev_data', true );
        $luogo   = (string) get_post_meta( $post->ID, '_gf_ev_luogo', true );
        $scad    = (string) get_post_meta( $post->ID, '_gf_ev_scadenza', true );
        $posti   = (string) get_post_meta( $post->ID, '_gf_ev_posti', true );
        wp_nonce_field( 'gfoss_evento_meta_' . $post->ID, '_gfoss_evento_nonce' );
        ?>
        <p><label><strong>Data e ora</strong></label><br>
            <input type="datetime-local" name="gf_ev_data" value="<?php echo esc_attr( $data ); ?>" class="widefat"></p>
        <p><label><strong>Luogo</strong></label><br>
            <input type="text" name="gf_ev_luogo" value="<?php echo esc_attr( $luogo ); ?>" class="widefat" placeholder="es. Padova / Online"></p>
        <p><label><strong>Scadenza iscrizioni</strong></label><br>
            <input type="date" name="gf_ev_scadenza" value="<?php echo esc_attr( $scad ); ?>" class="widefat"></p>
        <p><label><strong>Posti (0 = illimitati)</strong></label><br>
            <input type="number" name="gf_ev_posti" value="<?php echo esc_attr( $posti ); ?>" class="widefat" min="0"></p>
        <?php
    }

    public static function render_iscritti( \WP_Post $post ): void {
        $ids = self::iscritti( $post->ID );
        if ( ! $ids ) { echo '<p>Nessun iscritto.</p>'; return; }
        echo '<ol>';
        foreach ( $ids as $uid ) {
            $u = get_userdata( $uid );
            if ( $u ) { echo '<li>' . esc_html( $u->display_name ) . ' — ' . esc_html( $u->user_email ) . '</li>'; }
        }
        echo '</ol>';
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_evento_nonce'] )
             || ! wp_verify_nonce( $_POST['_gfoss_evento_nonce'], 'gfoss_evento_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return; }
        update_post_meta( $post_id, '_gf_ev_data',     sanitize_text_field( wp_unslash( $_POST['gf_ev_data'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_ev_luogo',    sanitize_text_field( wp_unslash( $_POST['gf_ev_luogo'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_ev_scadenza', sanitize_text_field( wp_unslash( $_POST['gf_ev_scadenza'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_ev_posti',    (int) ( $_POST['gf_ev_posti'] ?? 0 ) );
    }

    /** @return int[] */
    public static function iscritti( int $event_id ): array {
        $v = get_post_meta( $event_id, '_gfoss_iscritti', true );
        return is_array( $v ) ? array_map( 'intval', $v ) : [];
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) { $new['gf_ev_data'] = 'Data'; $new['gf_ev_iscr'] = 'Iscritti'; }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gf_ev_data' ) {
            $d = (string) get_post_meta( $post_id, '_gf_ev_data', true );
            echo $d ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $d ) ) ) : '—';
        }
        if ( $col === 'gf_ev_iscr' ) {
            echo esc_html( (string) count( self::iscritti( $post_id ) ) );
        }
    }

    // ---------------------------------------------------------------------

    public static function handle_iscrizione(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'login richiesto', 403 ); }
        $event_id = (int) ( $_POST['evento'] ?? 0 );
        $azione   = sanitize_key( (string) ( $_POST['azione'] ?? 'iscrivi' ) );
        check_admin_referer( 'gfoss_evento_' . $event_id );

        $post = get_post( $event_id );
        $back = wp_get_referer() ?: home_url( '/' );
        if ( ! $post || $post->post_type !== self::CPT ) {
            wp_safe_redirect( add_query_arg( 'ev', 'errore', $back ) ); exit;
        }
        // Solo soci in regola.
        $uid = get_current_user_id();
        if ( ! gfoss_members_is_socio( $uid ) ) {
            wp_safe_redirect( add_query_arg( 'ev', 'nonsocio', $back ) ); exit;
        }

        $ids = self::iscritti( $event_id );
        if ( $azione === 'annulla' ) {
            $ids = array_values( array_diff( $ids, [ $uid ] ) );
            update_post_meta( $event_id, '_gfoss_iscritti', $ids );
            wp_safe_redirect( add_query_arg( 'ev', 'annullato', $back ) ); exit;
        }

        // Iscrizione.
        $scad = (string) get_post_meta( $event_id, '_gf_ev_scadenza', true );
        if ( $scad && strtotime( $scad . ' 23:59:59' ) < time() ) {
            wp_safe_redirect( add_query_arg( 'ev', 'scaduto', $back ) ); exit;
        }
        $posti = (int) get_post_meta( $event_id, '_gf_ev_posti', true );
        if ( $posti > 0 && count( $ids ) >= $posti && ! in_array( $uid, $ids, true ) ) {
            wp_safe_redirect( add_query_arg( 'ev', 'completo', $back ) ); exit;
        }
        if ( ! in_array( $uid, $ids, true ) ) {
            $ids[] = $uid;
            update_post_meta( $event_id, '_gfoss_iscritti', $ids );
        }
        wp_safe_redirect( add_query_arg( 'ev', 'iscritto', $back ) ); exit;
    }

    public static function shortcode( $atts = [] ): string {
        $now = time();
        $events = get_posts( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => '_gf_ev_data',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ] );
        // Solo eventi futuri (o senza data).
        $events = array_filter( $events, static function ( $e ) use ( $now ) {
            $d = (string) get_post_meta( $e->ID, '_gf_ev_data', true );
            return ! $d || strtotime( $d ) >= $now - DAY_IN_SECONDS;
        } );

        $uid    = get_current_user_id();
        $is_soc = $uid && gfoss_members_is_socio( $uid );
        $msg    = isset( $_GET['ev'] ) ? sanitize_key( (string) $_GET['ev'] ) : '';

        ob_start();
        echo '<div class="gf-eventi">';

        if ( $msg ) {
            $notes = [
                'iscritto'  => [ 'ok',   'Iscrizione registrata. A presto!' ],
                'annullato' => [ 'warn', 'Iscrizione annullata.' ],
                'completo'  => [ 'warn', 'Posti esauriti per questo evento.' ],
                'scaduto'   => [ 'warn', 'Le iscrizioni per questo evento sono chiuse.' ],
                'nonsocio'  => [ 'warn', 'Solo i soci in regola possono iscriversi.' ],
                'errore'    => [ 'warn', 'Evento non trovato.' ],
            ];
            if ( isset( $notes[ $msg ] ) ) {
                echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>';
            }
        }

        if ( ! $events ) {
            echo '<p class="gf-muted">Nessun evento in programma al momento.</p></div>';
            return ob_get_clean();
        }

        foreach ( $events as $e ) {
            $d     = (string) get_post_meta( $e->ID, '_gf_ev_data', true );
            $luogo = (string) get_post_meta( $e->ID, '_gf_ev_luogo', true );
            $ids   = self::iscritti( $e->ID );
            $posti = (int) get_post_meta( $e->ID, '_gf_ev_posti', true );
            $iscr  = in_array( $uid, $ids, true );

            echo '<article class="gf-evento">';
            echo '<h3>' . esc_html( $e->post_title ) . '</h3>';
            echo '<p class="gf-evento__meta">';
            if ( $d )     { echo '📅 ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $d ) ) ) . ' '; }
            if ( $luogo ) { echo '📍 ' . esc_html( $luogo ); }
            echo '</p>';
            if ( $e->post_content ) {
                echo '<div class="gf-evento__desc">' . wp_kses_post( wpautop( $e->post_content ) ) . '</div>';
            }
            if ( $posti > 0 ) {
                echo '<p class="gf-muted">' . esc_html( count( $ids ) ) . ' / ' . esc_html( (string) $posti ) . ' iscritti</p>';
            }

            if ( $is_soc ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'gfoss_evento_' . $e->ID );
                echo '<input type="hidden" name="action" value="gfoss_evento_iscrizione">';
                echo '<input type="hidden" name="evento" value="' . (int) $e->ID . '">';
                if ( $iscr ) {
                    echo '<input type="hidden" name="azione" value="annulla">';
                    echo '<button class="gf-btn gf-btn--ghost" type="submit">Annulla iscrizione</button>';
                } else {
                    echo '<input type="hidden" name="azione" value="iscrivi">';
                    echo '<button class="gf-btn gf-btn--orange" type="submit">Iscriviti</button>';
                }
                echo '</form>';
            } elseif ( ! $uid ) {
                echo '<p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi come socio per iscriverti</a></p>';
            }
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
