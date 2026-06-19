<?php
/**
 * Interfaccia database per il plugin RNDT Manager
 *
 * Definisce il contratto che tutti i driver database devono implementare.
 *
 * @package RNDT_Manager
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface RNDT_Database_Interface
 */
interface RNDT_Database_Interface {

    /**
     * Ottieni la connessione al database
     *
     * @return mixed PDO|wpdb|null
     */
    public function get_connection();

    /**
     * Testa la connessione al database
     *
     * @param array $config Configurazione opzionale
     * @return bool|string True se successo, messaggio errore altrimenti
     */
    public function test_connection( $config = null );

    /**
     * Ottieni l'ultimo errore
     *
     * @return string
     */
    public function get_last_error();

    /**
     * Ottieni il nome dello schema (PostgreSQL) o stringa vuota (WordPress)
     *
     * @return string
     */
    public function get_schema();

    /**
     * Esegui una query preparata
     *
     * @param string $sql    Query SQL con placeholder
     * @param array  $params Parametri per la query
     * @return mixed
     */
    public function query( $sql, $params = array() );

    /**
     * Inserisci un record
     *
     * @param string $table Nome tabella
     * @param array  $data  Dati da inserire (colonna => valore)
     * @return int|false    ID inserito o false
     */
    public function insert( $table, $data );

    /**
     * Aggiorna record
     *
     * @param string $table Nome tabella
     * @param array  $data  Dati da aggiornare
     * @param array  $where Condizioni WHERE
     * @return int|false    Righe modificate o false
     */
    public function update( $table, $data, $where );

    /**
     * Elimina record
     *
     * @param string $table Nome tabella
     * @param array  $where Condizioni WHERE
     * @return int|false    Righe eliminate o false
     */
    public function delete( $table, $where );

    /**
     * Ottieni un singolo record
     *
     * @param string $sql    Query SQL
     * @param array  $params Parametri
     * @return array|null
     */
    public function get_row( $sql, $params = array() );

    /**
     * Ottieni tutti i risultati
     *
     * @param string $sql    Query SQL
     * @param array  $params Parametri
     * @return array
     */
    public function get_results( $sql, $params = array() );

    /**
     * Ottieni un singolo valore
     *
     * @param string $sql    Query SQL
     * @param array  $params Parametri
     * @return mixed|null
     */
    public function get_var( $sql, $params = array() );

    /**
     * Inizia una transazione
     *
     * @return bool
     */
    public function begin_transaction();

    /**
     * Commit della transazione
     *
     * @return bool
     */
    public function commit();

    /**
     * Rollback della transazione
     *
     * @return bool
     */
    public function rollback();

    /**
     * Crea le tabelle del database
     *
     * @return bool
     */
    public function create_tables();

    /**
     * Popola le tabelle di lookup
     *
     * @return bool
     */
    public function seed_lookup_tables();

    /**
     * Elimina tutte le tabelle
     *
     * @return bool
     */
    public function drop_tables();
}
