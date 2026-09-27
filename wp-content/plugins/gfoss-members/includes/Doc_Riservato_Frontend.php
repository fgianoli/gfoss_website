<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end per gestire i Documenti riservati ai soci (CAP_MANAGE_SOCI).
 * Shortcode [gfoss_gestione_documenti].
 *
 * I file caricati da qui NON finiscono nella Media Library ma in
 * uploads/gfoss-docs-private/ (protetta da .htaccess), così sono scaricabili
 * solo tramite l'endpoint /wp-json/gfoss/v1/doc/{id} con verifica della quota.
 * Si possono caricare più file insieme: ognuno diventa un documento.
 */
class Doc_Riservato_Frontend {

    public const DIR = 'gfoss-docs-private';

    private const EXT = [ 'pdf','odt','ott','ods','ots','odp','otp','odg','doc','docx','xls','xlsx','ppt','pptx','rtf','txt','csv','zip','7z','png','jpg','jpeg','svg' ];

    public static function init(): void {
        add_shortcode( 'gfoss_gestione_documenti', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_doc_save',   [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_gfoss_doc_delete', [ __CLASS__, 'handle_delete' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && current_user_can( Roles::CAP_MANAGE_SOCI );
    }

    /** Cartella protetta (creata al volo con .htaccess + index.php). */
    public static function dir(): string {
        $up  = wp_upload_dir( null, false );
        $dir = trailingslashit( $up['basedir'] ) . self::DIR;
        if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
        }
        if ( ! file_exists( $dir . '/index.php' ) ) {
            @file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
        }
        return $dir;
    }

    /** Percorso assoluto del file privato di un documento ('' se non c'è). */
    public static function private_path( int $post_id ): string {
        $rel = (string) get_post_meta( $post_id, '_gfoss_doc_path', true );
        if ( $rel === '' ) { return ''; }
        return self::dir() . '/' . basename( $rel );
    }

    /** Salva un file caricato nella cartella protetta. @return array{0:string,1:string}|null [nome su disco, nome originale] */
    private static function store_upload( array $f ): ?array {
        if ( ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $f['tmp_name'] ) ) { return null; }
        $orig = sanitize_file_name( wp_unslash( (string) $f['name'] ) );
        $ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, self::EXT, true ) ) { return null; }
        $disk = wp_generate_password( 12, false ) . '-' . $orig;
        if ( ! move_uploaded_file( $f['tmp_name'], self::dir() . '/' . $disk ) ) { return null; }
        return [ $disk, $orig ];
    }

    /**
     * Importa un file locale come documento riservato (per caricamenti in blocco da WP-CLI).
     * @return int ID del documento, 0 se il formato non è ammesso o la copia fallisce.
     */
    public static function import_file( string $src, string $title, string $cat, string $status = 'publish' ): int {
        $orig = sanitize_file_name( basename( $src ) );
        if ( ! is_readable( $src ) || ! in_array( strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) ), self::EXT, true ) ) { return 0; }
        $disk = wp_generate_password( 12, false ) . '-' . $orig;
        if ( ! copy( $src, self::dir() . '/' . $disk ) ) { return 0; }
        $pid = wp_insert_post( [ 'post_type' => Doc_Riservato::CPT, 'post_status' => $status, 'post_title' => $title ?: self::title_from( $orig ) ] );
        if ( ! $pid || is_wp_error( $pid ) ) { @unlink( self::dir() . '/' . $disk ); return 0; }
        update_post_meta( $pid, '_gfoss_doc_path', $disk );
        update_post_meta( $pid, '_gfoss_doc_name', $orig );
        update_post_meta( $pid, '_gfoss_doc_cat',  $cat );
        return (int) $pid;
    }

    /** Normalizza $_FILES['files'] (input multiplo) in una lista di file. */
    private static function uploaded_files(): array {
        $raw = $_FILES['files'] ?? null;
        if ( ! $raw || ! is_array( $raw['name'] ?? null ) ) { return []; }
        $out = [];
        foreach ( array_keys( $raw['name'] ) as $i ) {
            if ( (int) $raw['error'][ $i ] === UPLOAD_ERR_NO_FILE ) { continue; }
            $out[] = [ 'name' => $raw['name'][ $i ], 'tmp_name' => $raw['tmp_name'][ $i ], 'error' => (int) $raw['error'][ $i ] ];
        }
        return $out;
    }

    private static function title_from( string $filename ): string {
        $t = pathinfo( $filename, PATHINFO_FILENAME );
        return ucfirst( trim( (string) preg_replace( '/[_\-]+/', ' ', $t ) ) );
    }

    private static function delete_private_file( int $post_id ): void {
        $p = self::private_path( $post_id );
        if ( $p && is_file( $p ) ) { @unlink( $p ); }
    }

    public static function handle_save(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        // Invio oltre post_max_size: PHP svuota $_POST/$_FILES (e il nonce con loro).
        if ( empty( $_POST ) && (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > 0 ) { self::back( 'toobig' ); }
        check_admin_referer( 'gfoss_doc_fe' );

        $id     = (int) ( $_POST['doc_id'] ?? 0 );
        $title  = sanitize_text_field( wp_unslash( $_POST['titolo'] ?? '' ) );
        $cat    = sanitize_text_field( wp_unslash( $_POST['categoria'] ?? '' ) );
        $desc   = wp_kses_post( wp_unslash( $_POST['descrizione'] ?? '' ) );
        $status = ( $_POST['stato'] ?? 'publish' ) === 'draft' ? 'draft' : 'publish';
        $files  = self::uploaded_files();

        // Modifica di un documento esistente (al massimo un file, che sostituisce il precedente)
        if ( $id ) {
            $post = get_post( $id );
            if ( ! $post || $post->post_type !== Doc_Riservato::CPT ) { self::back( 'err' ); }
            if ( $title === '' ) { self::back( 'notitle', [ 'doc_edit' => $id ] ); }
            if ( $files ) {
                $st = self::store_upload( $files[0] );
                if ( ! $st ) { self::back( 'badfile', [ 'doc_edit' => $id ] ); }
                self::delete_private_file( $id );
                update_post_meta( $id, '_gfoss_doc_path', $st[0] );
                update_post_meta( $id, '_gfoss_doc_name', $st[1] );
            }
            wp_update_post( [ 'ID' => $id, 'post_title' => $title, 'post_content' => $desc, 'post_status' => $status ] );
            update_post_meta( $id, '_gfoss_doc_cat', $cat );
            self::back( 'updated' );
        }

        // Nuovi documenti: uno per file
        if ( ! $files ) { self::back( 'nofile' ); }
        $ok = 0; $bad = 0;
        foreach ( $files as $f ) {
            $st = self::store_upload( $f );
            if ( ! $st ) { $bad++; continue; }
            $t = ( count( $files ) === 1 && $title !== '' ) ? $title : self::title_from( $st[1] );
            $pid = wp_insert_post( [
                'post_type'    => Doc_Riservato::CPT,
                'post_status'  => $status,
                'post_title'   => $t,
                'post_content' => $desc,
            ] );
            if ( ! $pid || is_wp_error( $pid ) ) { @unlink( self::dir() . '/' . $st[0] ); $bad++; continue; }
            update_post_meta( $pid, '_gfoss_doc_path', $st[0] );
            update_post_meta( $pid, '_gfoss_doc_name', $st[1] );
            update_post_meta( $pid, '_gfoss_doc_cat',  $cat );
            $ok++;
        }
        self::back( $ok ? 'saved' : 'badfile', [ 'n' => $ok, 'bad' => $bad ] );
    }

    public static function handle_delete(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_doc_fe' );
        $id   = (int) ( $_POST['doc_id'] ?? 0 );
        $post = get_post( $id );
        if ( $post && $post->post_type === Doc_Riservato::CPT ) {
            self::delete_private_file( $id );
            wp_delete_post( $id, true );
        }
        self::back( 'deleted' );
    }

    private static function back( string $msg, array $extra = [] ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        $url = remove_query_arg( [ 'msg', 'doc_edit', 'n', 'bad' ], $url );
        wp_safe_redirect( add_query_arg( array_merge( [ 'msg' => $msg ], $extra ), $url ) );
        exit;
    }

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata al Consiglio Direttivo.</div>';
        }
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $nonce  = wp_nonce_field( 'gfoss_doc_fe', '_wpnonce', true, false );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $edit   = (int) ( $_GET['doc_edit'] ?? 0 );
        $ed     = $edit ? get_post( $edit ) : null;
        if ( $ed && $ed->post_type !== Doc_Riservato::CPT ) { $ed = null; }

        $docs = get_posts( [
            'post_type'      => Doc_Riservato::CPT,
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        $cats = [];
        foreach ( $docs as $d ) {
            $c = (string) get_post_meta( $d->ID, '_gfoss_doc_cat', true );
            if ( $c !== '' ) { $cats[ $c ] = true; }
        }
        ksort( $cats );

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Documenti riservati ai soci</h1><p class="gf-area__sub">Carica modulistica, verbali e materiali visibili solo ai soci in regola con la quota.</p></div></header>';

        $n   = (int) ( $_GET['n'] ?? 0 );
        $bad = (int) ( $_GET['bad'] ?? 0 );
        $notes = [
            'saved'   => [ 'success', ( $n === 1 ? '1 documento caricato.' : $n . ' documenti caricati.' ) . ( $bad ? ' ' . $bad . ' file scartati (formato non ammesso o errore di caricamento).' : '' ) ],
            'updated' => [ 'success', 'Documento aggiornato.' ],
            'deleted' => [ 'success', 'Documento eliminato.' ],
            'nofile'  => [ 'warn', 'Seleziona almeno un file da caricare.' ],
            'notitle' => [ 'warn', 'Il titolo è obbligatorio.' ],
            'badfile' => [ 'warn', 'File non caricato: formato non ammesso o errore di caricamento (max ' . size_format( wp_max_upload_size() ) . ').' ],
            'toobig'  => [ 'warn', 'Invio troppo grande (limite ' . size_format( wp_max_upload_size() ) . ' in totale): carica i file in più volte.' ],
            'err'     => [ 'warn', 'Documento non trovato.' ],
        ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>'; }

        // Form
        $cur_cat = $ed ? (string) get_post_meta( $ed->ID, '_gfoss_doc_cat', true ) : '';
        echo '<section class="gf-card"><h2 style="margin-top:0">' . ( $ed ? 'Modifica documento' : 'Carica documenti' ) . '</h2>';
        echo '<form method="post" action="' . $action . '" class="gf-form" enctype="multipart/form-data">' . $nonce . '<input type="hidden" name="action" value="gfoss_doc_save">';
        if ( $ed ) { echo '<input type="hidden" name="doc_id" value="' . (int) $ed->ID . '">'; }
        echo '<div class="gf-grid">';
        if ( $ed ) {
            $cur = (string) get_post_meta( $ed->ID, '_gfoss_doc_name', true );
            echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Sostituisci file (facoltativo)' . ( $cur ? ' — attuale: ' . esc_html( $cur ) : '' ) . '</span><input type="file" name="files[]"></label>';
        } else {
            echo '<div class="gf-field gf-col-2"><span class="gf-field__lbl">File * (ognuno diventa un documento)</span>'
               . '<label class="gf-dropzone" id="gf-doc-drop" style="display:block;position:relative;border:2px dashed #9bb;border-radius:10px;padding:1.4rem;text-align:center;cursor:pointer">'
               . '<strong>Trascina qui i file</strong> oppure clicca per sceglierli'
               . '<input type="file" name="files[]" id="gf-doc-files" multiple required style="position:absolute;width:1px;height:1px;opacity:0">'
               . '<ul id="gf-doc-list" class="gf-muted" style="list-style:none;margin:.8rem 0 0;padding:0;font-size:.9em;text-align:left"></ul>'
               . '</label></div>';
            ?>
            <script>
            (function(){
                var zone = document.getElementById('gf-doc-drop'), input = document.getElementById('gf-doc-files'), list = document.getElementById('gf-doc-list');
                if (!zone || !input || typeof DataTransfer === 'undefined') return;
                var dt = new DataTransfer();
                function sync(){
                    input.files = dt.files;
                    list.innerHTML = '';
                    Array.prototype.forEach.call(dt.files, function(f, i){
                        var li = document.createElement('li');
                        li.textContent = '📄 ' + f.name + ' (' + Math.round(f.size / 1024) + ' KB) ';
                        var x = document.createElement('button');
                        x.type = 'button'; x.textContent = '✕'; x.className = 'gf-btn gf-btn--ghost gf-btn--sm';
                        x.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); dt.items.remove(i); sync(); });
                        li.appendChild(x); list.appendChild(li);
                    });
                }
                function add(files){ Array.prototype.forEach.call(files, function(f){ if (f.size > 0 || f.type) dt.items.add(f); }); sync(); }
                input.addEventListener('change', function(){ var picked = Array.prototype.slice.call(input.files); input.files = dt.files; add(picked); });
                ['dragenter','dragover'].forEach(function(ev){ zone.addEventListener(ev, function(e){ e.preventDefault(); zone.style.background = 'rgba(93,163,77,.12)'; }); });
                ['dragleave','drop'].forEach(function(ev){ zone.addEventListener(ev, function(e){ e.preventDefault(); zone.style.background = ''; }); });
                zone.addEventListener('drop', function(e){ if (e.dataTransfer && e.dataTransfer.files) add(e.dataTransfer.files); });
            })();
            </script>
            <?php
        }
        echo '<label class="gf-field"><span class="gf-field__lbl">Titolo' . ( $ed ? ' *' : '' ) . '</span><input type="text" name="titolo" value="' . ( $ed ? esc_attr( $ed->post_title ) : '' ) . '"' . ( $ed ? ' required' : ' placeholder="Vuoto = nome del file"' ) . '></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Categoria</span><input type="text" name="categoria" list="gf-doc-cats" value="' . esc_attr( $cur_cat ) . '" placeholder="es. Modulistica, Verbali, Bilanci"></label>';
        echo '<datalist id="gf-doc-cats">';
        foreach ( array_keys( $cats ) as $c ) { echo '<option value="' . esc_attr( $c ) . '">'; }
        echo '</datalist>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Descrizione (facoltativa)</span><textarea name="descrizione" rows="2">' . ( $ed ? esc_textarea( $ed->post_content ) : '' ) . '</textarea></label>';
        $draft = $ed && $ed->post_status === 'draft';
        echo '<label class="gf-field"><span class="gf-field__lbl">Stato</span><select name="stato"><option value="publish"' . selected( ! $draft, true, false ) . '>Pubblicato (visibile ai soci)</option><option value="draft"' . selected( $draft, true, false ) . '>Bozza (nascosto)</option></select></label>';
        echo '</div>';
        echo '<p class="gf-muted" style="font-size:.85em">Formati ammessi: ' . esc_html( implode( ', ', self::EXT ) ) . '. Dimensione massima: ' . esc_html( size_format( wp_max_upload_size() ) ) . ' per invio.</p>';
        echo '<p class="gf-actions"><button class="gf-btn gf-btn--primary">' . ( $ed ? 'Aggiorna' : 'Carica' ) . '</button>';
        if ( $ed ) { echo ' <a class="gf-btn gf-btn--ghost" href="' . esc_url( remove_query_arg( [ 'doc_edit', 'msg' ] ) ) . '">Annulla</a>'; }
        echo '</p></form></section>';

        // Elenco
        echo '<section class="gf-card"><h2 style="margin-top:0">Documenti presenti</h2>';
        if ( ! $docs ) {
            echo '<p class="gf-muted">Nessun documento caricato.</p>';
        } else {
            $rest_nonce = wp_create_nonce( 'wp_rest' );
            echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Titolo</th><th>Categoria</th><th>Stato</th><th>Data</th><th></th></tr></thead><tbody>';
            foreach ( $docs as $d ) {
                $c     = (string) get_post_meta( $d->ID, '_gfoss_doc_cat', true );
                $has   = self::private_path( $d->ID ) !== '' || (int) get_post_meta( $d->ID, '_gfoss_doc_file', true );
                $dl    = add_query_arg( '_wpnonce', $rest_nonce, rest_url( 'gfoss/v1/doc/' . $d->ID ) );
                $stato = $d->post_status === 'draft' ? '<span class="gf-muted">Bozza</span>' : 'Pubblicato';
                echo '<tr><td><strong>' . esc_html( $d->post_title ) . '</strong>' . ( $has ? '' : ' <small class="gf-muted">(senza file)</small>' ) . '</td>';
                echo '<td>' . esc_html( $c ?: 'Generale' ) . '</td><td>' . $stato . '</td><td>' . esc_html( get_the_date( 'd/m/Y', $d ) ) . '</td>';
                echo '<td style="white-space:nowrap">';
                if ( $has && $d->post_status !== 'draft' ) { echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( $dl ) . '">Scarica</a> '; }
                echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( add_query_arg( 'doc_edit', $d->ID, remove_query_arg( 'msg' ) ) ) . '">Modifica</a> ';
                echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'Eliminare definitivamente questo documento e il suo file?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_doc_delete"><input type="hidden" name="doc_id" value="' . (int) $d->ID . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">Elimina</button></form>';
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section></div>';
        return (string) ob_get_clean();
    }
}
