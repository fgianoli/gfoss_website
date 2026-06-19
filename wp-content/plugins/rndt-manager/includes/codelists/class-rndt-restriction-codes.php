<?php
/**
 * Codelist delle restrizioni (MD_RestrictionCode)
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Restriction_Codes
 */
class RNDT_Restriction_Codes {

    /**
     * URI base per i codici ISO 19139
     */
    const CODELIST_URI = 'http://standards.iso.org/iso/19139/resources/gmxCodelists.xml#MD_RestrictionCode';

    /**
     * Ottieni tutti i codici di restrizione
     *
     * @return array
     */
    public static function get_all() {
        return array(
            'copyright' => array(
                'it' => 'Copyright',
                'en' => 'Copyright',
            ),
            'patent' => array(
                'it' => 'Brevetto',
                'en' => 'Patent',
            ),
            'patentPending' => array(
                'it' => 'Brevetto in attesa',
                'en' => 'Patent Pending',
            ),
            'trademark' => array(
                'it' => 'Marchio registrato',
                'en' => 'Trademark',
            ),
            'license' => array(
                'it' => 'Licenza',
                'en' => 'License',
            ),
            'intellectualPropertyRights' => array(
                'it' => 'Diritti di proprietà intellettuale',
                'en' => 'Intellectual Property Rights',
            ),
            'restricted' => array(
                'it' => 'Riservato',
                'en' => 'Restricted',
            ),
            'otherRestrictions' => array(
                'it' => 'Altre restrizioni',
                'en' => 'Other Restrictions',
            ),
        );
    }

    /**
     * Ottieni un codice specifico
     *
     * @param string $code Codice della restrizione
     * @return array|null
     */
    public static function get( $code ) {
        $codes = self::get_all();
        return isset( $codes[ $code ] ) ? $codes[ $code ] : null;
    }

    /**
     * Ottieni il label di una restrizione nella lingua specificata
     *
     * @param string $code Codice della restrizione
     * @param string $lang Lingua ('it' o 'en')
     * @return string|null
     */
    public static function get_label( $code, $lang = 'it' ) {
        $restriction = self::get( $code );
        if ( ! $restriction ) {
            return null;
        }
        return isset( $restriction[ $lang ] ) ? $restriction[ $lang ] : $restriction['en'];
    }

    /**
     * Ottieni le restrizioni come opzioni per un select
     *
     * @param string $lang Lingua ('it' o 'en')
     * @return array
     */
    public static function get_options( $lang = 'it' ) {
        $codes   = self::get_all();
        $options = array();

        foreach ( $codes as $code => $restriction ) {
            $label = isset( $restriction[ $lang ] ) ? $restriction[ $lang ] : $restriction['en'];
            $options[ $code ] = $label;
        }

        return $options;
    }

    /**
     * Ottieni l'URI della codelist
     *
     * @return string
     */
    public static function get_codelist_uri() {
        return self::CODELIST_URI;
    }

    /**
     * Verifica se un codice e valido
     *
     * @param string $code Codice da verificare
     * @return bool
     */
    public static function is_valid( $code ) {
        $codes = self::get_all();
        return isset( $codes[ $code ] );
    }

    /**
     * Ottieni i codici di classificazione di sicurezza (MD_ClassificationCode)
     *
     * @return array
     */
    public static function get_classification_codes() {
        return array(
            'unclassified' => array(
                'it' => 'Non classificato',
                'en' => 'Unclassified',
            ),
            'restricted' => array(
                'it' => 'Riservato',
                'en' => 'Restricted',
            ),
            'confidential' => array(
                'it' => 'Confidenziale',
                'en' => 'Confidential',
            ),
            'secret' => array(
                'it' => 'Segreto',
                'en' => 'Secret',
            ),
            'topSecret' => array(
                'it' => 'Top Secret',
                'en' => 'Top Secret',
            ),
        );
    }

    /**
     * Ottieni le opzioni di classificazione per un select
     *
     * @param string $lang Lingua ('it' o 'en')
     * @return array
     */
    public static function get_classification_options( $lang = 'it' ) {
        $codes   = self::get_classification_codes();
        $options = array();

        foreach ( $codes as $code => $classification ) {
            $label = isset( $classification[ $lang ] ) ? $classification[ $lang ] : $classification['en'];
            $options[ $code ] = $label;
        }

        return $options;
    }
}
