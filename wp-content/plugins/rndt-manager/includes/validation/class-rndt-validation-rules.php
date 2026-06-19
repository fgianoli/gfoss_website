<?php
/**
 * Regole di validazione RNDT 2020
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Validation_Rules
 *
 * Definisce le regole di validazione secondo il profilo RNDT 2020
 */
class RNDT_Validation_Rules {

    /**
     * Campi obbligatori per tipo risorsa
     *
     * @return array
     */
    public static function get_required_fields() {
        return array(
            'common' => array(
                'file_identifier'      => __( 'Identificativo del metadato', 'rndt-manager' ),
                'metadata_language'    => __( 'Lingua del metadato', 'rndt-manager' ),
                'metadata_date'        => __( 'Data del metadato', 'rndt-manager' ),
                'title'                => __( 'Titolo', 'rndt-manager' ),
                'abstract'             => __( 'Abstract', 'rndt-manager' ),
                'resource_identifier'  => __( 'Identificativo della risorsa', 'rndt-manager' ),
            ),
            'dataset' => array(
                'resource_language'           => __( 'Lingua della risorsa', 'rndt-manager' ),
                'reference_system_code'       => __( 'Sistema di riferimento', 'rndt-manager' ),
                'spatial_representation_type' => __( 'Tipo di rappresentazione spaziale', 'rndt-manager' ),
            ),
            'series' => array(
                'resource_language'           => __( 'Lingua della risorsa', 'rndt-manager' ),
                'reference_system_code'       => __( 'Sistema di riferimento', 'rndt-manager' ),
            ),
            'service' => array(
                'service_type'   => __( 'Tipo di servizio', 'rndt-manager' ),
                'coupling_type'  => __( 'Tipo di coupling', 'rndt-manager' ),
            ),
            'application' => array(
                'resource_language' => __( 'Lingua della risorsa', 'rndt-manager' ),
            ),
        );
    }

    /**
     * Validazione formato email
     *
     * @param string $email Email da validare
     * @return bool
     */
    public static function is_valid_email( $email ) {
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
    }

    /**
     * Validazione formato URL
     *
     * @param string $url URL da validare
     * @return bool
     */
    public static function is_valid_url( $url ) {
        return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
    }

    /**
     * Validazione formato data ISO 8601
     *
     * @param string $date Data da validare
     * @return bool
     */
    public static function is_valid_date( $date ) {
        if ( empty( $date ) ) {
            return false;
        }

        // Formato YYYY-MM-DD
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $parts = explode( '-', $date );
            return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
        }

        // Formato YYYY-MM-DDTHH:MM:SS (ISO 8601)
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $date ) ) {
            $dt = DateTime::createFromFormat( 'Y-m-d\TH:i:s', substr( $date, 0, 19 ) );
            return $dt !== false;
        }

        // Formato YYYY-MM-DD HH:MM:SS (MySQL datetime)
        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
            $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
            return $dt !== false;
        }

        return false;
    }

    /**
     * Validazione codice EPSG
     *
     * @param string $code Codice EPSG
     * @return bool
     */
    public static function is_valid_epsg( $code ) {
        // Formato: EPSG:XXXX o solo numero
        if ( preg_match( '/^EPSG:\d+$/i', $code ) ) {
            return true;
        }
        if ( preg_match( '/^\d+$/', $code ) ) {
            return true;
        }
        return false;
    }

    /**
     * Validazione bounding box
     *
     * @param float $west  Longitudine ovest
     * @param float $east  Longitudine est
     * @param float $south Latitudine sud
     * @param float $north Latitudine nord
     * @return array Array di errori (vuoto se valido)
     */
    public static function validate_bbox( $west, $east, $south, $north ) {
        $errors = array();

        // Controllo range longitudine (-180 a 180)
        if ( $west < -180 || $west > 180 ) {
            $errors[] = __( 'Longitudine ovest deve essere tra -180 e 180.', 'rndt-manager' );
        }
        if ( $east < -180 || $east > 180 ) {
            $errors[] = __( 'Longitudine est deve essere tra -180 e 180.', 'rndt-manager' );
        }

        // Controllo range latitudine (-90 a 90)
        if ( $south < -90 || $south > 90 ) {
            $errors[] = __( 'Latitudine sud deve essere tra -90 e 90.', 'rndt-manager' );
        }
        if ( $north < -90 || $north > 90 ) {
            $errors[] = __( 'Latitudine nord deve essere tra -90 e 90.', 'rndt-manager' );
        }

        // Controllo coerenza
        if ( empty( $errors ) ) {
            if ( $west > $east ) {
                $errors[] = __( 'Longitudine ovest deve essere minore della longitudine est.', 'rndt-manager' );
            }
            if ( $south > $north ) {
                $errors[] = __( 'Latitudine sud deve essere minore della latitudine nord.', 'rndt-manager' );
            }
        }

        return $errors;
    }

    /**
     * Validazione identificativo RNDT
     * Formato: {codice_iPA}:{id_locale}
     *
     * @param string $identifier Identificativo
     * @return bool
     */
    public static function is_valid_rndt_identifier( $identifier ) {
        // Deve contenere due punti e almeno un carattere prima e dopo
        return preg_match( '/^[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+$/', $identifier ) === 1;
    }

    /**
     * Ottieni codici lingua ISO 639-2/B validi
     *
     * @return array
     */
    public static function get_valid_language_codes() {
        return array(
            'ita', 'eng', 'deu', 'fra', 'spa', 'por', 'nld', 'ell', 'pol',
            'ces', 'slk', 'hun', 'ron', 'bul', 'hrv', 'slv', 'est', 'lav',
            'lit', 'mlt', 'fin', 'swe', 'dan', 'gle',
        );
    }

    /**
     * Ottieni tipi servizio validi
     *
     * @return array
     */
    public static function get_valid_service_types() {
        return array(
            'discovery', 'view', 'download', 'transformation', 'invoke', 'other',
        );
    }

    /**
     * Ottieni tipi coupling validi
     *
     * @return array
     */
    public static function get_valid_coupling_types() {
        return array( 'loose', 'mixed', 'tight' );
    }

    /**
     * Ottieni topic categories valide
     *
     * @return array
     */
    public static function get_valid_topic_categories() {
        return array(
            'farming', 'biota', 'boundaries', 'climatologyMeteorologyAtmosphere',
            'economy', 'elevation', 'environment', 'geoscientificInformation',
            'health', 'imageryBaseMapsEarthCover', 'intelligenceMilitary',
            'inlandWaters', 'location', 'oceans', 'planningCadastre',
            'society', 'structure', 'transportation', 'utilitiesCommunication',
        );
    }

    /**
     * Verifica se è richiesta almeno una data
     *
     * @param array $data Dati metadato
     * @return bool
     */
    public static function has_at_least_one_date( $data ) {
        return ! empty( $data['date_creation'] )
            || ! empty( $data['date_publication'] )
            || ! empty( $data['date_revision'] );
    }

    /**
     * Verifica presenza keyword INSPIRE
     *
     * @param array $keywords Array di keywords
     * @return bool
     */
    public static function has_inspire_keyword( $keywords ) {
        foreach ( $keywords as $kw ) {
            if ( isset( $kw['thesaurus_name'] ) && $kw['thesaurus_name'] === 'inspire' ) {
                return true;
            }
            if ( isset( $kw['thesaurus_title'] ) && stripos( $kw['thesaurus_title'], 'INSPIRE' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica presenza conformità INSPIRE
     *
     * @param array $conformities Array di conformità
     * @return bool
     */
    public static function has_inspire_conformity( $conformities ) {
        foreach ( $conformities as $conf ) {
            if ( isset( $conf['specification_title'] ) ) {
                if ( stripos( $conf['specification_title'], 'INSPIRE' ) !== false
                  || stripos( $conf['specification_title'], '1089/2010' ) !== false
                  || stripos( $conf['specification_title'], '1205/2008' ) !== false ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Verifica presenza contatto metadata
     *
     * @param array $parties Array di responsible parties
     * @return bool
     */
    public static function has_metadata_contact( $parties ) {
        foreach ( $parties as $party ) {
            $type = $party['role_type'] ?? $party['context'] ?? null;
            if ( 'metadata_contact' === $type ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica presenza contatto risorsa
     *
     * @param array $parties Array di responsible parties
     * @return bool
     */
    public static function has_resource_poc( $parties ) {
        foreach ( $parties as $party ) {
            $type = $party['role_type'] ?? $party['context'] ?? null;
            if ( 'resource_poc' === $type ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica che coupled resources sia presente se coupling = tight
     *
     * @param string $coupling_type     Tipo coupling
     * @param array  $coupled_resources Array risorse accoppiate
     * @return bool
     */
    public static function validate_coupled_resources( $coupling_type, $coupled_resources ) {
        if ( $coupling_type === 'tight' && empty( $coupled_resources ) ) {
            return false;
        }
        return true;
    }

    /**
     * Verifica che service operations sia presente per servizi
     *
     * @param array $operations Array operazioni
     * @return bool
     */
    public static function has_service_operations( $operations ) {
        return ! empty( $operations );
    }
}
