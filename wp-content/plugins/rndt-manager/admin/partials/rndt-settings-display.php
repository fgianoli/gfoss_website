<?php
/**
 * Template per la pagina impostazioni
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Impostazioni RNDT Manager', 'rndt-manager' ); ?></h1>

    <?php settings_errors( 'rndt_settings_group' ); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'rndt_settings_group' );
        do_settings_sections( RNDT_Settings_Page::PAGE_SLUG );
        submit_button();
        ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle campi PostgreSQL in base al tipo database selezionato
    function toggleDbTypeFields() {
        var dbType = $('input[name="rndt_settings[database][type]"]:checked').val() || 'postgresql';
        var isPg = (dbType === 'postgresql');
        // Nascondi/mostra i campi PostgreSQL (host, port, dbname, schema, user, password)
        $('#database_host, #database_port, #database_dbname, #database_schema, #database_user, #database_password')
            .closest('tr').toggle(isPg);
        // Aggiorna testo pulsante test
        if (!isPg) {
            $('#rndt-test-db-connection').hide();
        } else {
            $('#rndt-test-db-connection').show();
        }
    }
    $('input[name="rndt_settings[database][type]"]').on('change', toggleDbTypeFields);
    toggleDbTypeFields();

    // Funzione helper per test connessione
    function testConnection(service, data, $btn, $result) {
        $btn.prop('disabled', true);
        $result.html('<span class="spinner is-active" style="float:none;"></span>');

        $.ajax({
            url: rndtAdmin.restUrl + 'publish/test-connection',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', rndtAdmin.nonce);
            },
            data: JSON.stringify(data),
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">' + (rndtAdmin.i18n.success || 'Connessione riuscita!') + '</span>');
                } else {
                    $result.html('<span style="color:red;">' + (rndtAdmin.i18n.failed || 'Connessione fallita:') + ' ' + response.message + '</span>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error';
                $result.html('<span style="color:red;">' + (rndtAdmin.i18n.failed || 'Connessione fallita:') + ' ' + msg + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    // Test connessione database
    $('#rndt-test-db-connection').on('click', function() {
        testConnection('database', {
            service: 'database',
            host: $('input[name*="[database][host]"]').val() || $('#database_host').val(),
            port: $('input[name*="[database][port]"]').val() || $('#database_port').val(),
            dbname: $('input[name*="[database][dbname]"]').val() || $('#database_dbname').val(),
            user: $('input[name*="[database][user]"]').val() || $('#database_user').val(),
            password: $('input[name*="[database][password]"]').val() || $('#database_password').val(),
            schema: $('input[name*="[database][schema]"]').val() || $('#database_schema').val()
        }, $(this), $('#rndt-db-test-result'));
    });

    // Test connessione CSW
    $('#rndt-test-csw-connection').on('click', function() {
        testConnection('csw', {
            service: 'csw',
            config: {
                endpoint: $('input[name*="[csw][url]"]').val(),
                catalog_type: $('select[name*="[csw][catalog_type]"]').val(),
                username: $('input[name*="[csw][username]"]').val(),
                password: $('input[name*="[csw][password]"]').val()
            }
        }, $(this), $('#rndt-csw-test-result'));
    });

    // Test connessione GeoServer
    $('#rndt-test-geoserver-connection').on('click', function() {
        testConnection('geoserver', {
            service: 'geoserver',
            config: {
                url: $('input[name*="[geoserver][url]"]').val(),
                username: $('input[name*="[geoserver][username]"]').val(),
                password: $('input[name*="[geoserver][password]"]').val()
            }
        }, $(this), $('#rndt-geoserver-test-result'));
    });

    // Mostra/nascondi campi autenticazione CSW
    $('select[name*="[csw][auth_type]"]').on('change', function() {
        var authType = $(this).val();
        $('.csw-auth-basic').closest('tr').toggle(authType === 'basic');
        $('.csw-auth-bearer').closest('tr').toggle(authType === 'bearer');
    }).trigger('change');

    // Crea tabelle PostgreSQL
    $('#rndt-create-tables').on('click', function() {
        var $btn = $(this);
        var $result = $('#rndt-db-test-result');

        if (!confirm('<?php echo esc_js( __( 'Vuoi creare le tabelle nel database? Questa operazione potrebbe richiedere alcuni secondi.', 'rndt-manager' ) ); ?>')) {
            return;
        }

        $btn.prop('disabled', true);
        $result.html('<span class="spinner is-active" style="float:none;"></span> <?php echo esc_js( __( 'Creazione tabelle in corso...', 'rndt-manager' ) ); ?>');

        $.ajax({
            url: rndtAdmin.restUrl + 'publish/create-tables',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', rndtAdmin.nonce);
            },
            data: JSON.stringify({}),
            timeout: 60000, // 60 secondi timeout
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">' + response.message + '</span>');
                } else {
                    $result.html('<span style="color:red;">' + response.message + '</span>');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php echo esc_js( __( 'Errore sconosciuto', 'rndt-manager' ) ); ?>';
                $result.html('<span style="color:red;"><?php echo esc_js( __( 'Errore:', 'rndt-manager' ) ); ?> ' + msg + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
