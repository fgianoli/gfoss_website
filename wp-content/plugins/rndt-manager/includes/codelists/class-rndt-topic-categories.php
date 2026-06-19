<?php
/**
 * Codelist delle 19 categorie ISO 19115 (MD_TopicCategoryCode)
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Topic_Categories
 */
class RNDT_Topic_Categories {

    /**
     * Ottieni tutte le categorie ISO 19115
     *
     * @return array
     */
    public static function get_all() {
        return array(
            'farming' => array(
                'it' => 'Agricoltura',
                'en' => 'Farming',
            ),
            'biota' => array(
                'it' => 'Biota',
                'en' => 'Biota',
            ),
            'boundaries' => array(
                'it' => 'Confini',
                'en' => 'Boundaries',
            ),
            'climatologyMeteorologyAtmosphere' => array(
                'it' => 'Climatologia / Meteorologia / Atmosfera',
                'en' => 'Climatology / Meteorology / Atmosphere',
            ),
            'economy' => array(
                'it' => 'Economia',
                'en' => 'Economy',
            ),
            'elevation' => array(
                'it' => 'Elevazione',
                'en' => 'Elevation',
            ),
            'environment' => array(
                'it' => 'Ambiente',
                'en' => 'Environment',
            ),
            'geoscientificInformation' => array(
                'it' => 'Informazioni geoscientifiche',
                'en' => 'Geoscientific Information',
            ),
            'health' => array(
                'it' => 'Salute',
                'en' => 'Health',
            ),
            'imageryBaseMapsEarthCover' => array(
                'it' => 'Mappe di base / Immagini / Copertura terrestre',
                'en' => 'Imagery / Base Maps / Earth Cover',
            ),
            'intelligenceMilitary' => array(
                'it' => 'Intelligence / Settore militare',
                'en' => 'Intelligence / Military',
            ),
            'inlandWaters' => array(
                'it' => 'Acque interne',
                'en' => 'Inland Waters',
            ),
            'location' => array(
                'it' => 'Localizzazione',
                'en' => 'Location',
            ),
            'oceans' => array(
                'it' => 'Acque marine / Oceani',
                'en' => 'Oceans',
            ),
            'planningCadastre' => array(
                'it' => 'Pianificazione / Catasto',
                'en' => 'Planning / Cadastre',
            ),
            'society' => array(
                'it' => 'Società',
                'en' => 'Society',
            ),
            'structure' => array(
                'it' => 'Strutture',
                'en' => 'Structure',
            ),
            'transportation' => array(
                'it' => 'Trasporti',
                'en' => 'Transportation',
            ),
            'utilitiesCommunication' => array(
                'it' => 'Servizi di pubblica utilità / Comunicazione',
                'en' => 'Utilities / Communication',
            ),
        );
    }

    /**
     * Ottieni una categoria specifica per codice
     *
     * @param string $code Codice della categoria
     * @return array|null
     */
    public static function get( $code ) {
        $categories = self::get_all();
        return isset( $categories[ $code ] ) ? $categories[ $code ] : null;
    }

    /**
     * Ottieni il label di una categoria nella lingua specificata
     *
     * @param string $code Codice della categoria
     * @param string $lang Lingua ('it' o 'en')
     * @return string|null
     */
    public static function get_label( $code, $lang = 'it' ) {
        $category = self::get( $code );
        if ( ! $category ) {
            return null;
        }
        return isset( $category[ $lang ] ) ? $category[ $lang ] : $category['en'];
    }

    /**
     * Ottieni le categorie come opzioni per un select (codice => label)
     *
     * @param string $lang Lingua ('it' o 'en')
     * @return array
     */
    public static function get_options( $lang = 'it' ) {
        $categories = self::get_all();
        $options    = array();

        foreach ( $categories as $code => $category ) {
            $label = isset( $category[ $lang ] ) ? $category[ $lang ] : $category['en'];
            $options[ $code ] = $label;
        }

        asort( $options );
        return $options;
    }

    /**
     * Verifica se un codice e valido
     *
     * @param string $code Codice da verificare
     * @return bool
     */
    public static function is_valid( $code ) {
        $categories = self::get_all();
        return isset( $categories[ $code ] );
    }

    /**
     * Cerca categorie per testo
     *
     * @param string $search Testo da cercare
     * @param string $lang   Lingua ('it' o 'en')
     * @return array
     */
    public static function search( $search, $lang = 'it' ) {
        $categories = self::get_all();
        $results    = array();
        $search     = mb_strtolower( $search );

        foreach ( $categories as $code => $category ) {
            $label = isset( $category[ $lang ] ) ? $category[ $lang ] : $category['en'];
            if ( mb_strpos( mb_strtolower( $label ), $search ) !== false ) {
                $results[ $code ] = $category;
            }
        }

        return $results;
    }
}
