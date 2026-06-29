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
        add_action( 'admin_post_gfoss_acc_fe_save',   [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_gfoss_acc_fe_delete', [ __CLASS__, 'handle_delete' ] );
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

        $notes = [ 'saved' => [ 'success', 'Movimento salvato.' ], 'deleted' => [ 'success', 'Movimento eliminato.' ], 'err' => [ 'warn', 'Controlla categoria e importo.' ] ];
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

        echo '</div>';
        return (string) ob_get_clean();
    }
}
