<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

$users = get_users( [ 'role__in' => [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore' ], 'orderby' => 'display_name' ] );
$year  = (int) gmdate( 'Y' );
?>
<div class="wrap">
    <h1>
        <?php esc_html_e( 'Soci', 'gfoss-members' ); ?>
        <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Aggiungi socio', 'gfoss-members' ); ?></a>
    </h1>
    <table class="widefat striped">
        <thead>
        <tr>
            <th>#</th>
            <th><?php esc_html_e( 'Nome', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Email', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Ruolo', 'gfoss-members' ); ?></th>
            <th><?php printf( esc_html__( 'Quota %d', 'gfoss-members' ), $year ); ?></th>
            <th><?php esc_html_e( 'Volontario', 'gfoss-members' ); ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ( $users as $u ) :
            $status = Quote::status_for( $u->ID, $year );
            $chip = match ( $status ) {
                'paid'     => [ 'IN REGOLA', '#5DA34D', '#E5F2DF' ],
                'expiring' => [ 'IN SCADENZA', '#B26A00', '#FFEFD6' ],
                'pending'  => [ 'DA RINNOVARE', '#B26A00', '#FFEFD6' ],
                'expired'  => [ 'SCADUTA', '#C0392B', '#FCE3DF' ],
                default    => [ 'N.D.', '#4A5C6A', '#E2E8EC' ],
            };
            ?>
            <tr>
                <td><?php echo esc_html( (string) get_user_meta( $u->ID, 'gf_numero_socio', true ) ); ?></td>
                <td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
                <td><a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a></td>
                <td><?php echo esc_html( implode( ', ', $u->roles ) ); ?></td>
                <td><span style="display:inline-block;padding:.15rem .55rem;border-radius:999px;background:<?php echo esc_attr( $chip[2] ); ?>;color:<?php echo esc_attr( $chip[1] ); ?>;font-weight:600;font-size:.75rem"><?php echo esc_html( $chip[0] ); ?></span></td>
                <td><?php echo get_user_meta( $u->ID, 'gf_volontario', true ) === '1' ? '✓' : ''; ?></td>
                <td><a class="button button-small" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $u->ID ) ); ?>"><?php esc_html_e( 'Apri', 'gfoss-members' ); ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
