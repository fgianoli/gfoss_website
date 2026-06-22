<?php
/**
 * Import soci da CSV (eseguire via wp-cli).
 *
 *   1. Carica il file in:  wp-content/uploads/soci-import.csv
 *   2. Esegui:
 *      docker compose --profile tools run --rm wpcli eval-file /scripts/import-soci.php
 *
 * Colonne (prima riga = intestazione, ordine libero, nomi esatti):
 *   nome, cognome, email, codice_fiscale, numero_socio, citta, provincia,
 *   cap, indirizzo, telefono, volontario, anno_quota, importo, metodo
 *
 * - "email" è obbligatoria. Se l'utente esiste (per email) viene aggiornato,
 *   altrimenti creato con ruolo "Socio GFOSS".
 * - numero_socio: se vuoto viene generato (ANNO-NNNNN); se duplicato viene
 *   generato uno nuovo.
 * - anno_quota + importo (se presenti) → quota di quell'anno marcata pagata.
 * Idempotente: rilanciabile.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "Eseguire via wp-cli.\n" ); exit( 1 );
}

$path = WP_CONTENT_DIR . '/uploads/soci-import.csv';
if ( ! is_file( $path ) ) {
    \WP_CLI::error( "File non trovato: $path — carica il CSV in wp-content/uploads/soci-import.csv" );
}

$fh = fopen( $path, 'r' );
if ( ! $fh ) { \WP_CLI::error( 'Impossibile aprire il CSV.' ); }

// Intestazione → indici colonna.
$sep    = ';';
$header = fgetcsv( $fh, 0, $sep );
if ( $header && count( $header ) === 1 ) { // forse separatore virgola
    rewind( $fh ); $sep = ','; $header = fgetcsv( $fh, 0, $sep );
}
$header = array_map( static fn( $h ) => strtolower( trim( (string) $h ) ), (array) $header );
$col    = array_flip( $header );

$get = static function ( array $row, array $col, string $name ): string {
    return isset( $col[ $name ], $row[ $col[ $name ] ] ) ? trim( (string) $row[ $col[ $name ] ] ) : '';
};

if ( ! isset( $col['email'] ) ) { \WP_CLI::error( 'Manca la colonna "email" nell\'intestazione.' ); }

$created = 0; $updated = 0; $skipped = 0; $quote = 0;

while ( ( $row = fgetcsv( $fh, 0, $sep ) ) !== false ) {
    $email = sanitize_email( $get( $row, $col, 'email' ) );
    if ( ! is_email( $email ) ) { $skipped++; continue; }

    $nome    = $get( $row, $col, 'nome' );
    $cognome = $get( $row, $col, 'cognome' );

    $user = get_user_by( 'email', $email );
    if ( $user ) {
        $uid = (int) $user->ID;
        if ( ! gfoss_members_is_socio( $uid ) ) { $user->add_role( 'gfoss_socio' ); }
        $updated++;
    } else {
        $uid = wp_insert_user( [
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 20, true ),
            'first_name'   => $nome,
            'last_name'    => $cognome,
            'display_name' => trim( "$nome $cognome" ) ?: $email,
            'role'         => 'gfoss_socio',
        ] );
        if ( is_wp_error( $uid ) ) { \WP_CLI::log( "  ✗ errore su $email: " . $uid->get_error_message() ); $skipped++; continue; }
        $created++;
    }

    // Anagrafica.
    $map = [
        'codice_fiscale' => 'gf_codice_fiscale', 'citta' => 'gf_citta', 'provincia' => 'gf_provincia',
        'cap' => 'gf_cap', 'indirizzo' => 'gf_indirizzo', 'telefono' => 'gf_telefono',
    ];
    foreach ( $map as $src => $meta ) {
        $v = $get( $row, $col, $src );
        if ( $v !== '' ) { update_user_meta( $uid, $meta, sanitize_text_field( $v ) ); }
    }
    update_user_meta( $uid, 'gf_volontario', in_array( strtolower( $get( $row, $col, 'volontario' ) ), [ '1', 'si', 'sì', 'x', 'true' ], true ) ? '1' : '0' );

    // Numero socio.
    $num = $get( $row, $col, 'numero_socio' );
    $cur = (string) get_user_meta( $uid, 'gf_numero_socio', true );
    if ( $num !== '' && ! \GFOSS_Members\Candidatura::numero_in_use( $num, $uid ) ) {
        update_user_meta( $uid, 'gf_numero_socio', $num );
    } elseif ( $cur === '' ) {
        update_user_meta( $uid, 'gf_numero_socio', \GFOSS_Members\Candidatura::next_numero_socio() );
    }

    // Quota.
    $anno    = (int) $get( $row, $col, 'anno_quota' );
    $importo = (float) str_replace( ',', '.', $get( $row, $col, 'importo' ) );
    if ( $anno > 2000 ) {
        $metodo = $get( $row, $col, 'metodo' ) ?: 'bonifico';
        \GFOSS_Members\Quote::mark_paid( $uid, $anno, sanitize_key( $metodo ), null, 'Import CSV', $importo > 0 ? $importo : null );
        $quote++;
    }
}
fclose( $fh );

\WP_CLI::success( "Import completato: $created creati, $updated aggiornati, $quote quote, $skipped scartati." );
