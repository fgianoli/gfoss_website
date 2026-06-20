<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Ruoli associativi modellati sugli organi statutari (artt. 10, 15, 16, 17 dello Statuto).
 *
 *   gfoss_socio        — base: legge la sua quota, scarica la tessera, vede i documenti riservati
 *   gfoss_consigliere  — membro del Consiglio Direttivo: approva/rifiuta candidature
 *   gfoss_tesoriere    — accesso esclusivo a contabilità (capability gestita dal plugin gfoss-accounting)
 *   gfoss_presidente   — capability di consigliere + può convocare assemblee, gestire ruoli direttivo
 *   gfoss_revisore     — sola lettura su contabilità + bilanci
 *
 * I ruoli sono accumulabili sul singolo utente WP (un socio può essere anche consigliere e tesoriere).
 */
class Roles {

    public const CAP_VIEW_OWN_QUOTA       = 'gfoss_view_own_quota';
    public const CAP_DOWNLOAD_TESSERA     = 'gfoss_download_tessera';
    public const CAP_READ_PRIVATE_DOCS    = 'gfoss_read_private_docs';

    public const CAP_REVIEW_CANDIDATURE   = 'gfoss_review_candidature';
    public const CAP_MANAGE_SOCI          = 'gfoss_manage_soci';
    public const CAP_MANAGE_QUOTE         = 'gfoss_manage_quote';
    public const CAP_EXPORT_REGISTRO      = 'gfoss_export_registro';
    public const CAP_MANAGE_ASSEMBLEE     = 'gfoss_manage_assemblee';

    public const CAP_VIEW_ACCOUNTING      = 'gfoss_view_accounting';
    public const CAP_MANAGE_ACCOUNTING    = 'gfoss_manage_accounting';

    public static function register( bool $force = false ): void {
        $current_version = get_option( 'gfoss_members_roles_version' );
        if ( ! $force && $current_version === GFOSS_MEMBERS_VERSION ) { return; }

        $caps_socio       = self::caps_socio();
        $caps_consigliere = array_merge( $caps_socio, self::caps_consigliere() );
        $caps_presidente  = array_merge( $caps_consigliere, [ self::CAP_MANAGE_ASSEMBLEE => true ] );
        $caps_tesoriere   = array_merge( $caps_socio, self::caps_tesoriere() );
        $caps_revisore    = array_merge( $caps_socio, [ self::CAP_VIEW_ACCOUNTING => true ] );
        $caps_comunicazione = array_merge( $caps_socio, self::caps_comunicazione() );

        self::upsert_role( 'gfoss_socio',         __( 'Socio GFOSS', 'gfoss-members' ),   $caps_socio );
        self::upsert_role( 'gfoss_consigliere',   __( 'Consigliere',  'gfoss-members' ),   $caps_consigliere );
        self::upsert_role( 'gfoss_presidente',    __( 'Presidente',   'gfoss-members' ),   $caps_presidente );
        self::upsert_role( 'gfoss_tesoriere',     __( 'Tesoriere',    'gfoss-members' ),   $caps_tesoriere );
        self::upsert_role( 'gfoss_revisore',      __( 'Revisore',     'gfoss-members' ),   $caps_revisore );
        self::upsert_role( 'gfoss_comunicazione', __( 'Comunicazione', 'gfoss-members' ),  $caps_comunicazione );
        self::upsert_role( 'gfoss_archiviato',    __( 'Socio archiviato', 'gfoss-members' ), [ 'read' => true ] );

        // Administrator ottiene tutto.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( array_keys( array_merge( $caps_consigliere, $caps_tesoriere, $caps_presidente, $caps_comunicazione ) ) as $cap ) {
                $admin->add_cap( $cap );
            }
        }

        update_option( 'gfoss_members_roles_version', GFOSS_MEMBERS_VERSION, false );
    }

    private static function caps_socio(): array {
        return array_merge( [
            'read'                          => true,
            self::CAP_VIEW_OWN_QUOTA        => true,
            self::CAP_DOWNLOAD_TESSERA      => true,
            self::CAP_READ_PRIVATE_DOCS     => true,
        ], self::caps_rndt() );
    }

    /**
     * Capability del plugin RNDT Manager concesse ai soci: possono creare e
     * gestire i PROPRI metadati RNDT (non quelli altrui) e generare l'XML.
     * Restano agli amministratori la pubblicazione su CSW e le impostazioni.
     * (Sono semplici stringhe: innocue se il plugin RNDT non è attivo.)
     */
    private static function caps_rndt(): array {
        return [
            'manage_rndt_metadata'                 => true,
            'edit_rndt_metadata'                   => true,
            'read_rndt_metadata'                   => true,
            'delete_rndt_metadata'                 => true,
            'edit_rndt_metadata_items'             => true,
            'publish_rndt_metadata_items'          => true,
            'read_private_rndt_metadata_items'     => true,
            'delete_rndt_metadata_items'           => true,
            'edit_published_rndt_metadata_items'   => true,
            'delete_published_rndt_metadata_items' => true,
            'edit_private_rndt_metadata_items'     => true,
            'delete_private_rndt_metadata_items'   => true,
            'upload_files'                         => true,
        ];
    }

    private static function caps_consigliere(): array {
        return [
            self::CAP_REVIEW_CANDIDATURE    => true,
            self::CAP_MANAGE_SOCI           => true,
            self::CAP_EXPORT_REGISTRO       => true,
            self::CAP_VIEW_ACCOUNTING       => true,
        ];
    }

    private static function caps_tesoriere(): array {
        return [
            self::CAP_MANAGE_QUOTE          => true,
            self::CAP_VIEW_ACCOUNTING       => true,
            self::CAP_MANAGE_ACCOUNTING     => true,
            self::CAP_EXPORT_REGISTRO       => true,
        ];
    }

    /**
     * Comunicazione: socio che può creare e pubblicare le News del sito
     * (capability sui post WordPress = News). Niente accesso a soci/contabilità.
     */
    private static function caps_comunicazione(): array {
        return [
            'upload_files'           => true,
            'edit_posts'             => true,
            'edit_others_posts'      => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_published_posts' => true,
            'manage_categories'      => true,
        ];
    }

    private static function upsert_role( string $slug, string $label, array $caps ): void {
        $existing = get_role( $slug );
        if ( $existing ) {
            // Refresh caps without nuking the role (preserve user assignments).
            foreach ( $caps as $cap => $grant ) {
                $existing->add_cap( $cap, (bool) $grant );
            }
            return;
        }
        add_role( $slug, $label, $caps );
    }
}
