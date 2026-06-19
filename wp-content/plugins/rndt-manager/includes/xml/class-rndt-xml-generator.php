<?php
/**
 * Generatore XML ISO 19139
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
 * Classe RNDT_XML_Generator
 *
 * Genera documenti XML conformi allo schema ISO 19139 per metadati RNDT 2020
 */
class RNDT_XML_Generator {

    /**
     * Documento DOM
     *
     * @var DOMDocument
     */
    private $doc;

    /**
     * Modello metadati
     *
     * @var RNDT_Metadata_Model
     */
    private $metadata;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->doc = new DOMDocument( '1.0', 'UTF-8' );
        $this->doc->formatOutput = true;
        $this->doc->preserveWhiteSpace = false;
    }

    /**
     * Genera XML ISO 19139 da modello metadati
     *
     * @param RNDT_Metadata_Model $metadata Modello metadati
     * @return string XML generato
     */
    public function generate( RNDT_Metadata_Model $metadata ) {
        $this->metadata = $metadata;
        $this->doc = new DOMDocument( '1.0', 'UTF-8' );
        $this->doc->formatOutput = true;

        // Root element MD_Metadata
        $root = $this->create_root_element();
        $this->doc->appendChild( $root );

        // File identifier
        $this->append_file_identifier( $root );

        // Language
        $this->append_language( $root, 'language', $this->metadata->metadata_language ?? 'ita' );

        // Character set
        $this->append_character_set( $root, $this->metadata->metadata_character_set ?? 'utf8' );

        // Parent identifier (per serie)
        if ( ! empty( $this->metadata->parent_identifier ) ) {
            $this->append_parent_identifier( $root );
        }

        // Hierarchy level
        $this->append_hierarchy_level( $root );

        // Hierarchy level name
        $this->append_hierarchy_level_name( $root );

        // Contact (metadata)
        $this->append_contacts( $root, 'contact', 'metadata_contact' );

        // Date stamp
        $this->append_date_stamp( $root );

        // Metadata standard name and version
        $this->append_metadata_standard( $root );

        // Reference system info
        if ( $this->metadata->resource_type !== 'service' ) {
            $this->append_reference_system_info( $root );
        }

        // Identification info
        $this->append_identification_info( $root );

        // Distribution info
        $this->append_distribution_info( $root );

        // Data quality info
        $this->append_data_quality_info( $root );

        return $this->doc->saveXML();
    }

    /**
     * Crea elemento root MD_Metadata con namespace
     *
     * @return DOMElement
     */
    private function create_root_element() {
        $include_srv = ( $this->metadata->resource_type === 'service' );
        $namespaces = RNDT_XML_Namespaces::get_root_namespaces( $include_srv );

        $root = $this->doc->createElementNS( RNDT_XML_Namespaces::GMD, 'gmd:MD_Metadata' );

        foreach ( $namespaces as $attr => $uri ) {
            $root->setAttribute( $attr, $uri );
        }

        $root->setAttributeNS(
            RNDT_XML_Namespaces::XSI,
            'xsi:schemaLocation',
            RNDT_XML_Namespaces::SCHEMA_LOCATION
        );

        return $root;
    }

    /**
     * Append file identifier
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_file_identifier( $parent ) {
        $file_id = $this->create_element( 'gmd:fileIdentifier' );
        $file_id->appendChild( $this->create_character_string( $this->metadata->file_identifier ) );
        $parent->appendChild( $file_id );
    }

    /**
     * Append language element
     *
     * @param DOMElement $parent    Elemento parent
     * @param string     $tag_name  Nome tag (language o resourceLanguage)
     * @param string     $lang_code Codice lingua
     */
    private function append_language( $parent, $tag_name, $lang_code ) {
        $lang_code = RNDT_XML_Codelists::get_language_code( $lang_code );

        $lang_elem = $this->create_element( 'gmd:' . $tag_name );
        $lang_code_elem = $this->create_element( 'gmd:LanguageCode' );
        $lang_code_elem->setAttribute(
            'codeList',
            'http://www.loc.gov/standards/iso639-2/'
        );
        $lang_code_elem->setAttribute( 'codeListValue', $lang_code );
        $lang_code_elem->nodeValue = $lang_code;

        $lang_elem->appendChild( $lang_code_elem );
        $parent->appendChild( $lang_elem );
    }

    /**
     * Append character set
     *
     * @param DOMElement $parent  Elemento parent
     * @param string     $charset Codice charset
     */
    private function append_character_set( $parent, $charset ) {
        $cs_elem = $this->create_element( 'gmd:characterSet' );
        $cs_code = $this->create_codelist_element(
            'gmd:MD_CharacterSetCode',
            'MD_CharacterSetCode',
            $charset
        );
        $cs_elem->appendChild( $cs_code );
        $parent->appendChild( $cs_elem );
    }

    /**
     * Append parent identifier
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_parent_identifier( $parent ) {
        $pi_elem = $this->create_element( 'gmd:parentIdentifier' );
        $pi_elem->appendChild( $this->create_character_string( $this->metadata->parent_identifier ) );
        $parent->appendChild( $pi_elem );
    }

    /**
     * Append hierarchy level
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_hierarchy_level( $parent ) {
        $hl_elem = $this->create_element( 'gmd:hierarchyLevel' );
        $scope_code = $this->create_codelist_element(
            'gmd:MD_ScopeCode',
            'MD_ScopeCode',
            $this->metadata->resource_type
        );
        $hl_elem->appendChild( $scope_code );
        $parent->appendChild( $hl_elem );
    }

    /**
     * Append hierarchy level name
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_hierarchy_level_name( $parent ) {
        $hln_elem = $this->create_element( 'gmd:hierarchyLevelName' );

        $name_map = array(
            'dataset'     => 'dataset',
            'series'      => 'series',
            'service'     => 'service',
            'application' => 'application',
        );

        $name = $name_map[ $this->metadata->resource_type ] ?? 'dataset';
        $hln_elem->appendChild( $this->create_character_string( $name ) );
        $parent->appendChild( $hln_elem );
    }

    /**
     * Append contacts
     *
     * @param DOMElement $parent    Elemento parent
     * @param string     $tag_name  Nome tag
     * @param string     $role_type Tipo ruolo (metadata_contact, resource_poc, distributor)
     */
    private function append_contacts( $parent, $tag_name, $role_type ) {
        $parties = $this->metadata->get_responsible_parties();

        foreach ( $parties as $party ) {
            if ( $party['role_type'] === $role_type ) {
                $contact = $this->create_element( 'gmd:' . $tag_name );
                $contact->appendChild( $this->create_responsible_party( $party ) );
                $parent->appendChild( $contact );
            }
        }
    }

    /**
     * Crea CI_ResponsibleParty
     *
     * @param array $party Dati party
     * @return DOMElement
     */
    private function create_responsible_party( $party ) {
        $rp = $this->create_element( 'gmd:CI_ResponsibleParty' );

        // Individual name
        if ( ! empty( $party['individual_name'] ) ) {
            $in = $this->create_element( 'gmd:individualName' );
            $in->appendChild( $this->create_character_string( $party['individual_name'] ) );
            $rp->appendChild( $in );
        }

        // Organisation name
        if ( ! empty( $party['organisation_name'] ) ) {
            $on = $this->create_element( 'gmd:organisationName' );
            $on->appendChild( $this->create_character_string( $party['organisation_name'] ) );
            $rp->appendChild( $on );
        }

        // Position name
        if ( ! empty( $party['position_name'] ) ) {
            $pn = $this->create_element( 'gmd:positionName' );
            $pn->appendChild( $this->create_character_string( $party['position_name'] ) );
            $rp->appendChild( $pn );
        }

        // Contact info
        $ci = $this->create_element( 'gmd:contactInfo' );
        $ci->appendChild( $this->create_contact_info( $party ) );
        $rp->appendChild( $ci );

        // Role
        $role = $this->create_element( 'gmd:role' );
        $role->appendChild(
            $this->create_codelist_element( 'gmd:CI_RoleCode', 'CI_RoleCode', $party['role_code'] )
        );
        $rp->appendChild( $role );

        return $rp;
    }

    /**
     * Crea CI_Contact
     *
     * @param array $party Dati party
     * @return DOMElement
     */
    private function create_contact_info( $party ) {
        $contact = $this->create_element( 'gmd:CI_Contact' );

        // Phone
        if ( ! empty( $party['phone'] ) || ! empty( $party['fax'] ) ) {
            $phone = $this->create_element( 'gmd:phone' );
            $tel = $this->create_element( 'gmd:CI_Telephone' );

            if ( ! empty( $party['phone'] ) ) {
                $voice = $this->create_element( 'gmd:voice' );
                $voice->appendChild( $this->create_character_string( $party['phone'] ) );
                $tel->appendChild( $voice );
            }

            if ( ! empty( $party['fax'] ) ) {
                $fax = $this->create_element( 'gmd:facsimile' );
                $fax->appendChild( $this->create_character_string( $party['fax'] ) );
                $tel->appendChild( $fax );
            }

            $phone->appendChild( $tel );
            $contact->appendChild( $phone );
        }

        // Address
        $address = $this->create_element( 'gmd:address' );
        $addr = $this->create_element( 'gmd:CI_Address' );

        if ( ! empty( $party['delivery_point'] ) ) {
            $dp = $this->create_element( 'gmd:deliveryPoint' );
            $dp->appendChild( $this->create_character_string( $party['delivery_point'] ) );
            $addr->appendChild( $dp );
        }

        if ( ! empty( $party['city'] ) ) {
            $city = $this->create_element( 'gmd:city' );
            $city->appendChild( $this->create_character_string( $party['city'] ) );
            $addr->appendChild( $city );
        }

        if ( ! empty( $party['postal_code'] ) ) {
            $pc = $this->create_element( 'gmd:postalCode' );
            $pc->appendChild( $this->create_character_string( $party['postal_code'] ) );
            $addr->appendChild( $pc );
        }

        if ( ! empty( $party['country'] ) ) {
            $country = $this->create_element( 'gmd:country' );
            $country->appendChild( $this->create_character_string( $party['country'] ) );
            $addr->appendChild( $country );
        }

        if ( ! empty( $party['email'] ) ) {
            $email = $this->create_element( 'gmd:electronicMailAddress' );
            $email->appendChild( $this->create_character_string( $party['email'] ) );
            $addr->appendChild( $email );
        }

        $address->appendChild( $addr );
        $contact->appendChild( $address );

        // Online resource
        if ( ! empty( $party['url'] ) ) {
            $online = $this->create_element( 'gmd:onlineResource' );
            $online->appendChild( $this->create_online_resource( array(
                'url' => $party['url'],
            ) ) );
            $contact->appendChild( $online );
        }

        return $contact;
    }

    /**
     * Append date stamp
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_date_stamp( $parent ) {
        $ds = $this->create_element( 'gmd:dateStamp' );

        $date = $this->metadata->metadata_date ?? gmdate( 'Y-m-d' );

        // Se include orario, usa DateTime
        if ( strpos( $date, 'T' ) !== false || strpos( $date, ' ' ) !== false ) {
            $dt = $this->create_element( 'gco:DateTime' );
            $dt->nodeValue = str_replace( ' ', 'T', $date );
        } else {
            $dt = $this->create_element( 'gco:Date' );
            $dt->nodeValue = $date;
        }

        $ds->appendChild( $dt );
        $parent->appendChild( $ds );
    }

    /**
     * Append metadata standard name and version
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_metadata_standard( $parent ) {
        $name = $this->create_element( 'gmd:metadataStandardName' );
        $name->appendChild( $this->create_character_string(
            $this->metadata->metadata_standard_name ?? 'DM - Regole tecniche RNDT'
        ) );
        $parent->appendChild( $name );

        $version = $this->create_element( 'gmd:metadataStandardVersion' );
        $version->appendChild( $this->create_character_string(
            $this->metadata->metadata_standard_version ?? '10 novembre 2011'
        ) );
        $parent->appendChild( $version );
    }

    /**
     * Append reference system info
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_reference_system_info( $parent ) {
        if ( empty( $this->metadata->reference_system_code ) ) {
            return;
        }

        $rsi = $this->create_element( 'gmd:referenceSystemInfo' );
        $rs = $this->create_element( 'gmd:MD_ReferenceSystem' );
        $rsid = $this->create_element( 'gmd:referenceSystemIdentifier' );
        $rsid_inner = $this->create_element( 'gmd:RS_Identifier' );

        // Code
        $code = $this->create_element( 'gmd:code' );
        $code->appendChild( $this->create_character_string( $this->metadata->reference_system_code ) );
        $rsid_inner->appendChild( $code );

        // Code space
        if ( ! empty( $this->metadata->reference_system_code_space ) ) {
            $cs = $this->create_element( 'gmd:codeSpace' );
            $cs->appendChild( $this->create_character_string( $this->metadata->reference_system_code_space ) );
            $rsid_inner->appendChild( $cs );
        }

        $rsid->appendChild( $rsid_inner );
        $rs->appendChild( $rsid );
        $rsi->appendChild( $rs );
        $parent->appendChild( $rsi );
    }

    /**
     * Append identification info
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_identification_info( $parent ) {
        $ii = $this->create_element( 'gmd:identificationInfo' );

        if ( $this->metadata->resource_type === 'service' ) {
            $ii->appendChild( $this->create_service_identification() );
        } else {
            $ii->appendChild( $this->create_data_identification() );
        }

        $parent->appendChild( $ii );
    }

    /**
     * Crea MD_DataIdentification
     *
     * @return DOMElement
     */
    private function create_data_identification() {
        $di = $this->create_element( 'gmd:MD_DataIdentification' );

        // Citation
        $di->appendChild( $this->create_citation() );

        // Abstract
        $abs = $this->create_element( 'gmd:abstract' );
        $abs->appendChild( $this->create_character_string( $this->metadata->abstract ) );
        $di->appendChild( $abs );

        // Purpose
        if ( ! empty( $this->metadata->purpose ) ) {
            $purpose = $this->create_element( 'gmd:purpose' );
            $purpose->appendChild( $this->create_character_string( $this->metadata->purpose ) );
            $di->appendChild( $purpose );
        }

        // Status
        if ( ! empty( $this->metadata->status ) ) {
            $status = $this->create_element( 'gmd:status' );
            $status->appendChild(
                $this->create_codelist_element( 'gmd:MD_ProgressCode', 'MD_ProgressCode', $this->metadata->status )
            );
            $di->appendChild( $status );
        }

        // Point of contact
        $this->append_contacts( $di, 'pointOfContact', 'resource_poc' );

        // Resource maintenance
        if ( ! empty( $this->metadata->maintenance_frequency ) ) {
            $rm = $this->create_element( 'gmd:resourceMaintenance' );
            $mi = $this->create_element( 'gmd:MD_MaintenanceInformation' );
            $mf = $this->create_element( 'gmd:maintenanceAndUpdateFrequency' );
            $mf->appendChild(
                $this->create_codelist_element(
                    'gmd:MD_MaintenanceFrequencyCode',
                    'MD_MaintenanceFrequencyCode',
                    $this->metadata->maintenance_frequency
                )
            );
            $mi->appendChild( $mf );
            $rm->appendChild( $mi );
            $di->appendChild( $rm );
        }

        // Graphic overview
        if ( ! empty( $this->metadata->thumbnail_url ) ) {
            $go = $this->create_element( 'gmd:graphicOverview' );
            $bg = $this->create_element( 'gmd:MD_BrowseGraphic' );
            $fn = $this->create_element( 'gmd:fileName' );
            $fn->appendChild( $this->create_character_string( $this->metadata->thumbnail_url ) );
            $bg->appendChild( $fn );
            $go->appendChild( $bg );
            $di->appendChild( $go );
        }

        // Descriptive keywords
        $this->append_keywords( $di );

        // Resource constraints
        $this->append_resource_constraints( $di );

        // Spatial representation type
        if ( ! empty( $this->metadata->spatial_representation_type ) ) {
            $srt = $this->create_element( 'gmd:spatialRepresentationType' );
            $srt->appendChild(
                $this->create_codelist_element(
                    'gmd:MD_SpatialRepresentationTypeCode',
                    'MD_SpatialRepresentationTypeCode',
                    $this->metadata->spatial_representation_type
                )
            );
            $di->appendChild( $srt );
        }

        // Spatial resolution
        $this->append_spatial_resolution( $di );

        // Language
        $this->append_language( $di, 'language', $this->metadata->resource_language ?? 'ita' );

        // Character set
        if ( ! empty( $this->metadata->dataset_character_set ) ) {
            $this->append_character_set( $di, $this->metadata->dataset_character_set );
        }

        // Topic category
        $this->append_topic_categories( $di );

        // Extent
        $this->append_extent( $di );

        // Supplemental information
        if ( ! empty( $this->metadata->supplemental_information ) ) {
            $si = $this->create_element( 'gmd:supplementalInformation' );
            $si->appendChild( $this->create_character_string( $this->metadata->supplemental_information ) );
            $di->appendChild( $si );
        }

        return $di;
    }

    /**
     * Crea SV_ServiceIdentification
     *
     * @return DOMElement
     */
    private function create_service_identification() {
        $si = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:SV_ServiceIdentification' );

        // Citation
        $si->appendChild( $this->create_citation() );

        // Abstract
        $abs = $this->create_element( 'gmd:abstract' );
        $abs->appendChild( $this->create_character_string( $this->metadata->abstract ) );
        $si->appendChild( $abs );

        // Point of contact
        $this->append_contacts( $si, 'pointOfContact', 'resource_poc' );

        // Descriptive keywords
        $this->append_keywords( $si );

        // Resource constraints
        $this->append_resource_constraints( $si );

        // Service type
        $st = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:serviceType' );
        $ln = $this->create_element( 'gco:LocalName' );
        $ln->nodeValue = $this->metadata->service_type ?? 'view';
        $st->appendChild( $ln );
        $si->appendChild( $st );

        // Service type version
        if ( ! empty( $this->metadata->service_type_version ) ) {
            $stv = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:serviceTypeVersion' );
            $stv->appendChild( $this->create_character_string( $this->metadata->service_type_version ) );
            $si->appendChild( $stv );
        }

        // Extent
        $this->append_extent( $si, true );

        // Coupling type
        $ct = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:couplingType' );
        $ct->appendChild(
            $this->create_codelist_element(
                'srv:SV_CouplingType',
                'SV_CouplingType',
                $this->metadata->coupling_type ?? 'tight'
            )
        );
        $si->appendChild( $ct );

        // Coupled resources
        $this->append_coupled_resources( $si );

        // Contains operations
        $this->append_service_operations( $si );

        return $si;
    }

    /**
     * Crea CI_Citation
     *
     * @return DOMElement
     */
    private function create_citation() {
        $citation_wrapper = $this->create_element( 'gmd:citation' );
        $citation = $this->create_element( 'gmd:CI_Citation' );

        // Title
        $title = $this->create_element( 'gmd:title' );
        $title->appendChild( $this->create_character_string( $this->metadata->title ) );
        $citation->appendChild( $title );

        // Alternate title
        if ( ! empty( $this->metadata->alternate_title ) ) {
            $alt = $this->create_element( 'gmd:alternateTitle' );
            $alt->appendChild( $this->create_character_string( $this->metadata->alternate_title ) );
            $citation->appendChild( $alt );
        }

        // Dates
        $this->append_citation_dates( $citation );

        // Edition
        if ( ! empty( $this->metadata->edition ) ) {
            $ed = $this->create_element( 'gmd:edition' );
            $ed->appendChild( $this->create_character_string( $this->metadata->edition ) );
            $citation->appendChild( $ed );
        }

        // Identifier
        $this->append_citation_identifier( $citation );

        // Series (for series type)
        if ( ! empty( $this->metadata->series_name ) ) {
            $series = $this->create_element( 'gmd:series' );
            $series_inner = $this->create_element( 'gmd:CI_Series' );

            $sn = $this->create_element( 'gmd:name' );
            $sn->appendChild( $this->create_character_string( $this->metadata->series_name ) );
            $series_inner->appendChild( $sn );

            if ( ! empty( $this->metadata->series_issue ) ) {
                $issue = $this->create_element( 'gmd:issueIdentification' );
                $issue->appendChild( $this->create_character_string( $this->metadata->series_issue ) );
                $series_inner->appendChild( $issue );
            }

            $series->appendChild( $series_inner );
            $citation->appendChild( $series );
        }

        $citation_wrapper->appendChild( $citation );
        return $citation_wrapper;
    }

    /**
     * Append citation dates
     *
     * @param DOMElement $citation Elemento citation
     */
    private function append_citation_dates( $citation ) {
        $dates = array(
            'creation'    => $this->metadata->date_creation,
            'publication' => $this->metadata->date_publication,
            'revision'    => $this->metadata->date_revision,
        );

        foreach ( $dates as $type => $date ) {
            if ( ! empty( $date ) ) {
                $date_elem = $this->create_element( 'gmd:date' );
                $ci_date = $this->create_element( 'gmd:CI_Date' );

                $d = $this->create_element( 'gmd:date' );
                $gco_date = $this->create_element( 'gco:Date' );
                $gco_date->nodeValue = $date;
                $d->appendChild( $gco_date );
                $ci_date->appendChild( $d );

                $dt = $this->create_element( 'gmd:dateType' );
                $dt->appendChild(
                    $this->create_codelist_element( 'gmd:CI_DateTypeCode', 'CI_DateTypeCode', $type )
                );
                $ci_date->appendChild( $dt );

                $date_elem->appendChild( $ci_date );
                $citation->appendChild( $date_elem );
            }
        }
    }

    /**
     * Append citation identifier
     *
     * @param DOMElement $citation Elemento citation
     */
    private function append_citation_identifier( $citation ) {
        if ( empty( $this->metadata->resource_identifier ) ) {
            return;
        }

        $id = $this->create_element( 'gmd:identifier' );
        $rs_id = $this->create_element( 'gmd:RS_Identifier' );

        $code = $this->create_element( 'gmd:code' );
        $code->appendChild( $this->create_character_string( $this->metadata->resource_identifier ) );
        $rs_id->appendChild( $code );

        if ( ! empty( $this->metadata->resource_identifier_codespace ) ) {
            $cs = $this->create_element( 'gmd:codeSpace' );
            $cs->appendChild( $this->create_character_string( $this->metadata->resource_identifier_codespace ) );
            $rs_id->appendChild( $cs );
        }

        $id->appendChild( $rs_id );
        $citation->appendChild( $id );
    }

    /**
     * Append keywords
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_keywords( $parent ) {
        $keywords = $this->metadata->get_keywords();

        // Raggruppa per thesaurus
        $grouped = array();
        foreach ( $keywords as $kw ) {
            $thesaurus = $kw['thesaurus_name'] ?? 'free';
            if ( ! isset( $grouped[ $thesaurus ] ) ) {
                $grouped[ $thesaurus ] = array(
                    'keywords' => array(),
                    'thesaurus_title' => $kw['thesaurus_title'] ?? '',
                    'thesaurus_date' => $kw['thesaurus_date'] ?? '',
                    'thesaurus_date_type' => $kw['thesaurus_date_type'] ?? 'publication',
                );
            }
            $grouped[ $thesaurus ]['keywords'][] = $kw['keyword'];
        }

        foreach ( $grouped as $thesaurus => $data ) {
            $dk = $this->create_element( 'gmd:descriptiveKeywords' );
            $md_kw = $this->create_element( 'gmd:MD_Keywords' );

            // Keywords
            foreach ( $data['keywords'] as $keyword ) {
                $kw = $this->create_element( 'gmd:keyword' );
                $kw->appendChild( $this->create_character_string( $keyword ) );
                $md_kw->appendChild( $kw );
            }

            // Thesaurus
            if ( $thesaurus !== 'free' && ! empty( $data['thesaurus_title'] ) ) {
                $tt = $this->create_element( 'gmd:thesaurusName' );
                $ci_cit = $this->create_element( 'gmd:CI_Citation' );

                $title = $this->create_element( 'gmd:title' );
                $title->appendChild( $this->create_character_string( $data['thesaurus_title'] ) );
                $ci_cit->appendChild( $title );

                if ( ! empty( $data['thesaurus_date'] ) ) {
                    $date = $this->create_element( 'gmd:date' );
                    $ci_date = $this->create_element( 'gmd:CI_Date' );

                    $d = $this->create_element( 'gmd:date' );
                    $gco_date = $this->create_element( 'gco:Date' );
                    $gco_date->nodeValue = $data['thesaurus_date'];
                    $d->appendChild( $gco_date );
                    $ci_date->appendChild( $d );

                    $dt = $this->create_element( 'gmd:dateType' );
                    $dt->appendChild(
                        $this->create_codelist_element(
                            'gmd:CI_DateTypeCode',
                            'CI_DateTypeCode',
                            $data['thesaurus_date_type']
                        )
                    );
                    $ci_date->appendChild( $dt );

                    $date->appendChild( $ci_date );
                    $ci_cit->appendChild( $date );
                }

                $tt->appendChild( $ci_cit );
                $md_kw->appendChild( $tt );
            }

            $dk->appendChild( $md_kw );
            $parent->appendChild( $dk );
        }
    }

    /**
     * Append resource constraints
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_resource_constraints( $parent ) {
        // Legal constraints
        $lc = $this->create_element( 'gmd:resourceConstraints' );
        $md_lc = $this->create_element( 'gmd:MD_LegalConstraints' );

        // Access constraints
        if ( ! empty( $this->metadata->access_constraints ) ) {
            $ac = $this->create_element( 'gmd:accessConstraints' );
            $ac->appendChild(
                $this->create_codelist_element(
                    'gmd:MD_RestrictionCode',
                    'MD_RestrictionCode',
                    $this->metadata->access_constraints
                )
            );
            $md_lc->appendChild( $ac );
        }

        // Use constraints
        if ( ! empty( $this->metadata->use_constraints ) ) {
            $uc = $this->create_element( 'gmd:useConstraints' );
            $uc->appendChild(
                $this->create_codelist_element(
                    'gmd:MD_RestrictionCode',
                    'MD_RestrictionCode',
                    $this->metadata->use_constraints
                )
            );
            $md_lc->appendChild( $uc );
        }

        // Other constraints
        if ( ! empty( $this->metadata->other_constraints ) ) {
            $oc = $this->create_element( 'gmd:otherConstraints' );
            $oc->appendChild( $this->create_character_string( $this->metadata->other_constraints ) );
            $md_lc->appendChild( $oc );
        }

        // Use limitation
        if ( ! empty( $this->metadata->use_limitation ) ) {
            $ul = $this->create_element( 'gmd:useLimitation' );
            $ul->appendChild( $this->create_character_string( $this->metadata->use_limitation ) );
            $md_lc->appendChild( $ul );
        }

        $lc->appendChild( $md_lc );
        $parent->appendChild( $lc );

        // Security constraints
        if ( ! empty( $this->metadata->classification ) ) {
            $sc = $this->create_element( 'gmd:resourceConstraints' );
            $md_sc = $this->create_element( 'gmd:MD_SecurityConstraints' );

            $class = $this->create_element( 'gmd:classification' );
            $class->appendChild(
                $this->create_codelist_element(
                    'gmd:MD_ClassificationCode',
                    'MD_ClassificationCode',
                    $this->metadata->classification
                )
            );
            $md_sc->appendChild( $class );

            $sc->appendChild( $md_sc );
            $parent->appendChild( $sc );
        }
    }

    /**
     * Append spatial resolution
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_spatial_resolution( $parent ) {
        // Equivalent scale
        if ( ! empty( $this->metadata->equivalent_scale ) ) {
            $sr = $this->create_element( 'gmd:spatialResolution' );
            $md_res = $this->create_element( 'gmd:MD_Resolution' );
            $es = $this->create_element( 'gmd:equivalentScale' );
            $rf = $this->create_element( 'gmd:MD_RepresentativeFraction' );
            $den = $this->create_element( 'gmd:denominator' );
            $int = $this->create_element( 'gco:Integer' );
            $int->nodeValue = $this->metadata->equivalent_scale;
            $den->appendChild( $int );
            $rf->appendChild( $den );
            $es->appendChild( $rf );
            $md_res->appendChild( $es );
            $sr->appendChild( $md_res );
            $parent->appendChild( $sr );
        }

        // Distance
        if ( ! empty( $this->metadata->distance_value ) ) {
            $sr = $this->create_element( 'gmd:spatialResolution' );
            $md_res = $this->create_element( 'gmd:MD_Resolution' );
            $dist = $this->create_element( 'gmd:distance' );
            $gco_dist = $this->create_element( 'gco:Distance' );
            $gco_dist->setAttribute( 'uom', $this->metadata->distance_uom ?? 'm' );
            $gco_dist->nodeValue = $this->metadata->distance_value;
            $dist->appendChild( $gco_dist );
            $md_res->appendChild( $dist );
            $sr->appendChild( $md_res );
            $parent->appendChild( $sr );
        }
    }

    /**
     * Append topic categories
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_topic_categories( $parent ) {
        $categories = $this->metadata->topic_categories ?? array();

        foreach ( $categories as $cat ) {
            $tc = $this->create_element( 'gmd:topicCategory' );
            $code = $this->create_element( 'gmd:MD_TopicCategoryCode' );
            $code->nodeValue = $cat;
            $tc->appendChild( $code );
            $parent->appendChild( $tc );
        }
    }

    /**
     * Append extent
     *
     * @param DOMElement $parent      Elemento parent
     * @param bool       $for_service Se true, usa srv:extent
     */
    private function append_extent( $parent, $for_service = false ) {
        $tag = $for_service ? 'srv:extent' : 'gmd:extent';
        $extent = $for_service
            ? $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, $tag )
            : $this->create_element( $tag );

        $ex_extent = $this->create_element( 'gmd:EX_Extent' );

        // Geographic extent
        if ( ! empty( $this->metadata->bbox_west ) ) {
            $geo = $this->create_element( 'gmd:geographicElement' );
            $bbox = $this->create_element( 'gmd:EX_GeographicBoundingBox' );

            $west = $this->create_element( 'gmd:westBoundLongitude' );
            $west->appendChild( $this->create_decimal( $this->metadata->bbox_west ) );
            $bbox->appendChild( $west );

            $east = $this->create_element( 'gmd:eastBoundLongitude' );
            $east->appendChild( $this->create_decimal( $this->metadata->bbox_east ) );
            $bbox->appendChild( $east );

            $south = $this->create_element( 'gmd:southBoundLatitude' );
            $south->appendChild( $this->create_decimal( $this->metadata->bbox_south ) );
            $bbox->appendChild( $south );

            $north = $this->create_element( 'gmd:northBoundLatitude' );
            $north->appendChild( $this->create_decimal( $this->metadata->bbox_north ) );
            $bbox->appendChild( $north );

            $geo->appendChild( $bbox );
            $ex_extent->appendChild( $geo );
        }

        // Geographic description (region/country)
        if ( ! empty( $this->metadata->geographic_description ) ) {
            $geo = $this->create_element( 'gmd:geographicElement' );
            $gd = $this->create_element( 'gmd:EX_GeographicDescription' );
            $gi = $this->create_element( 'gmd:geographicIdentifier' );
            $md_id = $this->create_element( 'gmd:MD_Identifier' );
            $code = $this->create_element( 'gmd:code' );
            $code->appendChild( $this->create_character_string( $this->metadata->geographic_description ) );
            $md_id->appendChild( $code );
            $gi->appendChild( $md_id );
            $gd->appendChild( $gi );
            $geo->appendChild( $gd );
            $ex_extent->appendChild( $geo );
        }

        // Temporal extent
        if ( ! empty( $this->metadata->temporal_extent_begin ) || ! empty( $this->metadata->temporal_extent_end ) ) {
            $temp = $this->create_element( 'gmd:temporalElement' );
            $ex_temp = $this->create_element( 'gmd:EX_TemporalExtent' );
            $extent_inner = $this->create_element( 'gmd:extent' );

            $time_period = $this->doc->createElementNS( RNDT_XML_Namespaces::GML, 'gml:TimePeriod' );
            $time_period->setAttribute( 'gml:id', 'TP_' . uniqid() );

            if ( ! empty( $this->metadata->temporal_extent_begin ) ) {
                $begin = $this->doc->createElementNS( RNDT_XML_Namespaces::GML, 'gml:beginPosition' );
                $begin->nodeValue = $this->metadata->temporal_extent_begin;
                $time_period->appendChild( $begin );
            }

            if ( ! empty( $this->metadata->temporal_extent_end ) ) {
                $end = $this->doc->createElementNS( RNDT_XML_Namespaces::GML, 'gml:endPosition' );
                $end->nodeValue = $this->metadata->temporal_extent_end;
                $time_period->appendChild( $end );
            }

            $extent_inner->appendChild( $time_period );
            $ex_temp->appendChild( $extent_inner );
            $temp->appendChild( $ex_temp );
            $ex_extent->appendChild( $temp );
        }

        $extent->appendChild( $ex_extent );
        $parent->appendChild( $extent );
    }

    /**
     * Append coupled resources (per servizi)
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_coupled_resources( $parent ) {
        $resources = $this->metadata->get_coupled_resources();

        foreach ( $resources as $res ) {
            $or = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:operatesOn' );

            if ( ! empty( $res['identifier'] ) ) {
                $or->setAttributeNS( RNDT_XML_Namespaces::XLINK, 'xlink:href', $res['identifier'] );
            }
            if ( ! empty( $res['title'] ) ) {
                $or->setAttributeNS( RNDT_XML_Namespaces::XLINK, 'xlink:title', $res['title'] );
            }

            $parent->appendChild( $or );
        }
    }

    /**
     * Append service operations (per servizi)
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_service_operations( $parent ) {
        $operations = $this->metadata->get_service_operations();

        foreach ( $operations as $op ) {
            $co = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:containsOperations' );
            $sv_op = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:SV_OperationMetadata' );

            // Operation name
            $on = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:operationName' );
            $on->appendChild( $this->create_character_string( $op['operation_name'] ) );
            $sv_op->appendChild( $on );

            // DCP
            $dcp = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:DCP' );
            $dcp->appendChild(
                $this->create_codelist_element( 'srv:DCPList', 'DCPList', $op['dcp'] ?? 'WebServices' )
            );
            $sv_op->appendChild( $dcp );

            // Connect point (URL)
            if ( ! empty( $op['connect_point'] ) ) {
                $cp = $this->doc->createElementNS( RNDT_XML_Namespaces::SRV, 'srv:connectPoint' );
                $cp->appendChild( $this->create_online_resource( array( 'url' => $op['connect_point'] ) ) );
                $sv_op->appendChild( $cp );
            }

            $co->appendChild( $sv_op );
            $parent->appendChild( $co );
        }
    }

    /**
     * Append distribution info
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_distribution_info( $parent ) {
        $di = $this->create_element( 'gmd:distributionInfo' );
        $md_dist = $this->create_element( 'gmd:MD_Distribution' );

        // Distribution formats
        $formats = $this->metadata->get_distribution_formats();
        foreach ( $formats as $format ) {
            $df = $this->create_element( 'gmd:distributionFormat' );
            $md_format = $this->create_element( 'gmd:MD_Format' );

            // Supporta sia nomi DB (format_name) che frontend (name)
            $fmt_name = $format['format_name'] ?? $format['name'] ?? '';
            $fmt_version = $format['format_version'] ?? $format['version'] ?? '';
            $fmt_spec = $format['format_spec'] ?? $format['specification'] ?? '';

            $name = $this->create_element( 'gmd:name' );
            $name->appendChild( $this->create_character_string( $fmt_name ) );
            $md_format->appendChild( $name );

            $version = $this->create_element( 'gmd:version' );
            if ( ! empty( $fmt_version ) ) {
                $version->appendChild( $this->create_character_string( $fmt_version ) );
            } else {
                $version->setAttribute( 'gco:nilReason', 'unknown' );
            }
            $md_format->appendChild( $version );

            if ( ! empty( $fmt_spec ) ) {
                $spec = $this->create_element( 'gmd:specification' );
                $spec->appendChild( $this->create_character_string( $fmt_spec ) );
                $md_format->appendChild( $spec );
            }

            $df->appendChild( $md_format );
            $md_dist->appendChild( $df );
        }

        // Distributor
        $this->append_distributor( $md_dist );

        // Transfer options (online resources)
        $this->append_transfer_options( $md_dist );

        $di->appendChild( $md_dist );
        $parent->appendChild( $di );
    }

    /**
     * Append distributor
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_distributor( $parent ) {
        $parties = $this->metadata->get_responsible_parties();

        foreach ( $parties as $party ) {
            if ( $party['role_type'] === 'distributor' ) {
                $dist = $this->create_element( 'gmd:distributor' );
                $md_dist = $this->create_element( 'gmd:MD_Distributor' );
                $dc = $this->create_element( 'gmd:distributorContact' );
                $dc->appendChild( $this->create_responsible_party( $party ) );
                $md_dist->appendChild( $dc );
                $dist->appendChild( $md_dist );
                $parent->appendChild( $dist );
            }
        }
    }

    /**
     * Append transfer options
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_transfer_options( $parent ) {
        $resources = $this->metadata->get_online_resources();

        if ( empty( $resources ) ) {
            return;
        }

        $to = $this->create_element( 'gmd:transferOptions' );
        $md_dto = $this->create_element( 'gmd:MD_DigitalTransferOptions' );

        foreach ( $resources as $res ) {
            $online = $this->create_element( 'gmd:onLine' );
            $online->appendChild( $this->create_online_resource( $res ) );
            $md_dto->appendChild( $online );
        }

        $to->appendChild( $md_dto );
        $parent->appendChild( $to );
    }

    /**
     * Crea CI_OnlineResource
     *
     * @param array $resource Dati risorsa
     * @return DOMElement
     */
    private function create_online_resource( $resource ) {
        $ci_or = $this->create_element( 'gmd:CI_OnlineResource' );

        // Linkage - supporta sia nomi DB (linkage_url) che frontend (url)
        $res_url = $resource['linkage_url'] ?? $resource['url'] ?? '';
        $linkage = $this->create_element( 'gmd:linkage' );
        $url = $this->create_element( 'gmd:URL' );
        $url->nodeValue = $res_url;
        $linkage->appendChild( $url );
        $ci_or->appendChild( $linkage );

        // Protocol
        if ( ! empty( $resource['protocol'] ) ) {
            $prot = $this->create_element( 'gmd:protocol' );
            $prot->appendChild( $this->create_character_string( $resource['protocol'] ) );
            $ci_or->appendChild( $prot );
        }

        // Application profile
        if ( ! empty( $resource['application_profile'] ) ) {
            $ap = $this->create_element( 'gmd:applicationProfile' );
            $ap->appendChild( $this->create_character_string( $resource['application_profile'] ) );
            $ci_or->appendChild( $ap );
        }

        // Name
        if ( ! empty( $resource['name'] ) ) {
            $name = $this->create_element( 'gmd:name' );
            $name->appendChild( $this->create_character_string( $resource['name'] ) );
            $ci_or->appendChild( $name );
        }

        // Description
        if ( ! empty( $resource['description'] ) ) {
            $desc = $this->create_element( 'gmd:description' );
            $desc->appendChild( $this->create_character_string( $resource['description'] ) );
            $ci_or->appendChild( $desc );
        }

        // Function
        if ( ! empty( $resource['function'] ) ) {
            $func = $this->create_element( 'gmd:function' );
            $func->appendChild(
                $this->create_codelist_element(
                    'gmd:CI_OnLineFunctionCode',
                    'CI_OnLineFunctionCode',
                    $resource['function']
                )
            );
            $ci_or->appendChild( $func );
        }

        return $ci_or;
    }

    /**
     * Append data quality info
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_data_quality_info( $parent ) {
        $dqi = $this->create_element( 'gmd:dataQualityInfo' );
        $dq = $this->create_element( 'gmd:DQ_DataQuality' );

        // Scope
        $scope = $this->create_element( 'gmd:scope' );
        $dq_scope = $this->create_element( 'gmd:DQ_Scope' );
        $level = $this->create_element( 'gmd:level' );
        $level->appendChild(
            $this->create_codelist_element( 'gmd:MD_ScopeCode', 'MD_ScopeCode', $this->metadata->resource_type )
        );
        $dq_scope->appendChild( $level );
        $scope->appendChild( $dq_scope );
        $dq->appendChild( $scope );

        // Conformity (INSPIRE)
        $this->append_conformity( $dq );

        // Lineage
        if ( ! empty( $this->metadata->lineage ) ) {
            $li = $this->create_element( 'gmd:lineage' );
            $li_inner = $this->create_element( 'gmd:LI_Lineage' );
            $stmt = $this->create_element( 'gmd:statement' );
            $stmt->appendChild( $this->create_character_string( $this->metadata->lineage ) );
            $li_inner->appendChild( $stmt );
            $li->appendChild( $li_inner );
            $dq->appendChild( $li );
        }

        $dqi->appendChild( $dq );
        $parent->appendChild( $dqi );
    }

    /**
     * Append conformity declarations
     *
     * @param DOMElement $parent Elemento parent
     */
    private function append_conformity( $parent ) {
        $conformities = $this->metadata->get_conformity();

        foreach ( $conformities as $conf ) {
            $report = $this->create_element( 'gmd:report' );
            $dom_cons = $this->create_element( 'gmd:DQ_DomainConsistency' );
            $result = $this->create_element( 'gmd:result' );
            $conf_result = $this->create_element( 'gmd:DQ_ConformanceResult' );

            // Specification
            $spec = $this->create_element( 'gmd:specification' );
            $ci_cit = $this->create_element( 'gmd:CI_Citation' );

            $title = $this->create_element( 'gmd:title' );
            $title->appendChild( $this->create_character_string( $conf['specification_title'] ) );
            $ci_cit->appendChild( $title );

            if ( ! empty( $conf['specification_date'] ) ) {
                $date = $this->create_element( 'gmd:date' );
                $ci_date = $this->create_element( 'gmd:CI_Date' );

                $d = $this->create_element( 'gmd:date' );
                $gco_date = $this->create_element( 'gco:Date' );
                $gco_date->nodeValue = $conf['specification_date'];
                $d->appendChild( $gco_date );
                $ci_date->appendChild( $d );

                $dt = $this->create_element( 'gmd:dateType' );
                $dt->appendChild(
                    $this->create_codelist_element(
                        'gmd:CI_DateTypeCode',
                        'CI_DateTypeCode',
                        $conf['specification_date_type'] ?? 'publication'
                    )
                );
                $ci_date->appendChild( $dt );

                $date->appendChild( $ci_date );
                $ci_cit->appendChild( $date );
            }

            $spec->appendChild( $ci_cit );
            $conf_result->appendChild( $spec );

            // Explanation
            $expl = $this->create_element( 'gmd:explanation' );
            $expl->appendChild( $this->create_character_string( $conf['explanation'] ?? 'Vedere specifica citata' ) );
            $conf_result->appendChild( $expl );

            // Pass
            $pass = $this->create_element( 'gmd:pass' );
            $bool = $this->create_element( 'gco:Boolean' );
            $bool->nodeValue = ( $conf['pass'] ?? false ) ? 'true' : 'false';
            $pass->appendChild( $bool );
            $conf_result->appendChild( $pass );

            $result->appendChild( $conf_result );
            $dom_cons->appendChild( $result );
            $report->appendChild( $dom_cons );
            $parent->appendChild( $report );
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Crea elemento DOM
     *
     * @param string $name Nome elemento con prefisso
     * @return DOMElement
     */
    private function create_element( $name ) {
        $parts = explode( ':', $name );
        $prefix = $parts[0];

        $ns_map = array(
            'gmd' => RNDT_XML_Namespaces::GMD,
            'gco' => RNDT_XML_Namespaces::GCO,
            'gml' => RNDT_XML_Namespaces::GML,
            'srv' => RNDT_XML_Namespaces::SRV,
            'gmx' => RNDT_XML_Namespaces::GMX,
        );

        $ns = $ns_map[ $prefix ] ?? RNDT_XML_Namespaces::GMD;
        return $this->doc->createElementNS( $ns, $name );
    }

    /**
     * Crea gco:CharacterString
     *
     * @param string $value Valore
     * @return DOMElement
     */
    private function create_character_string( $value ) {
        $cs = $this->doc->createElementNS( RNDT_XML_Namespaces::GCO, 'gco:CharacterString' );
        $cs->nodeValue = $value;
        return $cs;
    }

    /**
     * Crea gco:Decimal
     *
     * @param float $value Valore
     * @return DOMElement
     */
    private function create_decimal( $value ) {
        $dec = $this->doc->createElementNS( RNDT_XML_Namespaces::GCO, 'gco:Decimal' );
        $dec->nodeValue = $value;
        return $dec;
    }

    /**
     * Crea elemento codelist
     *
     * @param string $element_name Nome elemento
     * @param string $codelist     Nome codelist
     * @param string $value        Valore
     * @return DOMElement
     */
    private function create_codelist_element( $element_name, $codelist, $value ) {
        $elem = $this->create_element( $element_name );
        $elem->setAttribute( 'codeList', RNDT_XML_Codelists::get_codelist_uri( $codelist ) );
        $elem->setAttribute( 'codeListValue', $value );
        $elem->nodeValue = $value;
        return $elem;
    }
}
