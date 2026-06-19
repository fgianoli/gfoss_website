<?php
/**
 * URI Codelist ISO 19139
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_XML_Codelists
 *
 * URI base e mappature per le codelist ISO 19115/19139
 */
class RNDT_XML_Codelists {

    /**
     * URI base codelist ISO
     */
    const BASE_URI = 'http://standards.iso.org/iso/19139/resources/gmxCodelists.xml';

    /**
     * URI base codelist INSPIRE
     */
    const INSPIRE_BASE_URI = 'http://inspire.ec.europa.eu/codelist';

    /**
     * URI thesaurus GEMET INSPIRE
     */
    const GEMET_INSPIRE_URI = 'http://inspire.ec.europa.eu/theme';

    /**
     * URI thesaurus GEMET
     */
    const GEMET_URI = 'http://www.eionet.europa.eu/gemet';

    /**
     * Ottieni URI completo per codelist
     *
     * @param string $codelist Nome codelist
     * @return string
     */
    public static function get_codelist_uri( $codelist ) {
        return self::BASE_URI . '#' . $codelist;
    }

    /**
     * Ottieni URI per valore codelist
     *
     * @param string $codelist Nome codelist
     * @param string $value    Valore
     * @return string
     */
    public static function get_value_uri( $codelist, $value ) {
        return self::BASE_URI . '#' . $codelist . '_' . $value;
    }

    /**
     * Mappatura CI_DateTypeCode
     *
     * @return array
     */
    public static function get_date_type_codes() {
        return array(
            'creation'    => 'creation',
            'publication' => 'publication',
            'revision'    => 'revision',
        );
    }

    /**
     * Mappatura MD_ScopeCode (hierarchy level)
     *
     * @return array
     */
    public static function get_scope_codes() {
        return array(
            'dataset'           => 'dataset',
            'series'            => 'series',
            'service'           => 'service',
            'application'       => 'application',
            'nonGeographicDataset' => 'nonGeographicDataset',
        );
    }

    /**
     * Mappatura MD_CharacterSetCode
     *
     * @return array
     */
    public static function get_character_set_codes() {
        return array(
            'utf8'        => 'utf8',
            'utf16'       => 'utf16',
            'iso88591'    => '8859part1',
            'iso885915'   => '8859part15',
        );
    }

    /**
     * Mappatura MD_TopicCategoryCode
     * Nota: questo non è una codelist ma un'enumerazione
     *
     * @return array
     */
    public static function get_topic_category_codes() {
        return array(
            'farming',
            'biota',
            'boundaries',
            'climatologyMeteorologyAtmosphere',
            'economy',
            'elevation',
            'environment',
            'geoscientificInformation',
            'health',
            'imageryBaseMapsEarthCover',
            'intelligenceMilitary',
            'inlandWaters',
            'location',
            'oceans',
            'planningCadastre',
            'society',
            'structure',
            'transportation',
            'utilitiesCommunication',
        );
    }

    /**
     * Mappatura MD_SpatialRepresentationTypeCode
     *
     * @return array
     */
    public static function get_spatial_representation_codes() {
        return array(
            'vector'    => 'vector',
            'grid'      => 'grid',
            'textTable' => 'textTable',
            'tin'       => 'tin',
            'stereoModel' => 'stereoModel',
            'video'     => 'video',
        );
    }

    /**
     * Mappatura MD_ProgressCode
     *
     * @return array
     */
    public static function get_progress_codes() {
        return array(
            'completed'     => 'completed',
            'historicalArchive' => 'historicalArchive',
            'obsolete'      => 'obsolete',
            'onGoing'       => 'onGoing',
            'planned'       => 'planned',
            'required'      => 'required',
            'underDevelopment' => 'underDevelopment',
        );
    }

    /**
     * Mappatura MD_MaintenanceFrequencyCode
     *
     * @return array
     */
    public static function get_maintenance_frequency_codes() {
        return array(
            'continual'     => 'continual',
            'daily'         => 'daily',
            'weekly'        => 'weekly',
            'fortnightly'   => 'fortnightly',
            'monthly'       => 'monthly',
            'quarterly'     => 'quarterly',
            'biannually'    => 'biannually',
            'annually'      => 'annually',
            'asNeeded'      => 'asNeeded',
            'irregular'     => 'irregular',
            'notPlanned'    => 'notPlanned',
            'unknown'       => 'unknown',
        );
    }

    /**
     * Mappatura CI_OnLineFunctionCode
     *
     * @return array
     */
    public static function get_online_function_codes() {
        return array(
            'download'      => 'download',
            'information'   => 'information',
            'offlineAccess' => 'offlineAccess',
            'order'         => 'order',
            'search'        => 'search',
        );
    }

    /**
     * Mappatura DQ_EvaluationMethodTypeCode
     *
     * @return array
     */
    public static function get_evaluation_method_codes() {
        return array(
            'directInternal'   => 'directInternal',
            'directExternal'   => 'directExternal',
            'indirect'         => 'indirect',
        );
    }

    /**
     * Mappatura SV_CouplingType (ISO 19119)
     *
     * @return array
     */
    public static function get_coupling_type_codes() {
        return array(
            'loose'  => 'loose',
            'mixed'  => 'mixed',
            'tight'  => 'tight',
        );
    }

    /**
     * Mappatura DCPList (Distributed Computing Platform)
     *
     * @return array
     */
    public static function get_dcp_codes() {
        return array(
            'XML'   => 'XML',
            'CORBA' => 'CORBA',
            'JAVA'  => 'JAVA',
            'COM'   => 'COM',
            'SQL'   => 'SQL',
            'WebServices' => 'WebServices',
        );
    }

    /**
     * Ottieni codice lingua ISO 639-2/B da codice ISO 639-1
     *
     * @param string $iso639_1 Codice ISO 639-1 (es: 'it')
     * @return string Codice ISO 639-2/B (es: 'ita')
     */
    public static function get_language_code( $iso639_1 ) {
        $mapping = array(
            'it' => 'ita',
            'en' => 'eng',
            'de' => 'deu',
            'fr' => 'fra',
            'es' => 'spa',
            'pt' => 'por',
            'nl' => 'nld',
            'el' => 'ell',
            'pl' => 'pol',
            'cs' => 'ces',
            'sk' => 'slk',
            'hu' => 'hun',
            'ro' => 'ron',
            'bg' => 'bul',
            'hr' => 'hrv',
            'sl' => 'slv',
            'et' => 'est',
            'lv' => 'lav',
            'lt' => 'lit',
            'mt' => 'mlt',
            'fi' => 'fin',
            'sv' => 'swe',
            'da' => 'dan',
            'ga' => 'gle',
        );

        // Se già in formato ISO 639-2/B
        if ( strlen( $iso639_1 ) === 3 ) {
            return $iso639_1;
        }

        return $mapping[ strtolower( $iso639_1 ) ] ?? 'ita';
    }

    /**
     * Ottieni URI tema INSPIRE
     *
     * @param string $theme_code Codice tema (es: 'au')
     * @return string
     */
    public static function get_inspire_theme_uri( $theme_code ) {
        return self::GEMET_INSPIRE_URI . '/' . strtolower( $theme_code );
    }

    /**
     * Ottieni URI specifiche INSPIRE per conformità
     *
     * @param string $spec_type Tipo specifica
     * @return array Array con title e date
     */
    public static function get_inspire_specification( $spec_type = 'metadata' ) {
        $specs = array(
            'metadata' => array(
                'title' => 'REGOLAMENTO (CE) N. 1205/2008 DELLA COMMISSIONE del 3 dicembre 2008 recante attuazione della direttiva 2007/2/CE del Parlamento europeo e del Consiglio per quanto riguarda i metadati',
                'date'  => '2008-12-04',
                'type'  => 'publication',
            ),
            'interoperability' => array(
                'title' => 'REGOLAMENTO (UE) N. 1089/2010 DELLA COMMISSIONE del 23 novembre 2010 recante attuazione della direttiva 2007/2/CE del Parlamento europeo e del Consiglio per quanto riguarda l\'interoperabilità dei set di dati territoriali e dei servizi di dati territoriali',
                'date'  => '2010-12-08',
                'type'  => 'publication',
            ),
            'network_services' => array(
                'title' => 'REGOLAMENTO (CE) N. 976/2009 DELLA COMMISSIONE del 19 ottobre 2009 recante attuazione della direttiva 2007/2/CE del Parlamento europeo e del Consiglio per quanto riguarda i servizi di rete',
                'date'  => '2009-10-20',
                'type'  => 'publication',
            ),
            'rndt' => array(
                'title' => 'Linee guida nazionali per i metadati - RNDT',
                'date'  => '2020-01-01',
                'type'  => 'publication',
            ),
        );

        return $specs[ $spec_type ] ?? $specs['metadata'];
    }
}
