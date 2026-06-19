<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Temporal extends RNDT_Section_Base {
    protected $id = 'temporal';
    protected $title;

    public function __construct() {
        $this->title = __( 'Riferimento temporale', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'date_creation' => array(
                'type' => 'date', 'label' => __( 'Data di creazione', 'rndt-manager' ),
                'help' => __( 'Almeno una data tra creazione, pubblicazione e revisione è obbligatoria.', 'rndt-manager' ),
            ),
            'date_publication' => array(
                'type' => 'date', 'label' => __( 'Data di pubblicazione', 'rndt-manager' ),
            ),
            'date_revision' => array(
                'type' => 'date', 'label' => __( 'Data di revisione', 'rndt-manager' ),
            ),
            'temporal_extent_begin' => array(
                'type' => 'date', 'label' => __( 'Inizio estensione temporale', 'rndt-manager' ),
            ),
            'temporal_extent_end' => array(
                'type' => 'date', 'label' => __( 'Fine estensione temporale', 'rndt-manager' ),
            ),
            'maintenance_frequency' => array(
                'type' => 'select', 'label' => __( 'Frequenza di aggiornamento', 'rndt-manager' ),
                'options' => array(
                    '' => '-- Seleziona --', 'continual' => 'Continuo', 'daily' => 'Giornaliero',
                    'weekly' => 'Settimanale', 'fortnightly' => 'Quindicinale', 'monthly' => 'Mensile',
                    'quarterly' => 'Trimestrale', 'biannually' => 'Semestrale', 'annually' => 'Annuale',
                    'asNeeded' => 'Quando necessario', 'irregular' => 'Irregolare',
                    'notPlanned' => 'Non pianificato', 'unknown' => 'Sconosciuto',
                ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();
        
        // Almeno una data obbligatoria
        $has_date = ! $this->is_empty( $data['date_creation'] ?? null ) ||
                    ! $this->is_empty( $data['date_publication'] ?? null ) ||
                    ! $this->is_empty( $data['date_revision'] ?? null );
        
        if ( ! $has_date ) {
            $this->add_error( $errors, 'date_creation', __( 'Almeno una data (creazione, pubblicazione o revisione) è obbligatoria.', 'rndt-manager' ) );
        }

        // Validazione date formato
        $date_fields = array( 'date_creation', 'date_publication', 'date_revision', 'temporal_extent_begin', 'temporal_extent_end' );
        foreach ( $date_fields as $field ) {
            if ( ! $this->is_empty( $data[ $field ] ?? null ) ) {
                if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data[ $field ] ) ) {
                    $this->add_error( $errors, $field, __( 'Formato data non valido (YYYY-MM-DD).', 'rndt-manager' ) );
                }
            }
        }

        return $errors;
    }
}
