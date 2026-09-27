<?php
/**
 * Importa in blocco una cartella di file come "Documenti riservati ai soci".
 * I file vanno nella cartella protetta (uploads/gfoss-docs-private), come quelli
 * caricati dalla console. Idempotente: salta i file già importati (stesso SHA-1)
 * e i doppioni esatti presenti in più sottocartelle.
 *
 * Categoria = nome della sottocartella (i file nella radice vanno in "Assemblea e deleghe"
 * se contengono "delega", altrimenti in "Generale"); vedi $CAT_OVERRIDE.
 *
 * Uso (produzione):
 *   docker compose --profile tools run --rm -v "$PWD/documenti_soci:/import:ro" \
 *     wpcli eval-file /scripts/import-documenti-soci.php /import
 * Aggiungere "--dry-run" dopo il percorso per vedere cosa verrebbe importato.
 */

if ( ! defined( 'ABSPATH' ) ) { require '/var/www/html/wp-load.php'; $args = array_slice( $argv, 1 ); }

use GFOSS_Members\Doc_Riservato;
use GFOSS_Members\Doc_Riservato_Frontend;

$src = rtrim( (string) ( $args[0] ?? '' ), '/' );
$dry = in_array( '--dry-run', $args, true );
if ( $src === '' || ! is_dir( $src ) ) { fwrite( STDERR, "Cartella non trovata: $src\n" ); exit( 1 ); }

// Titoli leggibili per i file noti (chiave = nome file in minuscolo).
$TITLES = [
    'delega_compilabile_bilancio-2026.pdf'         => 'Delega assemblea approvazione bilancio 2026 (PDF compilabile)',
    'delega_compilabile_bilancio-2026.odt'         => 'Delega assemblea approvazione bilancio 2026 (ODT)',
    'delega_compilabile_rev1.ott'                  => 'Modello di delega per l\'assemblea (modello ODT)',
    'gfoss_loghi.zip'                              => 'Loghi GFOSS.it e OSGeo (pacchetto ZIP)',
    'gfoss_modelli.zip'                            => 'Pacchetto modelli GFOSS.it (ZIP)',
    'modello_attestato_di_partecipazione_rev3.odt' => 'Modello attestato di partecipazione (ODT)',
    'modello_carta_intestata_rev4.odt'             => 'Carta intestata GFOSS.it (ODT)',
    'modello_iscrizione_rev3.odt'                  => 'Modulo di iscrizione (ODT)',
    'modello_iscrizione_rev3.pdf'                  => 'Modulo di iscrizione (PDF)',
    'modello_offerta_rev3.odt'                     => 'Modello di offerta (ODT)',
    'modello_ricevuta_rev4.odt'                    => 'Modello di ricevuta (ODT)',
    'modello_richiesta_pagamento_rev0.odt'         => 'Modello richiesta di pagamento (ODT)',
    'modello_rimborsi_compilabile_rev3.odt'        => 'Modulo richiesta rimborsi spese (ODT compilabile)',
    'modello_rimborsi_compilabile_rev3.pdf'        => 'Modulo richiesta rimborsi spese (PDF compilabile)',
    'modello_verbali_cd_rev0.odt'                  => 'Modello verbale del Consiglio Direttivo (ODT)',
    'ricevute_talloncini_rev4.pdf'                 => 'Ricevute a talloncino (PDF)',
    'ricevute_talloncini_rev4.svg'                 => 'Ricevute a talloncino (SVG modificabile)',
];
$CAT_OVERRIDE = [
    'gfoss_loghi.zip'                      => 'Loghi',
    'delega_compilabile_bilancio-2026.pdf' => 'Assemblea e deleghe',
    'delega_compilabile_bilancio-2026.odt' => 'Assemblea e deleghe',
    'delega_compilabile_rev1.ott'          => 'Assemblea e deleghe',
];

// Hash dei documenti già presenti
$known = [];
foreach ( get_posts( [ 'post_type' => Doc_Riservato::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] ) as $pid ) {
    $h = (string) get_post_meta( $pid, '_gfoss_doc_sha1', true );
    if ( $h !== '' ) { $known[ $h ] = $pid; }
}

$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
$files = [];
foreach ( $it as $f ) { if ( $f->isFile() ) { $files[] = $f->getPathname(); } }
sort( $files );

$ok = 0; $skip = 0; $fail = 0;
foreach ( $files as $path ) {
    $name = basename( $path );
    $key  = strtolower( $name );
    $rel  = ltrim( substr( dirname( $path ), strlen( $src ) ), '/' );
    $show = ltrim( "$rel/$name", '/' );
    $sha  = sha1_file( $path );
    if ( isset( $known[ $sha ] ) ) { echo "  = già presente: $show\n"; $skip++; continue; }

    $cat   = $CAT_OVERRIDE[ $key ] ?? ( $rel !== '' ? ucfirst( explode( '/', $rel )[0] ) : ( str_contains( $key, 'delega' ) ? 'Assemblea e deleghe' : 'Generale' ) );
    $title = $TITLES[ $key ] ?? '';
    if ( $dry ) { echo "  + [$cat] " . ( $title ?: $name ) . "\n"; $known[ $sha ] = -1; $ok++; continue; }

    $pid = Doc_Riservato_Frontend::import_file( $path, $title, $cat );
    if ( ! $pid ) { echo "  ! NON importato (formato non ammesso?): $show\n"; $fail++; continue; }
    update_post_meta( $pid, '_gfoss_doc_sha1', $sha );
    $known[ $sha ] = $pid;
    echo "  + #$pid [$cat] " . get_the_title( $pid ) . "\n";
    $ok++;
}
echo ( $dry ? "[dry-run] " : '' ) . "Importati: $ok · già presenti/doppioni: $skip · errori: $fail\n";
