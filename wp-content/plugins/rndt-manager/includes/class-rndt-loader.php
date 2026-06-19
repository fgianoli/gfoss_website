<?php
/**
 * Loader per la registrazione di actions e filters
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Loader
 */
class RNDT_Loader {

    /**
     * Array di actions da registrare
     *
     * @var array
     */
    protected $actions = array();

    /**
     * Array di filters da registrare
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Aggiungi una action
     *
     * @param string $hook          Hook WordPress
     * @param object $component     Oggetto che contiene il callback
     * @param string $callback      Nome del metodo callback
     * @param int    $priority      Priorita (default 10)
     * @param int    $accepted_args Numero di argomenti accettati (default 1)
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Aggiungi un filter
     *
     * @param string $hook          Hook WordPress
     * @param object $component     Oggetto che contiene il callback
     * @param string $callback      Nome del metodo callback
     * @param int    $priority      Priorita (default 10)
     * @param int    $accepted_args Numero di argomenti accettati (default 1)
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Metodo helper per aggiungere hooks
     *
     * @param array  $hooks         Array di hooks esistente
     * @param string $hook          Hook WordPress
     * @param object $component     Oggetto che contiene il callback
     * @param string $callback      Nome del metodo callback
     * @param int    $priority      Priorita
     * @param int    $accepted_args Numero di argomenti accettati
     * @return array
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
        return $hooks;
    }

    /**
     * Registra tutti gli hooks con WordPress
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
