/**
 * RNDT Manager - Admin JavaScript
 *
 * @package RNDT_Manager
 */

(function($) {
    'use strict';

    /**
     * RNDT Admin Module
     */
    var RNDTAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSettingsPage();
            this.initImportPage();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Confirm delete actions
            $(document).on('click', '.rndt-delete-confirm', this.confirmDelete);

            // Initialize tooltips if available
            if ($.fn.tooltip) {
                $('.rndt-tooltip').tooltip();
            }
        },

        /**
         * Initialize settings page
         * Note: Connection test handlers are in rndt-settings-display.php inline script
         * to keep all settings-related JS together with the "Create tables" handler
         */
        initSettingsPage: function() {
            // Placeholder - handlers are in inline script for settings page
        },

        /**
         * Initialize import page
         */
        initImportPage: function() {
            var self = this;
            var $dropzone = $('.rndt-import-dropzone');
            var $fileInput = $dropzone.find('input[type="file"]');
            var $fileList = $('.rndt-import-files');
            var $progress = $('.rndt-import-progress');
            var selectedFiles = [];

            if (!$dropzone.length) {
                return;
            }

            // Click to select files
            $dropzone.on('click', function(e) {
                if ($(e.target).is('button, input')) return;
                $fileInput.click();
            });

            // Drag and drop events
            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $dropzone.on('dragleave drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $dropzone.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                self.handleFiles(files, selectedFiles, $fileList);
            });

            // File input change
            $fileInput.on('change', function() {
                self.handleFiles(this.files, selectedFiles, $fileList);
            });

            // Remove file
            $fileList.on('click', '.remove', function() {
                var index = $(this).data('index');
                selectedFiles.splice(index, 1);
                self.renderFileList(selectedFiles, $fileList);
            });

            // Start import
            $('#rndt-start-import').on('click', function() {
                if (selectedFiles.length === 0) {
                    alert('Seleziona almeno un file da importare.');
                    return;
                }
                self.startImport(selectedFiles, $progress);
            });

            // CSW Import
            $('#rndt-csw-search').on('click', function() {
                self.searchCSWRecords();
            });

            $('#rndt-csw-import-selected').on('click', function() {
                self.importSelectedCSWRecords();
            });
        },

        /**
         * Handle dropped/selected files
         */
        handleFiles: function(files, selectedFiles, $fileList) {
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                if (file.name.toLowerCase().endsWith('.xml')) {
                    selectedFiles.push(file);
                }
            }
            this.renderFileList(selectedFiles, $fileList);
        },

        /**
         * Render file list
         */
        renderFileList: function(files, $container) {
            $container.empty();

            files.forEach(function(file, index) {
                var html = '<div class="rndt-import-file">' +
                    '<span class="dashicons dashicons-media-code"></span>' +
                    '<span class="filename">' + file.name + '</span>' +
                    '<span class="filesize">' + RNDTAdmin.formatFileSize(file.size) + '</span>' +
                    '<span class="remove dashicons dashicons-no-alt" data-index="' + index + '"></span>' +
                    '</div>';
                $container.append(html);
            });
        },

        /**
         * Start import process
         */
        startImport: function(files, $progress) {
            var self = this;
            var total = files.length;
            var current = 0;
            var results = [];

            $progress.addClass('active');
            self.updateProgress($progress, 0, total, 'Importazione in corso...');

            function importNext() {
                if (current >= total) {
                    self.showImportResults(results);
                    $progress.removeClass('active');
                    return;
                }

                var file = files[current];
                var formData = new FormData();
                formData.append('file', file);

                $.ajax({
                    url: rndtAdmin.restUrl + 'import/file',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': rndtAdmin.nonce
                    },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        results.push({
                            file: file.name,
                            success: true,
                            id: response.id,
                            title: response.title
                        });
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error';
                        results.push({
                            file: file.name,
                            success: false,
                            error: message
                        });
                    },
                    complete: function() {
                        current++;
                        self.updateProgress($progress, current, total, 'Importazione ' + current + ' di ' + total + '...');
                        importNext();
                    }
                });
            }

            importNext();
        },

        /**
         * Update progress bar
         */
        updateProgress: function($progress, current, total, text) {
            var percentage = total > 0 ? (current / total * 100) : 0;
            $progress.find('.rndt-progress-bar-fill').css('width', percentage + '%');
            $progress.find('.rndt-progress-text').text(text);
        },

        /**
         * Show import results
         */
        showImportResults: function(results) {
            var $container = $('.rndt-import-results');
            $container.empty();

            results.forEach(function(result) {
                var iconClass = result.success ? 'dashicons-yes-alt' : 'dashicons-no-alt';
                var statusClass = result.success ? 'success' : 'error';
                var message = result.success
                    ? result.title + ' (ID: ' + result.id + ')'
                    : result.error;

                var html = '<div class="rndt-import-result-item ' + statusClass + '">' +
                    '<span class="dashicons ' + iconClass + '"></span>' +
                    '<span><strong>' + result.file + ':</strong> ' + message + '</span>' +
                    '</div>';
                $container.append(html);
            });

            $container.show();
        },

        /**
         * Search CSW records
         */
        searchCSWRecords: function() {
            var cswUrl = $('#rndt-csw-url').val();
            var searchTerm = $('#rndt-csw-search-term').val();
            var $results = $('.rndt-csw-results');

            if (!cswUrl) {
                alert('Inserisci l\'URL del catalogo CSW');
                return;
            }

            $results.html('<div class="rndt-loading"><span class="spinner is-active"></span></div>');

            $.ajax({
                url: rndtAdmin.restUrl + 'import/csw',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': rndtAdmin.nonce
                },
                data: JSON.stringify({
                    csw_url: cswUrl,
                    search_term: searchTerm,
                    action: 'search'
                }),
                contentType: 'application/json',
                success: function(response) {
                    RNDTAdmin.renderCSWRecords(response.records, $results);
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error';
                    $results.html('<div class="notice notice-error"><p>' + message + '</p></div>');
                }
            });
        },

        /**
         * Render CSW search results
         */
        renderCSWRecords: function(records, $container) {
            $container.empty();

            if (!records || records.length === 0) {
                $container.html('<p>Nessun record trovato.</p>');
                return;
            }

            records.forEach(function(record) {
                var html = '<div class="rndt-csw-record">' +
                    '<input type="checkbox" value="' + record.identifier + '">' +
                    '<div class="details">' +
                    '<span class="title">' + record.title + '</span>' +
                    '<span class="identifier">' + record.identifier + '</span>' +
                    '</div>' +
                    '</div>';
                $container.append(html);
            });
        },

        /**
         * Import selected CSW records
         */
        importSelectedCSWRecords: function() {
            var cswUrl = $('#rndt-csw-url').val();
            var $selected = $('.rndt-csw-record input:checked');
            var identifiers = [];

            $selected.each(function() {
                identifiers.push($(this).val());
            });

            if (identifiers.length === 0) {
                alert('Seleziona almeno un record da importare');
                return;
            }

            var $results = $('.rndt-import-results');
            $results.html('<div class="rndt-loading"><span class="spinner is-active"></span></div>');

            $.ajax({
                url: rndtAdmin.restUrl + 'import/csw',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': rndtAdmin.nonce
                },
                data: JSON.stringify({
                    csw_url: cswUrl,
                    identifiers: identifiers,
                    action: 'import'
                }),
                contentType: 'application/json',
                success: function(response) {
                    RNDTAdmin.showImportResults(response.results);
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error';
                    $results.html('<div class="notice notice-error"><p>' + message + '</p></div>');
                }
            });
        },

        /**
         * Confirm delete action
         */
        confirmDelete: function(e) {
            var message = $(this).data('confirm') || rndtAdmin.i18n.confirm;
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            type = type || 'info';
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.wrap h1').first().after($notice);

            // Make it dismissible
            $notice.find('button.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Validate UUID format
         */
        isValidUUID: function(uuid) {
            var pattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
            return pattern.test(uuid);
        },

        /**
         * Generate UUID v4
         */
        generateUUID: function() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RNDTAdmin.init();
    });

    // Expose to global scope
    window.RNDTAdmin = RNDTAdmin;

})(jQuery);
