<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__col">
            <p class="site-footer__brand"><strong>GFOSS.it APS</strong><br>
                <?php esc_html_e( "Associazione Italiana per l'Informazione Geografica Libera", 'gfoss-2026' ); ?></p>
            <p class="site-footer__addr">
                <?php esc_html_e( 'Sede legale: Lungargine Gerolamo Rovetta 28, 35131 Padova', 'gfoss-2026' ); ?><br>
                <a href="mailto:info@gfoss.it">info@gfoss.it</a>
            </p>
            <p class="site-footer__runts"><?php esc_html_e( 'Associazione di Promozione Sociale iscritta al RUNTS', 'gfoss-2026' ); ?></p>
            <p class="site-footer__chip">OSGeo·IT Local Chapter</p>
        </div>

        <div class="site-footer__col">
            <h3><?php esc_html_e( 'Associazione', 'gfoss-2026' ); ?></h3>
            <?php wp_nav_menu( [
                'theme_location' => 'footer',
                'container'      => false,
                'menu_class'     => 'site-footer__menu',
                'depth'          => 1,
                'fallback_cb'    => '__return_empty_string',
            ] ); ?>
        </div>

        <div class="site-footer__col">
            <h3><?php esc_html_e( 'Sostienici', 'gfoss-2026' ); ?></h3>
            <ul class="site-footer__menu">
                <li><a href="<?php echo esc_url( home_url( '/associazione/iscrizioni-rinnovi/' ) ); ?>"><?php esc_html_e( 'Iscriviti / Rinnova', 'gfoss-2026' ); ?></a></li>
                <li><a href="<?php echo esc_url( home_url( '/5x1000/' ) ); ?>"><?php esc_html_e( 'Dona il 5×1000', 'gfoss-2026' ); ?></a></li>
                <li><a href="https://www.paypal.com/donate/?hosted_button_id=FMST69RX3D3WJ" rel="noopener" target="_blank"><?php esc_html_e( 'Erogazione liberale', 'gfoss-2026' ); ?></a></li>
            </ul>
        </div>

        <div class="site-footer__col">
            <h3><?php esc_html_e( 'Open data, open code', 'gfoss-2026' ); ?></h3>
            <p><?php esc_html_e( 'Tutti i contenuti del sito sono rilasciati con licenza CC BY-SA 4.0 salvo diversa indicazione.', 'gfoss-2026' ); ?></p>
            <p>
                <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'gfoss-2026' ); ?></a> ·
                <a href="<?php echo esc_url( home_url( '/cookie-policy/' ) ); ?>"><?php esc_html_e( 'Cookie', 'gfoss-2026' ); ?></a>
            </p>
        </div>
    </div>
    <div class="site-footer__bar">
        <small>© <?php echo esc_html( gmdate( 'Y' ) ); ?> GFOSS.it APS — Associazione di Promozione Sociale iscritta al RUNTS — C.F. <code>95090860131</code></small>
    </div>
</footer>

<?php
// Cookie banner: mostrato finché l'utente non prende visione (cookie tecnico, no profilazione).
if ( ! isset( $_COOKIE['gfoss_cookie_ok'] ) ) : ?>
<div class="gf-cookie" id="gf-cookie" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Informativa cookie', 'gfoss-2026' ); ?>">
    <p class="gf-cookie__text">
        <?php esc_html_e( 'Questo sito usa solo cookie tecnici necessari al suo funzionamento. Nessun cookie di profilazione.', 'gfoss-2026' ); ?>
        <a href="<?php echo esc_url( home_url( '/cookie-policy/' ) ); ?>"><?php esc_html_e( 'Maggiori informazioni', 'gfoss-2026' ); ?></a>
    </p>
    <button type="button" class="gf-cookie__btn" id="gf-cookie-ok"><?php esc_html_e( 'Ho capito', 'gfoss-2026' ); ?></button>
</div>
<script>
(function(){
    var b = document.getElementById('gf-cookie-ok'),
        box = document.getElementById('gf-cookie');
    if (!b || !box) return;
    b.addEventListener('click', function(){
        var d = new Date(); d.setTime(d.getTime() + 365*24*60*60*1000);
        document.cookie = 'gfoss_cookie_ok=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
        box.parentNode.removeChild(box);
    });
})();
</script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
