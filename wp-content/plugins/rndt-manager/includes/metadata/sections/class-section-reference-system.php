<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Reference_System extends RNDT_Section_Base {
    protected $id = 'reference_system';
    protected $title;

    public function __construct() {
        $this->title = __( 'Sistema di riferimento', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'reference_system_code' => array(
                'type' => 'select', 'label' => __( 'Sistema di riferimento', 'rndt-manager' ),
                'required' => array( 'dataset', 'series' ), 'options' => 'epsg_codes',
                'help' => __( 'Codice EPSG del sistema di riferimento (es. EPSG:4326).', 'rndt-manager' ),
            ),
            'reference_system_codespace' => array(
                'type' => 'text', 'label' => __( 'Codespace', 'rndt-manager' ),
                'default' => 'http://www.epsg-registry.org/',
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        // Non applicabile ai servizi
        if ( 'service' === $resource_type ) {
            return $errors;
        }

        if ( in_array( $resource_type, array( 'dataset', 'series' ), true ) ) {
            if ( $this->is_empty( $data['reference_system_code'] ?? null ) ) {
                $this->add_error( $errors, 'reference_system_code', __( 'Il sistema di riferimento è obbligatorio.', 'rndt-manager' ) );
            }
        }

        return $errors;
    }
}
