<?php
/**
 * Classe base per le sezioni del wizard
 * @package RNDT_Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

abstract class RNDT_Section_Base {
    protected $id;
    protected $title;
    protected $fields = array();

    public function get_id() { return $this->id; }
    public function get_title() { return $this->title; }
    public function get_fields() { return $this->fields; }

    abstract public function get_fields_config();
    abstract public function validate( $data, $resource_type );

    protected function is_empty( $value ) {
        return null === $value || '' === $value || ( is_array( $value ) && empty( $value ) );
    }

    protected function add_error( &$errors, $field, $message ) {
        if ( ! isset( $errors[ $field ] ) ) {
            $errors[ $field ] = array();
        }
        $errors[ $field ][] = $message;
    }
}
