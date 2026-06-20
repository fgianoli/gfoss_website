<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Documenti riservati ai soci.
 *
 *   - CPT 'gfoss_doc' (visibile solo nell'admin e via shortcode/endpoint protetto)
 *   - Campo allegato (attachment ID via media library)
 *   - Categoria libera (verbali CD, bozze bilancio, materiali, ecc.)
 *   - Shortcode [gfoss_documenti_riservati] elenca i documenti per i soci in regola
 *   - Endpoint /wp-json/gfoss/v1/doc/{id} streama il file con verifica capability + quota
 */
class Doc_Riservato {

    public const CPT = 'gfoss_doc';

    public static function init(): void {
        add_action( 'init',                [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',      [ __CLASS__, 'metabox' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_action( 'rest_api_init',       [ __CLASS__, 'register_routes' ] );
        add_shortcode( 'gfoss_documenti_riservati', [ __CLASS__, 'shortcode' ] );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Documenti riservati',
                'singular_name' => 'Documento riservato',
                'add_new'       => 'Aggiungi documento',
                'add_new_item'  => 'Nuovo documento riservato',
                'edit_item'     => 'Modifica documento',
                'menu_name'     => 'Documenti soci',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'gfoss-associazione',
            'show_in_rest'        => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'capabilities'        => [
                'edit_posts'         => Roles::CAP_MANAGE_SOCI,
                'edit_others_posts'  => Roles::CAP_MANAGE_SOCI,
                'publish_posts'      => Roles::CAP_MANAGE_SOCI,
                'read_private_posts' => Roles::CAP_READ_PRIVATE_DOCS,
            ],
            'supports'            => [ 'title', 'editor' ],
            'has_archive'         => false,
            'rewrite'             => false,
            'menu_icon'           => 'dashicons-lock',
        ] );
    }

    public static function metabox(): void {
        add_meta_box( 'gfoss_doc_meta', 'Allegato e categoria', [ __CLASS__, 'render_metabox' ], self::CPT, 'side', 'high' );
    }

    public static function render_metabox( \WP_Post $post ): void {
        $att_id = (int) get_post_meta( $post->ID, '_gfoss_doc_file', true );
        $cat    = (string) get_post_meta( $post->ID, '_gfoss_doc_cat', true );
        $att    = $att_id ? wp_get_attachment_url( $att_id ) : '';
        wp_nonce_field( 'gfoss_doc_meta_' . $post->ID, '_gfoss_doc_nonce' );
        ?>
        <p>
            <label><strong>File</strong></label><br>
            <input type="number" name="gfoss_doc_file" value="<?php echo esc_attr( (string) $att_id ); ?>" placeholder="ID allegato">
            <button type="button" class="button" id="gfoss-doc-pick">Scegli dalla Media Library</button>
            <?php if ( $att ) : ?>
                <br><small><code><?php echo esc_html( $att ); ?></code></small>
            <?php endif; ?>
        </p>
        <p>
            <label><strong>Categoria</strong></label><br>
            <input type="text" name="gfoss_doc_cat" value="<?php echo esc_attr( $cat ); ?>" class="widefat" placeholder="es. Verbali CD, Bozze bilancio, Materiali">
        </p>
        <script>
        (function(){
            var b = document.getElementById('gfoss-doc-pick');
            if (!b || !window.wp || !wp.media) return;
            b.addEventListener('click', function(e){
                e.preventDefault();
                var f = wp.media({ title:'Scegli file', multiple:false }).on('select', function(){
                    var att = f.state().get('selection').first().toJSON();
                    document.querySelector('input[name="gfoss_doc_file"]').value = att.id;
                });
                f.open();
            });
        })();
        </script>
        <?php
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_doc_nonce'] )
             || ! wp_verify_nonce( $_POST['_gfoss_doc_nonce'], 'gfoss_doc_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return; }
        update_post_meta( $post_id, '_gfoss_doc_file', (int) ( $_POST['gfoss_doc_file'] ?? 0 ) );
        update_post_meta( $post_id, '_gfoss_doc_cat',  sanitize_text_field( wp_unslash( $_POST['gfoss_doc_cat'] ?? '' ) ) );
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) {
                $new['gfoss_cat']  = 'Categoria';
                $new['gfoss_file'] = 'File';
            }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gfoss_cat' ) {
            echo esc_html( (string) get_post_meta( $post_id, '_gfoss_doc_cat', true ) );
        }
        if ( $col === 'gfoss_file' ) {
            $id = (int) get_post_meta( $post_id, '_gfoss_doc_file', true );
            echo $id ? '<a href="' . esc_url( wp_get_attachment_url( $id ) ) . '">apri</a>' : '—';
        }
    }

    // ---------------------------------------------------------------------
    // Frontend (shortcode + endpoint download)

    public static function register_routes(): void {
        register_rest_route( 'gfoss/v1', '/doc/(?P<id>\d+)', [
            'methods'             => 'GET',
            'permission_callback' => [ __CLASS__, 'can_download' ],
            'callback'            => [ __CLASS__, 'rest_download' ],
            'args'                => [ 'id' => [ 'validate_callback' => static fn( $v ) => is_numeric( $v ) ] ],
        ] );
    }

    public static function can_download(): bool {
        if ( ! is_user_logged_in() || ! current_user_can( Roles::CAP_READ_PRIVATE_DOCS ) ) { return false; }
        $year = (int) gmdate( 'Y' );
        $st = Quote::status_for( get_current_user_id(), $year );
        return in_array( $st, [ 'paid', 'expiring' ], true );
    }

    public static function rest_download( \WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return new \WP_REST_Response( 'not-found', 404 );
        }
        // Solo documenti effettivamente pubblicati: niente bozze/pending (es. bilanci
        // non ancora deliberati) scaricabili indovinando l'ID.
        if ( ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
            return new \WP_REST_Response( 'not-found', 404 );
        }
        $att_id = (int) get_post_meta( $id, '_gfoss_doc_file', true );
        $path = $att_id ? get_attached_file( $att_id ) : '';
        if ( ! $path || ! is_readable( $path ) ) {
            return new \WP_REST_Response( 'file-missing', 404 );
        }
        $mime = get_post_mime_type( $att_id ) ?: 'application/octet-stream';
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
        header( 'Content-Length: ' . (string) filesize( $path ) );
        readfile( $path );
        exit;
    }

    public static function shortcode( $atts = [], $content = null ): string {
        if ( ! is_user_logged_in() ) {
            return '<div class="gf-card gf-card--warn">Per consultare i documenti riservati devi <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">accedere</a> con le tue credenziali socio.</div>';
        }
        $year = (int) gmdate( 'Y' );
        $st   = Quote::status_for( get_current_user_id(), $year );
        if ( ! in_array( $st, [ 'paid', 'expiring' ], true ) ) {
            return '<div class="gf-card gf-card--warn">L\'accesso ai documenti riservati richiede la quota associativa in regola per il ' . esc_html( (string) $year ) . '. <a href="' . esc_url( home_url( '/area-soci/' ) ) . '">Vai all\'area soci per rinnovare</a>.</div>';
        }

        $docs = get_posts( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'private' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        if ( ! $docs ) {
            return '<div class="gf-card">Non ci sono ancora documenti pubblicati.</div>';
        }

        $by_cat = [];
        foreach ( $docs as $d ) {
            $cat = (string) get_post_meta( $d->ID, '_gfoss_doc_cat', true ) ?: 'Generale';
            $by_cat[ $cat ][] = $d;
        }
        ksort( $by_cat );

        ob_start();
        echo '<div class="gf-docs">';
        foreach ( $by_cat as $cat => $list ) {
            echo '<section class="gf-docs__cat"><h3>' . esc_html( $cat ) . '</h3><ul>';
            foreach ( $list as $d ) {
                $url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'gfoss/v1/doc/' . $d->ID ) );
                echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $d->post_title ) . '</a> '
                   . '<small class="gf-muted">' . esc_html( get_the_date( 'd/m/Y', $d ) ) . '</small></li>';
            }
            echo '</ul></section>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
