<?php
/**
 * Driver database PostgreSQL per RNDT Manager
 *
 * Implementazione PDO/PostgreSQL dell'interfaccia database.
 * Estratto dal class-rndt-database.php originale.
 *
 * @package RNDT_Manager
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe RNDT_Database_PostgreSQL
 */
class RNDT_Database_PostgreSQL implements RNDT_Database_Interface {

    /**
     * Connessione PDO
     *
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * Configurazione connessione
     *
     * @var array
     */
    private $config = array();

    /**
     * Ultimo errore
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Schema database
     *
     * @var string
     */
    private $schema = 'public';

    /**
     * Costruttore
     *
     * @param array $settings Impostazioni plugin (da get_option)
     */
    public function __construct( $settings = array() ) {
        $db_settings = isset( $settings['database'] ) ? $settings['database'] : array();

        $this->config = array(
            'host'     => isset( $db_settings['host'] ) ? $db_settings['host'] : 'localhost',
            'port'     => isset( $db_settings['port'] ) ? $db_settings['port'] : '5432',
            'dbname'   => isset( $db_settings['dbname'] ) ? $db_settings['dbname'] : 'rndt_metadata',
            'user'     => isset( $db_settings['user'] ) ? $db_settings['user'] : '',
            'password' => isset( $db_settings['password'] ) ? $db_settings['password'] : '',
            'schema'   => isset( $db_settings['schema'] ) ? $db_settings['schema'] : 'public',
        );

        $this->schema = $this->config['schema'];
    }

    /**
     * {@inheritdoc}
     */
    public function get_connection() {
        if ( null !== $this->pdo ) {
            return $this->pdo;
        }

        if ( empty( $this->config['user'] ) ) {
            $this->last_error = __( 'Configurazione database PostgreSQL non completata.', 'rndt-manager' );
            return null;
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=10',
                $this->config['host'],
                $this->config['port'],
                $this->config['dbname']
            );

            $this->pdo = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 30,
                )
            );

            $this->pdo->exec( "SET search_path TO {$this->schema}" );

            return $this->pdo;

        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection( $config = null ) {
        if ( null !== $config ) {
            $test_config = $config;
        } else {
            $test_config = $this->config;
        }

        if ( empty( $test_config['user'] ) ) {
            return __( 'Username non specificato.', 'rndt-manager' );
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=5',
                $test_config['host'],
                $test_config['port'],
                $test_config['dbname']
            );

            $pdo = new PDO(
                $dsn,
                $test_config['user'],
                $test_config['password'],
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                )
            );

            $pdo->query( 'SELECT 1' );

            return true;

        } catch ( PDOException $e ) {
            return $e->getMessage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * {@inheritdoc}
     */
    public function get_schema() {
        return $this->schema;
    }

    /**
     * {@inheritdoc}
     */
    public function query( $sql, $params = array() ) {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        try {
            $stmt = $pdo->prepare( $sql );
            $stmt->execute( $params );
            return $stmt;
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function insert( $table, $data ) {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        $columns = array_keys( $data );
        $placeholders = array_map( function( $col ) { return ':' . $col; }, $columns );

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table,
            implode( ', ', $columns ),
            implode( ', ', $placeholders )
        );

        try {
            $stmt = $pdo->prepare( $sql );
            foreach ( $data as $column => $value ) {
                $stmt->bindValue( ':' . $column, $value );
            }
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ? (int) $result['id'] : false;
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update( $table, $data, $where ) {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        $set_parts = array();
        foreach ( array_keys( $data ) as $column ) {
            $set_parts[] = "$column = :set_$column";
        }

        $where_parts = array();
        foreach ( array_keys( $where ) as $column ) {
            $where_parts[] = "$column = :where_$column";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode( ', ', $set_parts ),
            implode( ' AND ', $where_parts )
        );

        try {
            $stmt = $pdo->prepare( $sql );

            foreach ( $data as $column => $value ) {
                $stmt->bindValue( ':set_' . $column, $value );
            }
            foreach ( $where as $column => $value ) {
                $stmt->bindValue( ':where_' . $column, $value );
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete( $table, $where ) {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        $where_parts = array();
        foreach ( array_keys( $where ) as $column ) {
            $where_parts[] = "$column = :$column";
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode( ' AND ', $where_parts )
        );

        try {
            $stmt = $pdo->prepare( $sql );
            foreach ( $where as $column => $value ) {
                $stmt->bindValue( ':' . $column, $value );
            }
            $stmt->execute();
            return $stmt->rowCount();
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get_row( $sql, $params = array() ) {
        $stmt = $this->query( $sql, $params );
        if ( ! $stmt ) {
            return null;
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function get_results( $sql, $params = array() ) {
        $stmt = $this->query( $sql, $params );
        if ( ! $stmt ) {
            return array();
        }
        return $stmt->fetchAll();
    }

    /**
     * {@inheritdoc}
     */
    public function get_var( $sql, $params = array() ) {
        $stmt = $this->query( $sql, $params );
        if ( ! $stmt ) {
            return null;
        }
        return $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function begin_transaction() {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }
        return $pdo->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit() {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }
        return $pdo->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback() {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }
        return $pdo->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function create_tables() {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        $schema = $this->schema;

        $tables = array();

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_metadata (
                id SERIAL PRIMARY KEY,
                wp_post_id BIGINT UNIQUE,
                resource_type VARCHAR(20) NOT NULL DEFAULT 'dataset',
                file_identifier UUID NOT NULL DEFAULT gen_random_uuid(),
                resource_identifier VARCHAR(255),
                resource_identifier_codespace VARCHAR(255),
                parent_identifier VARCHAR(255),
                title TEXT NOT NULL,
                abstract TEXT,
                resource_language VARCHAR(10) DEFAULT 'ita',
                character_set VARCHAR(20) DEFAULT 'utf8',
                hierarchy_level_name VARCHAR(100),
                spatial_representation_type VARCHAR(50),
                date_creation DATE,
                date_publication DATE,
                date_revision DATE,
                temporal_extent_begin DATE,
                temporal_extent_end DATE,
                bbox_west DECIMAL(10,6),
                bbox_east DECIMAL(10,6),
                bbox_south DECIMAL(10,6),
                bbox_north DECIMAL(10,6),
                geographic_description TEXT,
                lineage_statement TEXT,
                spatial_resolution_scale INTEGER,
                spatial_resolution_distance DECIMAL(10,4),
                spatial_resolution_units VARCHAR(20),
                use_limitation TEXT,
                access_constraints VARCHAR(50) DEFAULT 'otherRestrictions',
                use_constraints VARCHAR(50) DEFAULT 'otherRestrictions',
                other_constraints TEXT,
                classification VARCHAR(50),
                reference_system_code VARCHAR(50),
                reference_system_codespace VARCHAR(255),
                metadata_language VARCHAR(10) DEFAULT 'ita',
                metadata_character_set VARCHAR(20) DEFAULT 'utf8',
                metadata_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                metadata_standard_name VARCHAR(255) DEFAULT 'DM - Regole tecniche RNDT',
                metadata_standard_version VARCHAR(50) DEFAULT '10 novembre 2011',
                service_type VARCHAR(50),
                service_type_version VARCHAR(100),
                coupling_type VARCHAR(20),
                maintenance_frequency VARCHAR(50),
                validation_status VARCHAR(20) DEFAULT 'not_validated',
                validation_errors JSONB,
                last_validated_at TIMESTAMP,
                csw_published_at TIMESTAMP,
                csw_record_id VARCHAR(255),
                geoserver_published_at TIMESTAMP,
                xml_cache TEXT,
                xml_cache_date TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by BIGINT,
                updated_by BIGINT
            )
        ";

        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_metadata_resource_type ON {$schema}.rndt_metadata(resource_type)";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_metadata_file_identifier ON {$schema}.rndt_metadata(file_identifier)";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_metadata_validation_status ON {$schema}.rndt_metadata(validation_status)";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_metadata_wp_post_id ON {$schema}.rndt_metadata(wp_post_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_keywords (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                keyword VARCHAR(500) NOT NULL,
                keyword_type VARCHAR(50) DEFAULT 'theme',
                thesaurus_name VARCHAR(500),
                thesaurus_date DATE,
                thesaurus_date_type VARCHAR(20) DEFAULT 'publication',
                anchor_href VARCHAR(1000),
                language VARCHAR(10) DEFAULT 'ita',
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_keywords_metadata_id ON {$schema}.rndt_keywords(metadata_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_responsible_parties (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                context VARCHAR(50) NOT NULL,
                individual_name VARCHAR(255),
                organisation_name VARCHAR(500) NOT NULL,
                position_name VARCHAR(255),
                role VARCHAR(50) NOT NULL,
                phone_voice VARCHAR(100),
                phone_fax VARCHAR(100),
                delivery_point VARCHAR(500),
                city VARCHAR(255),
                admin_area VARCHAR(255),
                postal_code VARCHAR(20),
                country VARCHAR(100),
                email VARCHAR(255),
                online_resource_url VARCHAR(1000),
                ipa_code VARCHAR(50),
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_responsible_parties_metadata_id ON {$schema}.rndt_responsible_parties(metadata_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_online_resources (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                linkage_url VARCHAR(2000) NOT NULL,
                protocol VARCHAR(100),
                name VARCHAR(500),
                description TEXT,
                function VARCHAR(50),
                application_profile VARCHAR(500),
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_online_resources_metadata_id ON {$schema}.rndt_online_resources(metadata_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_distribution_formats (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                format_name VARCHAR(255) NOT NULL,
                format_version VARCHAR(100),
                format_spec VARCHAR(500),
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_distribution_formats_metadata_id ON {$schema}.rndt_distribution_formats(metadata_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_conformity (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                specification_title VARCHAR(1000) NOT NULL,
                specification_date DATE NOT NULL,
                specification_date_type VARCHAR(20) DEFAULT 'publication',
                degree VARCHAR(20),
                explanation TEXT,
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_conformity_metadata_id ON {$schema}.rndt_conformity(metadata_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_service_operations (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                operation_name VARCHAR(255) NOT NULL,
                dcp VARCHAR(50) DEFAULT 'WebServices',
                connect_point_url VARCHAR(2000),
                operation_description TEXT,
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_service_operations_metadata_id ON {$schema}.rndt_service_operations(metadata_id)";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_coupled_resources (
                id SERIAL PRIMARY KEY,
                metadata_id INTEGER NOT NULL REFERENCES {$schema}.rndt_metadata(id) ON DELETE CASCADE,
                identifier VARCHAR(255) NOT NULL,
                resource_title VARCHAR(500),
                resource_url VARCHAR(2000),
                linked_metadata_id INTEGER REFERENCES {$schema}.rndt_metadata(id) ON DELETE SET NULL,
                sort_order INTEGER DEFAULT 0
            )
        ";
        $tables[] = "CREATE INDEX IF NOT EXISTS idx_rndt_coupled_resources_metadata_id ON {$schema}.rndt_coupled_resources(metadata_id)";

        // Tabella presets parti responsabili (standalone, no FK)
        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_responsible_presets (
                id SERIAL PRIMARY KEY,
                preset_name VARCHAR(255) NOT NULL,
                organisation_name VARCHAR(500) NOT NULL,
                individual_name VARCHAR(255),
                position_name VARCHAR(255),
                role_code VARCHAR(50) DEFAULT 'pointOfContact',
                phone VARCHAR(100),
                fax VARCHAR(100),
                email VARCHAR(255),
                delivery_point VARCHAR(500),
                city VARCHAR(255),
                admin_area VARCHAR(255),
                postal_code VARCHAR(20),
                country VARCHAR(100) DEFAULT 'Italia',
                online_resource_url VARCHAR(1000),
                ipa_code VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_inspire_themes (
                id SERIAL PRIMARY KEY,
                code VARCHAR(10) UNIQUE NOT NULL,
                label_it VARCHAR(255) NOT NULL,
                label_en VARCHAR(255) NOT NULL,
                annex VARCHAR(10),
                uri VARCHAR(500)
            )
        ";

        $tables[] = "
            CREATE TABLE IF NOT EXISTS {$schema}.rndt_topic_categories (
                id SERIAL PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                label_it VARCHAR(255) NOT NULL,
                label_en VARCHAR(255) NOT NULL
            )
        ";

        $tables[] = "
            CREATE OR REPLACE FUNCTION {$schema}.update_updated_at_column()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            \$\$ language 'plpgsql'
        ";

        $tables[] = "
            DROP TRIGGER IF EXISTS update_rndt_metadata_updated_at ON {$schema}.rndt_metadata;
            CREATE TRIGGER update_rndt_metadata_updated_at
            BEFORE UPDATE ON {$schema}.rndt_metadata
            FOR EACH ROW EXECUTE FUNCTION {$schema}.update_updated_at_column()
        ";

        try {
            foreach ( $tables as $sql ) {
                $pdo->exec( $sql );
            }
            return true;
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seed_lookup_tables() {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        try {
            $themes = RNDT_Inspire_Themes::get_all();
            $stmt = $pdo->prepare( "
                INSERT INTO {$this->schema}.rndt_inspire_themes (code, label_it, label_en, annex, uri)
                VALUES (:code, :label_it, :label_en, :annex, :uri)
                ON CONFLICT (code) DO UPDATE SET
                    label_it = EXCLUDED.label_it,
                    label_en = EXCLUDED.label_en,
                    annex = EXCLUDED.annex,
                    uri = EXCLUDED.uri
            " );

            foreach ( $themes as $code => $theme ) {
                $stmt->execute( array(
                    ':code'     => $code,
                    ':label_it' => $theme['it'],
                    ':label_en' => $theme['en'],
                    ':annex'    => isset( $theme['annex'] ) ? $theme['annex'] : null,
                    ':uri'      => isset( $theme['uri'] ) ? $theme['uri'] : null,
                ) );
            }

            $categories = RNDT_Topic_Categories::get_all();
            $stmt = $pdo->prepare( "
                INSERT INTO {$this->schema}.rndt_topic_categories (code, label_it, label_en)
                VALUES (:code, :label_it, :label_en)
                ON CONFLICT (code) DO UPDATE SET
                    label_it = EXCLUDED.label_it,
                    label_en = EXCLUDED.label_en
            " );

            foreach ( $categories as $code => $category ) {
                $stmt->execute( array(
                    ':code'     => $code,
                    ':label_it' => $category['it'],
                    ':label_en' => $category['en'],
                ) );
            }

            return true;

        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function drop_tables() {
        $pdo = $this->get_connection();
        if ( ! $pdo ) {
            return false;
        }

        $tables = array(
            'rndt_coupled_resources',
            'rndt_service_operations',
            'rndt_conformity',
            'rndt_distribution_formats',
            'rndt_online_resources',
            'rndt_responsible_parties',
            'rndt_keywords',
            'rndt_metadata',
            'rndt_responsible_presets',
            'rndt_inspire_themes',
            'rndt_topic_categories',
        );

        try {
            foreach ( $tables as $table ) {
                $pdo->exec( "DROP TABLE IF EXISTS {$this->schema}.{$table} CASCADE" );
            }
            $pdo->exec( "DROP FUNCTION IF EXISTS {$this->schema}.update_updated_at_column() CASCADE" );
            return true;
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }
}
