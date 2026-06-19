<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Classification extends RNDT_Section_Base {
    protected $id = 'classification';
    protected $title;

    public function __construct() {
        $this->title = __( 'Classificazione', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'topic_categories' => array(
                'type'     => 'multiselect',
                'label'    => __( 'Categoria ISO', 'rndt-manager' ),
                'required' => array( 'dataset', 'series', 'application' ),
                'options'  => 'topic_categories',
                'help'     => __( 'Seleziona una o più categorie ISO 19115.', 'rndt-manager' ),
            ),
            'inspire_themes' => array(
                'type'     => 'multiselect',
                'label'    => __( 'Tema INSPIRE', 'rndt-manager' ),
                'required' => true,
                'options'  => 'inspire_themes',
                'help'     => __( 'Seleziona uno o più temi INSPIRE (GEMET).', 'rndt-manager' ),
            ),
            'keywords' => array(
                'type'  => 'repeatable',
                'label' => __( 'Parole chiave aggiuntive', 'rndt-manager' ),
                'help'  => __( 'Parole chiave libere o da altri thesaurus.', 'rndt-manager' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        // Topic categories obbligatorie per dataset/series/application
        if ( in_array( $resource_type, array( 'dataset', 'series', 'application' ), true ) ) {
            if ( empty( $data['topic_categories'] ?? array() ) ) {
                $this->add_error( $errors, 'topic_categories', __( 'Seleziona almeno una categoria ISO.', 'rndt-manager' ) );
            }
        }

        // Almeno un tema INSPIRE obbligatorio
        if ( empty( $data['inspire_themes'] ?? array() ) ) {
            $this->add_error( $errors, 'inspire_themes', __( 'Seleziona almeno un tema INSPIRE.', 'rndt-manager' ) );
        }

        return $errors;
    }
}
