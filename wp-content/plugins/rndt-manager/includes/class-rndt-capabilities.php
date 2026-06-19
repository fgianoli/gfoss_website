<?php
/**
 * Gestione ruoli e capabilities personalizzate
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Capabilities
 */
class RNDT_Capabilities {

    /**
     * Capability principale per gestire i metadati RNDT
     */
    const CAP_MANAGE_RNDT = 'manage_rndt_metadata';

    /**
     * Capability per pubblicare su CSW
     */
    const CAP_PUBLISH_CSW = 'publish_rndt_csw';

    /**
     * Capability per modificare le impostazioni
     */
    const CAP_MANAGE_SETTINGS = 'manage_rndt_settings';

    /**
     * Aggiungi le capabilities ai ruoli
     */
    public function add_capabilities() {
        // Capabilities per il CPT rndt_metadata
        $capabilities = array(
            // Primitive capabilities
            'edit_rndt_metadata'              => true,
            'read_rndt_metadata'              => true,
            'delete_rndt_metadata'            => true,
            'edit_rndt_metadata_items'        => true,
            'edit_others_rndt_metadata_items' => true,
            'publish_rndt_metadata_items'     => true,
            'read_private_rndt_metadata_items'=> true,
            'delete_rndt_metadata_items'      => true,
            'delete_private_rndt_metadata_items' => true,
            'delete_published_rndt_metadata_items' => true,
            'delete_others_rndt_metadata_items' => true,
            'edit_private_rndt_metadata_items' => true,
            'edit_published_rndt_metadata_items' => true,

            // Custom capabilities
            self::CAP_MANAGE_RNDT     => true,
            self::CAP_PUBLISH_CSW     => true,
            self::CAP_MANAGE_SETTINGS => true,
        );

        // Aggiungi all'amministratore
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( $capabilities as $cap => $grant ) {
                $admin->add_cap( $cap, $grant );
            }
        }

        // Crea ruolo RNDT Editor con capabilities limitate
        $this->create_rndt_editor_role();
    }

    /**
     * Crea il ruolo RNDT Editor
     */
    private function create_rndt_editor_role() {
        // Rimuovi il ruolo esistente se presente (per aggiornamenti)
        remove_role( 'rndt_editor' );

        add_role(
            'rndt_editor',
            __( 'Editor RNDT', 'rndt-manager' ),
            array(
                // WordPress base
                'read'                            => true,
                'upload_files'                    => true,

                // RNDT metadata capabilities
                'edit_rndt_metadata'              => true,
                'read_rndt_metadata'              => true,
                'delete_rndt_metadata'            => true,
                'edit_rndt_metadata_items'        => true,
                'edit_others_rndt_metadata_items' => false, // Non puo modificare quelli altrui
                'publish_rndt_metadata_items'     => true,
                'read_private_rndt_metadata_items'=> true,
                'delete_rndt_metadata_items'      => true,
                'delete_private_rndt_metadata_items' => true,
                'delete_published_rndt_metadata_items' => true,
                'delete_others_rndt_metadata_items' => false, // Non puo eliminare quelli altrui
                'edit_private_rndt_metadata_items' => true,
                'edit_published_rndt_metadata_items' => true,

                // Custom
                self::CAP_MANAGE_RNDT     => true,
                self::CAP_PUBLISH_CSW     => false, // Non puo pubblicare su CSW
                self::CAP_MANAGE_SETTINGS => false, // Non puo modificare le impostazioni
            )
        );
    }

    /**
     * Rimuovi le capabilities (per disinstallazione)
     */
    public static function remove_capabilities() {
        $capabilities = array(
            'edit_rndt_metadata',
            'read_rndt_metadata',
            'delete_rndt_metadata',
            'edit_rndt_metadata_items',
            'edit_others_rndt_metadata_items',
            'publish_rndt_metadata_items',
            'read_private_rndt_metadata_items',
            'delete_rndt_metadata_items',
            'delete_private_rndt_metadata_items',
            'delete_published_rndt_metadata_items',
            'delete_others_rndt_metadata_items',
            'edit_private_rndt_metadata_items',
            'edit_published_rndt_metadata_items',
            self::CAP_MANAGE_RNDT,
            self::CAP_PUBLISH_CSW,
            self::CAP_MANAGE_SETTINGS,
        );

        // Rimuovi dall'amministratore
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( $capabilities as $cap ) {
                $admin->remove_cap( $cap );
            }
        }

        // Rimuovi il ruolo custom
        remove_role( 'rndt_editor' );
    }

    /**
     * Verifica se l'utente corrente puo gestire i metadati RNDT
     *
     * @return bool
     */
    public static function current_user_can_manage() {
        return current_user_can( self::CAP_MANAGE_RNDT );
    }

    /**
     * Verifica se l'utente corrente puo pubblicare su CSW
     *
     * @return bool
     */
    public static function current_user_can_publish_csw() {
        return current_user_can( self::CAP_PUBLISH_CSW );
    }

    /**
     * Verifica se l'utente corrente puo gestire le impostazioni
     *
     * @return bool
     */
    public static function current_user_can_manage_settings() {
        return current_user_can( self::CAP_MANAGE_SETTINGS );
    }
}
