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
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_media' ] );
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

    public static function enqueue_media( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) { return; }
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === self::CPT ) {
            wp_enqueue_media();
        }
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
            if (!b) return;
            b.addEventListener('click', function(e){
                e.preventDefault();
                if (!window.wp || !wp.media) { window.alert('Media Library non disponibile: ricarica la pagina.'); return; }
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
            $priv = (string) get_post_meta( $post_id, '_gfoss_doc_name', true );
            if ( $priv !== '' ) {
                $url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'gfoss/v1/doc/' . $post_id ) );
                echo '<a href="' . esc_url( $url ) . '">' . esc_html( $priv ) . '</a> <small>(protetto)</small>';
                return;
            }
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
        if ( ! is_user_logged_in() ) { return false; }
        if ( current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return true; } // il direttivo controlla ciò che carica
        if ( ! current_user_can( Roles::CAP_READ_PRIVATE_DOCS ) ) { return false; }
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
        // File nella cartella protetta (caricati dalla console front-end),
        // altrimenti allegato della Media Library (documenti inseriti da wp-admin).
        $path = Doc_Riservato_Frontend::private_path( $id );
        $name = (string) get_post_meta( $id, '_gfoss_doc_name', true );
        $mime = '';
        if ( ! $path || ! is_readable( $path ) ) {
            $att_id = (int) get_post_meta( $id, '_gfoss_doc_file', true );
            $path   = $att_id ? (string) get_attached_file( $att_id ) : '';
            $name   = '';
            $mime   = $att_id ? (string) get_post_mime_type( $att_id ) : '';
        }
        if ( ! $path || ! is_readable( $path ) ) {
            return new \WP_REST_Response( 'file-missing', 404 );
        }
        if ( $mime === '' ) {
            $ft   = wp_check_filetype( $name ?: $path );
            $mime = $ft['type'] ?: 'application/octet-stream';
        }
        $name = str_replace( [ '"', "\r", "\n" ], '', $name ?: basename( $path ) );
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );
        header( 'X-Content-Type-Options: nosniff' );
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
        $by_cat = [];
        foreach ( $docs as $d ) {
            $cat = (string) get_post_meta( $d->ID, '_gfoss_doc_cat', true ) ?: 'Generale';
            $by_cat[ $cat ][] = $d;
        }
        ksort( $by_cat );
        $manage = current_user_can( Roles::CAP_MANAGE_SOCI )
            ? get_posts( [ 'post_type' => 'page', 'name' => 'gestione-documenti', 'post_status' => 'publish', 'numberposts' => 1 ] )
            : [];

        ob_start();
        echo '<div class="gf-area gf-docman gf-docs">';
        echo '<div class="gf-docs__intro"><span class="gf-docs__lock" aria-hidden="true">🔒</span><p>Area riservata ai soci in regola con la quota ' . esc_html( (string) $year ) . '. '
           . 'Qui trovi modulistica, deleghe e materiali dell\'associazione: clicca su un documento per scaricarlo.</p>';
        if ( $manage ) {
            echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( get_permalink( $manage[0] ) ) . '">Gestisci documenti</a>';
        }
        echo '</div>';

        if ( ! $docs ) {
            echo '<div class="gf-area__card gf-docman__empty"><span aria-hidden="true">🗂️</span><p>Non ci sono ancora documenti pubblicati.</p></div></div>';
            return (string) ob_get_clean();
        }

        // Filtri: categorie + ricerca
        echo '<div class="gf-docs__bar">';
        if ( count( $by_cat ) > 1 ) {
            echo '<div class="gf-docs__filters" role="group" aria-label="Filtra per categoria">';
            echo '<button type="button" class="gf-pill is-active" data-cat="">Tutti <span>' . count( $docs ) . '</span></button>';
            foreach ( $by_cat as $cat => $list ) {
                echo '<button type="button" class="gf-pill" data-cat="' . esc_attr( $cat ) . '">' . esc_html( $cat ) . ' <span>' . count( $list ) . '</span></button>';
            }
            echo '</div>';
        }
        echo '<input type="search" class="gf-docman__search" id="gf-doc-search" placeholder="🔍 Cerca un documento…">';
        echo '</div>';

        $nonce = wp_create_nonce( 'wp_rest' );
        foreach ( $by_cat as $cat => $list ) {
            echo '<section class="gf-area__card gf-docman__cat gf-docs__cat" data-cat="' . esc_attr( $cat ) . '"><h3>' . esc_html( $cat ) . ' <span class="gf-docman__count">' . count( $list ) . '</span></h3><ul class="gf-docrows">';
            foreach ( $list as $d ) {
                [ $fname, $size ] = Doc_Riservato_Frontend::file_info( $d->ID );
                [ $lbl, $kind ]   = Doc_Riservato_Frontend::type_badge( $fname );
                $url  = add_query_arg( '_wpnonce', $nonce, rest_url( 'gfoss/v1/doc/' . $d->ID ) );
                $desc = trim( wp_strip_all_tags( $d->post_content ) );
                $meta = array_filter( [ $lbl !== '—' ? $lbl : '', $size, get_the_date( 'd/m/Y', $d ) ] );
                echo '<li class="gf-docrow" data-search="' . esc_attr( strtolower( $d->post_title . ' ' . $desc . ' ' . $fname . ' ' . $cat ) ) . '">';
                echo '<a class="gf-docrow__link" href="' . esc_url( $url ) . '" download>';
                echo '<span class="gf-ftype gf-ftype--' . esc_attr( $kind ) . '">' . esc_html( $lbl ) . '</span>';
                echo '<span class="gf-docrow__main"><strong>' . esc_html( $d->post_title ) . '</strong>'
                   . ( $desc !== '' ? '<span class="gf-docrow__desc">' . esc_html( $desc ) . '</span>' : '' )
                   . '<small>' . esc_html( implode( ' · ', $meta ) ) . '</small></span>';
                echo '<span class="gf-btn gf-btn--ghost gf-btn--sm gf-docrow__dl">⬇ Scarica</span>';
                echo '</a></li>';
            }
            echo '</ul></section>';
        }
        echo '<p class="gf-muted gf-docman__nores" id="gf-doc-nores" hidden>Nessun documento corrisponde alla ricerca.</p>';
        echo '</div>';
        ?>
        <script>
        (function(){
            var q = document.getElementById('gf-doc-search'), pills = document.querySelectorAll('.gf-docs .gf-pill'), cur = '';
            function apply(){
                var t = q ? q.value.trim().toLowerCase() : '', any = false;
                document.querySelectorAll('.gf-docs__cat').forEach(function(sec){
                    var vis = 0, inCat = !cur || sec.dataset.cat === cur;
                    sec.querySelectorAll('.gf-docrow').forEach(function(r){ var ok = inCat && (!t || r.dataset.search.indexOf(t) !== -1); r.hidden = !ok; if (ok) vis++; });
                    sec.hidden = vis === 0; if (vis) any = true;
                });
                document.getElementById('gf-doc-nores').hidden = any;
            }
            pills.forEach(function(p){ p.addEventListener('click', function(){ pills.forEach(function(x){ x.classList.remove('is-active'); }); p.classList.add('is-active'); cur = p.dataset.cat; apply(); }); });
            if (q) q.addEventListener('input', apply);
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
