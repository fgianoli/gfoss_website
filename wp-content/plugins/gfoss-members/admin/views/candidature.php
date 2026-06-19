<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Detail view se ?id presente.
if ( ! empty( $_GET['id'] ) ) {
    require GFOSS_MEMBERS_DIR . 'admin/views/candidatura.php';
    return;
}

$filter = isset( $_GET['stato'] ) ? sanitize_key( (string) $_GET['stato'] ) : '';
$rows   = Candidatura::list_filtered( $filter ?: null );

$labels = [
    Candidatura::STATO_PENDING          => [ 'In attesa',          '#B26A00', '#FFEFD6' ],
    Candidatura::STATO_AWAITING_CD      => [ 'Pagata, attende CD', '#1A6FA0', '#E6F3FA' ],
    Candidatura::STATO_AWAITING_PAYMENT => [ 'Approvata, da pagare','#B26A00', '#FFEFD6' ],
    Candidatura::STATO_EFFECTIVE        => [ 'Effettiva',          '#5DA34D', '#E5F2DF' ],
    Candidatura::STATO_REJECTED         => [ 'Respinta',           '#C0392B', '#FCE3DF' ],
    Candidatura::STATO_WITHDRAWN        => [ 'Ritirata',           '#4A5C6A', '#E2E8EC' ],
];
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Candidature nuovi soci', 'gfoss-members' ); ?></h1>

    <div class="notice notice-info" style="margin:1rem 0">
        <p><strong><?php esc_html_e( 'ℹ️ Voto online non ancora disponibile.', 'gfoss-members' ); ?></strong>
        <?php esc_html_e( 'Il sistema di votazione online è una funzione futura: al momento elezioni e votazioni si svolgono in assemblea secondo lo Statuto (artt. 11–14). Questa pagina gestisce solo le domande di ammissione dei soci.', 'gfoss-members' ); ?></p>
    </div>

    <ul class="subsubsub">
        <?php
        $tabs = [ '' => 'Tutte', 'pending' => 'In attesa', 'awaiting_cd' => 'Da deliberare (pagate)', 'awaiting_payment' => 'Approvate da pagare', 'effective' => 'Effettive', 'rejected' => 'Respinte' ];
        foreach ( $tabs as $key => $label ) :
            $url = admin_url( 'admin.php?page=gfoss-candidature' . ( $key ? '&stato=' . $key : '' ) );
            $cls = $filter === $key ? ' class="current"' : '';
            ?>
            <li><a href="<?php echo esc_url( $url ); ?>"<?php echo $cls; ?>><?php echo esc_html( $label ); ?></a> | </li>
        <?php endforeach; ?>
    </ul>

    <table class="widefat striped" style="margin-top:1rem">
        <thead>
        <tr>
            <th><?php esc_html_e( 'Data', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Nome', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Email', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Città', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Stato', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Pagamento', 'gfoss-members' ); ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( ! $rows ) : ?>
            <tr><td colspan="7"><?php esc_html_e( 'Nessuna candidatura.', 'gfoss-members' ); ?></td></tr>
        <?php else : foreach ( $rows as $r ) :
            $chip = $labels[ $r['stato'] ] ?? [ $r['stato'], '#4A5C6A', '#E2E8EC' ];
            $detail = admin_url( 'admin.php?page=gfoss-candidature&id=' . (int) $r['id'] );
        ?>
            <tr>
                <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $r['created_at'] ) ); ?></td>
                <td><strong><?php echo esc_html( $r['nome'] . ' ' . $r['cognome'] ); ?></strong></td>
                <td><a href="mailto:<?php echo esc_attr( $r['email'] ); ?>"><?php echo esc_html( $r['email'] ); ?></a></td>
                <td><?php echo esc_html( $r['citta'] . ' (' . $r['provincia'] . ')' ); ?></td>
                <td><span style="display:inline-block;padding:.15rem .55rem;border-radius:999px;background:<?php echo esc_attr( $chip[2] ); ?>;color:<?php echo esc_attr( $chip[1] ); ?>;font-weight:600;font-size:.75rem"><?php echo esc_html( $chip[0] ); ?></span></td>
                <td><?php echo $r['payment_status'] === 'paid' ? '✓ ' . esc_html( number_format_i18n( (float) $r['payment_amount'], 2 ) ) . ' €' : '—'; ?></td>
                <td><a class="button button-small" href="<?php echo esc_url( $detail ); ?>"><?php esc_html_e( 'Apri', 'gfoss-members' ); ?></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
