<?php
namespace GFOSS_Accounting;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use GFOSS_Members\Roles;

/**
 * Console contabilità front-end (riservata a tesoreria: CAP_VIEW_ACCOUNTING,
 * scrittura con CAP_MANAGE_ACCOUNTING). Shortcode [gfoss_contabilita].
 * Riusa il modello Movement e la grafica gf-* dell'area soci.
 */
class Frontend {

    public static function init(): void {
        add_shortcode( 'gfoss_contabilita', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
        add_action( 'admin_post_gfoss_acc_fe_save',     [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_gfoss_acc_fe_delete',   [ __CLASS__, 'handle_delete' ] );
        add_action( 'admin_post_gfoss_acc_cassa_save',  [ __CLASS__, 'handle_cassa_save' ] );
        add_action( 'admin_post_gfoss_acc_bilancio_pdf',[ __CLASS__, 'handle_bilancio_pdf' ] );
        add_action( 'admin_post_gfoss_acc_ricevute_csv',[ __CLASS__, 'handle_ricevute_csv' ] );
    }

    public static function handle_ricevute_csv(): void {
        if ( ! self::can_view() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_acc_fe' );
        $year = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );
        $rows = \GFOSS_Members\Quote::ricevute_for_year( $year );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="registro-ricevute-' . $year . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" ); // BOM UTF-8 per Excel
        fputcsv( $out, [ 'Numero', 'Anno', 'Data pagamento', 'Socio', 'Email', 'Importo', 'Metodo', 'Data verbale', 'Pagatore' ] );
        foreach ( $rows as $r ) {
            fputcsv( $out, [ $r['ricevuta_numero'], $r['anno'], $r['data_pagamento'], $r['display_name'], $r['user_email'], number_format( (float) $r['importo'], 2, ',', '' ), $r['metodo'], $r['verbale_data'], $r['pagatore_nome'] ] );
        }
        fclose( $out );
        exit;
    }

    public static function handle_cassa_save(): void {
        if ( ! self::can_edit() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_acc_fe' );
        $year = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );
        $val  = (float) str_replace( ',', '.', (string) ( $_POST['cassa_iniziale'] ?? '0' ) );
        Bilancio::set_cassa_iniziale( $year, $val );
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( [ 'acc_anno' => $year, 'msg' => 'cassa' ], remove_query_arg( [ 'msg', 'mov_edit' ], $url ) ) );
        exit;
    }

    public static function handle_bilancio_pdf(): void {
        if ( ! self::can_view() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_acc_fe' );
        $year = (int) ( $_POST['anno'] ?? gmdate( 'Y' ) );
        $pdf  = Bilancio::generate_pdf( $year );
        if ( $pdf instanceof \WP_Error ) { self::back( 'pdf_err' ); }
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="rendiconto-cassa-gfoss-' . $year . '.pdf"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf;
        exit;
    }

    public static function maybe_enqueue(): void {
        if ( ! is_singular() || ! defined( 'GFOSS_MEMBERS_URL' ) ) { return; }
        global $post;
        if ( ! $post || ! has_shortcode( (string) $post->post_content, 'gfoss_contabilita' ) ) { return; }
        wp_enqueue_style( 'gfoss-members-form', GFOSS_MEMBERS_URL . 'assets/css/form.css', [], GFOSS_MEMBERS_VERSION );
        wp_enqueue_style( 'gfoss-members-area', GFOSS_MEMBERS_URL . 'assets/css/area.css', [ 'gfoss-members-form' ], GFOSS_MEMBERS_VERSION );
    }

    private static function can_view(): bool { return is_user_logged_in() && current_user_can( Roles::CAP_VIEW_ACCOUNTING ); }
    private static function can_edit(): bool { return current_user_can( Roles::CAP_MANAGE_ACCOUNTING ); }

    private static function tipo_for_slug( string $slug ): string {
        foreach ( Movement::categories() as $c ) {
            if ( $c['slug'] === $slug ) { return $c['tipo']; }
        }
        return 'entrata';
    }

    private static function label_map(): array {
        $map = [];
        foreach ( Movement::categories() as $c ) { $map[ $c['slug'] ] = $c['label']; }
        return $map;
    }

    private static function back( string $msg ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'msg', $msg, remove_query_arg( [ 'msg', 'mov_edit' ], $url ) ) );
        exit;
    }

    public static function handle_save(): void {
        if ( ! self::can_edit() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_acc_fe' );
        $slug = sanitize_key( (string) ( $_POST['categoria_slug'] ?? '' ) );
        $data = [
            'data'           => sanitize_text_field( wp_unslash( $_POST['data'] ?? gmdate( 'Y-m-d' ) ) ),
            'tipo'           => self::tipo_for_slug( $slug ),
            'categoria_slug' => $slug,
            'importo'        => (float) str_replace( ',', '.', (string) ( $_POST['importo'] ?? '0' ) ),
            'descrizione'    => sanitize_text_field( wp_unslash( $_POST['descrizione'] ?? '' ) ),
            'metodo'         => sanitize_text_field( wp_unslash( $_POST['metodo'] ?? '' ) ),
            'fin_5x1000'     => ! empty( $_POST['fin_5x1000'] ) ? 1 : 0,
        ];
        if ( $slug === '' || $data['importo'] <= 0 ) { self::back( 'err' ); }

        $id = (int) ( $_POST['mov_id'] ?? 0 );
        if ( $id ) { Movement::update( $id, $data ); } else { Movement::create( $data ); }
        self::back( 'saved' );
    }

    public static function handle_delete(): void {
        if ( ! self::can_edit() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_acc_fe' );
        Movement::delete( (int) ( $_POST['mov_id'] ?? 0 ) );
        self::back( 'deleted' );
    }

    public static function render(): string {
        if ( ! self::can_view() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata alla tesoreria del Consiglio Direttivo.</div>';
        }
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $nonce  = wp_nonce_field( 'gfoss_acc_fe', '_wpnonce', true, false );
        $year   = (int) ( $_GET['acc_anno'] ?? gmdate( 'Y' ) );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $edit   = (int) ( $_GET['mov_edit'] ?? 0 );
        $ed     = $edit ? Movement::get( $edit ) : null;
        $labels = self::label_map();
        $tot    = Movement::totals_year( $year );
        $eur    = static fn( $v ) => number_format_i18n( (float) $v, 2 ) . ' €';

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Tesoreria</p><h1 class="gf-area__title">Contabilità ' . (int) $year . '</h1><p class="gf-area__sub">Movimenti, saldo e rendiconto dell\'anno.</p></div></header>';

        $notes = [ 'saved' => [ 'success', 'Movimento salvato.' ], 'deleted' => [ 'success', 'Movimento eliminato.' ], 'err' => [ 'warn', 'Controlla categoria e importo.' ], 'cassa' => [ 'success', 'Cassa iniziale salvata.' ], 'pdf_err' => [ 'warn', 'Generazione PDF non riuscita (mPDF).' ] ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>'; }

        // KPI
        echo '<div class="gf-kpis">';
        echo '<div class="gf-kpi"><div class="gf-kpi__num" style="color:#5DA34D">' . esc_html( $eur( $tot['tot_entrate'] ) ) . '</div><div class="gf-kpi__lbl">Entrate</div></div>';
        echo '<div class="gf-kpi"><div class="gf-kpi__num" style="color:#C0392B">' . esc_html( $eur( $tot['tot_uscite'] ) ) . '</div><div class="gf-kpi__lbl">Uscite</div></div>';
        echo '<div class="gf-kpi"><div class="gf-kpi__num" style="color:' . ( $tot['saldo'] >= 0 ? '#1A6FA0' : '#C0392B' ) . '">' . esc_html( $eur( $tot['saldo'] ) ) . '</div><div class="gf-kpi__lbl">Saldo</div></div>';
        echo '</div>';

        // Selettore anno
        echo '<form method="get" style="margin-bottom:1rem"><label>Anno: <select name="acc_anno" class="gf-select" onchange="this.form.submit()" style="max-width:140px">';
        for ( $y = (int) gmdate( 'Y' ) + 1; $y >= 2007; $y-- ) { echo '<option value="' . $y . '" ' . selected( $y, $year, false ) . '>' . $y . '</option>'; }
        echo '</select></label></form>';

        // Form movimento (solo chi può scrivere)
        if ( self::can_edit() ) {
            echo '<section class="gf-card"><h2 style="margin-top:0">' . ( $ed ? 'Modifica movimento' : 'Nuovo movimento' ) . '</h2>';
            echo '<form method="post" action="' . $action . '" class="gf-form">' . $nonce . '<input type="hidden" name="action" value="gfoss_acc_fe_save">';
            if ( $ed ) { echo '<input type="hidden" name="mov_id" value="' . (int) $ed['id'] . '">'; }
            echo '<div class="gf-grid">';
            echo '<label class="gf-field"><span class="gf-field__lbl">Data</span><input type="date" name="data" value="' . esc_attr( $ed['data'] ?? gmdate( 'Y-m-d' ) ) . '"></label>';
            echo '<label class="gf-field"><span class="gf-field__lbl">Categoria *</span><select name="categoria_slug" required>';
            foreach ( [ 'entrata' => 'Entrate', 'uscita' => 'Uscite' ] as $tp => $glab ) {
                echo '<optgroup label="' . esc_attr( $glab ) . '">';
                foreach ( Movement::categories( $tp ) as $c ) {
                    echo '<option value="' . esc_attr( $c['slug'] ) . '" ' . selected( $ed['categoria_slug'] ?? '', $c['slug'], false ) . '>' . esc_html( $c['label'] ) . '</option>';
                }
                echo '</optgroup>';
            }
            echo '</select></label>';
            echo '<label class="gf-field"><span class="gf-field__lbl">Importo (€) *</span><input type="text" name="importo" value="' . esc_attr( $ed ? number_format( (float) $ed['importo'], 2, '.', '' ) : '' ) . '" required></label>';
            echo '<label class="gf-field"><span class="gf-field__lbl">Metodo</span><select name="metodo">';
            foreach ( [ 'bonifico' => 'Bonifico', 'contanti' => 'Contanti', 'paypal' => 'PayPal', 'carta' => 'Carta', 'altro' => 'Altro' ] as $k => $v ) {
                echo '<option value="' . esc_attr( $k ) . '" ' . selected( $ed['metodo'] ?? '', $k, false ) . '>' . esc_html( $v ) . '</option>';
            }
            echo '</select></label>';
            echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Descrizione</span><input type="text" name="descrizione" value="' . esc_attr( $ed['descrizione'] ?? '' ) . '"></label>';
            echo '<label class="gf-check gf-col-2"><input type="checkbox" name="fin_5x1000" value="1" ' . checked( ! empty( $ed['fin_5x1000'] ), true, false ) . '> Spesa finanziata dal 5×1000 (rendicontazione)</label>';
            echo '</div><p class="gf-actions"><button class="gf-btn gf-btn--primary">' . ( $ed ? 'Salva' : 'Aggiungi movimento' ) . '</button>';
            if ( $ed ) { echo ' <a class="gf-btn gf-btn--ghost" href="' . esc_url( remove_query_arg( [ 'mov_edit', 'msg' ] ) ) . '">Annulla</a>'; }
            echo '</p></form></section>';
        }

        // Elenco movimenti dell'anno
        $list = Movement::paginated( [ 'anno' => $year ], 1, 200 );
        echo '<section class="gf-card"><h2 style="margin-top:0">Movimenti ' . (int) $year . ' (' . (int) $list['total_count'] . ')</h2>';
        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Data</th><th>Categoria</th><th>Descrizione</th><th style="text-align:right">Importo</th>' . ( self::can_edit() ? '<th></th>' : '' ) . '</tr></thead><tbody>';
        if ( ! $list['rows'] ) { echo '<tr><td colspan="5" class="gf-muted">Nessun movimento.</td></tr>'; }
        foreach ( $list['rows'] as $r ) {
            $segno = $r['tipo'] === 'entrata' ? '+' : '−';
            $col   = $r['tipo'] === 'entrata' ? '#5DA34D' : '#C0392B';
            echo '<tr><td>' . esc_html( date_i18n( 'd/m/Y', strtotime( $r['data'] ) ) ) . '</td>';
            echo '<td>' . esc_html( $labels[ $r['categoria_slug'] ] ?? $r['categoria_slug'] ) . ( ! empty( $r['fin_5x1000'] ) ? ' <small class="gf-muted">(5×1000)</small>' : '' ) . '</td>';
            echo '<td>' . esc_html( (string) $r['descrizione'] ) . '</td>';
            echo '<td style="text-align:right;color:' . $col . ';font-weight:600">' . $segno . ' ' . esc_html( $eur( $r['importo'] ) ) . '</td>';
            if ( self::can_edit() ) {
                echo '<td style="white-space:nowrap"><a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( add_query_arg( 'mov_edit', (int) $r['id'], remove_query_arg( 'msg' ) ) ) . '">Modifica</a> '
                    . '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'Eliminare il movimento?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_acc_fe_delete"><input type="hidden" name="mov_id" value="' . (int) $r['id'] . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">Elimina</button></form></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';

        // Rendiconto sintetico per categoria
        echo '<section class="gf-card"><h2 style="margin-top:0">Rendiconto ' . (int) $year . '</h2><div class="gf-grid">';
        echo '<div class="gf-col-1"><h3>Entrate</h3><table class="gf-table"><tbody>';
        foreach ( $tot['entrate'] as $slug => $imp ) { echo '<tr><td>' . esc_html( $labels[ $slug ] ?? $slug ) . '</td><td style="text-align:right">' . esc_html( $eur( $imp ) ) . '</td></tr>'; }
        echo '<tr><td><strong>Totale entrate</strong></td><td style="text-align:right"><strong>' . esc_html( $eur( $tot['tot_entrate'] ) ) . '</strong></td></tr></tbody></table></div>';
        echo '<div class="gf-col-1"><h3>Uscite</h3><table class="gf-table"><tbody>';
        foreach ( $tot['uscite'] as $slug => $imp ) { echo '<tr><td>' . esc_html( $labels[ $slug ] ?? $slug ) . '</td><td style="text-align:right">' . esc_html( $eur( $imp ) ) . '</td></tr>'; }
        echo '<tr><td><strong>Totale uscite</strong></td><td style="text-align:right"><strong>' . esc_html( $eur( $tot['tot_uscite'] ) ) . '</strong></td></tr></tbody></table></div>';
        echo '</div><p style="margin-top:.6rem"><strong>Saldo ' . (int) $year . ': ' . esc_html( $eur( $tot['saldo'] ) ) . '</strong></p></section>';

        // Bilancio — Rendiconto per cassa (Modello D)
        echo '<section class="gf-card"><h2 style="margin-top:0">Bilancio — Rendiconto per cassa ' . (int) $year . '</h2>';
        echo '<p class="gf-muted">Genera la <strong>bozza</strong> del rendiconto per cassa (Modello D, D.M. 5/3/2020) riclassificando i movimenti nelle sezioni ETS. La validazione finale spetta al commercialista; la disponibilità di cassa iniziale va impostata a mano.</p>';
        if ( self::can_edit() ) {
            echo '<form method="post" action="' . $action . '" style="margin-bottom:.8rem;display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">' . $nonce . '<input type="hidden" name="action" value="gfoss_acc_cassa_save"><input type="hidden" name="anno" value="' . (int) $year . '">';
            echo '<label class="gf-field" style="max-width:260px"><span class="gf-field__lbl">Cassa e banca al 1°/1/' . (int) $year . ' (€)</span><input type="text" name="cassa_iniziale" value="' . esc_attr( number_format( Bilancio::cassa_iniziale( $year ), 2, '.', '' ) ) . '"></label>';
            echo '<button class="gf-btn gf-btn--ghost">Salva cassa iniziale</button></form>';
        } else {
            echo '<p>Cassa iniziale ' . (int) $year . ': <strong>' . esc_html( $eur( Bilancio::cassa_iniziale( $year ) ) ) . '</strong></p>';
        }
        echo '<form method="post" action="' . $action . '">' . $nonce . '<input type="hidden" name="action" value="gfoss_acc_bilancio_pdf"><input type="hidden" name="anno" value="' . (int) $year . '"><button class="gf-btn gf-btn--primary">⬇ Genera Rendiconto per cassa (PDF)</button></form>';
        echo '</section>';

        // Registro ricevute emesse
        $ric_list = \GFOSS_Members\Quote::ricevute_for_year( $year );
        $can_dl   = current_user_can( Roles::CAP_MANAGE_QUOTE ) || current_user_can( Roles::CAP_MANAGE_SOCI );
        $tot_ric  = array_sum( array_map( static fn( $r ) => (float) $r['importo'], $ric_list ) );
        echo '<section class="gf-card"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem"><h2 style="margin:0">Registro ricevute ' . (int) $year . ' (' . count( $ric_list ) . ')</h2>';
        echo '<form method="post" action="' . $action . '" style="margin:0">' . $nonce . '<input type="hidden" name="action" value="gfoss_acc_ricevute_csv"><input type="hidden" name="anno" value="' . (int) $year . '"><button class="gf-btn gf-btn--ghost gf-btn--sm"' . ( $ric_list ? '' : ' disabled' ) . '>⬇ Esporta CSV</button></form></div>';
        echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>N.</th><th>Data</th><th>Socio</th><th style="text-align:right">Importo</th><th>Metodo</th>' . ( $can_dl ? '<th></th>' : '' ) . '</tr></thead><tbody>';
        if ( ! $ric_list ) { echo '<tr><td colspan="6" class="gf-muted">Nessuna ricevuta emessa per il ' . (int) $year . '.</td></tr>'; }
        foreach ( $ric_list as $r ) {
            echo '<tr><td><strong>' . (int) $r['ricevuta_numero'] . '/' . (int) $year . '</strong></td>';
            echo '<td>' . esc_html( $r['data_pagamento'] ? date_i18n( 'd/m/Y', strtotime( $r['data_pagamento'] ) ) : '—' ) . '</td>';
            echo '<td>' . esc_html( (string) $r['display_name'] ) . '</td>';
            echo '<td style="text-align:right">' . esc_html( $eur( $r['importo'] ) ) . '</td>';
            echo '<td>' . esc_html( (string) $r['metodo'] ) . '</td>';
            if ( $can_dl ) { echo '<td><a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( \GFOSS_Members\Ricevuta::download_url( (int) $r['user_id'], (int) $year ) ) . '">PDF</a></td>'; }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="gf-muted" style="margin-top:.5rem">Totale importi ricevute: <strong>' . esc_html( $eur( $tot_ric ) ) . '</strong></p>';
        echo '</section>';

        echo '</div>';
        return (string) ob_get_clean();
    }
}
