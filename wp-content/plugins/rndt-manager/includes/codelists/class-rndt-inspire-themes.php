<?php
/**
 * Codelist dei 34 temi INSPIRE
 *
 * Dati migrati dal template Joomla RNDT (functions.php)
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Inspire_Themes
 */
class RNDT_Inspire_Themes {

    /**
     * URI base per i temi INSPIRE nel thesaurus GEMET
     */
    const GEMET_BASE_URI = 'http://inspire.ec.europa.eu/theme/';

    /**
     * Nome del thesaurus GEMET INSPIRE
     */
    const THESAURUS_NAME = 'GEMET - INSPIRE themes, version 1.0';

    /**
     * Data del thesaurus
     */
    const THESAURUS_DATE = '2008-06-01';

    /**
     * Ottieni tutti i temi INSPIRE
     *
     * @return array
     */
    public static function get_all() {
        return array(
            // Annex I
            'rs' => array(
                'it'    => 'Sistemi di coordinate',
                'en'    => 'Coordinate reference systems',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'rs',
            ),
            'gg' => array(
                'it'    => 'Sistemi di griglie geografiche',
                'en'    => 'Geographical grid systems',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'gg',
            ),
            'gn' => array(
                'it'    => 'Nomi geografici',
                'en'    => 'Geographical names',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'gn',
            ),
            'au' => array(
                'it'    => 'Unità amministrative',
                'en'    => 'Administrative units',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'au',
            ),
            'ad' => array(
                'it'    => 'Indirizzi',
                'en'    => 'Addresses',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'ad',
            ),
            'cp' => array(
                'it'    => 'Parcelle catastali',
                'en'    => 'Cadastral parcels',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'cp',
            ),
            'tn' => array(
                'it'    => 'Reti di trasporto',
                'en'    => 'Transport networks',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'tn',
            ),
            'hy' => array(
                'it'    => 'Idrografia',
                'en'    => 'Hydrography',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'hy',
            ),
            'ps' => array(
                'it'    => 'Siti protetti',
                'en'    => 'Protected sites',
                'annex' => 'I',
                'uri'   => self::GEMET_BASE_URI . 'ps',
            ),

            // Annex II
            'el' => array(
                'it'    => 'Elevazione',
                'en'    => 'Elevation',
                'annex' => 'II',
                'uri'   => self::GEMET_BASE_URI . 'el',
            ),
            'lc' => array(
                'it'    => 'Copertura del suolo',
                'en'    => 'Land cover',
                'annex' => 'II',
                'uri'   => self::GEMET_BASE_URI . 'lc',
            ),
            'oi' => array(
                'it'    => 'Ortoimmagini',
                'en'    => 'Orthoimagery',
                'annex' => 'II',
                'uri'   => self::GEMET_BASE_URI . 'oi',
            ),
            'ge' => array(
                'it'    => 'Geologia',
                'en'    => 'Geology',
                'annex' => 'II',
                'uri'   => self::GEMET_BASE_URI . 'ge',
            ),

            // Annex III
            'su' => array(
                'it'    => 'Unità statistiche',
                'en'    => 'Statistical units',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'su',
            ),
            'bu' => array(
                'it'    => 'Edifici',
                'en'    => 'Buildings',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'bu',
            ),
            'so' => array(
                'it'    => 'Suolo',
                'en'    => 'Soil',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'so',
            ),
            'lu' => array(
                'it'    => 'Utilizzo del territorio',
                'en'    => 'Land use',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'lu',
            ),
            'hh' => array(
                'it'    => 'Salute umana e sicurezza',
                'en'    => 'Human health and safety',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'hh',
            ),
            'us' => array(
                'it'    => 'Servizi di pubblica utilità e servizi amministrativi',
                'en'    => 'Utility and governmental services',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'us',
            ),
            'ef' => array(
                'it'    => 'Impianti di monitoraggio ambientale',
                'en'    => 'Environmental monitoring facilities',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'ef',
            ),
            'pf' => array(
                'it'    => 'Produzione e impianti industriali',
                'en'    => 'Production and industrial facilities',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'pf',
            ),
            'af' => array(
                'it'    => 'Impianti agricoli e di acquacoltura',
                'en'    => 'Agricultural and aquaculture facilities',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'af',
            ),
            'pd' => array(
                'it'    => 'Distribuzione della popolazione - demografia',
                'en'    => 'Population distribution - demography',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'pd',
            ),
            'am' => array(
                'it'    => 'Zone sottoposte a gestione/limitazioni/regolamentazione e unità con obbligo di comunicare dati',
                'en'    => 'Area management/restriction/regulation zones and reporting units',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'am',
            ),
            'nz' => array(
                'it'    => 'Zone a rischio naturale',
                'en'    => 'Natural risk zones',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'nz',
            ),
            'ac' => array(
                'it'    => 'Condizioni atmosferiche',
                'en'    => 'Atmospheric conditions',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'ac',
            ),
            'mf' => array(
                'it'    => 'Elementi geografici meteorologici',
                'en'    => 'Meteorological geographical features',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'mf',
            ),
            'of' => array(
                'it'    => 'Elementi geografici oceanografici',
                'en'    => 'Oceanographic geographical features',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'of',
            ),
            'sr' => array(
                'it'    => 'Regioni marine',
                'en'    => 'Sea regions',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'sr',
            ),
            'br' => array(
                'it'    => 'Regioni biogeografiche',
                'en'    => 'Bio-geographical regions',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'br',
            ),
            'hb' => array(
                'it'    => 'Habitat e biotopi',
                'en'    => 'Habitats and biotopes',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'hb',
            ),
            'sd' => array(
                'it'    => 'Distribuzione delle specie',
                'en'    => 'Species distribution',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'sd',
            ),
            'er' => array(
                'it'    => 'Risorse energetiche',
                'en'    => 'Energy resources',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'er',
            ),
            'mr' => array(
                'it'    => 'Risorse minerarie',
                'en'    => 'Mineral resources',
                'annex' => 'III',
                'uri'   => self::GEMET_BASE_URI . 'mr',
            ),
        );
    }

    /**
     * Ottieni un tema specifico per codice
     *
     * @param string $code Codice del tema (es. 'el', 'hy')
     * @return array|null
     */
    public static function get( $code ) {
        $themes = self::get_all();
        return isset( $themes[ $code ] ) ? $themes[ $code ] : null;
    }

    /**
     * Ottieni il label di un tema nella lingua specificata
     *
     * @param string $code Codice del tema
     * @param string $lang Lingua ('it' o 'en')
     * @return string|null
     */
    public static function get_label( $code, $lang = 'it' ) {
        $theme = self::get( $code );
        if ( ! $theme ) {
            return null;
        }
        return isset( $theme[ $lang ] ) ? $theme[ $lang ] : $theme['en'];
    }

    /**
     * Ottieni l'URI del tema per l'elemento gmx:Anchor
     *
     * @param string $code Codice del tema
     * @return string|null
     */
    public static function get_uri( $code ) {
        $theme = self::get( $code );
        return $theme ? $theme['uri'] : null;
    }

    /**
     * Ottieni i temi raggruppati per Annex
     *
     * @return array
     */
    public static function get_by_annex() {
        $themes = self::get_all();
        $grouped = array(
            'I'   => array(),
            'II'  => array(),
            'III' => array(),
        );

        foreach ( $themes as $code => $theme ) {
            $grouped[ $theme['annex'] ][ $code ] = $theme;
        }

        return $grouped;
    }

    /**
     * Ottieni i temi come opzioni per un select (codice => label)
     *
     * @param string $lang Lingua ('it' o 'en')
     * @return array
     */
    public static function get_options( $lang = 'it' ) {
        $themes  = self::get_all();
        $options = array();

        foreach ( $themes as $code => $theme ) {
            $label = isset( $theme[ $lang ] ) ? $theme[ $lang ] : $theme['en'];
            $options[ $code ] = $label;
        }

        asort( $options );
        return $options;
    }

    /**
     * Ottieni il nome del thesaurus GEMET INSPIRE
     *
     * @return string
     */
    public static function get_thesaurus_name() {
        return self::THESAURUS_NAME;
    }

    /**
     * Ottieni la data del thesaurus
     *
     * @return string
     */
    public static function get_thesaurus_date() {
        return self::THESAURUS_DATE;
    }

    /**
     * Cerca temi per testo
     *
     * @param string $search Testo da cercare
     * @param string $lang   Lingua ('it' o 'en')
     * @return array
     */
    public static function search( $search, $lang = 'it' ) {
        $themes  = self::get_all();
        $results = array();
        $search  = mb_strtolower( $search );

        foreach ( $themes as $code => $theme ) {
            $label = isset( $theme[ $lang ] ) ? $theme[ $lang ] : $theme['en'];
            if ( mb_strpos( mb_strtolower( $label ), $search ) !== false ) {
                $results[ $code ] = $theme;
            }
        }

        return $results;
    }
}
