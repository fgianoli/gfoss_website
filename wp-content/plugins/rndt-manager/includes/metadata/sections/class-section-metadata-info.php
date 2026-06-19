<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-section-base.php';

class RNDT_Section_Metadata_Info extends RNDT_Section_Base {
    protected $id = 'metadata_info';
    protected $title;

    public function __construct() {
        $this->title = __( 'Info metadato', 'rndt-manager' );
    }

    public function get_fields_config() {
        return array(
            'file_identifier' => array(
                'type' => 'text', 'label' => __( 'Identificativo del metadato', 'rndt-manager' ),
                'readonly' => true, 'help' => __( 'UUID generato automaticamente.', 'rndt-manager' ),
            ),
            'metadata_language' => array(
                'type' => 'select', 'label' => __( 'Lingua del metadato', 'rndt-manager' ),
                'required' => true, 'options' => 'languages', 'default' => 'ita',
            ),
            'metadata_character_set' => array(
                'type' => 'select', 'label' => __( 'Set di caratteri', 'rndt-manager' ),
                'options' => 'charsets', 'default' => 'utf8',
            ),
            'metadata_date' => array(
                'type' => 'datetime', 'label' => __( 'Data del metadato', 'rndt-manager' ),
                'required' => true, 'help' => __( 'Data di creazione/aggiornamento del metadato.', 'rndt-manager' ),
            ),
            'metadata_standard_name' => array(
                'type' => 'text', 'label' => __( 'Nome standard', 'rndt-manager' ),
                'default' => 'DM - Regole tecniche RNDT', 'readonly' => true,
            ),
            'metadata_standard_version' => array(
                'type' => 'text', 'label' => __( 'Versione standard', 'rndt-manager' ),
                'default' => '10 novembre 2011', 'readonly' => true,
            ),
        );
    }

    public function validate( $data, $resource_type ) {
        $errors = array();

        if ( $this->is_empty( $data['metadata_language'] ?? null ) ) {
            $this->add_error( $errors, 'metadata_language', __( 'La lingua del metadato è obbligatoria.', 'rndt-manager' ) );
        }

        return $errors;
    }
}
