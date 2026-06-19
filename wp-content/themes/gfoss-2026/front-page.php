<?php
/**
 * Homepage. Tutti i blocchi sono editabili dall'admin tramite Customizer / pagina "Home"
 * (in fase 2 si aggiungerà ACF per renderli completamente WYSIWYG).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<section class="hero">
    <div class="hero__pattern" aria-hidden="true"></div>
    <div class="hero__inner">
        <p class="hero__eyebrow">Free as in Freedom · dal 2007</p>
        <h1 class="hero__title">
            <?php esc_html_e( 'Software libero per la geografia, ', 'gfoss-2026' ); ?>
            <span class="hero__title-accent"><?php esc_html_e( 'da chi la geografia la vive.', 'gfoss-2026' ); ?></span>
        </h1>
        <p class="hero__lead">
            <?php esc_html_e( "Promuoviamo lo sviluppo, la diffusione e la tutela del software geografico libero, gli standard aperti e l'accesso ai dati.", 'gfoss-2026' ); ?>
        </p>
        <div class="hero__actions">
            <a class="btn btn--primary btn--lg" href="<?php echo esc_url( home_url( '/associazione/iscrizioni-rinnovi/' ) ); ?>"><?php esc_html_e( 'Diventa socio', 'gfoss-2026' ); ?></a>
            <a class="btn btn--ghost btn--lg" href="<?php echo esc_url( home_url( '/associazione/' ) ); ?>"><?php esc_html_e( "Conosci l'associazione", 'gfoss-2026' ); ?></a>
        </div>
    </div>
</section>

<section class="band band--paper">
    <div class="band__inner stack-cards">
        <article class="card">
            <div class="card__icon" aria-hidden="true">🌐</div>
            <h2 class="card__title"><?php esc_html_e( 'Standard aperti', 'gfoss-2026' ); ?></h2>
            <p><?php esc_html_e( 'OGC, INSPIRE, OpenStreetMap. Lavoriamo perché i dati restino interoperabili e accessibili.', 'gfoss-2026' ); ?></p>
        </article>
        <article class="card">
            <div class="card__icon" aria-hidden="true">🧭</div>
            <h2 class="card__title"><?php esc_html_e( 'Comunità italiana', 'gfoss-2026' ); ?></h2>
            <p><?php esc_html_e( 'GRASS, QGIS, GeoServer, PostGIS, GDAL: facilitiamo gli incontri, le traduzioni, gli eventi.', 'gfoss-2026' ); ?></p>
        </article>
        <article class="card">
            <div class="card__icon" aria-hidden="true">🎓</div>
            <h2 class="card__title"><?php esc_html_e( 'Scuole, PA, ricerca', 'gfoss-2026' ); ?></h2>
            <p><?php esc_html_e( 'Supportiamo università ed enti pubblici nel passaggio a tecnologie geografiche libere.', 'gfoss-2026' ); ?></p>
        </article>
    </div>
</section>

<section class="band">
    <div class="band__inner">
        <header class="section-head">
            <h2><?php esc_html_e( 'Ultime dalla comunità', 'gfoss-2026' ); ?></h2>
            <a class="section-head__more" href="<?php echo esc_url( get_post_type_archive_link( 'post' ) ?: home_url( '/news/' ) ); ?>"><?php esc_html_e( 'Tutte le news →', 'gfoss-2026' ); ?></a>
        </header>
        <div class="news-grid">
            <?php
            $news = new WP_Query( [ 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => true ] );
            if ( $news->have_posts() ) :
                while ( $news->have_posts() ) : $news->the_post(); ?>
                    <article class="news-card">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <a class="news-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
                        <?php endif; ?>
                        <div class="news-card__body">
                            <p class="news-card__date"><?php echo esc_html( get_the_date() ); ?></p>
                            <h3 class="news-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p class="news-card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
                        </div>
                    </article>
                <?php endwhile; wp_reset_postdata();
            else : ?>
                <p class="muted"><?php esc_html_e( 'Nessuna news pubblicata. Le novità appariranno qui.', 'gfoss-2026' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="band band--blue">
    <div class="band__inner cta">
        <h2><?php esc_html_e( 'Vuoi sostenere il software libero geografico in Italia?', 'gfoss-2026' ); ?></h2>
        <p><?php printf( esc_html__( 'La quota associativa è di %s €/anno. Iscriversi richiede pochi minuti e dà diritto di voto in assemblea.', 'gfoss-2026' ), esc_html( (string) ( defined( 'GFOSS_QUOTA_AMOUNT' ) ? GFOSS_QUOTA_AMOUNT : 30 ) ) ); ?></p>
        <a class="btn btn--orange btn--lg" href="<?php echo esc_url( home_url( '/associazione/iscrizioni-rinnovi/' ) ); ?>"><?php esc_html_e( 'Iscriviti ora', 'gfoss-2026' ); ?></a>
    </div>
</section>

<?php get_footer();
