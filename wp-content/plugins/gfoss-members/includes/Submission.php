<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handler POST del form di candidatura. Hooked su admin-post.
 */
class Submission {

    public const ACTION = 'gfoss_iscrizione_submit';

    public static function init(): void {
        add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'handle' ] );
        add_action( 'admin_post_' . self::ACTION,        [ __CLASS__, 'handle' ] );
    }

    public static function handle(): void {
        $back_url = wp_get_referer() ?: gfoss_members_iscrizione_url();

        // -- 1. Hardening checks ---------------------------------------------
        if ( ! isset( $_POST[ Form::NONCE_FIELD ] )
            || ! wp_verify_nonce( $_POST[ Form::NONCE_FIELD ], Form::NONCE_ACTION ) ) {
            self::fail( $back_url, [ 'Sessione scaduta, ricarica la pagina e riprova.' ] );
        }

        // honeypot
        if ( ! empty( $_POST['website'] ) ) {
            self::fail( $back_url, [ 'Errore di validazione.' ] ); // silenzioso per i bot
        }

        // time-trap
        $t = (int) ( $_POST['_t'] ?? 0 );
        if ( $t === 0 || ( time() - $t ) < 4 ) {
            self::fail( $back_url, [ 'Compila il form più lentamente.' ] );
        }

        // rate-limit IP
        $ip = Candidatura::client_ip() ?? 'noip';
        $key = 'gfoss_rl_' . md5( $ip );
        $hits = (int) get_transient( $key );
        if ( $hits >= 3 ) {
            self::fail( $back_url, [ 'Troppi tentativi dal tuo indirizzo. Riprova più tardi.' ] );
        }
        set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

        // -- 2. Validazione --------------------------------------------------
        [ $clean, $errors ] = Validator::check( $_POST, [
            'nome'             => [ 'label' => 'Nome',             'required' => true,  'max' => 120 ],
            'cognome'          => [ 'label' => 'Cognome',          'required' => true,  'max' => 120 ],
            'email'            => [ 'label' => 'Email',            'required' => true,  'type' => 'email' ],
            'codice_fiscale'   => [ 'label' => 'Codice fiscale',   'required' => true,  'type' => 'codice_fiscale' ],
            'data_nascita'     => [ 'label' => 'Data di nascita',  'required' => false, 'type' => 'date' ],
            'comune_nascita'   => [ 'label' => 'Comune di nascita','required' => false, 'max' => 120 ],
            'indirizzo'        => [ 'label' => 'Indirizzo',        'required' => true,  'max' => 255 ],
            'cap'              => [ 'label' => 'CAP',              'required' => true,  'type' => 'cap' ],
            'citta'            => [ 'label' => 'Città',            'required' => true,  'max' => 120 ],
            'provincia'        => [ 'label' => 'Provincia',        'required' => true,  'type' => 'provincia' ],
            'telefono'         => [ 'label' => 'Telefono',         'required' => true,  'type' => 'phone' ],
            'professione'      => [ 'label' => 'Professione',      'required' => true,  'max' => 120 ],
            'competenze'       => [ 'label' => 'Competenze',       'required' => false, 'type' => 'textarea', 'max' => 2000 ],
            'motivazione'      => [ 'label' => 'Motivazione',      'required' => false, 'type' => 'textarea', 'max' => 2000 ],
            'consenso_statuto' => [ 'label' => "l'accettazione dello Statuto",   'accepted' => true ],
            'consenso_privacy' => [ 'label' => 'la privacy policy',              'accepted' => true ],
        ] );

        $clean['volontario'] = ! empty( $_POST['volontario'] ) ? 1 : 0;

        // Email + CF già usati da un socio attivo o da una candidatura in corso?
        if ( ! $errors && ! empty( $clean['email'] ) ) {
            if ( get_user_by( 'email', $clean['email'] ) ) {
                $errors['email'] = "Esiste già un account associato a questa email. Se sei un socio, accedi all'area riservata.";
            } elseif ( Candidatura::find_by_email_pending( $clean['email'] ) ) {
                $errors['email'] = 'Esiste già una domanda in corso con questa email. Controlla la tua casella.';
            }
        }

        if ( $errors ) {
            Form::flash_set( [ 'errors' => $errors, 'values' => $clean ] );
            wp_safe_redirect( $back_url );
            exit;
        }

        // -- 3. Persistenza --------------------------------------------------
        $cand_id = Candidatura::create( $clean );
        $cand    = Candidatura::get( $cand_id );

        // -- 4. Email -------------------------------------------------------
        Email::candidatura_received( $cand );
        Email::cd_new_candidatura( $cand );

        // -- 5. Success page ------------------------------------------------
        Form::flash_set( [ 'success' => [ 'cand' => $cand ] ] );
        wp_safe_redirect( $back_url );
        exit;
    }

    private static function fail( string $back_url, array $messages ): never {
        Form::flash_set( [ 'errors' => $messages, 'values' => $_POST ] );
        wp_safe_redirect( $back_url );
        exit;
    }
}
