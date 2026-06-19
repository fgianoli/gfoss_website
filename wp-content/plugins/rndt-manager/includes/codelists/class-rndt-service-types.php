<?php
/**
 * Codelist dei tipi di servizio INSPIRE (SV_ServiceType)
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Service_Types
 */
class RNDT_Service_Types {

    /**
     * Ottieni tutti i tipi di servizio
     *
     * @return array
     */
    public static function get_all() {
        return array(
            'discovery' => array(
                'it'          => 'Servizio di ricerca',
                'en'          => 'Discovery Service',
                'description' => 'Servizio che consente la ricerca di set di dati e servizi territoriali',
            ),
            'view' => array(
                'it'          => 'Servizio di consultazione',
                'en'          => 'View Service',
                'description' => 'Servizio che consente la visualizzazione di set di dati',
            ),
            'download' => array(
                'it'          => 'Servizio di scaricamento',
                'en'          => 'Download Service',
                'description' => 'Servizio che consente lo scaricamento di copie di set di dati',
            ),
            'transformation' => array(
                'it'          => 'Servizio di conversione',
                'en'          => 'Transformation Service',
                'description' => 'Servizio che consente la trasformazione di set di dati',
            ),
            'invoke' => array(
                'it'          => 'Servizio di richiesta servizi',
                'en'          => 'Invoke Spatial Data Service',
                'description' => 'Servizio che consente la richiesta di servizi su dati territoriali',
            ),
            'other' => array(
                'it'          => 'Altri servizi',
                'en'          => 'Other Services',
                'description' => 'Altri servizi territoriali non classificati',
            ),
        );
    }

    /**
     * Ottieni un tipo specifico per codice
     *
     * @param string $code Codice del tipo di servizio
     * @return array|null
     */
    public static function get( $code ) {
        $types = self::get_all();
        return isset( $types[ $code ] ) ? $types[ $code ] : null;
    }

    /**
     * Ottieni il label di un tipo nella lingua specificata
     *
     * @param string $code Codice del tipo
     * @param string $lang Lingua ('it' o 'en')
     * @return string|null
     */
    public static function get_label( $code, $lang = 'it' ) {
        $type = self::get( $code );
        if ( ! $type ) {
            return null;
        }
        return isset( $type[ $lang ] ) ? $type[ $lang ] : $type['en'];
    }

    /**
     * Ottieni i tipi come opzioni per un select (codice => label)
     *
     * @param string $lang Lingua ('it' o 'en')
     * @return array
     */
    public static function get_options( $lang = 'it' ) {
        $types   = self::get_all();
        $options = array();

        foreach ( $types as $code => $type ) {
            $label = isset( $type[ $lang ] ) ? $type[ $lang ] : $type['en'];
            $options[ $code ] = $label;
        }

        return $options;
    }

    /**
     * Verifica se un codice e valido
     *
     * @param string $code Codice da verificare
     * @return bool
     */
    public static function is_valid( $code ) {
        $types = self::get_all();
        return isset( $types[ $code ] );
    }

    /**
     * Ottieni i protocolli OGC associati a un tipo di servizio
     *
     * @param string $code Codice del tipo di servizio
     * @return array
     */
    public static function get_protocols( $code ) {
        $protocols = array(
            'discovery' => array( 'OGC:CSW' ),
            'view' => array( 'OGC:WMS', 'OGC:WMTS' ),
            'download' => array( 'OGC:WFS', 'OGC:WCS', 'OGC:SOS', 'ATOM' ),
            'transformation' => array( 'OGC:WPS' ),
            'invoke' => array( 'OGC:WPS', 'OGC:SOS' ),
            'other' => array(),
        );

        return isset( $protocols[ $code ] ) ? $protocols[ $code ] : array();
    }
}
