<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Quality extends RNDT_Section_Base {
    protected $id = 'quality';
    protected $title;

    public function __construct() {
        $this->title = __( 'Qualità', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'lineage_statement' => array(
                'type' => 'textarea', 'label' => __( 'Genealogia (Lineage)', 'rndt-manager' ),
                'required' => array( 'dataset', 'series' ), 'rows' => 4,
                'help' => __( 'Descrizione della storia e/o del ciclo di vita della risorsa.', 'rndt-manager' ),
            ),
            'spatial_resolution_scale' => array(
                'type' => 'number', 'label' => __( 'Scala equivalente (denominatore)', 'rndt-manager' ),
                'help' => __( 'Es: 10000 per scala 1:10000. Obbligatorio per dati vettoriali.', 'rndt-manager' ),
                'hide_for' => array( 'service' ),
            ),
            'spatial_resolution_distance' => array(
                'type' => 'number', 'label' => __( 'Distanza di risoluzione (metri)', 'rndt-manager' ),
                'step' => 0.01, 'help' => __( 'Obbligatorio per dati raster.', 'rndt-manager' ),
                'hide_for' => array( 'service' ),
            ),
            'conformity' => array(
                'type' => 'repeatable', 'label' => __( 'Dichiarazioni di conformità', 'rndt-manager' ),
                'required' => true,
                'help' => __( 'Dichiarazioni di conformità alle specifiche INSPIRE/nazionali.', 'rndt-manager' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        // Lineage obbligatorio per dataset/series
        if ( in_array( $resource_type, array( 'dataset', 'series' ), true ) ) {
            if ( $this->is_empty( $data['lineage_statement'] ?? null ) ) {
                $this->add_error( $errors, 'lineage_statement', __( 'La genealogia è obbligatoria per dataset e serie.', 'rndt-manager' ) );
            }
        }

        // Almeno una conformity obbligatoria
        if ( empty( $data['conformity'] ?? array() ) ) {
            $this->add_error( $errors, 'conformity', __( 'Almeno una dichiarazione di conformità è obbligatoria.', 'rndt-manager' ) );
        }

        return $errors;
    }
}
