<?php
/**
 * Codelist dei ruoli responsabili (CI_RoleCode)
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Role_Codes
 */
class RNDT_Role_Codes {

    /**
     * URI base per i codici ISO 19139
     */
    const CODELIST_URI = 'http://standards.iso.org/iso/19139/resources/gmxCodelists.xml#CI_RoleCode';

    /**
     * Ottieni tutti i codici ruolo
     *
     * @return array
     */
    public static function get_all() {
        return array(
            'resourceProvider' => array(
                'it' => 'Fornitore della risorsa',
                'en' => 'Resource Provider',
            ),
            'custodian' => array(
                'it' => 'Custode',
                'en' => 'Custodian',
            ),
            'owner' => array(
                'it' => 'Proprietario',
                'en' => 'Owner',
            ),
            'user' => array(
                'it' => 'Utente',
                'en' => 'User',
            ),
            'distributor' => array(
                'it' => 'Distributore',
                'en' => 'Distributor',
            ),
            'originator' => array(
                'it' => 'Autore',
                'en' => 'Originator',
            ),
            'pointOfContact' => array(
                'it' => 'Punto di contatto',
                'en' => 'Point of Contact',
            ),
            'principalInvestigator' => array(
                'it' => 'Ricercatore principale',
                'en' => 'Principal Investigator',
            ),
            'processor' => array(
                'it' => 'Responsabile dell\'elaborazione',
                'en' => 'Processor',
            ),
            'publisher' => array(
                'it' => 'Editore',
                'en' => 'Publisher',
            ),
            'author' => array(
                'it' => 'Autore',
                'en' => 'Author',
            ),
        );
    }

    /**
     * Ottieni un codice specifico
     *
     * @param string $code Codice del ruolo
     * @return array|null
     */
    public static function get( $code ) {
        $codes = self::get_all();
        return isset( $codes[ $code ] ) ? $codes[ $code ] : null;
    }

    /**
     * Ottieni il label di un ruolo nella lingua specificata
     *
     * @param string $code Codice del ruolo
     * @param string $lang Lingua ('it' o 'en')
     * @return string|null
     */
    public static function get_label( $code, $lang = 'it' ) {
        $role = self::get( $code );
        if ( ! $role ) {
            return null;
        }
        return isset( $role[ $lang ] ) ? $role[ $lang ] : $role['en'];
    }

    /**
     * Ottieni i ruoli come opzioni per un select
     *
     * @param string $lang Lingua ('it' o 'en')
     * @return array
     */
    public static function get_options( $lang = 'it' ) {
        $codes   = self::get_all();
        $options = array();

        foreach ( $codes as $code => $role ) {
            $label = isset( $role[ $lang ] ) ? $role[ $lang ] : $role['en'];
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
     * Ottieni i ruoli raccomandati per il contatto del metadato
     *
     * @return array
     */
    public static function get_metadata_contact_roles() {
        return array( 'pointOfContact' );
    }

    /**
     * Ottieni i ruoli raccomandati per il responsabile della risorsa
     *
     * @return array
     */
    public static function get_resource_roles() {
        return array(
            'resourceProvider',
            'custodian',
            'owner',
            'pointOfContact',
            'author',
            'publisher',
        );
    }

    /**
     * Ottieni i ruoli raccomandati per il distributore
     *
     * @return array
     */
    public static function get_distributor_roles() {
        return array( 'distributor' );
    }
}
