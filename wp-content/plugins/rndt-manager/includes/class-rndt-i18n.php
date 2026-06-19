<?php
/**
 * Gestione internazionalizzazione
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_i18n
 */
class RNDT_i18n {

    /**
     * Carica il text domain del plugin
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'rndt-manager',
            false,
            dirname( RNDT_MANAGER_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Ottieni la lingua corrente in formato ISO 639-2/B (3 lettere)
     *
     * @return string
     */
    public static function get_current_language_iso3() {
        $locale = get_locale();
        $lang   = substr( $locale, 0, 2 );

        $iso3_map = array(
            'it' => 'ita',
            'en' => 'eng',
            'de' => 'deu',
            'fr' => 'fra',
            'es' => 'spa',
        );

        return isset( $iso3_map[ $lang ] ) ? $iso3_map[ $lang ] : 'ita';
    }

    /**
     * Ottieni la lingua corrente in formato ISO 639-1 (2 lettere)
     *
     * @return string
     */
    public static function get_current_language() {
        $locale = get_locale();
        return substr( $locale, 0, 2 );
    }

    /**
     * Verifica se la lingua corrente e italiano
     *
     * @return bool
     */
    public static function is_italian() {
        return self::get_current_language() === 'it';
    }

    /**
     * Mappa dei codici lingua ISO 639-2/B a ISO 639-1
     *
     * @return array
     */
    public static function get_language_map() {
        return array(
            'ita' => 'it',
            'eng' => 'en',
            'deu' => 'de',
            'fra' => 'fr',
            'spa' => 'es',
            'por' => 'pt',
            'nld' => 'nl',
            'pol' => 'pl',
            'ron' => 'ro',
            'ces' => 'cs',
            'slv' => 'sl',
            'hrv' => 'hr',
            'hun' => 'hu',
            'ell' => 'el',
            'bul' => 'bg',
            'swe' => 'sv',
            'dan' => 'da',
            'fin' => 'fi',
            'est' => 'et',
            'lav' => 'lv',
            'lit' => 'lt',
            'mlt' => 'mt',
            'slk' => 'sk',
            'gle' => 'ga',
        );
    }

    /**
     * Ottieni le lingue supportate per i metadati RNDT
     *
     * @return array
     */
    public static function get_supported_languages() {
        return array(
            'ita' => __( 'Italiano', 'rndt-manager' ),
            'eng' => __( 'Inglese', 'rndt-manager' ),
            'deu' => __( 'Tedesco', 'rndt-manager' ),
            'fra' => __( 'Francese', 'rndt-manager' ),
        );
    }

    /**
     * Ottieni i character set supportati
     *
     * @return array
     */
    public static function get_supported_charsets() {
        return array(
            'utf8'        => 'UTF-8',
            'utf16'       => 'UTF-16',
            'iso88591'    => 'ISO-8859-1 (Latin-1)',
            'iso885915'   => 'ISO-8859-15 (Latin-9)',
            '8859part1'   => '8859part1',
            '8859part2'   => '8859part2',
            '8859part15'  => '8859part15',
        );
    }

    /**
     * Converte codice lingua ISO 639-2/B in ISO 639-1
     *
     * @param string $iso3 Codice a 3 lettere
     * @return string|null
     */
    public static function iso3_to_iso1( $iso3 ) {
        $map = self::get_language_map();
        return isset( $map[ $iso3 ] ) ? $map[ $iso3 ] : null;
    }

    /**
     * Converte codice lingua ISO 639-1 in ISO 639-2/B
     *
     * @param string $iso1 Codice a 2 lettere
     * @return string|null
     */
    public static function iso1_to_iso3( $iso1 ) {
        $map = array_flip( self::get_language_map() );
        return isset( $map[ $iso1 ] ) ? $map[ $iso1 ] : null;
    }
}
