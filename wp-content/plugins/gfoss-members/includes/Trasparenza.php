<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Documenti pubblici di trasparenza: bilanci e verbali d'assemblea.
 *
 * A differenza di Doc_Riservato (riservato ai soci), questi documenti sono
 * PUBBLICI: il Codice del Terzo Settore (D.Lgs. 117/2017, art. 14 e art. 48)
 * richiede la pubblicazione di bilanci ed emolumenti. I file sono quindi
 * scaricabili liberamente dal sito.
 *
 *   - CPT 'gfoss_pubbdoc' (gestito da Consiglio/admin, reso via shortcode)
 *   - Meta: tipo (bilancio consuntivo/preventivo | verbale), anno, data, luogo, file
 *   - Shortcode [gfoss_bilanci]              → bilanci + verbali (due colonne)
 *               [gfoss_bilanci mostra="bilanci"]
 *               [gfoss_bilanci mostra="verbali"]
 */
class Trasparenza {

    public const CPT = 'gfoss_pubbdoc';

    public const TIPI = [
        'bilancio_consuntivo' => 'Bilancio consuntivo',
        'bilancio_preventivo' => 'Bilancio preventivo',
        'verbale_assemblea'   => 'Verbale assemblea soci',
        'verbale_direttivo'   => 'Verbale direttivo',
        'altro'               => 'Altro documento',
    ];

    /** Etichetta del tipo, gestendo il valore storico 'verbale'. */
    public static function tipo_label( string $t ): string {
        if ( $t === 'verbale' ) { return 'Verbale assemblea soci'; }
        return self::TIPI[ $t ] ?? '—';
    }

    /** L'utente fa parte del Consiglio Direttivo (o è amministratore)? */
    public static function user_is_board(): bool {
        return is_user_logged_in() && (
            current_user_can( Roles::CAP_MANAGE_SOCI )
            || current_user_can( Roles::CAP_MANAGE_QUOTE )
            || current_user_can( Roles::CAP_MANAGE_ASSEMBLEE )
            || current_user_can( Roles::CAP_VIEW_ACCOUNTING )
        );
    }

    public static function init(): void {
        add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',         [ __CLASS__, 'metabox' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_media' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_shortcode( 'gfoss_bilanci',       [ __CLASS__, 'shortcode' ] );
        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
    }

    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'          => 'Bilanci e verbali',
                'singular_name' => 'Documento pubblico',
                'add_new'       => 'Aggiungi documento',
                'add_new_item'  => 'Nuovo bilancio / verbale',
                'edit_item'     => 'Modifica documento',
                'menu_name'     => 'Bilanci e verbali',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'gfoss-associazione',
            'show_in_rest'    => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'capabilities'    => [
                'edit_posts'        => Roles::CAP_MANAGE_SOCI,
                'edit_others_posts' => Roles::CAP_MANAGE_SOCI,
                'publish_posts'     => Roles::CAP_MANAGE_SOCI,
            ],
            'supports'        => [ 'title' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'menu_icon'       => 'dashicons-media-spreadsheet',
        ] );
    }

    public static function metabox(): void {
        add_meta_box( 'gfoss_pubbdoc_meta', 'Dettagli documento', [ __CLASS__, 'render_metabox' ], self::CPT, 'normal', 'high' );
    }

    /** Carica gli script della Media Library sulle schermate di modifica del CPT. */
    public static function enqueue_media( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) { return; }
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === self::CPT ) {
            wp_enqueue_media();
        }
    }

    public static function render_metabox( \WP_Post $post ): void {
        $tipo   = (string) get_post_meta( $post->ID, '_gf_tipo', true ) ?: 'bilancio_consuntivo';
        if ( $tipo === 'verbale' ) { $tipo = 'verbale_assemblea'; } // valore storico
        $anno   = (string) get_post_meta( $post->ID, '_gf_anno', true );
        $data   = (string) get_post_meta( $post->ID, '_gf_data', true );
        $luogo  = (string) get_post_meta( $post->ID, '_gf_luogo', true );
        $att_id = (int) get_post_meta( $post->ID, '_gf_file', true );
        $att    = $att_id ? wp_get_attachment_url( $att_id ) : '';
        wp_nonce_field( 'gfoss_pubbdoc_meta_' . $post->ID, '_gfoss_pubbdoc_nonce' );
        ?>
        <style>.gf-pubbdoc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:680px}.gf-pubbdoc-grid p{margin:0}.gf-pubbdoc-grid label{font-weight:600;display:block;margin-bottom:4px}</style>
        <div class="gf-pubbdoc-grid">
            <p>
                <label>Tipo documento</label>
                <select name="gf_tipo" class="widefat">
                    <?php foreach ( self::TIPI as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $tipo, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label>Anno di riferimento</label>
                <input type="number" name="gf_anno" value="<?php echo esc_attr( $anno ); ?>" class="widefat" placeholder="<?php echo esc_attr( (string) (int) gmdate( 'Y' ) ); ?>" min="2000" max="2100">
            </p>
            <p>
                <label>Data assemblea <small>(solo verbali)</small></label>
                <input type="date" name="gf_data" value="<?php echo esc_attr( $data ); ?>" class="widefat">
            </p>
            <p>
                <label>Luogo <small>(solo verbali)</small></label>
                <input type="text" name="gf_luogo" value="<?php echo esc_attr( $luogo ); ?>" class="widefat" placeholder="es. Padova">
            </p>
        </div>
        <p style="margin-top:14px">
            <label style="font-weight:600;display:block;margin-bottom:4px">File PDF</label>
            <input type="number" name="gf_file" id="gf_file_id" value="<?php echo esc_attr( (string) $att_id ); ?>" placeholder="ID allegato" style="width:140px">
            <button type="button" class="button" id="gfoss-pubbdoc-pick">Scegli dalla Media Library</button>
            <br><small><a id="gfoss-pubbdoc-filename" href="<?php echo esc_url( $att ?: '#' ); ?>" target="_blank" style="<?php echo $att ? '' : 'display:none'; ?>"><?php echo esc_html( $att ? basename( $att ) : '' ); ?></a></small>
        </p>
        <script>
        (function(){
            var b = document.getElementById('gfoss-pubbdoc-pick');
            if (!b) return;
            b.addEventListener('click', function(e){
                e.preventDefault();
                if (!window.wp || !wp.media) { window.alert('Media Library non disponibile: ricarica la pagina.'); return; }
                var f = wp.media({ title:'Scegli file PDF', library:{ type:'application/pdf' }, multiple:false }).on('select', function(){
                    var att = f.state().get('selection').first().toJSON();
                    document.getElementById('gf_file_id').value = att.id;
                    var link = document.getElementById('gfoss-pubbdoc-filename');
                    if (link) { link.textContent = att.filename || att.title || ('#' + att.id); link.href = att.url || '#'; link.style.display = 'inline'; }
                });
                f.open();
            });
        })();
        </script>
        <?php
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['_gfoss_pubbdoc_nonce'] )
             || ! wp_verify_nonce( $_POST['_gfoss_pubbdoc_nonce'], 'gfoss_pubbdoc_meta_' . $post_id ) ) { return; }
        if ( ! current_user_can( Roles::CAP_MANAGE_SOCI ) ) { return; }

        $tipo = sanitize_key( (string) ( $_POST['gf_tipo'] ?? 'altro' ) );
        if ( ! isset( self::TIPI[ $tipo ] ) ) { $tipo = 'altro'; }

        update_post_meta( $post_id, '_gf_tipo',  $tipo );
        update_post_meta( $post_id, '_gf_anno',  (int) ( $_POST['gf_anno'] ?? 0 ) );
        update_post_meta( $post_id, '_gf_data',  sanitize_text_field( wp_unslash( $_POST['gf_data'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_luogo', sanitize_text_field( wp_unslash( $_POST['gf_luogo'] ?? '' ) ) );
        update_post_meta( $post_id, '_gf_file',  (int) ( $_POST['gf_file'] ?? 0 ) );
    }

    public static function columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) {
                $new['gf_tipo'] = 'Tipo';
                $new['gf_anno'] = 'Anno';
                $new['gf_file'] = 'File';
            }
        }
        return $new;
    }

    public static function column_value( string $col, int $post_id ): void {
        if ( $col === 'gf_tipo' ) {
            $t = (string) get_post_meta( $post_id, '_gf_tipo', true );
            echo esc_html( self::tipo_label( $t ) );
        }
        if ( $col === 'gf_anno' ) {
            echo esc_html( (string) ( (int) get_post_meta( $post_id, '_gf_anno', true ) ?: '—' ) );
        }
        if ( $col === 'gf_file' ) {
            $id = (int) get_post_meta( $post_id, '_gf_file', true );
            echo $id ? '<a href="' . esc_url( wp_get_attachment_url( $id ) ) . '" target="_blank">apri</a>' : '—';
        }
    }

    // ---------------------------------------------------------------------
    // Frontend

    public static function shortcode( $atts = [], $content = null ): string {
        $atts   = shortcode_atts( [ 'mostra' => 'both' ], $atts, 'gfoss_bilanci' );
        $mostra = in_array( $atts['mostra'], [ 'both', 'bilanci', 'verbali', 'direttivo' ], true ) ? $atts['mostra'] : 'both';

        $docs = get_posts( [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );

        $bilanci  = [];   // anno => [ consuntivo => doc, preventivo => doc ]
        $verb_ass = [];   // verbali assemblea soci (incl. valore storico 'verbale')
        $verb_dir = [];   // verbali direttivo
        foreach ( $docs as $d ) {
            $tipo = (string) get_post_meta( $d->ID, '_gf_tipo', true );
            $anno = (int) get_post_meta( $d->ID, '_gf_anno', true );
            if ( $tipo === 'verbale' || $tipo === 'verbale_assemblea' ) {
                $verb_ass[] = $d;
            } elseif ( $tipo === 'verbale_direttivo' ) {
                $verb_dir[] = $d;
            } elseif ( $tipo === 'bilancio_consuntivo' || $tipo === 'bilancio_preventivo' ) {
                $bilanci[ $anno ][ $tipo ] = $d;
            }
        }
        krsort( $bilanci );
        $by_data = static function ( $a, $b ) {
            return strcmp( (string) get_post_meta( $b->ID, '_gf_data', true ), (string) get_post_meta( $a->ID, '_gf_data', true ) );
        };
        usort( $verb_ass, $by_data );
        usort( $verb_dir, $by_data );

        // Verbali del direttivo: vista RISERVATA al Consiglio Direttivo.
        if ( $mostra === 'direttivo' ) {
            if ( ! self::user_is_board() ) {
                return '<div class="gf-card gf-card--warn">Sezione riservata al Consiglio Direttivo.</div>';
            }
            ob_start();
            echo '<div class="gf-trasparenza">';
            self::render_verbali_list( $verb_dir, 'Verbali del Consiglio Direttivo' );
            echo '</div>';
            return (string) ob_get_clean();
        }

        ob_start();
        echo '<div class="gf-trasparenza">';

        if ( $mostra !== 'verbali' ) {
            echo '<section class="gf-trasparenza__col"><h2>Bilanci</h2>';
            if ( ! $bilanci ) {
                echo '<p class="gf-muted">Non ci sono ancora bilanci pubblicati.</p>';
            } else {
                echo '<ul class="gf-doclist">';
                foreach ( $bilanci as $anno => $set ) {
                    echo '<li><strong>' . esc_html( (string) $anno ) . '</strong><span class="gf-doclist__links">';
                    echo self::link( $set['bilancio_consuntivo'] ?? null, 'Consuntivo' );
                    echo self::link( $set['bilancio_preventivo'] ?? null, 'Preventivo' );
                    echo '</span></li>';
                }
                echo '</ul>';
            }
            echo '</section>';
        }

        if ( $mostra !== 'bilanci' ) {
            self::render_verbali_list( $verb_ass, "Verbali dell'assemblea dei soci" );
            // I verbali del direttivo NON sono pubblici: vedi [gfoss_bilanci mostra="direttivo"].
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function render_verbali_list( array $list, string $titolo ): void {
        echo '<section class="gf-trasparenza__col"><h2>' . esc_html( $titolo ) . '</h2>';
        if ( ! $list ) {
            echo '<p class="gf-muted">Non ci sono ancora documenti pubblicati.</p>';
        } else {
            echo '<ul class="gf-doclist">';
            foreach ( $list as $d ) {
                $luogo = (string) get_post_meta( $d->ID, '_gf_luogo', true );
                $data  = (string) get_post_meta( $d->ID, '_gf_data', true );
                $when  = $data ? date_i18n( 'd/m/Y', strtotime( $data ) ) : '';
                $label = esc_html( $d->post_title );
                if ( $luogo ) { $label .= ' — ' . esc_html( $luogo ); }
                echo '<li>' . self::link( $d, $label, true )
                   . ( $when ? ' <small class="gf-muted">' . esc_html( $when ) . '</small>' : '' )
                   . '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    }

    /** Link a un file allegato (pubblico). $raw=true se $label è già escaped. */
    private static function link( ?\WP_Post $doc, string $label, bool $raw = false ): string {
        if ( ! $doc ) {
            return '<span class="gf-muted">' . ( $raw ? $label : esc_html( $label ) ) . ' n.d.</span>';
        }
        $att_id = (int) get_post_meta( $doc->ID, '_gf_file', true );
        $url    = $att_id ? wp_get_attachment_url( $att_id ) : '';
        $text   = $raw ? $label : esc_html( $label );
        if ( ! $url ) {
            return '<span class="gf-muted">' . $text . ' n.d.</span>';
        }
        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . $text . '</a>';
    }
}
