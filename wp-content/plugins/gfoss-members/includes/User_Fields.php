<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Campi anagrafici e associativi sulle user di WordPress (~80 soci, niente CPT).
 * Mappa 1:1 con il modello iscrizione PDF.
 */
class User_Fields {

    public const META = [
        'gf_codice_fiscale'   => 'Codice fiscale',
        'gf_data_nascita'     => 'Data di nascita (YYYY-MM-DD)',
        'gf_comune_nascita'   => 'Comune di nascita',
        'gf_indirizzo'        => 'Indirizzo',
        'gf_cap'              => 'CAP',
        'gf_citta'            => 'Città',
        'gf_provincia'        => 'Provincia',
        'gf_telefono'         => 'Telefono',
        'gf_professione'      => 'Professione',
        'gf_competenze'       => 'Aree di competenza',
        'gf_volontario'       => 'Iscritto registro volontari (0/1)',
        'gf_data_ammissione'  => 'Data ammissione (YYYY-MM-DD)',
        'gf_numero_socio'     => 'Numero socio',
        'gf_note_interne'     => 'Note interne (solo CD)',
    ];

    public static function init(): void {
        add_action( 'show_user_profile',   [ __CLASS__, 'render' ] );
        add_action( 'edit_user_profile',   [ __CLASS__, 'render' ] );
        add_action( 'personal_options_update',  [ __CLASS__, 'save' ] );
        add_action( 'edit_user_profile_update', [ __CLASS__, 'save' ] );
    }

    public static function render( \WP_User $user ): void {
        $can_edit_internal = current_user_can( Roles::CAP_MANAGE_SOCI );
        ?>
        <h2><?php esc_html_e( 'Dati associativi GFOSS', 'gfoss-members' ); ?></h2>
        <table class="form-table" role="presentation">
            <?php foreach ( self::META as $key => $label ) :
                $is_internal = in_array( $key, [ 'gf_data_ammissione', 'gf_numero_socio', 'gf_note_interne' ], true );
                if ( $is_internal && ! $can_edit_internal ) { continue; }
                $value = (string) get_user_meta( $user->ID, $key, true );
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <?php if ( $key === 'gf_competenze' || $key === 'gf_note_interne' ) : ?>
                            <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
                                      rows="3" class="regular-text"><?php echo esc_textarea( $value ); ?></textarea>
                        <?php elseif ( $key === 'gf_volontario' ) : ?>
                            <label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $value, '1' ); ?>>
                                <?php esc_html_e( 'Sì, iscritto registro volontari (art. 18 Statuto)', 'gfoss-members' ); ?></label>
                        <?php else : ?>
                            <input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
                                   value="<?php echo esc_attr( $value ); ?>" class="regular-text">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public static function save( int $user_id ): void {
        if ( ! current_user_can( 'edit_user', $user_id ) ) { return; }
        $can_edit_internal = current_user_can( Roles::CAP_MANAGE_SOCI );

        foreach ( self::META as $key => $_label ) {
            $is_internal = in_array( $key, [ 'gf_data_ammissione', 'gf_numero_socio', 'gf_note_interne' ], true );
            if ( $is_internal && ! $can_edit_internal ) { continue; }

            $raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

            if ( $key === 'gf_volontario' ) {
                update_user_meta( $user_id, $key, $raw ? '1' : '0' );
                continue;
            }
            if ( $key === 'gf_codice_fiscale' ) {
                $raw = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw ) );
                if ( $raw !== '' && ! preg_match( '/^[A-Z0-9]{16}$/', $raw ) ) {
                    add_action( 'user_profile_update_errors', function ( $errors ) {
                        $errors->add( 'gf_cf_invalid', __( 'Codice fiscale non valido (16 caratteri).', 'gfoss-members' ) );
                    } );
                    continue;
                }
            }

            update_user_meta( $user_id, $key, sanitize_text_field( $raw ) );
        }
    }
}
