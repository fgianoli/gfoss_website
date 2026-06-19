<?php
/**
 * Facade/Factory database per RNDT Manager
 *
 * Singleton che delega al driver attivo (PostgreSQL o WordPress/MariaDB)
 * in base alla configurazione in rndt_settings['database']['type'].
 *
 * I consumer continuano a chiamare RNDT_Database::get_instance()->metodo()
 * senza alcuna modifica.
 *
 * @package RNDT_Manager
 * @since 1.0.0
 * @since 1.1.0 Refactoring in facade con supporto multi-driver
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Database
 */
class RNDT_Database implements RNDT_Database_Interface {

    /**
     * Istanza singleton
     *
     * @var RNDT_Database|null
     */
    private static $instance = null;

    /**
     * Driver attivo
     *
     * @var RNDT_Database_Interface
     */
    private $driver;

    /**
     * Tipo di driver ('postgresql' o 'wordpress')
     *
     * @var string
     */
    private $driver_type;

    /**
     * Ottieni l'istanza singleton
     *
     * @return RNDT_Database
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resetta l'istanza singleton.
     * Utile dopo cambio impostazioni DB per ricreare il driver.
     */
    public static function reset_instance() {
        self::$instance = null;
    }

    /**
     * Verifica se il driver attivo è WordPress
     *
     * @return bool
     */
    public static function is_wordpress_db() {
        $settings = get_option( 'rndt_settings', array() );
        $db_settings = isset( $settings['database'] ) ? $settings['database'] : array();
        return ( isset( $db_settings['type'] ) && 'wordpress' === $db_settings['type'] );
    }

    /**
     * Costruttore privato — crea il driver appropriato
     */
    private function __construct() {
        $settings = get_option( 'rndt_settings', array() );
        $db_settings = isset( $settings['database'] ) ? $settings['database'] : array();
        $this->driver_type = isset( $db_settings['type'] ) ? $db_settings['type'] : 'postgresql';

        if ( 'wordpress' === $this->driver_type ) {
            $this->driver = new RNDT_Database_WordPress();
        } else {
            $this->driver = new RNDT_Database_PostgreSQL( $settings );
        }
    }

    /**
     * Ottieni il tipo di driver attivo
     *
     * @return string 'postgresql' o 'wordpress'
     */
    public function get_driver_type() {
        return $this->driver_type;
    }

    /**
     * Ottieni il driver sottostante (per accesso diretto se necessario)
     *
     * @return RNDT_Database_Interface
     */
    public function get_driver() {
        return $this->driver;
    }

    // =========================================================================
    // Delegazione metodi RNDT_Database_Interface
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function get_connection() {
        return $this->driver->get_connection();
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection( $config = null ) {
        return $this->driver->test_connection( $config );
    }

    /**
     * {@inheritdoc}
     */
    public function get_last_error() {
        return $this->driver->get_last_error();
    }

    /**
     * {@inheritdoc}
     */
    public function get_schema() {
        return $this->driver->get_schema();
    }

    /**
     * {@inheritdoc}
     */
    public function query( $sql, $params = array() ) {
        return $this->driver->query( $sql, $params );
    }

    /**
     * {@inheritdoc}
     */
    public function insert( $table, $data ) {
        return $this->driver->insert( $table, $data );
    }

    /**
     * {@inheritdoc}
     */
    public function update( $table, $data, $where ) {
        return $this->driver->update( $table, $data, $where );
    }

    /**
     * {@inheritdoc}
     */
    public function delete( $table, $where ) {
        return $this->driver->delete( $table, $where );
    }

    /**
     * {@inheritdoc}
     */
    public function get_row( $sql, $params = array() ) {
        return $this->driver->get_row( $sql, $params );
    }

    /**
     * {@inheritdoc}
     */
    public function get_results( $sql, $params = array() ) {
        return $this->driver->get_results( $sql, $params );
    }

    /**
     * {@inheritdoc}
     */
    public function get_var( $sql, $params = array() ) {
        return $this->driver->get_var( $sql, $params );
    }

    /**
     * {@inheritdoc}
     */
    public function begin_transaction() {
        return $this->driver->begin_transaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit() {
        return $this->driver->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback() {
        return $this->driver->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function create_tables() {
        return $this->driver->create_tables();
    }

    /**
     * {@inheritdoc}
     */
    public function seed_lookup_tables() {
        return $this->driver->seed_lookup_tables();
    }

    /**
     * {@inheritdoc}
     */
    public function drop_tables() {
        return $this->driver->drop_tables();
    }
}
