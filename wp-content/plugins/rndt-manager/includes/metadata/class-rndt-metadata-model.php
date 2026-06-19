<?php
/**
 * Modello dati per i metadati RNDT
 *
 * Rappresenta un singolo record metadato con tutte le sue proprieta
 * e relazioni (keywords, contatti, risorse online, ecc.)
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Metadata_Model
 */
class RNDT_Metadata_Model {

    /**
     * ID del record nel database PostgreSQL
     *
     * @var int|null
     */
    public $id = null;

    /**
     * ID del post WordPress associato
     *
     * @var int|null
     */
    public $wp_post_id = null;

    // ========== IDENTIFICAZIONE ==========

    /**
     * Tipo di risorsa (dataset, series, service, application)
     *
     * @var string
     */
    public $resource_type = 'dataset';

    /**
     * Identificativo univoco del metadato (UUID)
     *
     * @var string
     */
    public $file_identifier = '';

    /**
     * Identificativo della risorsa
     *
     * @var string
     */
    public $resource_identifier = '';

    /**
     * Codespace dell'identificativo risorsa
     *
     * @var string
     */
    public $resource_identifier_codespace = '';

    /**
     * Identificativo del metadato padre (per serie)
     *
     * @var string
     */
    public $parent_identifier = '';

    /**
     * Titolo della risorsa
     *
     * @var string
     */
    public $title = '';

    /**
     * Abstract/descrizione della risorsa
     *
     * @var string
     */
    public $abstract = '';

    /**
     * Lingua della risorsa (codice ISO 639-2/B)
     *
     * @var string
     */
    public $resource_language = 'ita';

    /**
     * Set di caratteri
     *
     * @var string
     */
    public $character_set = 'utf8';

    /**
     * Nome del livello gerarchico
     *
     * @var string
     */
    public $hierarchy_level_name = '';

    /**
     * Tipo di rappresentazione spaziale
     *
     * @var string
     */
    public $spatial_representation_type = '';

    // ========== TEMPORALE ==========

    /**
     * Data di creazione
     *
     * @var string|null
     */
    public $date_creation = null;

    /**
     * Data di pubblicazione
     *
     * @var string|null
     */
    public $date_publication = null;

    /**
     * Data di revisione
     *
     * @var string|null
     */
    public $date_revision = null;

    /**
     * Inizio estensione temporale
     *
     * @var string|null
     */
    public $temporal_extent_begin = null;

    /**
     * Fine estensione temporale
     *
     * @var string|null
     */
    public $temporal_extent_end = null;

    // ========== GEOGRAFICO ==========

    /**
     * Bounding box - Ovest
     *
     * @var float|null
     */
    public $bbox_west = null;

    /**
     * Bounding box - Est
     *
     * @var float|null
     */
    public $bbox_east = null;

    /**
     * Bounding box - Sud
     *
     * @var float|null
     */
    public $bbox_south = null;

    /**
     * Bounding box - Nord
     *
     * @var float|null
     */
    public $bbox_north = null;

    /**
     * Descrizione geografica
     *
     * @var string
     */
    public $geographic_description = '';

    // ========== QUALITA ==========

    /**
     * Lineage/provenienza
     *
     * @var string
     */
    public $lineage_statement = '';

    /**
     * Risoluzione spaziale - scala (denominatore)
     *
     * @var int|null
     */
    public $spatial_resolution_scale = null;

    /**
     * Risoluzione spaziale - distanza
     *
     * @var float|null
     */
    public $spatial_resolution_distance = null;

    /**
     * Unita di misura risoluzione
     *
     * @var string
     */
    public $spatial_resolution_units = '';

    // ========== VINCOLI ==========

    /**
     * Limitazioni d'uso
     *
     * @var string
     */
    public $use_limitation = '';

    /**
     * Vincoli di accesso
     *
     * @var string
     */
    public $access_constraints = 'otherRestrictions';

    /**
     * Vincoli d'uso
     *
     * @var string
     */
    public $use_constraints = 'otherRestrictions';

    /**
     * Altri vincoli (testo libero)
     *
     * @var string
     */
    public $other_constraints = '';

    /**
     * Classificazione di sicurezza
     *
     * @var string
     */
    public $classification = '';

    // ========== SISTEMA DI RIFERIMENTO ==========

    /**
     * Codice del sistema di riferimento (es. EPSG:4326)
     *
     * @var string
     */
    public $reference_system_code = '';

    /**
     * Codespace del sistema di riferimento
     *
     * @var string
     */
    public $reference_system_codespace = '';

    // ========== INFO METADATO ==========

    /**
     * Lingua del metadato
     *
     * @var string
     */
    public $metadata_language = 'ita';

    /**
     * Set di caratteri del metadato
     *
     * @var string
     */
    public $metadata_character_set = 'utf8';

    /**
     * Data del metadato
     *
     * @var string
     */
    public $metadata_date = '';

    /**
     * Nome dello standard del metadato
     *
     * @var string
     */
    public $metadata_standard_name = 'DM - Regole tecniche RNDT';

    /**
     * Versione dello standard del metadato
     *
     * @var string
     */
    public $metadata_standard_version = '10 novembre 2011';

    // ========== SERVIZIO (ISO 19119) ==========

    /**
     * Tipo di servizio
     *
     * @var string
     */
    public $service_type = '';

    /**
     * Versione del tipo di servizio
     *
     * @var string
     */
    public $service_type_version = '';

    /**
     * Tipo di coupling (tight, mixed, loose)
     *
     * @var string
     */
    public $coupling_type = '';

    // ========== MANUTENZIONE ==========

    /**
     * Frequenza di manutenzione
     *
     * @var string
     */
    public $maintenance_frequency = '';

    // ========== STATO ==========

    /**
     * Stato validazione (not_validated, valid, invalid)
     *
     * @var string
     */
    public $validation_status = 'not_validated';

    /**
     * Errori di validazione (JSON)
     *
     * @var array
     */
    public $validation_errors = array();

    /**
     * Data ultima validazione
     *
     * @var string|null
     */
    public $last_validated_at = null;

    /**
     * Data pubblicazione su CSW
     *
     * @var string|null
     */
    public $csw_published_at = null;

    /**
     * ID record nel catalogo CSW
     *
     * @var string
     */
    public $csw_record_id = '';

    /**
     * Data pubblicazione su GeoServer
     *
     * @var string|null
     */
    public $geoserver_published_at = null;

    /**
     * Cache XML generato
     *
     * @var string
     */
    public $xml_cache = '';

    /**
     * Data cache XML
     *
     * @var string|null
     */
    public $xml_cache_date = null;

    // ========== AUDIT ==========

    /**
     * Data creazione
     *
     * @var string
     */
    public $created_at = '';

    /**
     * Data ultimo aggiornamento
     *
     * @var string
     */
    public $updated_at = '';

    /**
     * ID utente creatore
     *
     * @var int|null
     */
    public $created_by = null;

    /**
     * ID utente ultimo aggiornamento
     *
     * @var int|null
     */
    public $updated_by = null;

    // ========== RELAZIONI (lazy-loaded) ==========

    /**
     * Keywords
     *
     * @var array|null
     */
    private $keywords = null;

    /**
     * Parti responsabili
     *
     * @var array|null
     */
    private $responsible_parties = null;

    /**
     * Risorse online
     *
     * @var array|null
     */
    private $online_resources = null;

    /**
     * Formati di distribuzione
     *
     * @var array|null
     */
    private $distribution_formats = null;

    /**
     * Dichiarazioni di conformita
     *
     * @var array|null
     */
    private $conformity = null;

    /**
     * Operazioni del servizio (ISO 19119)
     *
     * @var array|null
     */
    private $service_operations = null;

    /**
     * Risorse accoppiate (ISO 19119)
     *
     * @var array|null
     */
    private $coupled_resources = null;

    /**
     * Costruttore
     *
     * @param array $data Dati opzionali per inizializzare il modello
     */
    public function __construct( $data = array() ) {
        if ( ! empty( $data ) ) {
            $this->from_array( $data );
        }
    }

    /**
     * Popola il modello da un array
     *
     * @param array $data Dati
     * @return self
     */
    public function from_array( $data ) {
        foreach ( $data as $key => $value ) {
            if ( property_exists( $this, $key ) ) {
                // Gestisci tipi speciali
                if ( in_array( $key, array( 'bbox_west', 'bbox_east', 'bbox_south', 'bbox_north', 'spatial_resolution_distance' ), true ) ) {
                    $this->$key = null !== $value ? (float) $value : null;
                } elseif ( in_array( $key, array( 'id', 'wp_post_id', 'spatial_resolution_scale', 'created_by', 'updated_by' ), true ) ) {
                    $this->$key = null !== $value ? (int) $value : null;
                } elseif ( $key === 'validation_errors' ) {
                    $this->$key = is_string( $value ) ? json_decode( $value, true ) : (array) $value;
                } else {
                    $this->$key = $value;
                }
            }
        }

        // Carica relazioni se presenti nei dati
        if ( isset( $data['keywords'] ) ) {
            $this->keywords = $data['keywords'];
        }
        if ( isset( $data['responsible_parties'] ) ) {
            $this->responsible_parties = $data['responsible_parties'];
        }
        if ( isset( $data['online_resources'] ) ) {
            $this->online_resources = $data['online_resources'];
        }
        if ( isset( $data['distribution_formats'] ) ) {
            $this->distribution_formats = $data['distribution_formats'];
        }
        if ( isset( $data['conformity'] ) ) {
            $this->conformity = $data['conformity'];
        }
        if ( isset( $data['service_operations'] ) ) {
            $this->service_operations = $data['service_operations'];
        }
        if ( isset( $data['coupled_resources'] ) ) {
            $this->coupled_resources = $data['coupled_resources'];
        }

        return $this;
    }

    /**
     * Converti il modello in array
     *
     * @param bool $include_relations Includere le relazioni
     * @return array
     */
    public function to_array( $include_relations = true ) {
        $data = array(
            'id'                          => $this->id,
            'wp_post_id'                  => $this->wp_post_id,
            'resource_type'               => $this->resource_type,
            'file_identifier'             => $this->file_identifier,
            'resource_identifier'         => $this->resource_identifier,
            'resource_identifier_codespace' => $this->resource_identifier_codespace,
            'parent_identifier'           => $this->parent_identifier,
            'title'                       => $this->title,
            'abstract'                    => $this->abstract,
            'resource_language'           => $this->resource_language,
            'character_set'               => $this->character_set,
            'hierarchy_level_name'        => $this->hierarchy_level_name,
            'spatial_representation_type' => $this->spatial_representation_type,
            'date_creation'               => $this->date_creation,
            'date_publication'            => $this->date_publication,
            'date_revision'               => $this->date_revision,
            'temporal_extent_begin'       => $this->temporal_extent_begin,
            'temporal_extent_end'         => $this->temporal_extent_end,
            'bbox_west'                   => $this->bbox_west,
            'bbox_east'                   => $this->bbox_east,
            'bbox_south'                  => $this->bbox_south,
            'bbox_north'                  => $this->bbox_north,
            'geographic_description'      => $this->geographic_description,
            'lineage_statement'           => $this->lineage_statement,
            'lineage'                     => $this->lineage_statement,
            'spatial_resolution_scale'    => $this->spatial_resolution_scale,
            'spatial_resolution_distance' => $this->spatial_resolution_distance,
            'spatial_resolution_units'    => $this->spatial_resolution_units,
            'use_limitation'              => $this->use_limitation,
            'access_constraints'          => $this->access_constraints,
            'use_constraints'             => $this->use_constraints,
            'other_constraints'           => $this->other_constraints,
            'classification'              => $this->classification,
            'reference_system_code'       => $this->reference_system_code,
            'reference_system_codespace'  => $this->reference_system_codespace,
            'metadata_language'           => $this->metadata_language,
            'metadata_character_set'      => $this->metadata_character_set,
            'metadata_date'               => $this->metadata_date,
            'metadata_standard_name'      => $this->metadata_standard_name,
            'metadata_standard_version'   => $this->metadata_standard_version,
            'service_type'                => $this->service_type,
            'service_type_version'        => $this->service_type_version,
            'coupling_type'               => $this->coupling_type,
            'maintenance_frequency'       => $this->maintenance_frequency,
            'validation_status'           => $this->validation_status,
            'validation_errors'           => $this->validation_errors,
            'last_validated_at'           => $this->last_validated_at,
            'csw_published_at'            => $this->csw_published_at,
            'csw_record_id'               => $this->csw_record_id,
            'geoserver_published_at'      => $this->geoserver_published_at,
            'created_at'                  => $this->created_at,
            'updated_at'                  => $this->updated_at,
            'created_by'                  => $this->created_by,
            'updated_by'                  => $this->updated_by,
        );

        if ( $include_relations ) {
            $data['keywords']            = $this->get_keywords();
            $data['responsible_parties'] = $this->get_responsible_parties();
            $data['online_resources']    = $this->get_online_resources();
            $data['distribution_formats'] = $this->get_distribution_formats();
            $data['conformity']          = $this->get_conformity();

            if ( $this->is_service() ) {
                $data['service_operations'] = $this->get_service_operations();
                $data['coupled_resources']  = $this->get_coupled_resources();
            }
        }

        return $data;
    }

    /**
     * Converti in JSON
     *
     * @param bool $include_relations Includere le relazioni
     * @return string
     */
    public function to_json( $include_relations = true ) {
        return wp_json_encode( $this->to_array( $include_relations ) );
    }

    /**
     * Verifica se e un nuovo record (non ancora salvato)
     *
     * @return bool
     */
    public function is_new() {
        return null === $this->id;
    }

    /**
     * Verifica se e un servizio
     *
     * @return bool
     */
    public function is_service() {
        return 'service' === $this->resource_type;
    }

    /**
     * Verifica se e un dataset
     *
     * @return bool
     */
    public function is_dataset() {
        return 'dataset' === $this->resource_type;
    }

    /**
     * Verifica se e una serie
     *
     * @return bool
     */
    public function is_series() {
        return 'series' === $this->resource_type;
    }

    /**
     * Verifica se e un'applicazione
     *
     * @return bool
     */
    public function is_application() {
        return 'application' === $this->resource_type;
    }

    /**
     * Verifica se e stato validato
     *
     * @return bool
     */
    public function is_validated() {
        return 'valid' === $this->validation_status;
    }

    /**
     * Verifica se e stato pubblicato su CSW
     *
     * @return bool
     */
    public function is_published_csw() {
        return ! empty( $this->csw_published_at );
    }

    /**
     * Ottieni il livello gerarchico ISO
     *
     * @return string
     */
    public function get_hierarchy_level() {
        return RNDT_Post_Type::get_hierarchy_level( $this->resource_type );
    }

    /**
     * Genera un nuovo file_identifier (UUID)
     *
     * @return string
     */
    public function generate_file_identifier() {
        $this->file_identifier = wp_generate_uuid4();
        return $this->file_identifier;
    }

    /**
     * Ottieni l'identificativo RNDT formattato (iPA:localId)
     *
     * @return string
     */
    public function get_rndt_identifier() {
        $settings = get_option( 'rndt_settings', array() );
        $ipa_code = isset( $settings['general']['default_ipa_code'] ) ? $settings['general']['default_ipa_code'] : '';

        // Se file_identifier contiene gia' il codice IPA (formato IPA:UUID), restituisci com'e'
        if ( ! empty( $ipa_code ) && ! empty( $this->file_identifier ) ) {
            if ( strpos( $this->file_identifier, $ipa_code . ':' ) === 0 ) {
                return $this->file_identifier;
            }
            return $ipa_code . ':' . $this->file_identifier;
        }

        return $this->file_identifier;
    }

    // ========== GETTERS PER RELAZIONI ==========

    /**
     * Ottieni le keywords
     *
     * @return array
     */
    public function get_keywords() {
        if ( null === $this->keywords && $this->id ) {
            $this->keywords = RNDT_Metadata_Repository::get_instance()->get_keywords( $this->id );
        }
        return $this->keywords ?: array();
    }

    /**
     * Imposta le keywords
     *
     * @param array $keywords Keywords
     * @return self
     */
    public function set_keywords( $keywords ) {
        $this->keywords = $keywords;
        return $this;
    }

    /**
     * Ottieni le parti responsabili
     *
     * @return array
     */
    public function get_responsible_parties() {
        if ( null === $this->responsible_parties && $this->id ) {
            $this->responsible_parties = RNDT_Metadata_Repository::get_instance()->get_responsible_parties( $this->id );
        }
        return $this->responsible_parties ?: array();
    }

    /**
     * Imposta le parti responsabili
     *
     * @param array $parties Parti responsabili
     * @return self
     */
    public function set_responsible_parties( $parties ) {
        $this->responsible_parties = $parties;
        return $this;
    }

    /**
     * Ottieni le risorse online
     *
     * @return array
     */
    public function get_online_resources() {
        if ( null === $this->online_resources && $this->id ) {
            $this->online_resources = RNDT_Metadata_Repository::get_instance()->get_online_resources( $this->id );
        }
        return $this->online_resources ?: array();
    }

    /**
     * Imposta le risorse online
     *
     * @param array $resources Risorse online
     * @return self
     */
    public function set_online_resources( $resources ) {
        $this->online_resources = $resources;
        return $this;
    }

    /**
     * Ottieni i formati di distribuzione
     *
     * @return array
     */
    public function get_distribution_formats() {
        if ( null === $this->distribution_formats && $this->id ) {
            $this->distribution_formats = RNDT_Metadata_Repository::get_instance()->get_distribution_formats( $this->id );
        }
        return $this->distribution_formats ?: array();
    }

    /**
     * Imposta i formati di distribuzione
     *
     * @param array $formats Formati
     * @return self
     */
    public function set_distribution_formats( $formats ) {
        $this->distribution_formats = $formats;
        return $this;
    }

    /**
     * Ottieni le dichiarazioni di conformita
     *
     * @return array
     */
    public function get_conformity() {
        if ( null === $this->conformity && $this->id ) {
            $this->conformity = RNDT_Metadata_Repository::get_instance()->get_conformity( $this->id );
        }
        return $this->conformity ?: array();
    }

    /**
     * Imposta le dichiarazioni di conformita
     *
     * @param array $conformity Conformita
     * @return self
     */
    public function set_conformity( $conformity ) {
        $this->conformity = $conformity;
        return $this;
    }

    /**
     * Ottieni le operazioni del servizio
     *
     * @return array
     */
    public function get_service_operations() {
        if ( null === $this->service_operations && $this->id ) {
            $this->service_operations = RNDT_Metadata_Repository::get_instance()->get_service_operations( $this->id );
        }
        return $this->service_operations ?: array();
    }

    /**
     * Imposta le operazioni del servizio
     *
     * @param array $operations Operazioni
     * @return self
     */
    public function set_service_operations( $operations ) {
        $this->service_operations = $operations;
        return $this;
    }

    /**
     * Ottieni le risorse accoppiate
     *
     * @return array
     */
    public function get_coupled_resources() {
        if ( null === $this->coupled_resources && $this->id ) {
            $this->coupled_resources = RNDT_Metadata_Repository::get_instance()->get_coupled_resources( $this->id );
        }
        return $this->coupled_resources ?: array();
    }

    /**
     * Imposta le risorse accoppiate
     *
     * @param array $resources Risorse accoppiate
     * @return self
     */
    public function set_coupled_resources( $resources ) {
        $this->coupled_resources = $resources;
        return $this;
    }

    // ========== HELPER METHODS ==========

    /**
     * Ottieni le keywords INSPIRE
     *
     * @return array
     */
    public function get_inspire_keywords() {
        $keywords = $this->get_keywords();
        return array_filter( $keywords, function( $kw ) {
            return isset( $kw['thesaurus_name'] ) &&
                   strpos( $kw['thesaurus_name'], 'GEMET' ) !== false;
        } );
    }

    /**
     * Ottieni il contatto del metadato
     *
     * @return array|null
     */
    public function get_metadata_contact() {
        $parties = $this->get_responsible_parties();
        foreach ( $parties as $party ) {
            if ( isset( $party['context'] ) && 'metadata_contact' === $party['context'] ) {
                return $party;
            }
        }
        return null;
    }

    /**
     * Ottieni i contatti della risorsa (point of contact)
     *
     * @return array
     */
    public function get_resource_contacts() {
        $parties = $this->get_responsible_parties();
        return array_filter( $parties, function( $party ) {
            return isset( $party['context'] ) && 'resource_poc' === $party['context'];
        } );
    }

    /**
     * Verifica se ha almeno una data di riferimento
     *
     * @return bool
     */
    public function has_temporal_reference() {
        return ! empty( $this->date_creation ) ||
               ! empty( $this->date_publication ) ||
               ! empty( $this->date_revision );
    }

    /**
     * Verifica se ha un bounding box valido
     *
     * @return bool
     */
    public function has_valid_bbox() {
        return null !== $this->bbox_west &&
               null !== $this->bbox_east &&
               null !== $this->bbox_south &&
               null !== $this->bbox_north &&
               $this->bbox_west < $this->bbox_east &&
               $this->bbox_south < $this->bbox_north;
    }

    /**
     * Invalida la cache XML
     *
     * @return self
     */
    public function invalidate_xml_cache() {
        $this->xml_cache      = '';
        $this->xml_cache_date = null;
        return $this;
    }
}
