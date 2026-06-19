<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Service extends RNDT_Section_Base {
    protected $id = 'service';
    protected $title;

    public function __construct() {
        $this->title = __( 'Dettagli servizio', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'service_type' => array(
                'type' => 'select', 'label' => __( 'Tipo di servizio', 'rndt-manager' ),
                'required' => true, 'options' => 'service_types',
            ),
            'service_type_version' => array(
                'type' => 'text', 'label' => __( 'Versione del servizio', 'rndt-manager' ),
                'help' => __( 'Es: 1.1.1 per WMS, 2.0.0 per WFS.', 'rndt-manager' ),
            ),
            'coupling_type' => array(
                'type' => 'select', 'label' => __( 'Tipo di coupling', 'rndt-manager' ),
                'required' => true,
                'options' => array(
                    '' => '-- Seleziona --', 'tight' => 'Tight (stretto)',
                    'mixed' => 'Mixed (misto)', 'loose' => 'Loose (lasco)',
                ),
            ),
            'service_operations' => array(
                'type' => 'repeatable', 'label' => __( 'Operazioni del servizio', 'rndt-manager' ),
                'required' => true,
                'help' => __( 'Operazioni supportate dal servizio (es. GetCapabilities, GetMap).', 'rndt-manager' ),
            ),
            'coupled_resources' => array(
                'type' => 'repeatable', 'label' => __( 'Risorse accoppiate', 'rndt-manager' ),
                'help' => __( 'Dataset serviti. Obbligatorio se coupling type = tight.', 'rndt-manager' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        // Questa sezione è solo per i servizi
        if ( 'service' !== $resource_type ) {
            return $errors;
        }

        if ( $this->is_empty( $data['service_type'] ?? null ) ) {
            $this->add_error( $errors, 'service_type', __( 'Il tipo di servizio è obbligatorio.', 'rndt-manager' ) );
        }

        if ( $this->is_empty( $data['coupling_type'] ?? null ) ) {
            $this->add_error( $errors, 'coupling_type', __( 'Il tipo di coupling è obbligatorio.', 'rndt-manager' ) );
        }

        if ( empty( $data['service_operations'] ?? array() ) ) {
            $this->add_error( $errors, 'service_operations', __( 'Almeno un\'operazione del servizio è obbligatoria.', 'rndt-manager' ) );
        }

        // Coupled resources obbligatorio se coupling = tight
        if ( ( $data['coupling_type'] ?? '' ) === 'tight' && empty( $data['coupled_resources'] ?? array() ) ) {
            $this->add_error( $errors, 'coupled_resources', __( 'Le risorse accoppiate sono obbligatorie per coupling tight.', 'rndt-manager' ) );
        }

        return $errors;
    }
}
