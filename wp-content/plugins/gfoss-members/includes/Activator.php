<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Activator {

    public static function activate(): void {
        Roles::register( true );
        Schema::install();
        self::ensure_pages();
        Cron::schedule();
        flush_rewrite_rules();
    }

    /**
     * Crea le pagine "di sistema" se non esistono ancora.
     * Sono pagine identificate via option, così si possono rinominare lato editor.
     */
    private static function content_5x1000(): string {
        return <<<'HTML'
<!-- wp:heading {"level":2} --><h2>Dona il 5×1000 a GFOSS.it APS</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Senza alcun costo aggiuntivo per te, puoi destinare il <strong>5 per mille</strong> della tua IRPEF all'Associazione Italiana per l'Informazione Geografica Libera. È il modo più semplice per supportare il software libero geografico in Italia.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Come fare in 2 passaggi</h3><!-- /wp:heading -->
<!-- wp:list {"ordered":true} -->
<ol>
<li>Nella tua dichiarazione dei redditi (Modello 730, Modello Redditi PF o CU), nella sezione "<em>Sostegno degli enti del Terzo Settore iscritti nel RUNTS</em>", <strong>firma nel riquadro</strong>.</li>
<li>Nello spazio "<em>Codice fiscale del beneficiario</em>" scrivi:<br>
<strong style="font-family:monospace;font-size:1.6em;letter-spacing:.08em">95090860131</strong></li>
</ol>
<!-- /wp:list -->

<!-- wp:heading {"level":3} --><h3>Cosa facciamo con il tuo 5×1000</h3><!-- /wp:heading -->
<!-- wp:list -->
<ul>
<li>Sostegno alle comunità italiane di <strong>QGIS, GRASS, GeoServer, PostGIS, GDAL</strong></li>
<li>Organizzazione di <strong>eventi, workshop e conferenze</strong> (FOSS4G-IT, GFOSSday)</li>
<li>Attività nelle <strong>scuole e nelle università</strong> per la diffusione degli strumenti geografici liberi</li>
<li>Traduzione e localizzazione di software e manuali</li>
<li>Promozione degli <strong>standard aperti</strong> e dell'accesso libero ai dati geografici</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} --><h3>Trasparenza</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>I bilanci approvati dall'Assemblea sono pubblici e consultabili nella <a href="/associazione/bilanci/">sezione Bilanci</a>. Il rendiconto sull'uso del 5×1000 è disponibile sul portale del <a href="https://www.lavoro.gov.it/" target="_blank" rel="noopener">Ministero del Lavoro</a>.</p><!-- /wp:paragraph -->
HTML;
    }

    private static function ensure_pages(): void {
        $pages = [
            'gfoss_page_iscriviti'        => [ 'title' => "Iscriviti a GFOSS.it",   'content' => '[gfoss_iscrizione_form]', 'parent' => 'gfoss_page_associazione' ],
            'gfoss_page_area_soci'        => [ 'title' => 'Area soci',              'content' => '[gfoss_area_personale]',  'parent' => 0 ],
            'gfoss_page_verifica_tessera' => [ 'title' => 'Verifica tessera',       'content' => '[gfoss_verifica_tessera]', 'parent' => 0 ],
            'gfoss_page_5x1000'           => [ 'title' => 'Dona il 5×1000',         'content' => self::content_5x1000(),     'parent' => 0 ],
            'gfoss_page_documenti_soci'   => [ 'title' => 'Documenti riservati',    'content' => '[gfoss_documenti_riservati]', 'parent' => 'gfoss_page_area_soci' ],
        ];

        foreach ( $pages as $opt => $data ) {
            if ( get_option( $opt ) && get_post( (int) get_option( $opt ) ) ) { continue; }

            $parent_id = 0;
            if ( ! empty( $data['parent'] ) ) {
                $parent_id = (int) get_option( $data['parent'], 0 );
            }

            $page_id = wp_insert_post( [
                'post_title'   => $data['title'],
                'post_content' => $data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id,
            ] );
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_option( $opt, $page_id );
            }
        }
    }
}
