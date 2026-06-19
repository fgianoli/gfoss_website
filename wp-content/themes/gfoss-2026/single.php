<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<article <?php post_class( 'post-single' ); ?>>
    <header class="post-single__head">
        <div class="post-single__head-inner">
            <p class="post-single__eyebrow"><?php echo esc_html( get_the_date() ); ?> · <?php the_category( ', ' ); ?></p>
            <h1 class="post-single__title"><?php the_title(); ?></h1>
        </div>
    </header>

    <?php if ( has_post_thumbnail() ) : ?>
        <figure class="post-single__media"><?php the_post_thumbnail( 'large' ); ?></figure>
    <?php endif; ?>

    <div class="post-single__body prose">
        <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
    </div>

    <footer class="post-single__foot">
        <p class="post-single__tags"><?php the_tags( '<span class="tag">#', '</span> <span class="tag">#', '</span>' ); ?></p>
    </footer>
</article>
<?php get_footer();
