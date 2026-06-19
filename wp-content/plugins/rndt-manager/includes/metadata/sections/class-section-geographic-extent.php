<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Geographic_Extent extends RNDT_Section_Base {
    protected $id = 'geographic_extent';
    protected $title;

    public function __construct() {
        $this->title = __( 'Estensione geografica', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'bbox_west' => array(
                'type' => 'number', 'label' => __( 'Longitudine Ovest', 'rndt-manager' ),
                'required' => true, 'min' => -180, 'max' => 180, 'step' => 0.000001,
            ),
            'bbox_east' => array(
                'type' => 'number', 'label' => __( 'Longitudine Est', 'rndt-manager' ),
                'required' => true, 'min' => -180, 'max' => 180, 'step' => 0.000001,
            ),
            'bbox_south' => array(
                'type' => 'number', 'label' => __( 'Latitudine Sud', 'rndt-manager' ),
                'required' => true, 'min' => -90, 'max' => 90, 'step' => 0.000001,
            ),
            'bbox_north' => array(
                'type' => 'number', 'label' => __( 'Latitudine Nord', 'rndt-manager' ),
                'required' => true, 'min' => -90, 'max' => 90, 'step' => 0.000001,
            ),
            'geographic_description' => array(
                'type' => 'text', 'label' => __( 'Descrizione geografica', 'rndt-manager' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();
        $bbox_fields = array( 'bbox_west', 'bbox_east', 'bbox_south', 'bbox_north' );

        foreach ( $bbox_fields as $field ) {
            if ( ! isset( $data[ $field ] ) || '' === $data[ $field ] ) {
                $this->add_error( $errors, $field, __( 'Il bounding box è obbligatorio.', 'rndt-manager' ) );
            }
        }

        // Validazione range
        if ( isset( $data['bbox_west'] ) && ( $data['bbox_west'] < -180 || $data['bbox_west'] > 180 ) ) {
            $this->add_error( $errors, 'bbox_west', __( 'Longitudine deve essere tra -180 e 180.', 'rndt-manager' ) );
        }
        if ( isset( $data['bbox_east'] ) && ( $data['bbox_east'] < -180 || $data['bbox_east'] > 180 ) ) {
            $this->add_error( $errors, 'bbox_east', __( 'Longitudine deve essere tra -180 e 180.', 'rndt-manager' ) );
        }
        if ( isset( $data['bbox_south'] ) && ( $data['bbox_south'] < -90 || $data['bbox_south'] > 90 ) ) {
            $this->add_error( $errors, 'bbox_south', __( 'Latitudine deve essere tra -90 e 90.', 'rndt-manager' ) );
        }
        if ( isset( $data['bbox_north'] ) && ( $data['bbox_north'] < -90 || $data['bbox_north'] > 90 ) ) {
            $this->add_error( $errors, 'bbox_north', __( 'Latitudine deve essere tra -90 e 90.', 'rndt-manager' ) );
        }

        // Validazione coerenza bbox
        if ( isset( $data['bbox_west'], $data['bbox_east'] ) && $data['bbox_west'] >= $data['bbox_east'] ) {
            $this->add_error( $errors, 'bbox_west', __( 'Ovest deve essere minore di Est.', 'rndt-manager' ) );
        }
        if ( isset( $data['bbox_south'], $data['bbox_north'] ) && $data['bbox_south'] >= $data['bbox_north'] ) {
            $this->add_error( $errors, 'bbox_south', __( 'Sud deve essere minore di Nord.', 'rndt-manager' ) );
        }

        return $errors;
    }
}
