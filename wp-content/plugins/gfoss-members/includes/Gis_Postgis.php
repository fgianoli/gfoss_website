<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Provisioning PostGIS per socio — modello "schema per socio".
 *
 * Un unico database condiviso (GFOSS_PG_ADMIN_DB) con l'estensione PostGIS già
 * installata. Per ogni socio si crea un ruolo di login e uno schema di sua
 * proprietà; il ruolo ha accesso in lettura/scrittura SOLO al proprio schema.
 *
 * Configurazione (costanti, definite via WORDPRESS_CONFIG_EXTRA / .env):
 *   GFOSS_PG_HOST            host del container PostGIS visto da WordPress (es. "postgis")
 *   GFOSS_PG_PORT            porta interna (default 5432)
 *   GFOSS_PG_ADMIN_DB        database condiviso (es. "soci_gis")
 *   GFOSS_PG_ADMIN_USER      ruolo amministrativo (CREATEROLE, NON superuser)
 *   GFOSS_PG_ADMIN_PASS      password del ruolo amministrativo
 *   GFOSS_PG_PUBLIC_HOST     host mostrato al socio per QGIS/ogr2ogr (default = GFOSS_PG_HOST)
 *   GFOSS_PG_PUBLIC_PORT     porta pubblica (default = GFOSS_PG_PORT)
 */
class Gis_Postgis {

    public static function cfg( string $key, string $default = '' ): string {
        if ( defined( $key ) ) { return (string) constant( $key ); }
        $env = getenv( $key );
        return $env !== false ? (string) $env : $default;
    }

    public static function is_configured(): bool {
        return function_exists( 'pg_connect' )
            && self::cfg( 'GFOSS_PG_HOST' ) !== ''
            && self::cfg( 'GFOSS_PG_ADMIN_DB' ) !== ''
            && self::cfg( 'GFOSS_PG_ADMIN_USER' ) !== '';
    }

    /** Host/porta/db da mostrare al socio (connessione esterna QGIS, ecc.). */
    public static function public_conn(): array {
        return [
            'host' => self::cfg( 'GFOSS_PG_PUBLIC_HOST', self::cfg( 'GFOSS_PG_HOST' ) ),
            'port' => self::cfg( 'GFOSS_PG_PUBLIC_PORT', self::cfg( 'GFOSS_PG_PORT', '5432' ) ),
            'db'   => self::cfg( 'GFOSS_PG_ADMIN_DB' ),
        ];
    }

    /** Connessione amministrativa. @return resource|\WP_Error */
    private static function connect() {
        if ( ! function_exists( 'pg_connect' ) ) {
            return new \WP_Error( 'no_pgsql', 'Estensione PHP pgsql non installata sul container WordPress.' );
        }
        $dsn = sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s connect_timeout=8',
            self::cfg( 'GFOSS_PG_HOST' ),
            self::cfg( 'GFOSS_PG_PORT', '5432' ),
            self::cfg( 'GFOSS_PG_ADMIN_DB' ),
            self::cfg( 'GFOSS_PG_ADMIN_USER' ),
            self::cfg( 'GFOSS_PG_ADMIN_PASS' )
        );
        $conn = @pg_connect( $dsn );
        if ( ! $conn ) {
            return new \WP_Error( 'pg_connect_failed', 'Connessione a PostGIS non riuscita: verifica host/credenziali e che il container sia raggiungibile.' );
        }
        return $conn;
    }

    /**
     * Crea (o aggiorna la password di) ruolo + schema del socio.
     * Idempotente: se ruolo/schema esistono, aggiorna soltanto la password.
     *
     * @return true|\WP_Error
     */
    public static function provision( string $base, string $password ) {
        $conn = self::connect();
        if ( is_wp_error( $conn ) ) { return $conn; }

        $role   = $base;
        $schema = $base;
        $db     = self::cfg( 'GFOSS_PG_ADMIN_DB' );

        $r  = pg_escape_identifier( $conn, $role );
        $s  = pg_escape_identifier( $conn, $schema );
        $d  = pg_escape_identifier( $conn, $db );
        $pw = pg_escape_literal( $conn, $password );

        // Esiste già il ruolo?
        $res    = pg_query_params( $conn, 'SELECT 1 FROM pg_roles WHERE rolname = $1', [ $role ] );
        $exists = $res && pg_num_rows( $res ) > 0;

        $statements = [];
        if ( $exists ) {
            $statements[] = "ALTER ROLE $r WITH LOGIN PASSWORD $pw";
        } else {
            $statements[] = "CREATE ROLE $r LOGIN PASSWORD $pw NOSUPERUSER NOCREATEDB NOCREATEROLE";
        }
        $statements[] = "GRANT CONNECT ON DATABASE $d TO $r";
        $statements[] = "CREATE SCHEMA IF NOT EXISTS $s AUTHORIZATION $r";
        // Accesso alle funzioni/tipi PostGIS (estensione in public) ma niente scrittura lì.
        $statements[] = "GRANT USAGE ON SCHEMA public TO $r";
        $statements[] = "REVOKE CREATE ON SCHEMA public FROM $r";
        // spatial_ref_sys serve in lettura per le proiezioni.
        $statements[] = "GRANT SELECT ON TABLE public.spatial_ref_sys TO $r";
        // Lo schema del socio diventa il default.
        $statements[] = "ALTER ROLE $r SET search_path = $s, public";

        foreach ( $statements as $sql ) {
            $ok = @pg_query( $conn, $sql );
            if ( ! $ok ) {
                $err = pg_last_error( $conn );
                // spatial_ref_sys potrebbe non esistere se PostGIS non è installato nel DB.
                if ( strpos( $sql, 'spatial_ref_sys' ) !== false ) { continue; }
                pg_close( $conn );
                return new \WP_Error( 'pg_query_failed', 'Errore PostGIS: ' . $err );
            }
        }
        pg_close( $conn );
        return true;
    }

    /** Sospende l'accesso (socio decaduto) senza cancellare i dati. */
    public static function suspend( string $base ): bool {
        $conn = self::connect();
        if ( is_wp_error( $conn ) ) { return false; }
        $ok = @pg_query( $conn, 'ALTER ROLE ' . pg_escape_identifier( $conn, $base ) . ' WITH NOLOGIN' );
        pg_close( $conn );
        return (bool) $ok;
    }

    /** Riattiva l'accesso. */
    public static function resume( string $base ): bool {
        $conn = self::connect();
        if ( is_wp_error( $conn ) ) { return false; }
        $ok = @pg_query( $conn, 'ALTER ROLE ' . pg_escape_identifier( $conn, $base ) . ' WITH LOGIN' );
        pg_close( $conn );
        return (bool) $ok;
    }

    /** Elimina schema (CASCADE) e ruolo del socio. Operazione distruttiva. */
    public static function drop( string $base ): bool {
        $conn = self::connect();
        if ( is_wp_error( $conn ) ) { return false; }
        $r = pg_escape_identifier( $conn, $base );
        $s = pg_escape_identifier( $conn, $base );
        @pg_query( $conn, "DROP SCHEMA IF EXISTS $s CASCADE" );
        @pg_query( $conn, "REASSIGN OWNED BY $r TO " . pg_escape_identifier( $conn, self::cfg( 'GFOSS_PG_ADMIN_USER' ) ) );
        @pg_query( $conn, "DROP OWNED BY $r" );
        $ok = @pg_query( $conn, "DROP ROLE IF EXISTS $r" );
        pg_close( $conn );
        return (bool) $ok;
    }
}
