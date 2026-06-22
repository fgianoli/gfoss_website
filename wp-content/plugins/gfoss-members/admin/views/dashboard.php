<?php
namespace GFOSS_Members;
if ( ! defined( 'ABSPATH' ) ) { exit; }

$year       = (int) gmdate( 'Y' );
$ruoli_soci = [ 'gfoss_socio', 'gfoss_consigliere', 'gfoss_presidente', 'gfoss_tesoriere', 'gfoss_revisore', 'gfoss_comunicazione', 'gfoss_segreteria' ];
$all_soci   = get_users( [ 'role__in' => $ruoli_soci, 'fields' => [ 'ID' ] ] );
$tot_soci   = count( $all_soci );
$unpaid     = Quote::unpaid_for_year( $year );

// Quote pagate + incasso dell'anno corrente.
$q_year   = Quote::all_for_year( $year );
$paid     = 0; $incasso = 0.0;
foreach ( $q_year as $r ) {
    if ( $r['stato'] === 'paid' ) { $paid++; $incasso += (float) $r['importo']; }
}

// Volontari e nuovi soci dell'anno.
$volontari = count( get_users( [ 'role__in' => $ruoli_soci, 'fields' => [ 'ID' ], 'meta_key' => 'gf_volontario', 'meta_value' => '1' ] ) );
$nuovi     = count( get_users( [
    'role__in'   => $ruoli_soci,
    'fields'     => [ 'ID' ],
    'meta_query' => [ [ 'key' => 'gf_data_ammissione', 'value' => (string) $year . '-', 'compare' => 'LIKE' ] ],
] ) );

// Andamento ultimi 6 anni (tesseramenti + incasso).
$trend = [];
$max_n = 1;
for ( $y = $year; $y > $year - 6; $y-- ) {
    $rows = Quote::all_for_year( $y );
    $n = 0; $tot = 0.0;
    foreach ( $rows as $r ) { if ( $r['stato'] === 'paid' ) { $n++; $tot += (float) $r['importo']; } }
    $trend[ $y ] = [ 'n' => $n, 'tot' => $tot ];
    $max_n = max( $max_n, $n );
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'GFOSS.it — Dashboard associazione', 'gfoss-members' ); ?></h1>
    <p class="description">
        <?php printf(
            esc_html__( 'Anno corrente: %1$d · Quota associativa: %2$s €.', 'gfoss-members' ),
            $year,
            esc_html( number_format_i18n( Quote::default_amount(), 2 ) )
        ); ?>
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:24px 0;">
        <div class="card" style="padding:16px;background:#fff;border:1px solid #e2e8ec;border-radius:8px;">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px;letter-spacing:.04em"><?php esc_html_e( 'Soci totali', 'gfoss-members' ); ?></p>
            <p style="margin:.25rem 0 0;font-size:2rem;font-weight:700;"><?php echo esc_html( (string) $tot_soci ); ?></p>
        </div>
        <div class="card" style="padding:16px;background:#fff;border:1px solid #e2e8ec;border-radius:8px;">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px;letter-spacing:.04em"><?php esc_html_e( 'In regola con la quota', 'gfoss-members' ); ?></p>
            <p style="margin:.25rem 0 0;font-size:2rem;font-weight:700;color:#5DA34D;"><?php echo esc_html( (string) $paid ); ?></p>
        </div>
        <div class="card" style="padding:16px;background:#fff;border:1px solid #e2e8ec;border-radius:8px;">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px;letter-spacing:.04em"><?php esc_html_e( 'Da rinnovare', 'gfoss-members' ); ?></p>
            <p style="margin:.25rem 0 0;font-size:2rem;font-weight:700;color:#C0392B;"><?php echo esc_html( (string) count( $unpaid ) ); ?></p>
        </div>
        <div class="card" style="padding:16px;background:#fff;border:1px solid #e2e8ec;border-radius:8px;">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px;letter-spacing:.04em"><?php printf( esc_html__( 'Incassato %d', 'gfoss-members' ), $year ); ?></p>
            <p style="margin:.25rem 0 0;font-size:2rem;font-weight:700;color:#1A6FA0;"><?php echo esc_html( number_format_i18n( $incasso, 2 ) ); ?> €</p>
        </div>
        <div class="card" style="padding:16px;background:#fff;border:1px solid #e2e8ec;border-radius:8px;">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px;letter-spacing:.04em"><?php printf( esc_html__( 'Nuovi soci %d', 'gfoss-members' ), $year ); ?></p>
            <p style="margin:.25rem 0 0;font-size:2rem;font-weight:700;"><?php echo esc_html( (string) $nuovi ); ?></p>
        </div>
        <div class="card" style="padding:16px;background:#fff;border:1px solid #e2e8ec;border-radius:8px;">
            <p style="margin:0;color:#4A5C6A;text-transform:uppercase;font-size:12px;letter-spacing:.04em"><?php esc_html_e( 'Volontari', 'gfoss-members' ); ?></p>
            <p style="margin:.25rem 0 0;font-size:2rem;font-weight:700;color:#5DA34D;"><?php echo esc_html( (string) $volontari ); ?></p>
        </div>
    </div>

    <h2><?php esc_html_e( 'Andamento tesseramenti', 'gfoss-members' ); ?></h2>
    <table class="widefat striped" style="max-width:640px">
        <thead><tr>
            <th><?php esc_html_e( 'Anno', 'gfoss-members' ); ?></th>
            <th><?php esc_html_e( 'Quote pagate', 'gfoss-members' ); ?></th>
            <th style="width:45%"></th>
            <th><?php esc_html_e( 'Incasso', 'gfoss-members' ); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $trend as $y => $d ) :
            $w = (int) round( ( $d['n'] / $max_n ) * 100 ); ?>
            <tr>
                <td><strong><?php echo esc_html( (string) $y ); ?></strong><?php echo $y === $year ? ' <small>(in corso)</small>' : ''; ?></td>
                <td><?php echo esc_html( (string) $d['n'] ); ?></td>
                <td><div style="background:#1A6FA0;height:14px;border-radius:7px;width:<?php echo esc_attr( (string) $w ); ?>%;min-width:2px"></div></td>
                <td><?php echo esc_html( number_format_i18n( $d['tot'], 2 ) ); ?> €</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 style="margin-top:1.5rem"><?php esc_html_e( 'Prossime azioni', 'gfoss-members' ); ?></h2>
    <ul style="list-style:disc;padding-left:1.4rem">
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-candidature' ) ); ?>"><?php esc_html_e( 'Esamina candidature in attesa di delibera', 'gfoss-members' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-quote' ) ); ?>"><?php esc_html_e( 'Registra incassi quote', 'gfoss-members' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gfoss-export' ) ); ?>"><?php esc_html_e( 'Esporta libro soci (CSV)', 'gfoss-members' ); ?></a></li>
    </ul>

    <?php if ( $unpaid ) : ?>
        <h2><?php printf( esc_html__( 'Soci ancora da rinnovare per il %d', 'gfoss-members' ), $year ); ?></h2>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Nome', 'gfoss-members' ); ?></th><th><?php esc_html_e( 'Email', 'gfoss-members' ); ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ( $unpaid as $u ) : ?>
                    <tr>
                        <td><?php echo esc_html( $u['display_name'] ); ?></td>
                        <td><?php echo esc_html( $u['user_email'] ); ?></td>
                        <td><a class="button" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $u['ID'] ) ); ?>"><?php esc_html_e( 'Apri scheda', 'gfoss-members' ); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
