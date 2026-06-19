<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Distribution extends RNDT_Section_Base {
    protected $id = 'distribution';
    protected $title;

    public function __construct() {
        $this->title = __( 'Distribuzione', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'distribution_formats' => array(
                'type' => 'repeatable', 'label' => __( 'Formati di distribuzione', 'rndt-manager' ),
                'required' => array( 'dataset' ),
                'help' => __( 'Formato in cui i dati sono disponibili (es. GML, Shapefile, GeoTIFF).', 'rndt-manager' ),
            ),
            'online_resources' => array(
                'type' => 'repeatable', 'label' => __( 'Risorse online', 'rndt-manager' ),
                'required' => array( 'service', 'application' ),
                'help' => __( 'URL per accedere alla risorsa (es. endpoint WMS/WFS, pagina download).', 'rndt-manager' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        // Formato obbligatorio per dataset
        if ( 'dataset' === $resource_type && empty( $data['distribution_formats'] ?? array() ) ) {
            $this->add_error( $errors, 'distribution_formats', __( 'Almeno un formato di distribuzione è obbligatorio per i dataset.', 'rndt-manager' ) );
        }

        // Online resource obbligatorio per servizi e applicazioni
        if ( in_array( $resource_type, array( 'service', 'application' ), true ) ) {
            if ( empty( $data['online_resources'] ?? array() ) ) {
                $this->add_error( $errors, 'online_resources', __( 'Almeno una risorsa online è obbligatoria.', 'rndt-manager' ) );
            }
        }

        // Valida URL nelle online resources
        foreach ( ( $data['online_resources'] ?? array() ) as $i => $res ) {
            if ( ! empty( $res['linkage_url'] ) && ! filter_var( $res['linkage_url'], FILTER_VALIDATE_URL ) ) {
                $this->add_error( $errors, 'online_resources', sprintf( __( 'URL non valido nella risorsa %d.', 'rndt-manager' ), $i + 1 ) );
            }
        }

        return $errors;
    }
}
