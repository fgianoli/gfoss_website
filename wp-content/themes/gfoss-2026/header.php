<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1A6FA0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Vai al contenuto', 'gfoss-2026' ); ?></a>

<header class="site-header">
    <div class="site-header__inner">
        <?php if ( has_custom_logo() ) : ?>
            <div class="site-header__brand"><?php the_custom_logo(); ?></div>
        <?php else : ?>
            <a class="site-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                <img src="<?php echo esc_url( GFOSS_THEME_URI . '/assets/img/logo.png' ); ?>"
                     alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="site-logo" width="180" height="65">
            </a>
        <?php endif; ?>

        <nav class="site-nav" aria-label="<?php esc_attr_e( 'Navigazione principale', 'gfoss-2026' ); ?>">
            <?php wp_nav_menu( [
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'site-nav__list',
                'fallback_cb'    => '__return_empty_string',
                'depth'          => 2,
            ] ); ?>
        </nav>

        <div class="site-header__cta">
            <?php if ( is_user_logged_in() ) : ?>
                <a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/area-soci/' ) ); ?>"><?php esc_html_e( 'Area soci', 'gfoss-2026' ); ?></a>
                <a class="btn btn--link" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"><?php esc_html_e( 'Esci', 'gfoss-2026' ); ?></a>
            <?php else : ?>
                <a class="btn btn--ghost" href="<?php echo esc_url( wp_login_url( home_url( '/area-soci/' ) ) ); ?>"><?php esc_html_e( 'Accedi', 'gfoss-2026' ); ?></a>
                <a class="btn btn--primary" href="<?php echo esc_url( home_url( '/associazione/iscrizioni-rinnovi/' ) ); ?>"><?php esc_html_e( 'Iscriviti', 'gfoss-2026' ); ?></a>
            <?php endif; ?>
        </div>

        <button class="site-header__burger" aria-controls="primary-menu" aria-expanded="false" data-gfoss-menu-toggle>
            <span class="sr-only"><?php esc_html_e( 'Menù', 'gfoss-2026' ); ?></span>
            <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
        </button>
    </div>
</header>

<main id="content" class="site-main">
