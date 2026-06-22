<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Mappa / directory dei soci, su base volontaria (opt-in).
 *
 *   - Il socio attiva "Localizzami in mappa" nel proprio profilo (gf_mappa_consenso).
 *   - Alla conferma, la città+provincia viene geocodificata (OSM Nominatim) e
 *     salvata come gf_lat/gf_lon (precisione comunale, non l'indirizzo esatto).
 *   - Shortcode [gfoss_mappa_soci] mostra una mappa Leaflet ai soli soci loggati,
 *     con i soci che hanno dato il consenso (nome, città, competenze — niente
 *     email/indirizzo).
 */
class Mappa_Soci {

    public static function init(): void {
        add_shortcode( 'gfoss_mappa_soci', [ __CLASS__, 'shortcode' ] );
    }

    /** Geocodifica la città del socio se ha dato il consenso e qualcosa è cambiato. */
    public static function maybe_geocode( int $user_id ): void {
        $consenso = get_user_meta( $user_id, 'gf_mappa_consenso', true ) === '1';
        if ( ! $consenso ) { return; }

        $citta = trim( (string) get_user_meta( $user_id, 'gf_citta', true ) );
        $prov  = trim( (string) get_user_meta( $user_id, 'gf_provincia', true ) );
        if ( $citta === '' ) { return; }

        $key = $citta . '|' . $prov;
        if ( get_user_meta( $user_id, 'gf_geo_src', true ) === $key
             && get_user_meta( $user_id, 'gf_lat', true ) !== '' ) {
            return; // già geocodificato per questa città
        }

        $coords = self::geocode( $citta, $prov );
        if ( $coords ) {
            update_user_meta( $user_id, 'gf_lat', $coords[0] );
            update_user_meta( $user_id, 'gf_lon', $coords[1] );
            update_user_meta( $user_id, 'gf_geo_src', $key );
        }
    }

    /** @return array{0:float,1:float}|null */
    private static function geocode( string $citta, string $prov ): ?array {
        $q = $citta . ( $prov ? ' (' . $prov . ')' : '' ) . ', Italia';
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query( [
            'q'      => $q,
            'format' => 'json',
            'limit'  => 1,
            'countrycodes' => 'it',
        ] );
        $resp = wp_remote_get( $url, [
            'timeout'    => 8,
            'user-agent' => 'GFOSS.it-website/1.0 (info@gfoss.it)',
            'headers'    => [ 'Accept-Language' => 'it' ],
        ] );
        if ( is_wp_error( $resp ) ) { return null; }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) { return null; }
        return [ (float) $data[0]['lat'], (float) $data[0]['lon'] ];
    }

    /** Soci che hanno dato il consenso e hanno coordinate. */
    private static function located_members(): array {
        $users = get_users( [
            'role__in'   => [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore', 'gfoss_comunicazione', 'gfoss_segreteria' ],
            'meta_query' => [
                [ 'key' => 'gf_mappa_consenso', 'value' => '1' ],
                [ 'key' => 'gf_lat', 'value' => '', 'compare' => '!=' ],
            ],
        ] );
        $points = [];
        foreach ( $users as $u ) {
            $lat = (float) get_user_meta( $u->ID, 'gf_lat', true );
            $lon = (float) get_user_meta( $u->ID, 'gf_lon', true );
            if ( ! $lat || ! $lon ) { continue; }
            $points[] = [
                'lat'  => $lat,
                'lon'  => $lon,
                'nome' => $u->display_name,
                'citta'=> (string) get_user_meta( $u->ID, 'gf_citta', true ),
                'comp' => wp_trim_words( (string) get_user_meta( $u->ID, 'gf_competenze', true ), 16 ),
            ];
        }
        return $points;
    }

    public static function shortcode( $atts = [] ): string {
        if ( ! is_user_logged_in() || ! gfoss_members_is_socio( get_current_user_id() ) ) {
            return '<div class="gf-card gf-card--warn">La mappa dei soci è riservata ai soci. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Accedi</a>.</div>';
        }

        $points = self::located_members();
        $json   = wp_json_encode( $points );

        ob_start();
        ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
        <div id="gf-mappa-soci" style="height:460px;border-radius:12px;overflow:hidden;border:1px solid #E2E8EC"></div>
        <p class="gf-muted" style="margin-top:.5rem">Compaiono solo i soci che hanno attivato «Localizzami in mappa» nel profilo. La posizione è a livello di città.</p>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script>
        (function(){
            var pts = <?php echo $json ?: '[]'; ?>;
            if (!window.L) return;
            var map = L.map('gf-mappa-soci').setView([42.5, 12.5], 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18, attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            var group = [];
            pts.forEach(function(p){
                var m = L.marker([p.lat, p.lon]).addTo(map);
                var html = '<strong>' + p.nome + '</strong>';
                if (p.citta) html += '<br>' + p.citta;
                if (p.comp)  html += '<br><em>' + p.comp + '</em>';
                m.bindPopup(html);
                group.push([p.lat, p.lon]);
            });
            if (group.length) { map.fitBounds(group, { padding:[30,30], maxZoom:8 }); }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
