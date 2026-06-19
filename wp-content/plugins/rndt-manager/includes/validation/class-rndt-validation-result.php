<?php
/**
 * Risultato della validazione
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Validation_Result
 */
class RNDT_Validation_Result {

    /**
     * Indica se la validazione e passata
     *
     * @var bool
     */
    public $valid = true;

    /**
     * Lista degli errori
     *
     * @var array
     */
    public $errors = array();

    /**
     * Lista degli avvisi
     *
     * @var array
     */
    public $warnings = array();

    /**
     * Lista delle info
     *
     * @var array
     */
    public $info = array();

    /**
     * Timestamp della validazione
     *
     * @var string
     */
    public $timestamp;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->timestamp = current_time( 'mysql' );
    }

    /**
     * Aggiungi un errore
     *
     * @param string $field   Campo con errore.
     * @param string $message Messaggio di errore.
     * @param string $code    Codice errore opzionale.
     */
    public function add_error( $field, $message, $code = '' ) {
        $this->valid    = false;
        $this->errors[] = array(
            'field'   => $field,
            'message' => $message,
            'code'    => $code,
        );
    }

    /**
     * Aggiungi un avviso
     *
     * @param string $field   Campo con avviso.
     * @param string $message Messaggio di avviso.
     * @param string $code    Codice avviso opzionale.
     */
    public function add_warning( $field, $message, $code = '' ) {
        $this->warnings[] = array(
            'field'   => $field,
            'message' => $message,
            'code'    => $code,
        );
    }

    /**
     * Aggiungi un'informazione
     *
     * @param string $field   Campo.
     * @param string $message Messaggio informativo.
     */
    public function add_info( $field, $message ) {
        $this->info[] = array(
            'field'   => $field,
            'message' => $message,
        );
    }

    /**
     * Verifica se ci sono errori
     *
     * @return bool
     */
    public function has_errors() {
        return ! empty( $this->errors );
    }

    /**
     * Verifica se ci sono avvisi
     *
     * @return bool
     */
    public function has_warnings() {
        return ! empty( $this->warnings );
    }

    /**
     * Ottieni il numero di errori
     *
     * @return int
     */
    public function error_count() {
        return count( $this->errors );
    }

    /**
     * Ottieni il numero di avvisi
     *
     * @return int
     */
    public function warning_count() {
        return count( $this->warnings );
    }

    /**
     * Ottieni gli errori per un campo specifico
     *
     * @param string $field Nome del campo.
     * @return array
     */
    public function get_errors_for_field( $field ) {
        return array_filter(
            $this->errors,
            function ( $error ) use ( $field ) {
                return $error['field'] === $field;
            }
        );
    }

    /**
     * Ottieni gli avvisi per un campo specifico
     *
     * @param string $field Nome del campo.
     * @return array
     */
    public function get_warnings_for_field( $field ) {
        return array_filter(
            $this->warnings,
            function ( $warning ) use ( $field ) {
                return $warning['field'] === $field;
            }
        );
    }

    /**
     * Unisci un altro risultato
     *
     * @param RNDT_Validation_Result $other Altro risultato.
     */
    public function merge( RNDT_Validation_Result $other ) {
        if ( ! $other->valid ) {
            $this->valid = false;
        }

        $this->errors   = array_merge( $this->errors, $other->errors );
        $this->warnings = array_merge( $this->warnings, $other->warnings );
        $this->info     = array_merge( $this->info, $other->info );
    }

    /**
     * Converti in array
     *
     * @return array
     */
    public function to_array() {
        return array(
            'valid'     => $this->valid,
            'errors'    => $this->errors,
            'warnings'  => $this->warnings,
            'info'      => $this->info,
            'timestamp' => $this->timestamp,
            'summary'   => array(
                'error_count'   => $this->error_count(),
                'warning_count' => $this->warning_count(),
            ),
        );
    }

    /**
     * Crea da array
     *
     * @param array $data Dati.
     * @return RNDT_Validation_Result
     */
    public static function from_array( $data ) {
        $result = new self();

        if ( isset( $data['valid'] ) ) {
            $result->valid = (bool) $data['valid'];
        }

        if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
            $result->errors = $data['errors'];
        }

        if ( isset( $data['warnings'] ) && is_array( $data['warnings'] ) ) {
            $result->warnings = $data['warnings'];
        }

        if ( isset( $data['info'] ) && is_array( $data['info'] ) ) {
            $result->info = $data['info'];
        }

        if ( isset( $data['timestamp'] ) ) {
            $result->timestamp = $data['timestamp'];
        }

        return $result;
    }

    /**
     * Ottieni un riepilogo testuale
     *
     * @return string
     */
    public function get_summary() {
        if ( $this->valid ) {
            return __( 'Validazione completata con successo.', 'rndt-manager' );
        }

        return sprintf(
            /* translators: 1: number of errors, 2: number of warnings */
            __( 'Validazione fallita: %1$d errori, %2$d avvisi.', 'rndt-manager' ),
            $this->error_count(),
            $this->warning_count()
        );
    }
}
