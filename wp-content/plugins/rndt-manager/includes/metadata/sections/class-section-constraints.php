<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Constraints extends RNDT_Section_Base {
    protected $id = 'constraints';
    protected $title;

    public function __construct() {
        $this->title = __( 'Vincoli', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'use_limitation' => array(
                'type' => 'textarea', 'label' => __( 'Limitazioni d\'uso', 'rndt-manager' ), 'rows' => 3,
            ),
            'access_constraints' => array(
                'type' => 'select', 'label' => __( 'Vincoli di accesso', 'rndt-manager' ),
                'required' => true, 'options' => 'restriction_codes', 'default' => 'otherRestrictions',
            ),
            'use_constraints' => array(
                'type' => 'select', 'label' => __( 'Vincoli d\'uso', 'rndt-manager' ),
                'required' => true, 'options' => 'restriction_codes', 'default' => 'otherRestrictions',
            ),
            'other_constraints' => array(
                'type' => 'textarea', 'label' => __( 'Altri vincoli', 'rndt-manager' ), 'rows' => 3,
                'help' => __( 'Obbligatorio se vincoli di accesso o uso = "Altre restrizioni".', 'rndt-manager' ),
            ),
            'classification' => array(
                'type' => 'select', 'label' => __( 'Classificazione di sicurezza', 'rndt-manager' ),
                'options' => array(
                    '' => '-- Nessuna --', 'unclassified' => 'Non classificato',
                    'restricted' => 'Riservato', 'confidential' => 'Confidenziale',
                    'secret' => 'Segreto', 'topSecret' => 'Top Secret',
                ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        if ( $this->is_empty( $data['access_constraints'] ?? null ) ) {
            $this->add_error( $errors, 'access_constraints', __( 'I vincoli di accesso sono obbligatori.', 'rndt-manager' ) );
        }
        if ( $this->is_empty( $data['use_constraints'] ?? null ) ) {
            $this->add_error( $errors, 'use_constraints', __( 'I vincoli d\'uso sono obbligatori.', 'rndt-manager' ) );
        }

        // other_constraints obbligatorio se access o use = otherRestrictions
        $needs_other = ( ( $data['access_constraints'] ?? '' ) === 'otherRestrictions' ) ||
                       ( ( $data['use_constraints'] ?? '' ) === 'otherRestrictions' );
        if ( $needs_other && $this->is_empty( $data['other_constraints'] ?? null ) ) {
            $this->add_error( $errors, 'other_constraints', __( 'Specificare "Altri vincoli" quando si seleziona "Altre restrizioni".', 'rndt-manager' ) );
        }

        return $errors;
    }
}
