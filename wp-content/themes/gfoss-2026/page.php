<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<article class="page">
    <header class="page__head">
        <div class="page__head-inner">
            <?php
            $crumbs = [];
            $parent_id = wp_get_post_parent_id( get_the_ID() );
            while ( $parent_id ) {
                $crumbs[] = '<a href="' . esc_url( get_permalink( $parent_id ) ) . '">' . esc_html( get_the_title( $parent_id ) ) . '</a>';
                $parent_id = wp_get_post_parent_id( $parent_id );
            }
            if ( $crumbs ) {
                echo '<nav class="breadcrumbs" aria-label="breadcrumb"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a> · '
                    . implode( ' · ', array_reverse( $crumbs ) ) . '</nav>';
            }
            ?>
            <h1 class="page__title"><?php the_title(); ?></h1>
        </div>
    </header>

    <div class="page__body">
        <div class="page__body-inner prose">
            <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
        </div>
    </div>
</article>
<?php get_footer();
