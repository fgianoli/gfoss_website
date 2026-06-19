<?php
/**
 * Costanti namespace ISO 19139
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_XML_Namespaces
 *
 * Definisce tutti i namespace utilizzati negli schemi ISO 19139/19119
 */
class RNDT_XML_Namespaces {

    /**
     * Namespace ISO 19139 Geographic Metadata
     */
    const GMD = 'http://www.isotc211.org/2005/gmd';

    /**
     * Namespace ISO 19139 Geographic Common
     */
    const GCO = 'http://www.isotc211.org/2005/gco';

    /**
     * Namespace GML 3.2
     */
    const GML = 'http://www.opengis.net/gml/3.2';

    /**
     * Namespace ISO 19119 Services
     */
    const SRV = 'http://www.isotc211.org/2005/srv';

    /**
     * Namespace ISO 19139 Metadata Extension
     */
    const GMX = 'http://www.isotc211.org/2005/gmx';

    /**
     * Namespace XLink
     */
    const XLINK = 'http://www.w3.org/1999/xlink';

    /**
     * Namespace XML Schema Instance
     */
    const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Schema Location ISO 19139
     */
    const SCHEMA_LOCATION = 'http://www.isotc211.org/2005/gmd http://schemas.opengis.net/csw/2.0.2/profiles/apiso/1.0.0/apiso.xsd';

    /**
     * Ottieni tutti i namespace come array
     *
     * @return array
     */
    public static function get_all() {
        return array(
            'gmd'   => self::GMD,
            'gco'   => self::GCO,
            'gml'   => self::GML,
            'srv'   => self::SRV,
            'gmx'   => self::GMX,
            'xlink' => self::XLINK,
            'xsi'   => self::XSI,
        );
    }

    /**
     * Ottieni i namespace per il documento root
     *
     * @param bool $include_srv Include namespace srv per servizi
     * @return array
     */
    public static function get_root_namespaces( $include_srv = false ) {
        // NB: xmlns:xsi è dichiarato automaticamente da setAttributeNS()
        // per xsi:schemaLocation in create_root_element(), quindi NON va incluso qui
        // per evitare "Attribute xmlns:xsi redefined".
        $namespaces = array(
            'xmlns:gco'   => self::GCO,
            'xmlns:gml'   => self::GML,
            'xmlns:gmx'   => self::GMX,
            'xmlns:xlink' => self::XLINK,
        );

        if ( $include_srv ) {
            $namespaces['xmlns:srv'] = self::SRV;
        }

        return $namespaces;
    }
}
