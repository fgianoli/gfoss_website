<?php
/**
 * Template per la pagina editor metadati
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php
        if ( $metadata_id ) {
            esc_html_e( 'Modifica metadato', 'rndt-manager' );
        } else {
            esc_html_e( 'Nuovo metadato', 'rndt-manager' );
        }
        ?>
    </h1>

    <?php if ( $metadata_id ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rndt-manager-new' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Aggiungi nuovo', 'rndt-manager' ); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php
    // Mostra notifiche admin
    settings_errors( 'rndt_manager_messages' );

    // Verifica connessione database
    $db = RNDT_Database::get_instance();
    if ( ! $db->get_connection() ) :
    ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e( 'Attenzione:', 'rndt-manager' ); ?></strong>
                <?php esc_html_e( 'La connessione al database PostgreSQL non e configurata.', 'rndt-manager' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rndt-manager-settings' ) ); ?>">
                    <?php esc_html_e( 'Configura ora', 'rndt-manager' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>
        <!-- Mount point per il wizard React -->
        <div id="rndt-metadata-editor"
             data-metadata-id="<?php echo esc_attr( $metadata_id ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
             data-api-url="<?php echo esc_attr( rest_url( 'rndt/v1/' ) ); ?>">
            <div class="rndt-wizard-loading">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e( 'Caricamento editor...', 'rndt-manager' ); ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.rndt-wizard-loading {
    text-align: center;
    padding: 50px;
}
.rndt-wizard-loading .spinner {
    float: none;
    margin: 0 auto 10px;
}
</style>
