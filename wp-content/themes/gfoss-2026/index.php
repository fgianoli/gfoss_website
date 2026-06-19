<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<section class="archive">
    <header class="archive__head">
        <div class="archive__head-inner">
            <h1 class="archive__title">
                <?php
                if ( is_home() ) { esc_html_e( 'News', 'gfoss-2026' ); }
                elseif ( is_search() ) { printf( esc_html__( 'Risultati per: %s', 'gfoss-2026' ), '<em>' . esc_html( get_search_query() ) . '</em>' ); }
                else { the_archive_title(); }
                ?>
            </h1>
        </div>
    </header>

    <div class="archive__body">
        <div class="archive__body-inner news-grid">
            <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
                <article class="news-card">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <a class="news-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
                    <?php endif; ?>
                    <div class="news-card__body">
                        <p class="news-card__date"><?php echo esc_html( get_the_date() ); ?></p>
                        <h2 class="news-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p class="news-card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    </div>
                </article>
            <?php endwhile; else : ?>
                <p class="muted"><?php esc_html_e( 'Nessun contenuto.', 'gfoss-2026' ); ?></p>
            <?php endif; ?>
        </div>

        <nav class="pagination">
            <?php the_posts_pagination( [ 'mid_size' => 2, 'prev_text' => '←', 'next_text' => '→' ] ); ?>
        </nav>
    </div>
</section>
<?php get_footer();
