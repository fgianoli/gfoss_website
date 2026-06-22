<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Provisioning GeoServer per socio via REST API.
 *
 * Per ogni socio crea: workspace dedicato, utente + ruolo, regola ACL che limita
 * l'editing al solo workspace del socio, e un datastore PostGIS già collegato al
 * suo schema.
 *
 * Configurazione (costanti / .env):
 *   GFOSS_GEOSERVER_URL          base REST interna (es. "http://geoserver:8080/geoserver")
 *   GFOSS_GEOSERVER_PUBLIC_URL   URL mostrato al socio (default = GFOSS_GEOSERVER_URL)
 *   GFOSS_GEOSERVER_ADMIN_USER   utente admin REST (default "admin")
 *   GFOSS_GEOSERVER_ADMIN_PASS   password admin REST
 *   GFOSS_PG_INTERNAL_HOST       host PostGIS visto DA GeoServer (default = GFOSS_PG_HOST)
 *   GFOSS_PG_PORT                porta PostGIS interna (default 5432)
 *   GFOSS_PG_ADMIN_DB            database condiviso
 */
class Gis_Geoserver {

    public static function is_configured(): bool {
        return self::base() !== '' && self::cfg( 'GFOSS_GEOSERVER_ADMIN_PASS' ) !== '';
    }

    private static function cfg( string $key, string $default = '' ): string {
        if ( defined( $key ) ) { return (string) constant( $key ); }
        $env = getenv( $key );
        return $env !== false ? (string) $env : $default;
    }

    private static function base(): string {
        return rtrim( self::cfg( 'GFOSS_GEOSERVER_URL' ), '/' );
    }

    public static function public_url(): string {
        return rtrim( self::cfg( 'GFOSS_GEOSERVER_PUBLIC_URL', self::cfg( 'GFOSS_GEOSERVER_URL' ) ), '/' );
    }

    /** @return array|\WP_Error response array da wp_remote_request */
    private static function request( string $method, string $path, $body = null ) {
        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( self::cfg( 'GFOSS_GEOSERVER_ADMIN_USER', 'admin' ) . ':' . self::cfg( 'GFOSS_GEOSERVER_ADMIN_PASS' ) ),
                'Accept'        => 'application/json',
            ],
        ];
        if ( $body !== null ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = is_array( $body ) ? wp_json_encode( $body ) : $body;
        }
        return wp_remote_request( self::base() . $path, $args );
    }

    private static function ok( $res ): bool {
        return ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) < 400;
    }

    private static function exists( string $path ): bool {
        return self::ok( self::request( 'GET', $path ) );
    }

    /**
     * Crea/aggiorna workspace, utente, ruolo, ACL e datastore PostGIS.
     *
     * @param string $base     base name del socio (= ruolo/schema PostGIS)
     * @param string $password password (condivisa con l'utente PostGIS)
     * @return true|\WP_Error
     */
    public static function provision( string $base, string $password ) {
        if ( ! self::is_configured() ) {
            return new \WP_Error( 'gs_not_configured', 'GeoServer non configurato (manca URL o password admin).' );
        }

        $ws    = 'ws_' . $base;
        $user  = $base;
        $role  = 'GIS_' . strtoupper( $base );
        $store = 'postgis';

        // 1. Workspace
        if ( ! self::exists( '/rest/workspaces/' . rawurlencode( $ws ) ) ) {
            $res = self::request( 'POST', '/rest/workspaces', [ 'workspace' => [ 'name' => $ws ] ] );
            if ( ! self::ok( $res ) ) { return self::err( 'workspace', $res ); }
        }

        // 2. Utente (crea o aggiorna password)
        $payload = [ 'user' => [ 'userName' => $user, 'password' => $password, 'enabled' => true ] ];
        $res = self::request( 'POST', '/rest/security/usergroup/users', $payload );
        if ( ! self::ok( $res ) ) {
            // Probabilmente esiste già: aggiorna.
            $res = self::request( 'POST', '/rest/security/usergroup/user/' . rawurlencode( $user ), $payload );
            if ( ! self::ok( $res ) ) { return self::err( 'utente', $res ); }
        }

        // 3. Ruolo + associazione all'utente (errori ignorati se già presenti)
        self::request( 'POST', '/rest/security/roles/role/' . rawurlencode( $role ) );
        self::request( 'POST', '/rest/security/roles/role/' . rawurlencode( $role ) . '/user/' . rawurlencode( $user ) );

        // 4. ACL: lettura/scrittura solo sul proprio workspace
        $rules = [
            $ws . '.*.r' => $role,
            $ws . '.*.w' => $role,
        ];
        self::request( 'POST', '/rest/security/acl/layers', $rules );

        // 5. Datastore PostGIS collegato allo schema del socio
        if ( ! self::exists( '/rest/workspaces/' . rawurlencode( $ws ) . '/datastores/' . rawurlencode( $store ) ) ) {
            $params = [
                'host'                  => self::cfg( 'GFOSS_PG_INTERNAL_HOST', self::cfg( 'GFOSS_PG_HOST' ) ),
                'port'                  => self::cfg( 'GFOSS_PG_PORT', '5432' ),
                'database'              => self::cfg( 'GFOSS_PG_ADMIN_DB' ),
                'schema'                => $base,
                'user'                  => $user,
                'passwd'                => $password,
                'dbtype'               => 'postgis',
                'Expose primary keys'  => 'true',
            ];
            $entries = [];
            foreach ( $params as $k => $v ) {
                $entries[] = [ '@key' => $k, '$' => (string) $v ];
            }
            $ds = [ 'dataStore' => [
                'name'                 => $store,
                'connectionParameters' => [ 'entry' => $entries ],
            ] ];
            $res = self::request( 'POST', '/rest/workspaces/' . rawurlencode( $ws ) . '/datastores', $ds );
            if ( ! self::ok( $res ) ) { return self::err( 'datastore', $res ); }
        }

        return true;
    }

    public static function suspend( string $base ): bool {
        $payload = [ 'user' => [ 'userName' => $base, 'enabled' => false ] ];
        return self::ok( self::request( 'POST', '/rest/security/usergroup/user/' . rawurlencode( $base ), $payload ) );
    }

    public static function resume( string $base ): bool {
        $payload = [ 'user' => [ 'userName' => $base, 'enabled' => true ] ];
        return self::ok( self::request( 'POST', '/rest/security/usergroup/user/' . rawurlencode( $base ), $payload ) );
    }

    private static function err( string $what, $res ): \WP_Error {
        $detail = is_wp_error( $res )
            ? $res->get_error_message()
            : 'HTTP ' . wp_remote_retrieve_response_code( $res ) . ' — ' . wp_remote_retrieve_body( $res );
        return new \WP_Error( 'gs_' . $what, "Errore GeoServer ($what): $detail" );
    }
}
