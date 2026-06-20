<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Vista dettaglio se ?id presente.
if ( ! empty( $_GET['id'] ) ) {
    require GFOSS_MEMBERS_DIR . 'admin/views/socio.php';
    return;
}

// Azione in blocco: archivia tutti i decaduti.
if ( ! empty( $_POST['_action'] ) && $_POST['_action'] === 'archive_lapsed' && current_user_can( Roles::CAP_MANAGE_SOCI ) ) {
    check_admin_referer( 'gfoss_soci_bulk' );
    $n = 0;
    foreach ( Archivio::lapsed_members() as $u ) { Archivio::archive( (int) $u->ID ); $n++; }
    wp_safe_redirect( add_query_arg( [ 'stato' => 'archiviati', 'msg' => 'bulk_archived', 'n' => $n ], admin_url( 'admin.php?page=gfoss-soci' ) ) );
    exit;
}

$stato = isset( $_GET['stato'] ) ? sanitize_key( (string) $_GET['stato'] ) : 'attivi';
$msg   = sanitize_key( (string) ( $_GET['msg'] ?? '' ) );
$year  = (int) gmdate( 'Y' );

$active_roles = [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore', 'gfoss_comunicazione' ];

if ( $stato === 'archiviati' ) {
    $users = Archivio::archived_members();
} elseif ( $stato === 'decaduti' ) {
    $users = Archivio::lapsed_members();
} else {
    $stato = 'attivi';
    $users = get_users( [ 'role__in' => $active_roles, 'orderby' => 'display_name' ] );
}

$counts = [
    'attivi'     => count( get_users( [ 'role__in' => $active_roles, 'fields' => 'ID' ] ) ),
    'decaduti'   => count( Archivio::lapsed_members() ),
    'archiviati' => count( Archivio::archived_members() ),
];
?>
<div class="wrap">
    <h1>
        <?php esc_html_e( 'Soci', 'gfoss-members' ); ?>
        <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Aggiungi socio', 'gfoss-members' ); ?></a>
    </h1>

    <?php if ( $msg === 'deleted' ) : ?><div class="notice notice-success is-dismissible"><p>Socio e relativi dati eliminati definitivamente.</p></div><?php endif; ?>
    <?php if ( $msg === 'bulk_archived' ) : ?><div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Archiviati %d soci decaduti.', 'gfoss-members' ), (int) ( $_GET['n'] ?? 0 ) ); ?></p></div><?php endif; ?>

    <ul class="subsubsub">
        <?php
        $tabs = [ 'attivi' => 'Attivi', 'decaduti' => 'Decaduti', 'archiviati' => 'Archiviati' ];
        $i = 0;
        foreach ( $tabs as $key => $label ) :
            $url = admin_url( 'admin.php?page=gfoss-soci&stato=' . $key );
            $cls = $stato === $key ? ' class="current"' : '';
            $sep = ++$i < count( $tabs ) ? ' |' : '';
            ?>
            <li><a href="<?php echo esc_url( $url ); ?>"<?php echo $cls; ?>><?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) $counts[ $key ]; ?>)</span></a><?php echo $sep; ?> </li>
        <?php endforeach; ?>
    </ul>

    <?php if ( $stato === 'decaduti' && $users ) : ?>
        <form method="post" style="margin:1rem 0">
            <?php wp_nonce_field( 'gfoss_soci_bulk' ); ?>
            <input type="hidden" name="_action" value="archive_lapsed">
            <button type="submit" class="button" onclick="return confirm('Archiviare tutti i <?php echo (int) count( $users ); ?> soci decaduti?')">Archivia tutti i decaduti</button>
            <span class="description">Mancato rinnovo oltre fine marzo dell'anno successivo all'ultima quota.</span>
        </form>
    <?php endif; ?>

    <table class="widefat striped" style="margin-top:.5rem">
        <thead>
        <tr>
            <th>#</th>
            <th><?php esc_html_e( 'Nome', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Email', 'gfoss-members' ); ?></th>
            <?php if ( $stato === 'archiviati' ) : ?>
                <th><?php esc_html_e( 'Archiviato il', 'gfoss-members' ); ?></th>
            <?php else : ?>
                <th><?php esc_html_e( 'Ruolo', 'gfoss-members' ); ?></th>
                <th><?php printf( esc_html__( 'Quota %d', 'gfoss-members' ), $year ); ?></th>
            <?php endif; ?>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( ! $users ) : ?>
            <tr><td colspan="6"><?php esc_html_e( 'Nessun socio in questa vista.', 'gfoss-members' ); ?></td></tr>
        <?php else : foreach ( $users as $u ) :
            $detail = admin_url( 'admin.php?page=gfoss-soci&id=' . $u->ID );
            ?>
            <tr>
                <td><?php echo esc_html( (string) get_user_meta( $u->ID, 'gf_numero_socio', true ) ); ?></td>
                <td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
                <td><a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a></td>
                <?php if ( $stato === 'archiviati' ) : ?>
                    <td><?php echo esc_html( (string) get_user_meta( $u->ID, Archivio::META_DATE, true ) ); ?></td>
                <?php else :
                    $status = Quote::status_for( $u->ID, $year );
                    $chip = match ( $status ) {
                        'paid'     => [ 'IN REGOLA', '#5DA34D', '#E5F2DF' ],
                        'expiring' => [ 'IN SCADENZA', '#B26A00', '#FFEFD6' ],
                        'pending'  => [ 'DA RINNOVARE', '#B26A00', '#FFEFD6' ],
                        'expired'  => [ 'SCADUTA', '#C0392B', '#FCE3DF' ],
                        default    => [ 'N.D.', '#4A5C6A', '#E2E8EC' ],
                    };
                    ?>
                    <td><?php echo esc_html( implode( ', ', $u->roles ) ); ?></td>
                    <td><span style="display:inline-block;padding:.15rem .55rem;border-radius:999px;background:<?php echo esc_attr( $chip[2] ); ?>;color:<?php echo esc_attr( $chip[1] ); ?>;font-weight:600;font-size:.75rem"><?php echo esc_html( $chip[0] ); ?></span></td>
                <?php endif; ?>
                <td><a class="button button-small" href="<?php echo esc_url( $detail ); ?>"><?php esc_html_e( 'Apri scheda', 'gfoss-members' ); ?></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
