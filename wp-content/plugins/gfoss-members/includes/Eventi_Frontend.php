<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end per la gestione degli Eventi (riservata a chi può pubblicare:
 * comunicazione, consiglieri, presidente, segreteria, admin).
 * Shortcode [gfoss_gestione_eventi].
 */
class Eventi_Frontend {

    const META = [ '_gf_ev_data', '_gf_ev_data_fine', '_gf_ev_luogo', '_gf_ev_indirizzo', '_gf_ev_url', '_gf_ev_scadenza', '_gf_ev_posti' ];

    public static function init(): void {
        add_shortcode( 'gfoss_gestione_eventi', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_evento_save',   [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_gfoss_evento_delete', [ __CLASS__, 'handle_delete' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && current_user_can( 'edit_posts' );
    }

    public static function handle_save(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_evento' );

        $id    = (int) ( $_POST['evento_id'] ?? 0 );
        $title = sanitize_text_field( wp_unslash( $_POST['titolo'] ?? '' ) );
        $body  = wp_kses_post( wp_unslash( $_POST['descrizione'] ?? '' ) );
        if ( $title === '' ) { self::back( 'err' ); }

        $postarr = [ 'post_type' => Eventi::CPT, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $body ];
        if ( $id ) { $postarr['ID'] = $id; $id = (int) wp_update_post( $postarr ); }
        else       { $id = (int) wp_insert_post( $postarr ); }
        if ( ! $id ) { self::back( 'err' ); }

        update_post_meta( $id, '_gf_ev_data',      sanitize_text_field( wp_unslash( $_POST['data'] ?? '' ) ) );
        update_post_meta( $id, '_gf_ev_data_fine', sanitize_text_field( wp_unslash( $_POST['data_fine'] ?? '' ) ) );
        update_post_meta( $id, '_gf_ev_luogo',     sanitize_text_field( wp_unslash( $_POST['luogo'] ?? '' ) ) );
        update_post_meta( $id, '_gf_ev_indirizzo', sanitize_text_field( wp_unslash( $_POST['indirizzo'] ?? '' ) ) );
        update_post_meta( $id, '_gf_ev_url',       esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ) );
        update_post_meta( $id, '_gf_ev_scadenza',  sanitize_text_field( wp_unslash( $_POST['scadenza'] ?? '' ) ) );
        update_post_meta( $id, '_gf_ev_posti',     (int) ( $_POST['posti'] ?? 0 ) );

        self::back( 'saved' );
    }

    public static function handle_delete(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_evento' );
        wp_trash_post( (int) ( $_POST['evento_id'] ?? 0 ) );
        self::back( 'deleted' );
    }

    private static function back( string $msg ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'msg', $msg, remove_query_arg( [ 'msg', 'ev_edit' ], $url ) ) );
        exit;
    }

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata a chi gestisce gli eventi (Comunicazione/Direttivo).</div>';
        }

        $action = esc_url( admin_url( 'admin-post.php' ) );
        $nonce  = wp_nonce_field( 'gfoss_evento', '_wpnonce', true, false );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $edit   = (int) ( $_GET['ev_edit'] ?? 0 );
        $ed     = $edit ? get_post( $edit ) : null;
        $m      = static fn( string $k ) => $ed ? esc_attr( (string) get_post_meta( $ed->ID, $k, true ) ) : '';

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Consiglio Direttivo</p><h1 class="gf-area__title">Gestione eventi</h1><p class="gf-area__sub">Crea e modifica gli eventi del sito. A questi si agganciano le liste volontari.</p></div></header>';

        $notes = [ 'saved' => [ 'success', 'Evento salvato.' ], 'deleted' => [ 'success', 'Evento spostato nel cestino.' ], 'err' => [ 'warn', 'Controlla i dati: il titolo è obbligatorio.' ] ];
        if ( isset( $notes[ $msg ] ) ) {
            echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>';
        }

        // Form crea/modifica
        echo '<section class="gf-card"><h2 style="margin-top:0">' . ( $ed ? 'Modifica evento' : 'Nuovo evento' ) . '</h2>';
        echo '<form method="post" action="' . $action . '" class="gf-form">' . $nonce;
        echo '<input type="hidden" name="action" value="gfoss_evento_save">';
        if ( $ed ) { echo '<input type="hidden" name="evento_id" value="' . (int) $ed->ID . '">'; }
        echo '<div class="gf-grid">';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Titolo *</span><input type="text" name="titolo" value="' . ( $ed ? esc_attr( $ed->post_title ) : '' ) . '" required></label>';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Descrizione</span><textarea name="descrizione" rows="4">' . ( $ed ? esc_textarea( $ed->post_content ) : '' ) . '</textarea></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Inizio</span><input type="datetime-local" name="data" value="' . $m( '_gf_ev_data' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Fine (per eventi multi-giorno)</span><input type="datetime-local" name="data_fine" value="' . $m( '_gf_ev_data_fine' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Luogo</span><input type="text" name="luogo" value="' . $m( '_gf_ev_luogo' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Indirizzo</span><input type="text" name="indirizzo" value="' . $m( '_gf_ev_indirizzo' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Link (sito/iscrizione)</span><input type="url" name="url" value="' . $m( '_gf_ev_url' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Scadenza iscrizioni</span><input type="date" name="scadenza" value="' . $m( '_gf_ev_scadenza' ) . '"></label>';
        echo '<label class="gf-field"><span class="gf-field__lbl">Posti (0 = illimitati)</span><input type="number" name="posti" min="0" value="' . ( $ed ? $m( '_gf_ev_posti' ) : '0' ) . '"></label>';
        echo '</div><p class="gf-actions"><button class="gf-btn gf-btn--primary">' . ( $ed ? 'Salva modifiche' : 'Crea evento' ) . '</button> ';
        if ( $ed ) { echo '<a class="gf-btn gf-btn--ghost" href="' . esc_url( remove_query_arg( [ 'ev_edit', 'msg' ] ) ) . '">Annulla</a>'; }
        echo '</p></form></section>';

        // Elenco eventi
        $eventi = get_posts( [ 'post_type' => Eventi::CPT, 'numberposts' => 100, 'post_status' => [ 'publish', 'future', 'draft' ], 'meta_key' => '_gf_ev_data', 'orderby' => 'meta_value', 'order' => 'DESC' ] );
        echo '<section class="gf-card"><h2 style="margin-top:0">Eventi (' . count( $eventi ) . ')</h2>';
        if ( ! $eventi ) {
            echo '<p class="gf-muted">Nessun evento ancora creato.</p>';
        } else {
            echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Evento</th><th>Quando</th><th>Luogo</th><th></th></tr></thead><tbody>';
            foreach ( $eventi as $ev ) {
                $ini = (string) get_post_meta( $ev->ID, '_gf_ev_data', true );
                $fin = (string) get_post_meta( $ev->ID, '_gf_ev_data_fine', true );
                echo '<tr><td><strong>' . esc_html( $ev->post_title ) . '</strong></td>';
                echo '<td>' . esc_html( $ini ? Volontari::format_periodo( $ini, $fin ) : '—' ) . '</td>';
                echo '<td>' . esc_html( (string) get_post_meta( $ev->ID, '_gf_ev_luogo', true ) ?: '—' ) . '</td>';
                echo '<td style="white-space:nowrap"><a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( add_query_arg( 'ev_edit', $ev->ID, remove_query_arg( 'msg' ) ) ) . '">Modifica</a> ';
                echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'Spostare l\\\'evento nel cestino?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_evento_delete"><input type="hidden" name="evento_id" value="' . (int) $ev->ID . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">Elimina</button></form></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section></div>';
        return (string) ob_get_clean();
    }
}
