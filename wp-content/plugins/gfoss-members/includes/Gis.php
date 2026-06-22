<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Servizio "Spazio dati GIS" per i soci: provisioning self-service di uno schema
 * PostGIS dedicato (lettura/scrittura) e di un account GeoServer per pubblicare.
 *
 * Orchestrazione di Gis_Postgis e Gis_Geoserver + UI nella card dell'area soci.
 */
class Gis {

    const META_ACTIVE  = 'gf_gis_active';
    const META_BASE    = 'gf_gis_base';
    const META_CREATED = 'gf_gis_created';

    public static function init(): void {
        add_action( 'admin_post_gfoss_gis_activate', [ __CLASS__, 'handle_provision' ] );
        add_action( 'admin_post_gfoss_gis_reset',    [ __CLASS__, 'handle_provision' ] );
    }

    public static function is_configured(): bool {
        return Gis_Postgis::is_configured();
    }

    public static function is_active( int $uid ): bool {
        return get_user_meta( $uid, self::META_ACTIVE, true ) === '1';
    }

    /** Solo soci in regola possono attivare/usare lo spazio GIS. */
    public static function eligible( int $uid ): bool {
        if ( ! gfoss_members_is_socio( $uid ) ) { return false; }
        return in_array( Quote::status_for( $uid, (int) gmdate( 'Y' ) ), [ 'paid', 'expiring' ], true );
    }

    /** Nome base (ruolo/schema/workspace) derivato dal numero socio. */
    public static function base_for( int $uid ): string {
        $num  = (string) get_user_meta( $uid, 'gf_numero_socio', true );
        $slug = strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '_', $num ) );
        $slug = trim( $slug, '_' );
        if ( $slug === '' ) { $slug = 'u' . $uid; }
        return 'socio_' . $slug;
    }

    /** Handler condiviso attivazione / reset password (provisioning idempotente). */
    public static function handle_provision(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'Accesso richiesto.' ); }
        $uid = get_current_user_id();
        check_admin_referer( 'gfoss_gis_' . $uid );

        $back = wp_get_referer() ?: home_url( '/' );

        if ( ! self::is_configured() ) {
            wp_safe_redirect( add_query_arg( 'msg', 'gis_unconfigured', $back ) ); exit;
        }
        if ( ! self::eligible( $uid ) ) {
            wp_safe_redirect( add_query_arg( 'msg', 'gis_not_eligible', $back ) ); exit;
        }

        $base = self::base_for( $uid );
        $pw   = wp_generate_password( 18, false ); // solo alfanumerici: comodo nelle stringhe di connessione

        $pg = Gis_Postgis::provision( $base, $pw );
        if ( is_wp_error( $pg ) ) {
            set_transient( 'gf_gis_err_' . $uid, $pg->get_error_message(), 300 );
            wp_safe_redirect( add_query_arg( 'msg', 'gis_err', $back ) ); exit;
        }

        $gs_warning = '';
        if ( Gis_Geoserver::is_configured() ) {
            $gs = Gis_Geoserver::provision( $base, $pw );
            if ( is_wp_error( $gs ) ) {
                $gs_warning = $gs->get_error_message();
            }
        }

        update_user_meta( $uid, self::META_ACTIVE, '1' );
        update_user_meta( $uid, self::META_BASE, $base );
        if ( ! get_user_meta( $uid, self::META_CREATED, true ) ) {
            update_user_meta( $uid, self::META_CREATED, current_time( 'Y-m-d' ) );
        }

        // La password si mostra UNA volta sola.
        set_transient( 'gf_gis_pw_' . $uid, $pw, 300 );

        if ( $gs_warning !== '' ) {
            set_transient( 'gf_gis_err_' . $uid, 'PostGIS attivato correttamente. GeoServer però ha segnalato: ' . $gs_warning, 300 );
            wp_safe_redirect( add_query_arg( 'msg', 'gis_partial', $back ) ); exit;
        }
        wp_safe_redirect( add_query_arg( 'msg', 'gis_ok', $back ) ); exit;
    }

    /** Card per l'area soci. Stringa vuota se non c'è nulla da mostrare. */
    public static function render_area_card( \WP_User $user ): string {
        if ( ! self::is_configured() ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<section class="gf-area__card"><header class="gf-area__card-head"><h2>Spazio dati GIS</h2></header>'
                    . '<p class="gf-muted">Servizio non ancora configurato. Imposta le variabili <code>GFOSS_PG_*</code> e <code>GFOSS_GEOSERVER_*</code> nel <code>.env</code> del server. (Visibile solo a te come amministratore.)</p></section>';
            }
            return '';
        }

        $uid    = $user->ID;
        $active = self::is_active( $uid );
        $pw     = get_transient( 'gf_gis_pw_' . $uid );
        if ( $pw ) { delete_transient( 'gf_gis_pw_' . $uid ); }
        $err = get_transient( 'gf_gis_err_' . $uid );
        if ( $err ) { delete_transient( 'gf_gis_err_' . $uid ); }

        ob_start();
        ?>
        <section class="gf-area__card gf-area__card--wide">
            <header class="gf-area__card-head"><h2>Il tuo spazio dati GIS</h2></header>

            <?php if ( $err ) : ?>
                <div class="gf-card gf-card--warn" style="margin-bottom:1rem"><?php echo esc_html( $err ); ?></div>
            <?php endif; ?>

            <?php if ( ! $active ) : ?>
                <p class="gf-muted">Da socio in regola puoi attivare un <strong>database PostGIS personale</strong> (lettura/scrittura sul tuo schema) e un account <strong>GeoServer</strong> per pubblicare i tuoi dati.</p>
                <?php if ( self::eligible( $uid ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="gfoss_gis_activate">
                        <?php wp_nonce_field( 'gfoss_gis_' . $uid ); ?>
                        <button type="submit" class="gf-btn gf-btn--primary">Attiva il mio spazio GIS</button>
                    </form>
                <?php else : ?>
                    <p class="gf-muted">Disponibile per i soci con quota in regola. Rinnova la quota per attivarlo.</p>
                <?php endif; ?>

            <?php else :
                $conn   = Gis_Postgis::public_conn();
                $base   = (string) get_user_meta( $uid, self::META_BASE, true );
                ?>
                <?php if ( $pw ) : ?>
                    <div class="gf-card gf-card--success" style="margin-bottom:1rem">
                        <strong>Password generata:</strong> <code style="font-size:1.05em"><?php echo esc_html( $pw ); ?></code><br>
                        <small>⚠️ Salvala adesso: per sicurezza non verrà più mostrata. Se la perdi, usa “Rigenera password”.</small>
                    </div>
                <?php endif; ?>

                <p class="gf-muted">Il tuo spazio è attivo. Usa questi parametri in QGIS, <code>psql</code> o <code>ogr2ogr</code>.</p>
                <div class="gf-tablewrap">
                <table class="gf-table">
                    <tbody>
                        <tr><th>Host</th><td><code><?php echo esc_html( $conn['host'] ); ?></code></td></tr>
                        <tr><th>Porta</th><td><code><?php echo esc_html( $conn['port'] ); ?></code></td></tr>
                        <tr><th>Database</th><td><code><?php echo esc_html( $conn['db'] ); ?></code></td></tr>
                        <tr><th>Schema</th><td><code><?php echo esc_html( $base ); ?></code></td></tr>
                        <tr><th>Utente</th><td><code><?php echo esc_html( $base ); ?></code></td></tr>
                        <tr><th>Password</th><td><code><?php echo $pw ? esc_html( $pw ) : '•••••••• (impostata)'; ?></code></td></tr>
                    </tbody>
                </table>
                </div>

                <p class="gf-muted" style="margin-top:.5rem">Esempio connessione:</p>
                <pre class="gf-pre"><code>psql "host=<?php echo esc_html( $conn['host'] ); ?> port=<?php echo esc_html( $conn['port'] ); ?> dbname=<?php echo esc_html( $conn['db'] ); ?> user=<?php echo esc_html( $base ); ?> options=--search_path=<?php echo esc_html( $base ); ?>"</code></pre>

                <?php if ( Gis_Geoserver::is_configured() ) : ?>
                    <p style="margin-top:1rem"><a class="gf-btn gf-btn--ghost" href="<?php echo esc_url( Gis_Geoserver::public_url() ); ?>/web/" target="_blank" rel="noopener">Apri GeoServer →</a></p>
                    <p class="gf-muted">Workspace: <code>ws_<?php echo esc_html( $base ); ?></code> — utente <code><?php echo esc_html( $base ); ?></code> (stessa password). Il datastore PostGIS è già collegato al tuo schema.</p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem">
                    <input type="hidden" name="action" value="gfoss_gis_reset">
                    <?php wp_nonce_field( 'gfoss_gis_' . $uid ); ?>
                    <button type="submit" class="gf-btn gf-btn--ghost" onclick="return confirm('Rigenerare la password? Quella attuale smetterà di funzionare.')">Rigenera password</button>
                </form>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
