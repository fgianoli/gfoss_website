<?php
/**
 * Parser XML ISO 19139
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-rndt-xml-namespaces.php';
require_once __DIR__ . '/class-rndt-xml-codelists.php';

/**
 * Classe RNDT_XML_Parser
 *
 * Importa documenti XML ISO 19139 e li converte in array per il salvataggio
 */
class RNDT_XML_Parser {

    /**
     * Documento DOM
     *
     * @var DOMDocument
     */
    private $doc;

    /**
     * XPath per query
     *
     * @var DOMXPath
     */
    private $xpath;

    /**
     * Errori di parsing
     *
     * @var array
     */
    private $errors = array();

    /**
     * Costruttore
     */
    public function __construct() {
        $this->doc = new DOMDocument();
    }

    /**
     * Parse XML string
     *
     * @param string $xml_string XML da parsare
     * @return array|WP_Error Array dati o errore
     */
    public function parse( $xml_string ) {
        $this->errors = array();

        // Carica XML - LIBXML_NONET previene attacchi XXE (no network access)
        libxml_use_internal_errors( true );
        $loaded = $this->doc->loadXML( $xml_string, LIBXML_NONET );

        if ( ! $loaded ) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            return new WP_Error(
                'xml_parse_error',
                __( 'Errore nel parsing XML: ', 'rndt-manager' ) . $errors[0]->message
            );
        }

        // Setup XPath con namespace
        $this->setup_xpath();

        // Verifica root element
        $root = $this->doc->documentElement;
        if ( $root->localName !== 'MD_Metadata' ) {
            return new WP_Error(
                'invalid_root',
                __( 'Elemento root non valido. Atteso gmd:MD_Metadata.', 'rndt-manager' )
            );
        }

        // Estrai dati
        return $this->extract_metadata();
    }

    /**
     * Parse file XML
     *
     * @param string $file_path Percorso file
     * @return array|WP_Error Array dati o errore
     */
    public function parse_file( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File non trovato.', 'rndt-manager' ) );
        }

        $xml_string = file_get_contents( $file_path );
        return $this->parse( $xml_string );
    }

    /**
     * Setup XPath con namespace
     */
    private function setup_xpath() {
        $this->xpath = new DOMXPath( $this->doc );

        foreach ( RNDT_XML_Namespaces::get_all() as $prefix => $uri ) {
            $this->xpath->registerNamespace( $prefix, $uri );
        }
    }

    /**
     * Estrai metadati dal documento
     *
     * @return array
     */
    private function extract_metadata() {
        $data = array(
            'fields'              => array(),
            'keywords'            => array(),
            'responsible_parties' => array(),
            'online_resources'    => array(),
            'distribution_formats'=> array(),
            'conformity'          => array(),
            'service_operations'  => array(),
            'coupled_resources'   => array(),
        );

        // File identifier
        $data['fields']['file_identifier'] = $this->get_text( '//gmd:fileIdentifier/gco:CharacterString' );

        // Language
        $data['fields']['metadata_language'] = $this->get_language( '//gmd:language' );

        // Character set
        $data['fields']['metadata_character_set'] = $this->get_codelist_value( '//gmd:characterSet/gmd:MD_CharacterSetCode' );

        // Parent identifier
        $data['fields']['parent_identifier'] = $this->get_text( '//gmd:parentIdentifier/gco:CharacterString' );

        // Hierarchy level
        $data['fields']['resource_type'] = $this->get_codelist_value( '//gmd:hierarchyLevel/gmd:MD_ScopeCode' );

        // Date stamp
        $data['fields']['metadata_date'] = $this->get_date( '//gmd:dateStamp' );

        // Metadata standard
        $data['fields']['metadata_standard_name'] = $this->get_text( '//gmd:metadataStandardName/gco:CharacterString' );
        $data['fields']['metadata_standard_version'] = $this->get_text( '//gmd:metadataStandardVersion/gco:CharacterString' );

        // Reference system
        $this->extract_reference_system( $data );

        // Metadata contact
        $this->extract_contacts( $data, '//gmd:contact/gmd:CI_ResponsibleParty', 'metadata_contact' );

        // Identification info
        $this->extract_identification( $data );

        // Distribution info
        $this->extract_distribution( $data );

        // Data quality info
        $this->extract_data_quality( $data );

        return $data;
    }

    /**
     * Estrai reference system info
     *
     * @param array $data Array dati
     */
    private function extract_reference_system( &$data ) {
        $code = $this->get_text( '//gmd:referenceSystemInfo/gmd:MD_ReferenceSystem/gmd:referenceSystemIdentifier/gmd:RS_Identifier/gmd:code/gco:CharacterString' );
        $code_space = $this->get_text( '//gmd:referenceSystemInfo/gmd:MD_ReferenceSystem/gmd:referenceSystemIdentifier/gmd:RS_Identifier/gmd:codeSpace/gco:CharacterString' );

        if ( $code ) {
            $data['fields']['reference_system_code'] = $code;
            $data['fields']['reference_system_code_space'] = $code_space ?: 'EPSG';
        }
    }

    /**
     * Estrai identification info
     *
     * @param array $data Array dati
     */
    private function extract_identification( &$data ) {
        // Determina se è un servizio
        $is_service = ( $data['fields']['resource_type'] === 'service' );
        $base_path = $is_service
            ? '//gmd:identificationInfo/srv:SV_ServiceIdentification'
            : '//gmd:identificationInfo/gmd:MD_DataIdentification';

        // Citation
        $this->extract_citation( $data, $base_path . '/gmd:citation/gmd:CI_Citation' );

        // Abstract
        $data['fields']['abstract'] = $this->get_text( $base_path . '/gmd:abstract/gco:CharacterString' );

        // Purpose
        $data['fields']['purpose'] = $this->get_text( $base_path . '/gmd:purpose/gco:CharacterString' );

        // Status
        $data['fields']['status'] = $this->get_codelist_value( $base_path . '/gmd:status/gmd:MD_ProgressCode' );

        // Point of contact
        $this->extract_contacts( $data, $base_path . '/gmd:pointOfContact/gmd:CI_ResponsibleParty', 'resource_poc' );

        // Maintenance frequency
        $data['fields']['maintenance_frequency'] = $this->get_codelist_value(
            $base_path . '/gmd:resourceMaintenance/gmd:MD_MaintenanceInformation/gmd:maintenanceAndUpdateFrequency/gmd:MD_MaintenanceFrequencyCode'
        );

        // Thumbnail
        $data['fields']['thumbnail_url'] = $this->get_text( $base_path . '/gmd:graphicOverview/gmd:MD_BrowseGraphic/gmd:fileName/gco:CharacterString' );

        // Keywords
        $this->extract_keywords( $data, $base_path );

        // Constraints
        $this->extract_constraints( $data, $base_path );

        // Spatial representation type (solo per dataset)
        if ( ! $is_service ) {
            $data['fields']['spatial_representation_type'] = $this->get_codelist_value(
                $base_path . '/gmd:spatialRepresentationType/gmd:MD_SpatialRepresentationTypeCode'
            );

            // Spatial resolution
            $this->extract_spatial_resolution( $data, $base_path );

            // Resource language
            $data['fields']['resource_language'] = $this->get_language( $base_path . '/gmd:language' );

            // Dataset character set
            $data['fields']['dataset_character_set'] = $this->get_codelist_value(
                $base_path . '/gmd:characterSet/gmd:MD_CharacterSetCode'
            );

            // Topic categories
            $this->extract_topic_categories( $data, $base_path );
        }

        // Extent
        $extent_path = $is_service ? $base_path . '/srv:extent' : $base_path . '/gmd:extent';
        $this->extract_extent( $data, $extent_path );

        // Service specific
        if ( $is_service ) {
            $this->extract_service_info( $data, $base_path );
        }

        // Supplemental information
        $data['fields']['supplemental_information'] = $this->get_text(
            $base_path . '/gmd:supplementalInformation/gco:CharacterString'
        );
    }

    /**
     * Estrai citation
     *
     * @param array  $data Array dati
     * @param string $path XPath base
     */
    private function extract_citation( &$data, $path ) {
        $data['fields']['title'] = $this->get_text( $path . '/gmd:title/gco:CharacterString' );
        $data['fields']['alternate_title'] = $this->get_text( $path . '/gmd:alternateTitle/gco:CharacterString' );
        $data['fields']['edition'] = $this->get_text( $path . '/gmd:edition/gco:CharacterString' );

        // Dates
        $dates = $this->xpath->query( $path . '/gmd:date/gmd:CI_Date' );
        foreach ( $dates as $date_node ) {
            $date_value = $this->get_node_text( $date_node, 'gmd:date/gco:Date' )
                       ?: $this->get_node_text( $date_node, 'gmd:date/gco:DateTime' );
            $date_type = $this->get_node_codelist( $date_node, 'gmd:dateType/gmd:CI_DateTypeCode' );

            if ( $date_value && $date_type ) {
                $data['fields'][ 'date_' . $date_type ] = $date_value;
            }
        }

        // Identifier
        $data['fields']['resource_identifier'] = $this->get_text( $path . '/gmd:identifier/gmd:RS_Identifier/gmd:code/gco:CharacterString' )
            ?: $this->get_text( $path . '/gmd:identifier/gmd:MD_Identifier/gmd:code/gco:CharacterString' );
        $data['fields']['resource_identifier_codespace'] = $this->get_text( $path . '/gmd:identifier/gmd:RS_Identifier/gmd:codeSpace/gco:CharacterString' );

        // Series
        $data['fields']['series_name'] = $this->get_text( $path . '/gmd:series/gmd:CI_Series/gmd:name/gco:CharacterString' );
        $data['fields']['series_issue'] = $this->get_text( $path . '/gmd:series/gmd:CI_Series/gmd:issueIdentification/gco:CharacterString' );
    }

    /**
     * Estrai keywords
     *
     * @param array  $data      Array dati
     * @param string $base_path XPath base
     */
    private function extract_keywords( &$data, $base_path ) {
        $keyword_groups = $this->xpath->query( $base_path . '/gmd:descriptiveKeywords/gmd:MD_Keywords' );

        foreach ( $keyword_groups as $group ) {
            $thesaurus_title = $this->get_node_text( $group, 'gmd:thesaurusName/gmd:CI_Citation/gmd:title/gco:CharacterString' );
            $thesaurus_date = $this->get_node_text( $group, 'gmd:thesaurusName/gmd:CI_Citation/gmd:date/gmd:CI_Date/gmd:date/gco:Date' );
            $thesaurus_date_type = $this->get_node_codelist( $group, 'gmd:thesaurusName/gmd:CI_Citation/gmd:date/gmd:CI_Date/gmd:dateType/gmd:CI_DateTypeCode' );

            // Determina tipo thesaurus
            $thesaurus_name = 'free';
            if ( $thesaurus_title ) {
                if ( stripos( $thesaurus_title, 'GEMET' ) !== false && stripos( $thesaurus_title, 'INSPIRE' ) !== false ) {
                    $thesaurus_name = 'inspire';
                } elseif ( stripos( $thesaurus_title, 'GEMET' ) !== false ) {
                    $thesaurus_name = 'gemet';
                } else {
                    $thesaurus_name = sanitize_title( $thesaurus_title );
                }
            }

            $keywords = $this->xpath->query( 'gmd:keyword/gco:CharacterString', $group );
            foreach ( $keywords as $kw ) {
                $data['keywords'][] = array(
                    'keyword'             => $kw->nodeValue,
                    'thesaurus_name'      => $thesaurus_name,
                    'thesaurus_title'     => $thesaurus_title,
                    'thesaurus_date'      => $thesaurus_date,
                    'thesaurus_date_type' => $thesaurus_date_type ?: 'publication',
                );
            }
        }
    }

    /**
     * Estrai constraints
     *
     * @param array  $data      Array dati
     * @param string $base_path XPath base
     */
    private function extract_constraints( &$data, $base_path ) {
        // Legal constraints
        $data['fields']['access_constraints'] = $this->get_codelist_value(
            $base_path . '/gmd:resourceConstraints/gmd:MD_LegalConstraints/gmd:accessConstraints/gmd:MD_RestrictionCode'
        );
        $data['fields']['use_constraints'] = $this->get_codelist_value(
            $base_path . '/gmd:resourceConstraints/gmd:MD_LegalConstraints/gmd:useConstraints/gmd:MD_RestrictionCode'
        );
        $data['fields']['other_constraints'] = $this->get_text(
            $base_path . '/gmd:resourceConstraints/gmd:MD_LegalConstraints/gmd:otherConstraints/gco:CharacterString'
        );
        $data['fields']['use_limitation'] = $this->get_text(
            $base_path . '/gmd:resourceConstraints/gmd:MD_LegalConstraints/gmd:useLimitation/gco:CharacterString'
        );

        // Security constraints
        $data['fields']['classification'] = $this->get_codelist_value(
            $base_path . '/gmd:resourceConstraints/gmd:MD_SecurityConstraints/gmd:classification/gmd:MD_ClassificationCode'
        );
    }

    /**
     * Estrai spatial resolution
     *
     * @param array  $data      Array dati
     * @param string $base_path XPath base
     */
    private function extract_spatial_resolution( &$data, $base_path ) {
        // Equivalent scale
        $data['fields']['equivalent_scale'] = $this->get_text(
            $base_path . '/gmd:spatialResolution/gmd:MD_Resolution/gmd:equivalentScale/gmd:MD_RepresentativeFraction/gmd:denominator/gco:Integer'
        );

        // Distance
        $distance_node = $this->xpath->query( $base_path . '/gmd:spatialResolution/gmd:MD_Resolution/gmd:distance/gco:Distance' )->item( 0 );
        if ( $distance_node ) {
            $data['fields']['distance_value'] = $distance_node->nodeValue;
            $data['fields']['distance_uom'] = $distance_node->getAttribute( 'uom' );
        }
    }

    /**
     * Estrai topic categories
     *
     * @param array  $data      Array dati
     * @param string $base_path XPath base
     */
    private function extract_topic_categories( &$data, $base_path ) {
        $categories = array();
        $nodes = $this->xpath->query( $base_path . '/gmd:topicCategory/gmd:MD_TopicCategoryCode' );

        foreach ( $nodes as $node ) {
            $categories[] = $node->nodeValue;
        }

        $data['fields']['topic_categories'] = $categories;
    }

    /**
     * Estrai extent
     *
     * @param array  $data Array dati
     * @param string $path XPath extent
     */
    private function extract_extent( &$data, $path ) {
        // Geographic bounding box
        $bbox_path = $path . '/gmd:EX_Extent/gmd:geographicElement/gmd:EX_GeographicBoundingBox';
        $data['fields']['bbox_west'] = $this->get_text( $bbox_path . '/gmd:westBoundLongitude/gco:Decimal' );
        $data['fields']['bbox_east'] = $this->get_text( $bbox_path . '/gmd:eastBoundLongitude/gco:Decimal' );
        $data['fields']['bbox_south'] = $this->get_text( $bbox_path . '/gmd:southBoundLatitude/gco:Decimal' );
        $data['fields']['bbox_north'] = $this->get_text( $bbox_path . '/gmd:northBoundLatitude/gco:Decimal' );

        // Geographic description
        $data['fields']['geographic_description'] = $this->get_text(
            $path . '/gmd:EX_Extent/gmd:geographicElement/gmd:EX_GeographicDescription/gmd:geographicIdentifier/gmd:MD_Identifier/gmd:code/gco:CharacterString'
        );

        // Temporal extent
        $temp_path = $path . '/gmd:EX_Extent/gmd:temporalElement/gmd:EX_TemporalExtent/gmd:extent/gml:TimePeriod';
        $data['fields']['temporal_extent_begin'] = $this->get_text( $temp_path . '/gml:beginPosition' );
        $data['fields']['temporal_extent_end'] = $this->get_text( $temp_path . '/gml:endPosition' );
    }

    /**
     * Estrai service info
     *
     * @param array  $data      Array dati
     * @param string $base_path XPath base
     */
    private function extract_service_info( &$data, $base_path ) {
        // Service type
        $data['fields']['service_type'] = $this->get_text( $base_path . '/srv:serviceType/gco:LocalName' );

        // Service type version
        $data['fields']['service_type_version'] = $this->get_text( $base_path . '/srv:serviceTypeVersion/gco:CharacterString' );

        // Coupling type
        $data['fields']['coupling_type'] = $this->get_codelist_value( $base_path . '/srv:couplingType/srv:SV_CouplingType' );

        // Coupled resources (operatesOn)
        $operates_on = $this->xpath->query( $base_path . '/srv:operatesOn' );
        foreach ( $operates_on as $op ) {
            $data['coupled_resources'][] = array(
                'identifier' => $op->getAttributeNS( RNDT_XML_Namespaces::XLINK, 'href' ),
                'title'      => $op->getAttributeNS( RNDT_XML_Namespaces::XLINK, 'title' ),
            );
        }

        // Service operations
        $operations = $this->xpath->query( $base_path . '/srv:containsOperations/srv:SV_OperationMetadata' );
        foreach ( $operations as $op ) {
            $data['service_operations'][] = array(
                'operation_name' => $this->get_node_text( $op, 'srv:operationName/gco:CharacterString' ),
                'dcp'            => $this->get_node_codelist( $op, 'srv:DCP/srv:DCPList' ),
                'connect_point'  => $this->get_node_text( $op, 'srv:connectPoint/gmd:CI_OnlineResource/gmd:linkage/gmd:URL' ),
            );
        }
    }

    /**
     * Estrai distribution info
     *
     * @param array $data Array dati
     */
    private function extract_distribution( &$data ) {
        $base_path = '//gmd:distributionInfo/gmd:MD_Distribution';

        // Distribution formats
        $formats = $this->xpath->query( $base_path . '/gmd:distributionFormat/gmd:MD_Format' );
        foreach ( $formats as $format ) {
            $data['distribution_formats'][] = array(
                'name'          => $this->get_node_text( $format, 'gmd:name/gco:CharacterString' ),
                'version'       => $this->get_node_text( $format, 'gmd:version/gco:CharacterString' ),
                'specification' => $this->get_node_text( $format, 'gmd:specification/gco:CharacterString' ),
            );
        }

        // Distributor contacts
        $this->extract_contacts( $data, $base_path . '/gmd:distributor/gmd:MD_Distributor/gmd:distributorContact/gmd:CI_ResponsibleParty', 'distributor' );

        // Online resources
        $resources = $this->xpath->query( $base_path . '/gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource' );
        foreach ( $resources as $res ) {
            $data['online_resources'][] = array(
                'url'                 => $this->get_node_text( $res, 'gmd:linkage/gmd:URL' ),
                'protocol'            => $this->get_node_text( $res, 'gmd:protocol/gco:CharacterString' ),
                'application_profile' => $this->get_node_text( $res, 'gmd:applicationProfile/gco:CharacterString' ),
                'name'                => $this->get_node_text( $res, 'gmd:name/gco:CharacterString' ),
                'description'         => $this->get_node_text( $res, 'gmd:description/gco:CharacterString' ),
                'function'            => $this->get_node_codelist( $res, 'gmd:function/gmd:CI_OnLineFunctionCode' ),
            );
        }
    }

    /**
     * Estrai data quality info
     *
     * @param array $data Array dati
     */
    private function extract_data_quality( &$data ) {
        $base_path = '//gmd:dataQualityInfo/gmd:DQ_DataQuality';

        // Lineage
        $data['fields']['lineage'] = $this->get_text( $base_path . '/gmd:lineage/gmd:LI_Lineage/gmd:statement/gco:CharacterString' );

        // Conformity
        $reports = $this->xpath->query( $base_path . '/gmd:report/gmd:DQ_DomainConsistency/gmd:result/gmd:DQ_ConformanceResult' );
        foreach ( $reports as $report ) {
            $pass_node = $this->xpath->query( 'gmd:pass/gco:Boolean', $report )->item( 0 );
            $pass_value = $pass_node ? ( strtolower( $pass_node->nodeValue ) === 'true' ) : false;

            $data['conformity'][] = array(
                'specification_title'     => $this->get_node_text( $report, 'gmd:specification/gmd:CI_Citation/gmd:title/gco:CharacterString' ),
                'specification_date'      => $this->get_node_text( $report, 'gmd:specification/gmd:CI_Citation/gmd:date/gmd:CI_Date/gmd:date/gco:Date' ),
                'specification_date_type' => $this->get_node_codelist( $report, 'gmd:specification/gmd:CI_Citation/gmd:date/gmd:CI_Date/gmd:dateType/gmd:CI_DateTypeCode' ),
                'explanation'             => $this->get_node_text( $report, 'gmd:explanation/gco:CharacterString' ),
                'pass'                    => $pass_value,
            );
        }
    }

    /**
     * Estrai contacts
     *
     * @param array  $data      Array dati
     * @param string $path      XPath
     * @param string $role_type Tipo ruolo
     */
    private function extract_contacts( &$data, $path, $role_type ) {
        $parties = $this->xpath->query( $path );

        foreach ( $parties as $party ) {
            $data['responsible_parties'][] = array(
                'role_type'         => $role_type,
                'individual_name'   => $this->get_node_text( $party, 'gmd:individualName/gco:CharacterString' ),
                'organisation_name' => $this->get_node_text( $party, 'gmd:organisationName/gco:CharacterString' ),
                'position_name'     => $this->get_node_text( $party, 'gmd:positionName/gco:CharacterString' ),
                'role_code'         => $this->get_node_codelist( $party, 'gmd:role/gmd:CI_RoleCode' ),
                'phone'             => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:voice/gco:CharacterString' ),
                'fax'               => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:facsimile/gco:CharacterString' ),
                'delivery_point'    => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:deliveryPoint/gco:CharacterString' ),
                'city'              => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:city/gco:CharacterString' ),
                'postal_code'       => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:postalCode/gco:CharacterString' ),
                'country'           => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:country/gco:CharacterString' ),
                'email'             => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:electronicMailAddress/gco:CharacterString' ),
                'url'               => $this->get_node_text( $party, 'gmd:contactInfo/gmd:CI_Contact/gmd:onlineResource/gmd:CI_OnlineResource/gmd:linkage/gmd:URL' ),
            );
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Ottieni testo da XPath
     *
     * @param string $xpath Query XPath
     * @return string|null
     */
    private function get_text( $xpath ) {
        $node = $this->xpath->query( $xpath )->item( 0 );
        return $node ? trim( $node->nodeValue ) : null;
    }

    /**
     * Ottieni testo da nodo relativo
     *
     * @param DOMNode $context Nodo contesto
     * @param string  $xpath   Query XPath relativa
     * @return string|null
     */
    private function get_node_text( $context, $xpath ) {
        $node = $this->xpath->query( $xpath, $context )->item( 0 );
        return $node ? trim( $node->nodeValue ) : null;
    }

    /**
     * Ottieni valore codelist
     *
     * @param string $xpath Query XPath
     * @return string|null
     */
    private function get_codelist_value( $xpath ) {
        $node = $this->xpath->query( $xpath )->item( 0 );
        if ( ! $node ) {
            return null;
        }

        // Prima prova attributo codeListValue
        $value = $node->getAttribute( 'codeListValue' );
        if ( $value ) {
            return $value;
        }

        // Altrimenti contenuto testo
        return trim( $node->nodeValue ) ?: null;
    }

    /**
     * Ottieni valore codelist da nodo relativo
     *
     * @param DOMNode $context Nodo contesto
     * @param string  $xpath   Query XPath relativa
     * @return string|null
     */
    private function get_node_codelist( $context, $xpath ) {
        $node = $this->xpath->query( $xpath, $context )->item( 0 );
        if ( ! $node ) {
            return null;
        }

        $value = $node->getAttribute( 'codeListValue' );
        return $value ?: trim( $node->nodeValue ) ?: null;
    }

    /**
     * Ottieni codice lingua
     *
     * @param string $xpath Query XPath
     * @return string|null
     */
    private function get_language( $xpath ) {
        // Prova LanguageCode
        $code = $this->get_codelist_value( $xpath . '/gmd:LanguageCode' );
        if ( $code ) {
            return $code;
        }

        // Prova CharacterString
        return $this->get_text( $xpath . '/gco:CharacterString' );
    }

    /**
     * Ottieni data
     *
     * @param string $xpath Query XPath
     * @return string|null
     */
    private function get_date( $xpath ) {
        // Prova Date
        $date = $this->get_text( $xpath . '/gco:Date' );
        if ( $date ) {
            return $date;
        }

        // Prova DateTime
        return $this->get_text( $xpath . '/gco:DateTime' );
    }

    /**
     * Ottieni errori
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
}
