<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Sincronizzazione automatica con MailPoet (newsletter).
 *
 * Quando un socio diventa effettivo viene aggiunto alla lista "Soci GFOSS.it".
 * Quando un utente viene rimosso o gli viene tolto il ruolo gfoss_socio, viene
 * cancellato dalla lista. Tutto graceful: se MailPoet non è installato non fa nulla.
 *
 * Configurazione: la lista viene creata automaticamente al primo trigger se non
 * esiste. Il nome è in option 'gfoss_newsletter_list_name'.
 */
class Newsletter {

    public static function init(): void {
        add_action( 'gfoss_members_candidatura_effective', [ __CLASS__, 'on_effective' ], 10, 2 );
        add_action( 'set_user_role',                       [ __CLASS__, 'on_role_change' ], 10, 3 );
        add_action( 'delete_user',                         [ __CLASS__, 'on_user_delete' ] );
    }

    private static function api(): ?object {
        if ( ! class_exists( '\\MailPoet\\API\\API' ) ) { return null; }
        try { return \MailPoet\API\API::MP( 'v1' ); }
        catch ( \Throwable $e ) { return null; }
    }

    private static function list_id(): ?int {
        $api = self::api(); if ( ! $api ) { return null; }
        $name = (string) get_option( 'gfoss_newsletter_list_name', 'Soci GFOSS.it' );

        try {
            foreach ( $api->getLists() as $list ) {
                if ( $list['name'] === $name ) { return (int) $list['id']; }
            }
            $created = $api->addList( [ 'name' => $name, 'description' => 'Soci attivi GFOSS.it APS' ] );
            return (int) $created['id'];
        } catch ( \Throwable $e ) {
            error_log( '[gfoss-newsletter] list lookup failed: ' . $e->getMessage() );
            return null;
        }
    }

    public static function on_effective( int $user_id, array $cand ): void {
        $api = self::api(); if ( ! $api ) { return; }
        $list_id = self::list_id(); if ( ! $list_id ) { return; }
        try {
            $api->addSubscriber( [
                'email'      => $cand['email'],
                'first_name' => $cand['nome'],
                'last_name'  => $cand['cognome'],
                'status'     => 'subscribed',
            ], [ $list_id ], [ 'send_confirmation_email' => false, 'schedule_welcome_email' => false ] );
        } catch ( \Throwable $e ) {
            error_log( '[gfoss-newsletter] add subscriber failed: ' . $e->getMessage() );
        }
    }

    public static function on_role_change( int $user_id, string $new_role, array $old_roles ): void {
        $had = in_array( 'gfoss_socio', $old_roles, true );
        $has = $new_role === 'gfoss_socio'
            || in_array( $new_role, [ 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore' ], true );
        if ( $had && ! $has ) {
            self::remove_user( $user_id );
        }
    }

    public static function on_user_delete( int $user_id ): void {
        self::remove_user( $user_id );
    }

    private static function remove_user( int $user_id ): void {
        $api = self::api(); if ( ! $api ) { return; }
        $u = get_userdata( $user_id ); if ( ! $u ) { return; }
        try {
            $sub = $api->getSubscriber( $u->user_email );
            if ( ! empty( $sub['id'] ) ) { $api->unsubscribe( $sub['id'] ); }
        } catch ( \Throwable $e ) { /* silenzioso */ }
    }
}
