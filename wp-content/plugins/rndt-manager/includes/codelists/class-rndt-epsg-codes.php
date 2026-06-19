<?php
/**
 * Codelist dei codici EPSG comuni per l'Italia
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_EPSG_Codes
 */
class RNDT_EPSG_Codes {

    /**
     * URI base per i codici EPSG
     */
    const EPSG_BASE_URI = 'http://www.opengis.net/def/crs/EPSG/0/';

    /**
     * Codespace EPSG
     */
    const CODESPACE = 'http://www.epsg-registry.org/';

    /**
     * Ottieni tutti i codici EPSG comuni per l'Italia
     *
     * @return array
     */
    public static function get_all() {
        return array(
            // Sistemi di riferimento globali
            '4326' => array(
                'name' => 'WGS 84',
                'type' => 'geographic',
                'area' => 'World',
            ),
            '3857' => array(
                'name' => 'WGS 84 / Pseudo-Mercator',
                'type' => 'projected',
                'area' => 'World',
            ),
            '4258' => array(
                'name' => 'ETRS89',
                'type' => 'geographic',
                'area' => 'Europe',
            ),

            // Italia - Sistema nazionale
            '6706' => array(
                'name' => 'RDN2008',
                'type' => 'geographic',
                'area' => 'Italy',
            ),
            '7791' => array(
                'name' => 'RDN2008 / UTM zone 32N (N-E)',
                'type' => 'projected',
                'area' => 'Italy - West',
            ),
            '7792' => array(
                'name' => 'RDN2008 / UTM zone 33N (N-E)',
                'type' => 'projected',
                'area' => 'Italy - Central',
            ),
            '7793' => array(
                'name' => 'RDN2008 / UTM zone 34N (N-E)',
                'type' => 'projected',
                'area' => 'Italy - East',
            ),
            '6875' => array(
                'name' => 'RDN2008 / Italy zone (N-E)',
                'type' => 'projected',
                'area' => 'Italy',
            ),

            // UTM zones
            '32632' => array(
                'name' => 'WGS 84 / UTM zone 32N',
                'type' => 'projected',
                'area' => 'Italy - West',
            ),
            '32633' => array(
                'name' => 'WGS 84 / UTM zone 33N',
                'type' => 'projected',
                'area' => 'Italy - Central',
            ),
            '32634' => array(
                'name' => 'WGS 84 / UTM zone 34N',
                'type' => 'projected',
                'area' => 'Italy - East',
            ),

            // ETRS89 / UTM
            '25832' => array(
                'name' => 'ETRS89 / UTM zone 32N',
                'type' => 'projected',
                'area' => 'Italy - West',
            ),
            '25833' => array(
                'name' => 'ETRS89 / UTM zone 33N',
                'type' => 'projected',
                'area' => 'Italy - Central',
            ),
            '25834' => array(
                'name' => 'ETRS89 / UTM zone 34N',
                'type' => 'projected',
                'area' => 'Italy - East',
            ),

            // Vecchi sistemi italiani (per dati storici)
            '3003' => array(
                'name' => 'Monte Mario / Italy zone 1',
                'type' => 'projected',
                'area' => 'Italy - West (legacy)',
            ),
            '3004' => array(
                'name' => 'Monte Mario / Italy zone 2',
                'type' => 'projected',
                'area' => 'Italy - East (legacy)',
            ),
            '4265' => array(
                'name' => 'Monte Mario',
                'type' => 'geographic',
                'area' => 'Italy (legacy)',
            ),

            // ED50
            '4230' => array(
                'name' => 'ED50',
                'type' => 'geographic',
                'area' => 'Europe (legacy)',
            ),
            '23032' => array(
                'name' => 'ED50 / UTM zone 32N',
                'type' => 'projected',
                'area' => 'Italy - West (legacy)',
            ),
            '23033' => array(
                'name' => 'ED50 / UTM zone 33N',
                'type' => 'projected',
                'area' => 'Italy - Central (legacy)',
            ),
        );
    }

    /**
     * Ottieni un codice EPSG specifico
     *
     * @param string $code Codice EPSG (solo numero)
     * @return array|null
     */
    public static function get( $code ) {
        // Normalizza il codice (rimuovi prefisso EPSG: se presente)
        $code = preg_replace( '/^EPSG:/i', '', $code );
        $codes = self::get_all();
        return isset( $codes[ $code ] ) ? $codes[ $code ] : null;
    }

    /**
     * Ottieni l'URI completo per un codice EPSG
     *
     * @param string $code Codice EPSG
     * @return string
     */
    public static function get_uri( $code ) {
        $code = preg_replace( '/^EPSG:/i', '', $code );
        return self::EPSG_BASE_URI . $code;
    }

    /**
     * Ottieni i codici come opzioni per un select
     *
     * @param bool $include_legacy Includere i sistemi legacy
     * @return array
     */
    public static function get_options( $include_legacy = true ) {
        $codes   = self::get_all();
        $options = array();

        foreach ( $codes as $code => $info ) {
            if ( ! $include_legacy && strpos( $info['area'], 'legacy' ) !== false ) {
                continue;
            }
            $options[ 'EPSG:' . $code ] = sprintf( '%s (EPSG:%s)', $info['name'], $code );
        }

        return $options;
    }

    /**
     * Ottieni i codici raggruppati per tipo
     *
     * @return array
     */
    public static function get_by_type() {
        $codes   = self::get_all();
        $grouped = array(
            'geographic' => array(),
            'projected'  => array(),
        );

        foreach ( $codes as $code => $info ) {
            $grouped[ $info['type'] ][ $code ] = $info;
        }

        return $grouped;
    }

    /**
     * Ottieni i codici raccomandati per l'Italia (RNDT)
     *
     * @return array
     */
    public static function get_recommended() {
        return array( '6706', '6875', '7791', '7792', '7793', '4258', '25832', '25833', '25834' );
    }

    /**
     * Ottieni il codespace
     *
     * @return string
     */
    public static function get_codespace() {
        return self::CODESPACE;
    }

    /**
     * Verifica se un codice EPSG e valido
     *
     * @param string $code Codice da verificare
     * @return bool
     */
    public static function is_valid( $code ) {
        $code = preg_replace( '/^EPSG:/i', '', $code );
        $codes = self::get_all();
        return isset( $codes[ $code ] );
    }

    /**
     * Formatta un codice EPSG nel formato standard
     *
     * @param string $code Codice da formattare
     * @return string
     */
    public static function format( $code ) {
        $code = preg_replace( '/^EPSG:/i', '', $code );
        return 'EPSG:' . $code;
    }
}
