<?php
/**
 * Sezione Identificazione
 *
 * @package RNDT_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-section-base.php';

/**
 * Classe RNDT_Section_Identification
 */
class RNDT_Section_Identification extends RNDT_Section_Base {

    protected $id    = 'identification';
    protected $title;

    public function __construct() {
        $this->title = __( 'Identificazione', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'title' => array(
                'type'     => 'text',
                'label'    => __( 'Titolo', 'rndt-manager' ),
                'required' => true,
                'help'     => __( 'Nome caratteristico con il quale la risorsa è conosciuta.', 'rndt-manager' ),
            ),
            'abstract' => array(
                'type'     => 'textarea',
                'label'    => __( 'Descrizione', 'rndt-manager' ),
                'required' => true,
                'rows'     => 5,
                'help'     => __( 'Breve descrizione del contenuto della risorsa.', 'rndt-manager' ),
            ),
            'resource_identifier' => array(
                'type'     => 'text',
                'label'    => __( 'Identificativo della risorsa', 'rndt-manager' ),
                'required' => array( 'dataset', 'series', 'application' ),
                'help'     => __( 'Riferimento univoco che identifica la risorsa nel formato codiceIPA:id.', 'rndt-manager' ),
            ),
            'resource_identifier_codespace' => array(
                'type'  => 'text',
                'label' => __( 'Namespace identificativo', 'rndt-manager' ),
            ),
            'parent_identifier' => array(
                'type'      => 'text',
                'label'     => __( 'Identificativo risorsa padre', 'rndt-manager' ),
                'help'      => __( 'Per dataset appartenenti a una serie, UUID del metadato della serie.', 'rndt-manager' ),
                'show_for'  => array( 'dataset' ),
            ),
            'resource_language' => array(
                'type'     => 'select',
                'label'    => __( 'Lingua della risorsa', 'rndt-manager' ),
                'required' => array( 'dataset', 'series', 'application' ),
                'options'  => 'languages',
                'default'  => 'ita',
            ),
            'character_set' => array(
                'type'    => 'select',
                'label'   => __( 'Set di caratteri', 'rndt-manager' ),
                'options' => 'charsets',
                'default' => 'utf8',
            ),
            'spatial_representation_type' => array(
                'type'     => 'select',
                'label'    => __( 'Tipo di rappresentazione spaziale', 'rndt-manager' ),
                'options'  => array(
                    ''         => __( '-- Seleziona --', 'rndt-manager' ),
                    'vector'   => __( 'Vettoriale', 'rndt-manager' ),
                    'grid'     => __( 'Raster', 'rndt-manager' ),
                    'textTable' => __( 'Tabella testuale', 'rndt-manager' ),
                    'tin'      => __( 'TIN', 'rndt-manager' ),
                    'stereoModel' => __( 'Modello stereo', 'rndt-manager' ),
                    'video'    => __( 'Video', 'rndt-manager' ),
                ),
                'hide_for' => array( 'service' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        // Titolo obbligatorio
        if ( $this->is_empty( $data['title'] ?? null ) ) {
            $this->add_error( $errors, 'title', __( 'Il titolo è obbligatorio.', 'rndt-manager' ) );
        }

        // Abstract obbligatorio
        if ( $this->is_empty( $data['abstract'] ?? null ) ) {
            $this->add_error( $errors, 'abstract', __( 'La descrizione è obbligatoria.', 'rndt-manager' ) );
        }

        // Identificativo risorsa obbligatorio per dataset/series/application
        if ( in_array( $resource_type, array( 'dataset', 'series', 'application' ), true ) ) {
            if ( $this->is_empty( $data['resource_identifier'] ?? null ) ) {
                $this->add_error( $errors, 'resource_identifier', __( 'L\'identificativo della risorsa è obbligatorio.', 'rndt-manager' ) );
            }
        }

        // Lingua obbligatoria per dataset/series/application
        if ( in_array( $resource_type, array( 'dataset', 'series', 'application' ), true ) ) {
            if ( $this->is_empty( $data['resource_language'] ?? null ) ) {
                $this->add_error( $errors, 'resource_language', __( 'La lingua della risorsa è obbligatoria.', 'rndt-manager' ) );
            }
        }

        return $errors;
    }
}
