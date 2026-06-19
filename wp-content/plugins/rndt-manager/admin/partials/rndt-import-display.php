<?php
/**
 * Template per la pagina importazione metadati
 *
 * @package RNDT_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Importa metadati', 'rndt-manager' ); ?></h1>

    <?php settings_errors( 'rndt_manager_import' ); ?>

    <div class="rndt-import-container">
        <div class="card">
            <h2><?php esc_html_e( 'Importa file XML', 'rndt-manager' ); ?></h2>
            <p><?php esc_html_e( 'Carica uno o piu file XML ISO 19139 per importare i metadati nel sistema.', 'rndt-manager' ); ?></p>

            <form id="rndt-import-form" enctype="multipart/form-data">
                <?php wp_nonce_field( 'rndt_import', 'rndt_import_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="xml_files"><?php esc_html_e( 'File XML', 'rndt-manager' ); ?></label>
                        </th>
                        <td>
                            <input type="file" id="xml_files" name="xml_files[]" accept=".xml" multiple required />
                            <p class="description">
                                <?php esc_html_e( 'Seleziona uno o piu file XML (formato ISO 19139).', 'rndt-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Opzioni', 'rndt-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="validate_on_import" value="1" checked />
                                <?php esc_html_e( 'Valida i metadati dopo l\'importazione', 'rndt-manager' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="skip_duplicates" value="1" checked />
                                <?php esc_html_e( 'Salta i metadati con identificativo gia esistente', 'rndt-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="rndt-import-submit">
                        <?php esc_html_e( 'Importa', 'rndt-manager' ); ?>
                    </button>
                    <span class="spinner" style="float:none;"></span>
                </p>
            </form>

            <div id="rndt-import-results" style="display:none;">
                <h3><?php esc_html_e( 'Risultati importazione', 'rndt-manager' ); ?></h3>
                <div id="rndt-import-results-content"></div>
            </div>
        </div>

        <div class="card">
            <h2><?php esc_html_e( 'Importa da URL CSW', 'rndt-manager' ); ?></h2>
            <p><?php esc_html_e( 'Importa metadati da un catalogo CSW esistente.', 'rndt-manager' ); ?></p>

            <form id="rndt-import-csw-form">
                <?php wp_nonce_field( 'rndt_import_csw', 'rndt_import_csw_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="csw_url"><?php esc_html_e( 'URL CSW', 'rndt-manager' ); ?></label>
                        </th>
                        <td>
                            <input type="url" id="csw_url" name="csw_url" class="regular-text" placeholder="https://example.com/csw" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="csw_query"><?php esc_html_e( 'Query (opzionale)', 'rndt-manager' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="csw_query" name="csw_query" class="regular-text" placeholder="AnyText Like '%territorio%'" />
                            <p class="description">
                                <?php esc_html_e( 'Filtro CQL per selezionare i metadati da importare.', 'rndt-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="csw_max_records"><?php esc_html_e( 'Numero massimo record', 'rndt-manager' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="csw_max_records" name="csw_max_records" value="100" min="1" max="1000" />
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="rndt-import-csw-submit">
                        <?php esc_html_e( 'Importa da CSW', 'rndt-manager' ); ?>
                    </button>
                    <span class="spinner" style="float:none;"></span>
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.rndt-import-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}
.rndt-import-container .card {
    max-width: 600px;
    flex: 1;
    min-width: 400px;
}
#rndt-import-results {
    margin-top: 20px;
    padding: 15px;
    background: #f0f0f1;
    border-left: 4px solid #2271b1;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Import da file
    $('#rndt-import-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#rndt-import-submit');
        var $spinner = $form.find('.spinner');
        var formData = new FormData(this);

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: rndtAdmin.restUrl + 'import/xml',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', rndtAdmin.nonce);
            },
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                var html = '<p><strong><?php esc_html_e( 'Importazione completata!', 'rndt-manager' ); ?></strong></p>';
                html += '<ul>';
                html += '<li><?php esc_html_e( 'Importati:', 'rndt-manager' ); ?> ' + response.imported + '</li>';
                html += '<li><?php esc_html_e( 'Saltati:', 'rndt-manager' ); ?> ' + response.skipped + '</li>';
                html += '<li><?php esc_html_e( 'Errori:', 'rndt-manager' ); ?> ' + response.errors + '</li>';
                html += '</ul>';

                if (response.error_details && response.error_details.length > 0) {
                    html += '<p><strong><?php esc_html_e( 'Dettagli errori:', 'rndt-manager' ); ?></strong></p><ul>';
                    response.error_details.forEach(function(err) {
                        html += '<li>' + err + '</li>';
                    });
                    html += '</ul>';
                }

                $('#rndt-import-results-content').html(html);
                $('#rndt-import-results').show();
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php esc_html_e( 'Errore durante l\'importazione.', 'rndt-manager' ); ?>';
                $('#rndt-import-results-content').html('<p style="color:red;">' + msg + '</p>');
                $('#rndt-import-results').show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Import da CSW
    $('#rndt-import-csw-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#rndt-import-csw-submit');
        var $spinner = $form.find('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: rndtAdmin.restUrl + 'import/csw',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', rndtAdmin.nonce);
            },
            data: {
                url: $('#csw_url').val(),
                query: $('#csw_query').val(),
                max_records: $('#csw_max_records').val()
            },
            success: function(response) {
                var html = '<p><strong><?php esc_html_e( 'Importazione completata!', 'rndt-manager' ); ?></strong></p>';
                html += '<ul>';
                html += '<li><?php esc_html_e( 'Importati:', 'rndt-manager' ); ?> ' + response.imported + '</li>';
                html += '<li><?php esc_html_e( 'Errori:', 'rndt-manager' ); ?> ' + response.errors + '</li>';
                html += '</ul>';

                $('#rndt-import-results-content').html(html);
                $('#rndt-import-results').show();
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php esc_html_e( 'Errore durante l\'importazione.', 'rndt-manager' ); ?>';
                $('#rndt-import-results-content').html('<p style="color:red;">' + msg + '</p>');
                $('#rndt-import-results').show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>
