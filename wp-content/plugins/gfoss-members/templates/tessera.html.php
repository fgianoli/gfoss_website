<?php
/**
 * Template tessera per mPDF.
 * Variabili in scope: $user, $year, $numero_socio, $data_iscr, $qr_data_uri, $verify_url, $logo_svg
 *
 * Pagina: 85.6mm × 108mm (due facciate impilate, ognuna 85.6×54).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!doctype html>
<html lang="it"><head><meta charset="utf-8">
<style>
@page { margin: 0; }
* { box-sizing: border-box; }
body { font-family: sans-serif; margin: 0; }
.card {
    width: 85.6mm; height: 54mm;
    position: relative; overflow: hidden;
    color: #FAFBFC;
    page-break-after: always;
}
.card.front { background: #1A6FA0; }
.card.back  { background: #0F2330; }

.front .brandbar { background: #fff; padding: 2.5mm 5mm; height: 15mm; vertical-align: middle; }
.front .brandbar img { height: 10mm; width: auto; }
.front .top { padding: 4mm 5mm 0; display: table; width: 100%; }
.front .logo, .front .lbl { display: table-cell; vertical-align: middle; }
.front .logo { width: 12mm; }
.front .lbl  { font-size: 10pt; letter-spacing: 0.5pt; font-weight: bold; color: #fff; }
.front .lbl small { display: block; font-size: 6pt; font-weight: normal; opacity: .85; letter-spacing: 1pt; text-transform: uppercase; margin-top: 1mm; }

.front .body { padding: 4mm 5mm 0; }
.front .name {
    font-size: 14pt; font-weight: bold; color: #fff;
    margin: 2mm 0 1mm; line-height: 1.1;
    border-bottom: 0.5pt solid rgba(255,255,255,.3); padding-bottom: 1mm;
}
.front .num { font-size: 9pt; margin: 0 0 1mm; }
.front .num strong { color: #F39200; }
.front .meta { font-size: 7pt; color: rgba(255,255,255,.85); margin: 0; }

.front .qr {
    position: absolute; bottom: 4mm; right: 5mm;
    width: 18mm; height: 18mm; background: #fff; padding: 1mm;
}
.front .qr img { width: 16mm; height: 16mm; display: block; }

.back { padding: 6mm 6mm; font-size: 7.5pt; line-height: 1.45; color: #C8D4DC; }
.back h2 { color: #fff; margin: 0 0 2mm; font-size: 9pt; letter-spacing: .5pt; }
.back p  { margin: 0 0 1.5mm; }
.back .verify { margin-top: 3mm; padding-top: 2.5mm; border-top: 0.4pt solid rgba(255,255,255,.15); font-size: 6.5pt; color: #8ea4b1; }
.back .verify code { color: #2BA5D9; }
.back .stripe {
    position: absolute; left: 0; right: 0; top: 0;
    height: 6mm; background: #F39200;
}
</style>
</head>
<body>

<!-- FRONT -->
<div class="card front" style="background: <?php echo esc_attr( $palette['a'] ?? '#1A6FA0' ); ?>">
    <?php if ( ! empty( $logo_uri ) ) : ?>
        <div class="brandbar"><img src="<?php echo esc_attr( $logo_uri ); ?>" alt="GFOSS.it APS"></div>
    <?php else : ?>
        <div class="top">
            <div class="logo"><?php echo $logo_svg; // SVG safe perché generato da noi ?></div>
            <div class="lbl">GFOSS.it APS<small>OSGeo·IT Local Chapter</small></div>
        </div>
    <?php endif; ?>
    <div class="body">
        <p class="name"><?php echo esc_html( $user->display_name ); ?></p>
        <p class="num">Socio n° <strong><?php echo esc_html( $numero_socio ?: '—' ); ?></strong> · validità <strong><?php echo esc_html( (string) $year ); ?></strong></p>
        <p class="meta">Iscritto/a dal <?php echo esc_html( $data_iscr ?: '—' ); ?></p>
    </div>
    <?php if ( $qr_data_uri ) : ?>
        <div class="qr"><img src="<?php echo esc_attr( $qr_data_uri ); ?>" alt=""></div>
    <?php endif; ?>
</div>

<!-- BACK -->
<div class="card back" style="background: <?php echo esc_attr( $palette['b'] ?? '#0F2330' ); ?>">
    <div class="stripe"></div>
    <div style="padding-top: 5mm">
        <h2>Associazione Italiana per l'Informazione Geografica Libera APS</h2>
        <p>Sede legale: Lungargine Gerolamo Rovetta 28, 35131 Padova</p>
        <p>info@gfoss.it · gfoss.it</p>
        <p>Ente del Terzo Settore — D. Lgs. 117/2017</p>
        <div class="verify">
            <strong>Verifica autenticità</strong><br>
            Inquadra il QR sul fronte oppure visita<br>
            <code><?php echo esc_html( $verify_url ); ?></code>
        </div>
    </div>
</div>

</body></html>
