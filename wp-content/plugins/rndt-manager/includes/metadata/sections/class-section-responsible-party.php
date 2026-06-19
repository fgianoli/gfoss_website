<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Responsible_Party extends RNDT_Section_Base {
    protected $id = 'responsible_party';
    protected $title;

    public function __construct() {
        $this->title = __( 'Parte responsabile', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'responsible_parties' => array(
                'type' => 'repeatable', 'label' => __( 'Parti responsabili', 'rndt-manager' ),
                'required' => true,
                'help' => __( 'Organizzazioni responsabili della risorsa. Obbligatorio almeno un contatto del metadato e un responsabile della risorsa.', 'rndt-manager' ),
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();
        $parties = $data['responsible_parties'] ?? array();

        if ( empty( $parties ) ) {
            $this->add_error( $errors, 'responsible_parties', __( 'Almeno una parte responsabile è obbligatoria.', 'rndt-manager' ) );
            return $errors;
        }

        // Verifica presenza metadata_contact
        $has_metadata_contact = false;
        $has_resource_poc = false;

        foreach ( $parties as $party ) {
            $context = $party['context'] ?? '';
            if ( 'metadata_contact' === $context ) $has_metadata_contact = true;
            if ( 'resource_poc' === $context ) $has_resource_poc = true;

            // Validazione email
            if ( ! empty( $party['email'] ) && ! is_email( $party['email'] ) ) {
                $this->add_error( $errors, 'responsible_parties', __( 'Email non valida.', 'rndt-manager' ) );
            }
        }

        if ( ! $has_metadata_contact ) {
            $this->add_error( $errors, 'responsible_parties', __( 'È obbligatorio un contatto del metadato.', 'rndt-manager' ) );
        }
        if ( ! $has_resource_poc ) {
            $this->add_error( $errors, 'responsible_parties', __( 'È obbligatorio un responsabile della risorsa.', 'rndt-manager' ) );
        }

        return $errors;
    }
}
