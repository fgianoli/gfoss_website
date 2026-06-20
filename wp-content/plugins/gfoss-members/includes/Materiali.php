<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Materiali e risorse per i soci (presentazioni, template, kit, documentazione).
 *
 *   - CPT 'gfoss_risorsa' gestito da admin/CD
 *   - Allegato (Media Library) + categoria libera + descrizione
 *   - Shortcode [gfoss_materiali] elenca le risorse per i soci loggati
 *
 * Diverso da Doc_Riservato (governance: verbali CD/bozze bilancio): qui materiali
 * operativi condivisi. Visibili a tutti i soci loggati.
 */
class Materiali {

    public const CPT = 'gfoss_risorsa';

    public static function init(): void {
        add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',         [ __CLASS__, 'metabox' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_shortcode( 'gfoss_materiali',     [ __CLASS__, 'shortcode' ] );
        add_filter( 'the_content',            [ __CLASS__, 'append_to_content' ], 20 );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Materiali soci',
                'singular_name' => 'Materiale',
                'add_new'       => 'Aggiungi materiale',
                'add_new_item'  => 'Nuovo materiale',
                'edit_item'     => 'Modifica materiale',
                'menu_name'     => 'Materiali soci',
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
            'menu_icon'       => 'dashicons-portfolio',
        ] );
    }

    public static function metabox(): void {
        add_meta_box( 'gfoss_risorsa_meta', 'Allegato e categoria', [ __CLASS__, 'render_metabox' ], self::CPT, 'side', 'high' );
    }

    public static function render_metabox( \WP_Post $post ): void {
        $att_id = (int) get_post_meta( $post->ID, '_gf_ris_file', true );
        $cat    = (string) get_post_meta( $post->ID, '_gf_ris_cat', true );
        $url    = (string) get_post_meta( $post->ID, '_gf_ris_url', true );
        $att    = $att_id ? wp_get_attachment_url( $att_id ) : '';
        wp_nonce_field( 'gfoss_risorsa_meta_' . $post->ID, '_gfoss_risorsa_nonce' );
        ?>
        <p><label><strong>File</strong></label><br>
            <input type="number" name="gf_ris_file" id="gf_ris_file" value="<?php echo esc_attr( (string) $att_id ); ?>" placeholder="ID allegato" style="width:120px">
            <button type="button" class="button" id="gfoss-ris-pick">Media Library</button>
            <?php if ( $att ) : ?><br><small><a href="<?php echo esc_url( $att ); ?>" target="_blank"><?php echo esc_html( basename( $att ) ); ?></a></small><?php endif; ?>
        </p>
        <p><label><strong>oppure URL esterno</strong></label><br>
            <input type="url" name="gf_ris_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://…"></p>
        <p><label><strong>Categoria</strong></label><br>
            <input type="text" name="gf_ris_cat" value="<?php echo esc_attr( $cat ); ?>" class="widefat" placeholder="es. Presentazioni, Template, Branding"></p>
        <p><label><strong>Collega a un contenuto</strong> <small>(opzionale)</small></label><br>
            <?php $linked = (int) get_post_meta( $post->ID, '_gf_ris_post', true ); ?>
            <select name="gf_ris_post" class="widefat">
                <option value="0">— nessuno (solo area soci) —</option>
                <?php
                $choices = get_posts( [ 'post_type' => [ 'post', 'page' ], 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC' ] );
                foreach ( $choices as $c ) :
                    $tag = $c->post_type === 'page' ? 'Pagina' : 'News';
                    ?>
                    <option value="<?php echo (int) $c->ID; ?>" <?php selected( $linked, $c->ID ); ?>>[<?php echo esc_html( $tag ); ?>] <?php echo esc_html( $c->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="description">Se selezionato, la risorsa appare come download in fondo a quel contenuto (pubblico). Altrimenti resta nei materiali dell'area soci.</small>
        </p>
        <script>
        (function(){ var b=document.getElementById('gfoss-ris-pick'); if(!b||!window.wp||!wp.media)return;
            b.addEventListener('click',function(e){e.preventDefault();
                var f=wp.media({title:'Scegli file',multiple:false}).on('select',function(){
                    document.getElementById('gf_ris_file').value=f.state().get('selection').first().toJSON().id;});
                f.open();});})();
        </script>
        <?php
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_risorsa_nonce'] )
             || ! wp_verify_nonce( $_POST['_gfoss_risorsa_nonce'], 'gfoss_risorsa_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return; }
        update_post_meta( $post_id, '_gf_ris_file', (int) ( $_POST['gf_ris_file'] ?? 0 ) );
        update_post_meta( $post_id, '_gf_ris_url',  esc_url_raw( wp_unslash( $_POST['gf_ris_url'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_ris_cat',  sanitize_text_field( wp_unslash( $_POST['gf_ris_cat'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_ris_post', (int) ( $_POST['gf_ris_post'] ?? 0 ) );
    }

    /** Risorse collegate a un contenuto (post/pagina). */
    public static function linked_resources( int $post_id ): array {
        if ( $post_id <= 0 ) { return []; }
        return get_posts( [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_gf_ris_post',
            'meta_value'     => (string) $post_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    /** Mostra in fondo al contenuto le risorse collegate (download pubblici). */
    public static function append_to_content( string $content ): string {
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $content; }
        $items = self::linked_resources( (int) get_the_ID() );
        if ( ! $items ) { return $content; }

        ob_start();
        echo '<section class="gf-allegati"><h3>Risorse e allegati</h3><ul class="gf-doclist">';
        foreach ( $items as $it ) {
            $url = self::file_url( $it->ID );
            echo '<li>';
            echo $url
                ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $it->post_title ) . '</a>'
                : esc_html( $it->post_title );
            if ( $it->post_content ) {
                echo ' <small class="gf-muted">— ' . esc_html( wp_trim_words( wp_strip_all_tags( $it->post_content ), 16 ) ) . '</small>';
            }
            echo '</li>';
        }
        echo '</ul></section>';
        return $content . ob_get_clean();
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) { $new['gf_ris_cat'] = 'Categoria'; $new['gf_ris_file'] = 'File'; }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gf_ris_cat' ) { echo esc_html( (string) get_post_meta( $post_id, '_gf_ris_cat', true ) ); }
        if ( $col === 'gf_ris_file' ) {
            $u = self::file_url( $post_id );
            echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank">apri</a>' : '—';
        }
    }

    private static function file_url( int $post_id ): string {
        $att = (int) get_post_meta( $post_id, '_gf_ris_file', true );
        if ( $att ) { return (string) wp_get_attachment_url( $att ); }
        return (string) get_post_meta( $post_id, '_gf_ris_url', true );
    }

    public static function shortcode( $atts = [] ): string {
        if ( ! is_user_logged_in() || ! gfoss_members_is_socio( get_current_user_id() ) ) {
            return '<div class="gf-card gf-card--warn">Area riservata ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }
        $items = get_posts( [ 'post_type' => self::CPT, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ] );
        if ( ! $items ) { return '<div class="gf-card">Nessun materiale disponibile.</div>'; }

        $by_cat = [];
        foreach ( $items as $it ) {
            // Le risorse collegate a un contenuto si mostrano su quel contenuto, non qui.
            if ( (int) get_post_meta( $it->ID, '_gf_ris_post', true ) > 0 ) { continue; }
            $cat = (string) get_post_meta( $it->ID, '_gf_ris_cat', true ) ?: 'Generale';
            $by_cat[ $cat ][] = $it;
        }
        if ( ! $by_cat ) { return '<div class="gf-card">Nessun materiale disponibile.</div>'; }
        ksort( $by_cat );

        ob_start();
        echo '<div class="gf-materiali">';
        foreach ( $by_cat as $cat => $list ) {
            echo '<section class="gf-materiali__cat"><h3>' . esc_html( $cat ) . '</h3><ul class="gf-doclist">';
            foreach ( $list as $it ) {
                $url = self::file_url( $it->ID );
                echo '<li>';
                if ( $url ) {
                    echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $it->post_title ) . '</a>';
                } else {
                    echo esc_html( $it->post_title );
                }
                if ( $it->post_content ) {
                    echo ' <small class="gf-muted">— ' . esc_html( wp_trim_words( wp_strip_all_tags( $it->post_content ), 18 ) ) . '</small>';
                }
                echo '</li>';
            }
            echo '</ul></section>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
