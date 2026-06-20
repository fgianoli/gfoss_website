<?php
/**
 * GFOSS 2026 — theme bootstrap.
 * Solo logica presentazionale. Gestione soci/quote/contabilità → plugin dedicati.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GFOSS_THEME_VERSION', '1.2.1' );
define( 'GFOSS_THEME_DIR', get_template_directory() );
define( 'GFOSS_THEME_URI', get_template_directory_uri() );

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ] );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'custom-logo', [
        'height'      => 80,
        'width'       => 240,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
    add_editor_style( 'assets/css/editor.css' );

    register_nav_menus( [
        'primary'   => __( 'Menù principale', 'gfoss-2026' ),
        'footer'    => __( 'Menù footer', 'gfoss-2026' ),
        'soci'      => __( 'Menù area soci', 'gfoss-2026' ),
    ] );

    load_theme_textdomain( 'gfoss-2026', GFOSS_THEME_DIR . '/languages' );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'gfoss-fonts',
        'data:text/css,' . rawurlencode( "@import url('https://fonts.bunny.net/css?family=manrope:400,600,700|inter:400,500,600|jetbrains-mono:400&display=swap');" ),
        [], null );
    wp_enqueue_style( 'gfoss-main', GFOSS_THEME_URI . '/assets/css/main.css', [ 'gfoss-fonts' ], GFOSS_THEME_VERSION );
    // Vincolo dimensione logo iniettato inline: viaggia con l'HTML e quindi non
    // dipende dalla cache del file main.css esterno (che alcuni browser tengono).
    wp_add_inline_style( 'gfoss-main',
        '.site-header img,.site-header .custom-logo,.site-header .site-logo{height:52px!important;width:auto!important;max-width:none!important}' );
    wp_enqueue_script( 'gfoss-main', GFOSS_THEME_URI . '/assets/js/main.js', [], GFOSS_THEME_VERSION, true );
} );

add_action( 'widgets_init', function () {
    register_sidebar( [
        'name'          => __( 'Footer', 'gfoss-2026' ),
        'id'            => 'footer-1',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ] );
} );

/** Hide WP admin bar for non-admin users on the frontend (cleaner area soci). */
add_action( 'after_setup_theme', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        show_admin_bar( false );
    }
} );

/** Force a clean excerpt for news listing. */
add_filter( 'excerpt_more', fn() => '…' );
add_filter( 'excerpt_length', fn() => 28, 999 );

/** Helper: stato quota socio (delegato al plugin se attivo). */
function gfoss_user_quota_status( int $user_id ): string {
    if ( function_exists( 'gfoss_members_quota_status' ) ) {
        return gfoss_members_quota_status( $user_id );
    }
    return 'unknown';
}

/** Block-pattern category per gli editor di contenuti. */
add_action( 'init', function () {
    if ( function_exists( 'register_block_pattern_category' ) ) {
        register_block_pattern_category( 'gfoss', [ 'label' => __( 'GFOSS.it', 'gfoss-2026' ) ] );
    }
} );

/* -------------------------------------------------------------------------
 * Backend più chiaro per il direttivo (non tecnici).
 * - "Articoli" diventa "News" ovunque (menu, sottomenu, schermate).
 * - Widget "Azioni rapide" in cima alla dashboard con i pulsanti giusti.
 * ---------------------------------------------------------------------- */

/** Rinomina il tipo "Articoli" in "News". */
add_action( 'init', function () {
    $pt = get_post_type_object( 'post' );
    if ( ! $pt ) { return; }
    $l = $pt->labels;
    $pt->label             = 'News';
    $l->name               = 'News';
    $l->singular_name      = 'News';
    $l->menu_name          = 'News';
    $l->name_admin_bar     = 'News';
    $l->all_items          = 'Tutte le news';
    $l->add_new            = 'Aggiungi news';
    $l->add_new_item       = 'Aggiungi una news';
    $l->new_item           = 'Nuova news';
    $l->edit_item          = 'Modifica news';
    $l->view_item          = 'Vedi news';
    $l->view_items         = 'Vedi le news';
    $l->search_items       = 'Cerca news';
    $l->not_found          = 'Nessuna news trovata';
    $l->not_found_in_trash = 'Nessuna news nel cestino';
}, 11 );

/** Icona "megafono" per la voce News nel menu admin. */
add_action( 'admin_menu', function () {
    global $menu;
    foreach ( $menu as $i => $m ) {
        if ( ! empty( $m[2] ) && $m[2] === 'edit.php' ) {
            $menu[ $i ][0] = 'News';
            $menu[ $i ][6] = 'dashicons-megaphone';
        }
    }
}, 999 );

/** Widget dashboard "Azioni rapide". */
add_action( 'wp_dashboard_setup', function () {
    wp_add_dashboard_widget(
        'gfoss_quickstart',
        'GFOSS.it — Azioni rapide',
        'gfoss_render_quickstart_widget',
        null, null, 'normal', 'high'
    );
} );

function gfoss_render_quickstart_widget(): void {
    $cards = [];
    if ( current_user_can( 'edit_posts' ) ) {
        $cards[] = [ '📣', 'Aggiungi una news', admin_url( 'post-new.php' ), 'Pubblica una notizia o un comunicato' ];
        $cards[] = [ '🗂️', 'Tutte le news', admin_url( 'edit.php' ), 'Modifica o elimina le notizie esistenti' ];
    }
    if ( current_user_can( 'edit_pages' ) ) {
        $cards[] = [ '📄', 'Aggiungi una pagina', admin_url( 'post-new.php?post_type=page' ), 'Una nuova pagina del sito (es. Associazione)' ];
        $cards[] = [ '📚', 'Tutte le pagine', admin_url( 'edit.php?post_type=page' ), 'Modifica le pagine esistenti' ];
    }
    if ( post_type_exists( 'gfoss_pubbdoc' ) && current_user_can( 'gfoss_manage_soci' ) ) {
        $cards[] = [ '📊', 'Bilanci e verbali', admin_url( 'edit.php?post_type=gfoss_pubbdoc' ), 'Carica bilanci e verbali (con PDF)' ];
    }
    if ( current_user_can( 'gfoss_manage_soci' ) ) {
        $cards[] = [ '👥', 'Soci e candidature', admin_url( 'admin.php?page=gfoss-associazione' ), 'Gestione soci, candidature, quote' ];
    }

    echo '<p style="margin:0 0 12px;color:#50575e">Le notizie del sito sono le <strong>News</strong>. Le sezioni fisse (Associazione, Statuto…) sono le <strong>Pagine</strong>.</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px">';
    foreach ( $cards as $c ) {
        printf(
            '<a href="%s" style="display:block;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;text-decoration:none;color:#1d2327">'
            . '<span style="font-size:20px;line-height:1">%s</span>'
            . '<strong style="display:block;margin:6px 0 2px">%s</strong>'
            . '<span style="color:#646970;font-size:12px">%s</span></a>',
            esc_url( $c[2] ), esc_html( $c[0] ), esc_html( $c[1] ), esc_html( $c[3] )
        );
    }
    echo '</div>';
}

/* -------------------------------------------------------------------------
 * Commenti disabilitati ovunque (sito istituzionale: nessun commento).
 * ---------------------------------------------------------------------- */

// Frontend: chiudi commenti e ping, nascondi eventuali commenti esistenti.
add_filter( 'comments_open',  '__return_false', 20 );
add_filter( 'pings_open',     '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 10 );

// Togli il supporto a commenti/trackback da tutti i tipi di contenuto.
add_action( 'init', function () {
    foreach ( get_post_types() as $pt ) {
        remove_post_type_support( $pt, 'comments' );
        remove_post_type_support( $pt, 'trackbacks' );
    }
}, 100 );

// Backend: rimuovi la voce "Commenti" dal menu e blocca l'accesso diretto.
add_action( 'admin_menu', function () {
    remove_menu_page( 'edit-comments.php' );
} );
add_action( 'admin_init', function () {
    global $pagenow;
    if ( $pagenow === 'edit-comments.php' ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
    foreach ( [ 'post', 'page' ] as $pt ) {
        remove_meta_box( 'commentsdiv', $pt, 'normal' );
        remove_meta_box( 'commentstatusdiv', $pt, 'normal' );
        remove_meta_box( 'trackbacksdiv', $pt, 'normal' );
    }
} );

// Rimuovi il widget "Commenti recenti" dalla bacheca e l'icona nella barra in alto.
add_action( 'wp_dashboard_setup', function () {
    remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
} );
add_action( 'admin_bar_menu', function ( $bar ) {
    $bar->remove_node( 'comments' );
}, 999 );

/* -------------------------------------------------------------------------
 * PWA — installabile su mobile (manifest + service worker root-scoped).
 * sw.js e il manifest sono serviti da PHP alla radice del sito così il
 * service worker ha scope "/" e controlla tutto il sito.
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
    $uri = strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '?' );

    if ( $uri === '/sw.js' ) {
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );
        header( 'Cache-Control: no-cache' );
        echo gfoss_pwa_service_worker();
        exit;
    }
    if ( $uri === '/gfoss-manifest.webmanifest' ) {
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: max-age=86400' );
        echo gfoss_pwa_manifest();
        exit;
    }
}, 0 );

function gfoss_pwa_manifest(): string {
    $img = GFOSS_THEME_URI . '/assets/img/';
    return wp_json_encode( [
        'name'             => 'GFOSS.it APS',
        'short_name'       => 'GFOSS.it',
        'description'      => "Associazione Italiana per l'Informazione Geografica Libera — area soci e servizi.",
        'lang'             => 'it',
        'start_url'        => home_url( '/' ),
        'scope'            => home_url( '/' ),
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'background_color' => '#FAFBFC',
        'theme_color'      => '#1A6FA0',
        'icons'            => [
            [ 'src' => $img . 'icon-192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
            [ 'src' => $img . 'icon-512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
            [ 'src' => $img . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function gfoss_pwa_service_worker(): string {
    $cache = 'gfoss-' . GFOSS_THEME_VERSION;
    $home  = wp_json_encode( home_url( '/' ) );
    return <<<JS
const CACHE = '{$cache}';
self.addEventListener('install', e => { self.skipWaiting(); e.waitUntil(caches.open(CACHE).then(c => c.add({$home}))); });
self.addEventListener('activate', e => {
    e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))));
    self.clients.claim();
});
self.addEventListener('fetch', e => {
    const req = e.request, url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin) return;
    const p = url.pathname;
    if (p.startsWith('/wp-admin') || p.startsWith('/wp-login') || p.indexOf('/wp-json/') !== -1 || p === '/sw.js') return;
    e.respondWith(
        fetch(req).then(res => {
            if (res && res.status === 200 && res.type === 'basic') {
                const copy = res.clone();
                caches.open(CACHE).then(c => c.put(req, copy));
            }
            return res;
        }).catch(() => caches.match(req).then(m => m || caches.match({$home})))
    );
});
JS;
}

add_action( 'wp_head', function () {
    echo '<link rel="manifest" href="' . esc_url( home_url( '/gfoss-manifest.webmanifest' ) ) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url( GFOSS_THEME_URI . '/assets/img/icon-192.png' ) . '">' . "\n";
    // Fallback favicon dagli asset del tema finché il site_icon non è impostato.
    if ( ! has_site_icon() ) {
        echo '<link rel="icon" href="' . esc_url( GFOSS_THEME_URI . '/assets/img/favicon.png' ) . '" sizes="any">' . "\n";
    }
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="GFOSS.it">' . "\n";
}, 5 );

add_action( 'wp_footer', function () {
    ?><script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js').catch(function(){});});}</script><?php
} );
