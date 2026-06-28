<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Console front-end per scrivere le News del sito (riservata a chi pubblica:
 * Comunicazione, consiglieri, presidente, segreteria, admin).
 * Shortcode [gfoss_scrivi_news].
 */
class News_Frontend {

    public static function init(): void {
        add_shortcode( 'gfoss_scrivi_news', [ __CLASS__, 'render' ] );
        add_action( 'admin_post_gfoss_news_save',   [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_gfoss_news_delete', [ __CLASS__, 'handle_delete' ] );
    }

    private static function can(): bool {
        return is_user_logged_in() && current_user_can( 'publish_posts' );
    }

    public static function handle_save(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_news' );
        $id    = (int) ( $_POST['news_id'] ?? 0 );
        $title = sanitize_text_field( wp_unslash( $_POST['titolo'] ?? '' ) );
        $body  = wp_kses_post( wp_unslash( $_POST['contenuto'] ?? '' ) );
        if ( $title === '' ) { self::back( 'err' ); }

        $arr = [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $body ];
        if ( $id ) { $arr['ID'] = $id; wp_update_post( $arr ); }
        else       { wp_insert_post( $arr ); }
        self::back( 'saved' );
    }

    public static function handle_delete(): void {
        if ( ! self::can() ) { wp_die( 'Permesso negato.' ); }
        check_admin_referer( 'gfoss_news' );
        wp_trash_post( (int) ( $_POST['news_id'] ?? 0 ) );
        self::back( 'deleted' );
    }

    private static function back( string $msg ): void {
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'msg', $msg, remove_query_arg( [ 'msg', 'news_edit' ], $url ) ) );
        exit;
    }

    public static function render(): string {
        if ( ! self::can() ) {
            return '<div class="gf-card gf-card--warn">Sezione riservata a chi pubblica le News (Comunicazione/Direttivo).</div>';
        }
        $action = esc_url( admin_url( 'admin-post.php' ) );
        $nonce  = wp_nonce_field( 'gfoss_news', '_wpnonce', true, false );
        $msg    = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
        $edit   = (int) ( $_GET['news_edit'] ?? 0 );
        $ed     = $edit ? get_post( $edit ) : null;

        ob_start();
        echo '<div class="gf-area gf-vol">';
        echo '<header class="gf-area__head"><div><p class="gf-area__eyebrow">Comunicazione</p><h1 class="gf-area__title">Scrivi una News</h1><p class="gf-area__sub">Pubblica una notizia sul sito senza passare dal backend.</p></div></header>';

        $notes = [ 'saved' => [ 'success', 'News pubblicata.' ], 'deleted' => [ 'success', 'News spostata nel cestino.' ], 'err' => [ 'warn', 'Il titolo è obbligatorio.' ] ];
        if ( isset( $notes[ $msg ] ) ) { echo '<div class="gf-card gf-card--' . esc_attr( $notes[ $msg ][0] ) . '">' . esc_html( $notes[ $msg ][1] ) . '</div>'; }

        echo '<section class="gf-card"><h2 style="margin-top:0">' . ( $ed ? 'Modifica News' : 'Nuova News' ) . '</h2>';
        echo '<form method="post" action="' . $action . '" class="gf-form">' . $nonce . '<input type="hidden" name="action" value="gfoss_news_save">';
        if ( $ed ) { echo '<input type="hidden" name="news_id" value="' . (int) $ed->ID . '">'; }
        echo '<div class="gf-grid">';
        echo '<label class="gf-field gf-col-2"><span class="gf-field__lbl">Titolo *</span><input type="text" name="titolo" value="' . ( $ed ? esc_attr( $ed->post_title ) : '' ) . '" required></label>';
        echo '</div>';
        echo '<div class="gf-field gf-news-editor"><span class="gf-field__lbl">Contenuto</span>';
        wp_editor( $ed ? $ed->post_content : '', 'contenuto', [
            'textarea_name' => 'contenuto',
            'media_buttons' => false,
            'teeny'         => true,
            'textarea_rows' => 12,
            'quicktags'     => true,
        ] );
        echo '</div>';
        echo '<p class="gf-actions"><button class="gf-btn gf-btn--primary">' . ( $ed ? 'Aggiorna' : 'Pubblica' ) . '</button>';
        if ( $ed ) { echo ' <a class="gf-btn gf-btn--ghost" href="' . esc_url( remove_query_arg( [ 'news_edit', 'msg' ] ) ) . '">Annulla</a>'; }
        echo '</p></form></section>';

        $posts = get_posts( [ 'post_type' => 'post', 'numberposts' => 15, 'post_status' => [ 'publish', 'draft' ] ] );
        echo '<section class="gf-card"><h2 style="margin-top:0">Ultime News</h2>';
        if ( ! $posts ) {
            echo '<p class="gf-muted">Nessuna news pubblicata.</p>';
        } else {
            echo '<div class="gf-tablewrap"><table class="gf-table"><thead><tr><th>Titolo</th><th>Data</th><th></th></tr></thead><tbody>';
            foreach ( $posts as $p ) {
                echo '<tr><td><strong>' . esc_html( $p->post_title ) . '</strong></td><td>' . esc_html( get_the_date( 'd/m/Y', $p ) ) . '</td>';
                echo '<td style="white-space:nowrap"><a class="gf-btn gf-btn--ghost gf-btn--sm" href="' . esc_url( add_query_arg( 'news_edit', $p->ID, remove_query_arg( 'msg' ) ) ) . '">Modifica</a> ';
                echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'Cestinare questa news?\')">' . $nonce . '<input type="hidden" name="action" value="gfoss_news_delete"><input type="hidden" name="news_id" value="' . (int) $p->ID . '"><button class="gf-btn gf-btn--ghost gf-btn--sm">Elimina</button></form></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section></div>';
        return (string) ob_get_clean();
    }
}
