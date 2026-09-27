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

    /** Etichetta e variante colore del badge formato. @return array{0:string,1:string} */
    private static function type_badge( string $filename ): array {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $map = [
            'pdf' => 'pdf',
            'odt' => 'doc', 'ott' => 'doc', 'doc' => 'doc', 'docx' => 'doc', 'rtf' => 'doc', 'txt' => 'doc',
            'ods' => 'xls', 'ots' => 'xls', 'xls' => 'xls', 'xlsx' => 'xls', 'csv' => 'xls',
            'odp' => 'ppt', 'otp' => 'ppt', 'ppt' => 'ppt', 'pptx' => 'ppt',
            'zip' => 'zip', '7z' => 'zip',
            'png' => 'img', 'jpg' => 'img', 'jpeg' => 'img', 'svg' => 'img', 'odg' => 'img',
        ];
        return [ $ext !== '' ? strtoupper( $ext ) : '—', $map[ $ext ] ?? 'zip' ];
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
        $max    = size_format( wp_max_upload_size() );

        $docs = get_posts( [
            'post_type'      => Doc_Riservato::CPT,
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        $by_cat = [];
        foreach ( $docs as $d ) {
            $c = (string) get_post_meta( $d->ID, '_gfoss_doc_cat', true );
            $by_cat[ $c !== '' ? $c : 'Generale' ][] = $d;
        }
        ksort( $by_cat );
        $published = count( array_filter( $docs, static fn( $d ) => $d->post_status !== 'draft' ) );
        $area_pg   = (int) get_option( 'gfoss_page_documenti_soci' );

        ob_start();
        echo '<div class="gf-area gf-docman">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Documenti riservati ai soci</h1>'
           . '<p class="gf-area__sub">Modulistica, verbali e materiali visibili solo ai soci in regola con la quota.</p></div>';
        if ( $area_pg ) {
            echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( get_permalink( $area_pg ) ) . '" target="_blank" rel="noopener">Vedi come la vedono i soci ↗</a>';
        }
        echo '</header>';

        $n   = (int) ( $_GET['n'] ?? 0 );
        $bad = (int) ( $_GET['bad'] ?? 0 );
        $notes = [
            'saved'   => [ 'ok',   ( $n === 1 ? '1 documento caricato.' : $n . ' documenti caricati.' ) . ( $bad ? ' ' . $bad . ' file scartati (formato non ammesso o errore di caricamento).' : '' ) ],
            'updated' => [ 'ok',   'Documento aggiornato.' ],
            'deleted' => [ 'ok',   'Documento eliminato.' ],
            'nofile'  => [ 'warn', 'Seleziona almeno un file da caricare.' ],
            'notitle' => [ 'warn', 'Il titolo è obbligatorio.' ],
            'badfile' => [ 'warn', 'File non caricato: formato non ammesso o errore di caricamento (max ' . $max . ').' ],
            'toobig'  => [ 'warn', 'Invio troppo grande (limite ' . $max . ' in totale): carica i file in più volte.' ],
            'err'     => [ 'warn', 'Documento non trovato.' ],
        ];
        if ( isset( $notes[ $msg ] ) ) {
            echo '<div class="gf-card--' . esc_attr( $notes[ $msg ][0] ) . ' gf-docman__note">' . ( $notes[ $msg ][0] === 'ok' ? '✓ ' : '⚠ ' ) . esc_html( $notes[ $msg ][1] ) . '</div>';
        }

        // KPI
        echo '<div class="gf-kpis gf-docman__kpis">';
        foreach ( [ [ count( $docs ), 'Documenti', '#1A6FA0' ], [ $published, 'Visibili ai soci', '#5DA34D' ], [ count( $by_cat ), 'Categorie', '#B26A00' ] ] as $k ) {
            echo '<div class="gf-kpi"><div class="gf-kpi__num" style="color:' . esc_attr( $k[2] ) . '">' . (int) $k[0] . '</div><div class="gf-kpi__lbl">' . esc_html( $k[1] ) . '</div></div>';
        }
        echo '</div>';

        // ---- Form caricamento / modifica
        $cur_cat = $ed ? (string) get_post_meta( $ed->ID, '_gfoss_doc_cat', true ) : '';
        $draft   = $ed && $ed->post_status === 'draft';
        echo '<section class="gf-area__card gf-docman__upload' . ( $ed ? ' is-edit' : '' ) . '" id="gf-doc-form">';
        echo '<header class="gf-area__card-head"><h2>' . ( $ed ? '✏️ Modifica «' . esc_html( $ed->post_title ) . '»' : '⬆️ Carica nuovi documenti' ) . '</h2>';
        if ( $ed ) { echo '<a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( remove_query_arg( [ 'doc_edit', 'msg' ] ) ) . '">Annulla</a>'; }
        echo '</header>';
        echo '<form method="post" action="' . $action . '" class="gf-form" enctype="multipart/form-data">' . $nonce . '<input type="hidden" name="action" value="gfoss_doc_save">';
        if ( $ed ) { echo '<input type="hidden" name="doc_id" value="' . (int) $ed->ID . '">'; }

        $cur_name = $ed ? (string) get_post_meta( $ed->ID, '_gfoss_doc_name', true ) : '';
        echo '<label class="gf-drop" id="gf-doc-drop">'
           . '<input type="file" name="files[]" id="gf-doc-files"' . ( $ed ? '' : ' multiple required' ) . ' class="gf-drop__input">'
           . '<span class="gf-drop__ico" aria-hidden="true">📂</span>'
           . '<span class="gf-drop__title">' . ( $ed ? 'Trascina qui il nuovo file per sostituirlo' : 'Trascina qui i file' ) . '</span>'
           . '<span class="gf-drop__sub">oppure <u>clicca per sceglierli</u> dal computer · max ' . esc_html( $max ) . ' per invio</span>'
           . ( $cur_name !== '' ? '<span class="gf-drop__sub">File attuale: <strong>' . esc_html( $cur_name ) . '</strong> (lascia vuoto per mantenerlo)</span>' : '' )
           . '</label>';
        echo '<ul class="gf-chips" id="gf-doc-list" aria-live="polite"></ul>';

        echo '<div class="gf-grid gf-docman__fields">';
        echo '<label class="gf-field"><span class="gf-field__lbl">Titolo' . ( $ed ? ' *' : '' ) . '</span><input type="text" name="titolo" value="' . ( $ed ? esc_attr( $ed->post_title ) : '' ) . '"' . ( $ed ? ' required' : ' placeholder="Se vuoto, uso il nome del file"' ) . '></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Categoria</span><input type="text" name="categoria" list="gf-doc-cats" value="' . esc_attr( $cur_cat ) . '" placeholder="es. Modulistica, Verbali, Bilanci"></label>';
        echo '<datalist id="gf-doc-cats">';
        foreach ( array_keys( $by_cat ) as $c ) { echo '<option value="' . esc_attr( $c ) . '">'; }
        echo '</datalist>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Descrizione <small class="gf-muted">(facoltativa, la vedono i soci sotto il titolo)</small></span><textarea name="descrizione" rows="2">' . ( $ed ? esc_textarea( $ed->post_content ) : '' ) . '</textarea></label>';
        echo '</div>';

        echo '<div class="gf-docman__foot">';
        echo '<div class="gf-seg" role="radiogroup" aria-label="Visibilità">'
           . '<label><input type="radio" name="stato" value="publish"' . checked( ! $draft, true, false ) . '><span>👁 Pubblicato</span></label>'
           . '<label><input type="radio" name="stato" value="draft"' . checked( $draft, true, false ) . '><span>🔒 Bozza</span></label>'
           . '</div>';
        echo '<button class="gf-btn gf-btn--primary" id="gf-doc-submit">' . ( $ed ? 'Salva modifiche' : 'Carica documenti' ) . '</button>';
        echo '</div>';
        echo '<details class="gf-docman__formats"><summary>Formati ammessi</summary><p class="gf-muted">' . esc_html( implode( ', ', self::EXT ) ) . '</p></details>';
        echo '</form></section>';

        // ---- Elenco per categoria
        echo '<section class="gf-area__card gf-docman__list">';
        echo '<header class="gf-area__card-head"><h2>📚 Documenti presenti</h2>';
        if ( $docs ) { echo '<input type="search" class="gf-docman__search" id="gf-doc-search" placeholder="🔍 Cerca per titolo o file…">'; }
        echo '</header>';
        if ( ! $docs ) {
            echo '<div class="gf-docman__empty"><span aria-hidden="true">🗂️</span><p>Nessun documento caricato.<br><small class="gf-muted">Trascina i primi file nel riquadro qui sopra.</small></p></div>';
        } else {
            $rest_nonce = wp_create_nonce( 'wp_rest' );
            foreach ( $by_cat as $cat => $list ) {
                echo '<div class="gf-docman__cat"><h3>' . esc_html( $cat ) . ' <span class="gf-docman__count">' . count( $list ) . '</span></h3><ul class="gf-docrows">';
                foreach ( $list as $d ) {
                    $fname = (string) get_post_meta( $d->ID, '_gfoss_doc_name', true );
                    $ppath = self::private_path( $d->ID );
                    $size  = ( $ppath && is_file( $ppath ) ) ? size_format( (int) filesize( $ppath ) ) : '';
                    if ( $fname === '' ) {
                        $att   = (int) get_post_meta( $d->ID, '_gfoss_doc_file', true );
                        $apath = $att ? (string) get_attached_file( $att ) : '';
                        $fname = $apath ? basename( $apath ) : '';
                        $size  = ( $apath && is_file( $apath ) ) ? size_format( (int) filesize( $apath ) ) : '';
                    }
                    [ $lbl, $kind ] = self::type_badge( $fname );
                    $is_draft = $d->post_status === 'draft';
                    $dl = add_query_arg( '_wpnonce', $rest_nonce, rest_url( 'gfoss/v1/doc/' . $d->ID ) );
                    $search = strtolower( $d->post_title . ' ' . $fname . ' ' . $cat );

                    echo '<li class="gf-docrow' . ( $is_draft ? ' is-draft' : '' ) . ( $ed && $ed->ID === $d->ID ? ' is-current' : '' ) . '" data-search="' . esc_attr( $search ) . '">';
                    echo '<span class="gf-ftype gf-ftype--' . esc_attr( $kind ) . '">' . esc_html( $lbl ) . '</span>';
                    echo '<div class="gf-docrow__main"><strong>' . esc_html( $d->post_title ) . '</strong>'
                       . '<small>' . ( $fname !== '' ? esc_html( $fname ) : '<em>nessun file allegato</em>' ) . ( $size ? ' · ' . esc_html( $size ) : '' ) . ' · ' . esc_html( get_the_date( 'd/m/Y', $d ) ) . '</small></div>';
                    echo '<span class="chip ' . ( $is_draft ? 'chip--warn">Bozza' : 'chip--ok">Pubblicato' ) . '</span>';
                    echo '<div class="gf-docrow__act">';
                    if ( $fname !== '' ) { echo '<a class="gf-iconbtn" href="' . esc_url( $dl ) . '" title="Scarica" aria-label="Scarica">⬇</a>'; }
                    echo '<a class="gf-iconbtn" href="' . esc_url( add_query_arg( 'doc_edit', $d->ID, remove_query_arg( 'msg' ) ) ) . '#gf-doc-form" title="Modifica" aria-label="Modifica">✎</a>';
                    echo '<form method="post" action="' . $action . '" onsubmit="return confirm(\'Eliminare definitivamente «' . esc_js( $d->post_title ) . '» e il suo file?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_doc_delete"><input type="hidden" name="doc_id" value="' . (int) $d->ID . '"><button class="gf-iconbtn gf-iconbtn--danger" title="Elimina" aria-label="Elimina">🗑</button></form>';
                    echo '</div></li>';
                }
                echo '</ul></div>';
            }
            echo '<p class="gf-muted gf-docman__nores" id="gf-doc-nores" hidden>Nessun documento corrisponde alla ricerca.</p>';
        }
        echo '</section></div>';
        ?>
        <script>
        (function(){
            var zone = document.getElementById('gf-doc-drop'), input = document.getElementById('gf-doc-files'),
                list = document.getElementById('gf-doc-list'), multi = input && input.multiple;
            if (zone && input) {
                var dt = (typeof DataTransfer !== 'undefined') ? new DataTransfer() : null;
                function human(b){ return b > 1048576 ? (b/1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b/1024)) + ' KB'; }
                function render(files){
                    list.innerHTML = '';
                    Array.prototype.forEach.call(files, function(f, i){
                        var li = document.createElement('li'), ext = (f.name.split('.').pop() || '').toUpperCase();
                        li.className = 'gf-chip';
                        li.innerHTML = '<span class="gf-chip__ext"></span><span class="gf-chip__name"></span><span class="gf-chip__size"></span>';
                        li.children[0].textContent = ext; li.children[1].textContent = f.name; li.children[2].textContent = human(f.size);
                        if (dt) {
                            var x = document.createElement('button');
                            x.type = 'button'; x.className = 'gf-chip__x'; x.textContent = '✕'; x.title = 'Togli';
                            x.addEventListener('click', function(){ dt.items.remove(i); sync(); });
                            li.appendChild(x);
                        }
                        list.appendChild(li);
                    });
                    zone.classList.toggle('has-files', files.length > 0);
                }
                function sync(){ input.files = dt.files; render(dt.files); }
                function add(files){
                    if (!dt) { render(input.files); return; }
                    if (!multi) { dt.items.clear(); files = [files[0]]; }
                    Array.prototype.forEach.call(files, function(f){ if (f) dt.items.add(f); });
                    sync();
                }
                input.addEventListener('change', function(){
                    if (!dt) { render(input.files); return; }
                    var picked = Array.prototype.slice.call(input.files); input.files = dt.files; add(picked);
                });
                ['dragenter','dragover'].forEach(function(ev){ zone.addEventListener(ev, function(e){ e.preventDefault(); zone.classList.add('is-over'); }); });
                ['dragleave','drop'].forEach(function(ev){ zone.addEventListener(ev, function(e){ e.preventDefault(); zone.classList.remove('is-over'); }); });
                zone.addEventListener('drop', function(e){ if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });
                // evita che un file lasciato fuori dal riquadro venga aperto dal browser
                window.addEventListener('dragover', function(e){ e.preventDefault(); });
                window.addEventListener('drop', function(e){ e.preventDefault(); });
                var form = zone.closest('form'), btn = document.getElementById('gf-doc-submit');
                if (form && btn) form.addEventListener('submit', function(){ btn.disabled = true; btn.textContent = 'Caricamento in corso…'; });
            }
            var q = document.getElementById('gf-doc-search');
            if (q) q.addEventListener('input', function(){
                var t = q.value.trim().toLowerCase(), any = false;
                document.querySelectorAll('.gf-docman__cat').forEach(function(cat){
                    var vis = 0;
                    cat.querySelectorAll('.gf-docrow').forEach(function(r){ var ok = !t || r.dataset.search.indexOf(t) !== -1; r.hidden = !ok; if (ok) vis++; });
                    cat.hidden = vis === 0; if (vis) any = true;
                });
                document.getElementById('gf-doc-nores').hidden = any;
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
