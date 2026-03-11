/**
 * WP File Guardian — Admin JavaScript
 */
(function($) {
    'use strict';

    var WPFG = {

        init: function() {
            this.bindScan();
            this.bindQuarantine();
            this.bindBackups();
            this.bindRepair();
            this.bindLogs();
            this.bindSettings();
            this.bindBulk();
            this.bindModal();
            this.bindFileActions();
            this.bindDBScanner();
            this.bindMonitor();
        },

        // --- AJAX helper ---
        ajax: function(action, data, callback) {
            data = data || {};
            data.action = action;
            data.nonce = wpfg.nonce;
            $.post(wpfg.ajax_url, data, function(response) {
                if (callback) callback(response);
            }).fail(function() {
                alert(wpfg.i18n.error);
            });
        },

        // --- Scanner ---
        bindScan: function() {
            var self = this;
            var scanning = false;
            var sessionId = 0;

            $('#wpfg-start-scan').on('click', function() {
                if (scanning) return;
                scanning = true;
                var type = $('#wpfg-scan-type').val();
                $(this).prop('disabled', true);
                $('#wpfg-cancel-scan').show();
                $('#wpfg-scan-progress').show();
                $('#wpfg-scan-status').text(wpfg.i18n.scanning);
                $('#wpfg-progress-fill').css('width', '0%');

                self.ajax('wpfg_start_scan', { scan_type: type }, function(resp) {
                    if (resp.success) {
                        sessionId = resp.data.session_id;
                        self.processBatch(sessionId, 0, resp.data.total);
                    } else {
                        scanning = false;
                        $('#wpfg-start-scan').prop('disabled', false);
                        $('#wpfg-scan-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $('#wpfg-cancel-scan').on('click', function() {
                if (!sessionId) return;
                self.ajax('wpfg_cancel_scan', { session_id: sessionId }, function() {
                    scanning = false;
                    $('#wpfg-start-scan').prop('disabled', false);
                    $('#wpfg-cancel-scan').hide();
                    $('#wpfg-scan-status').text('Cancelled.');
                });
            });
        },

        processBatch: function(sessionId, offset, total) {
            var self = this;
            this.ajax('wpfg_scan_batch', { session_id: sessionId, offset: offset }, function(resp) {
                if (!resp.success) {
                    $('#wpfg-scan-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    $('#wpfg-start-scan').prop('disabled', false);
                    return;
                }
                var d = resp.data;
                var pct = total > 0 ? Math.round((d.processed / total) * 100) : 100;
                $('#wpfg-progress-fill').css('width', pct + '%');
                $('#wpfg-scan-status').text(d.processed + ' / ' + total + ' files (' + pct + '%)');

                if (d.done) {
                    $('#wpfg-scan-status').text(wpfg.i18n.scan_complete + ' ' + d.processed + ' files, ' + d.issues + ' issues in this batch.');
                    $('#wpfg-start-scan').prop('disabled', false);
                    $('#wpfg-cancel-scan').hide();
                    // Redirect to results.
                    window.location.href = wpfg.ajax_url.replace('admin-ajax.php', 'admin.php') + '?page=wpfg-scanner&session=' + sessionId;
                } else {
                    self.processBatch(sessionId, d.processed, total);
                }
            });
        },

        // --- Quarantine ---
        bindQuarantine: function() {
            var self = this;
            $(document).on('click', '.wpfg-q-action', function() {
                var btn = $(this);
                var action = btn.data('action');
                var id = btn.data('id');

                if (action === 'restore' && !confirm(wpfg.i18n.confirm_restore)) return;
                if (action === 'delete' && !confirm(wpfg.i18n.confirm_delete)) return;

                var ajaxAction = action === 'restore' ? 'wpfg_restore_quarantine' : 'wpfg_delete_quarantine';
                btn.prop('disabled', true);
                self.ajax(ajaxAction, { id: id }, function(resp) {
                    if (resp.success) {
                        btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                        btn.prop('disabled', false);
                    }
                });
            });
        },

        // --- Backups ---
        bindBackups: function() {
            var self = this;

            $('#wpfg-create-backup').on('click', function() {
                var btn = $(this);
                var type = $('#wpfg-backup-type').val();
                btn.prop('disabled', true);
                $('#wpfg-backup-status').text('Creating backup...');

                self.ajax('wpfg_create_backup', { backup_type: type }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        $('#wpfg-backup-status').text('Backup created! Refreshing...');
                        location.reload();
                    } else {
                        $('#wpfg-backup-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $(document).on('click', '.wpfg-backup-action', function() {
                var btn = $(this);
                var action = btn.data('action');
                var id = btn.data('id');

                if (action === 'delete' && !confirm(wpfg.i18n.confirm_delete)) return;
                if (action === 'restore' && !confirm(wpfg.i18n.confirm_restore)) return;

                var ajaxAction = action === 'restore' ? 'wpfg_restore_backup' : 'wpfg_delete_backup';
                btn.prop('disabled', true);

                self.ajax(ajaxAction, { id: id }, function(resp) {
                    if (resp.success) {
                        if (action === 'delete') {
                            btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                        } else {
                            alert('Backup restored successfully.');
                            btn.prop('disabled', false);
                        }
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                        btn.prop('disabled', false);
                    }
                });
            });
        },

        // --- Repair ---
        bindRepair: function() {
            var self = this;

            $('#wpfg-verify-core').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-verify-status').text('Verifying...');

                self.ajax('wpfg_verify_core', {}, function(resp) {
                    btn.prop('disabled', false);
                    $('#wpfg-verify-status').text('');
                    $('#wpfg-core-results').show();

                    if (!resp.success) {
                        $('#wpfg-core-summary').html('<p class="wpfg-text-critical">' + (resp.data ? resp.data.message : wpfg.i18n.error) + '</p>');
                        return;
                    }

                    var d = resp.data;
                    var html = '<p><strong>Verified:</strong> ' + d.verified + ' files OK</p>';
                    html += '<p><strong>Modified:</strong> ' + d.modified.length + '</p>';
                    html += '<p><strong>Missing:</strong> ' + d.missing.length + '</p>';
                    $('#wpfg-core-summary').html(html);

                    // Modified files table.
                    if (d.modified.length > 0) {
                        $('#wpfg-modified-section').show();
                        var tbody = '';
                        d.modified.forEach(function(m) {
                            tbody += '<tr>';
                            tbody += '<td><input type="checkbox" class="wpfg-repair-file" value="' + m.file + '" checked /></td>';
                            tbody += '<td><code>' + m.file + '</code></td>';
                            tbody += '<td><code>' + m.expected.substring(0, 12) + '...</code></td>';
                            tbody += '<td><code>' + m.actual.substring(0, 12) + '...</code></td>';
                            tbody += '</tr>';
                        });
                        $('#wpfg-modified-tbody').html(tbody);
                    }

                    // Missing files.
                    if (d.missing.length > 0) {
                        $('#wpfg-missing-section').show();
                        var list = '';
                        d.missing.forEach(function(f) {
                            list += '<li><code>' + f + '</code> <input type="checkbox" class="wpfg-repair-file" value="' + f + '" checked /></li>';
                        });
                        $('#wpfg-missing-list').html(list);
                    }

                    if (d.modified.length > 0 || d.missing.length > 0) {
                        $('#wpfg-repair-actions').show();
                    }
                });
            });

            $('#wpfg-repair-select-all').on('change', function() {
                $('.wpfg-repair-file').prop('checked', this.checked);
            });

            function getSelectedRepairFiles() {
                var files = [];
                $('.wpfg-repair-file:checked').each(function() { files.push($(this).val()); });
                return files;
            }

            $('#wpfg-repair-dry-run').on('click', function() {
                var files = getSelectedRepairFiles();
                if (!files.length) { alert('No files selected.'); return; }
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-repair-status').text('Running dry run...');

                self.ajax('wpfg_repair_core', { files: files, dry_run: 1 }, function(resp) {
                    btn.prop('disabled', false);
                    $('#wpfg-repair-status').text('');
                    if (resp.success) {
                        var html = '<h4>Dry Run Preview:</h4><ul>';
                        resp.data.repaired.forEach(function(r) {
                            html += '<li><code>' + r.file + '</code> — ' + r.action + ' (' + r.size + ' bytes)</li>';
                        });
                        html += '</ul>';
                        if (resp.data.errors.length) {
                            html += '<p class="wpfg-text-critical">Errors: ' + resp.data.errors.join(', ') + '</p>';
                        }
                        $('#wpfg-repair-preview').html(html).show();
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $('#wpfg-repair-execute').on('click', function() {
                var files = getSelectedRepairFiles();
                if (!files.length) { alert('No files selected.'); return; }
                if (!confirm(wpfg.i18n.confirm_repair)) return;

                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-repair-status').text('Repairing...');

                self.ajax('wpfg_repair_core', { files: files, dry_run: 0 }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        $('#wpfg-repair-status').text('Repair complete! ' + resp.data.repaired.length + ' files fixed.');
                        if (resp.data.errors.length) {
                            alert('Some errors: ' + resp.data.errors.join('\n'));
                        }
                    } else {
                        $('#wpfg-repair-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Reinstall.
            $('#wpfg-reinstall-preview, #wpfg-reinstall-execute').on('click', function() {
                var type = $('#wpfg-reinstall-type').val();
                var slug = $('#wpfg-reinstall-slug').val().trim();
                var dryRun = $(this).attr('id') === 'wpfg-reinstall-preview' ? 1 : 0;

                if (!slug) { alert('Enter a slug.'); return; }
                if (!dryRun && !confirm('Reinstall ' + type + ': ' + slug + '?')) return;

                var action = type === 'plugin' ? 'wpfg_reinstall_plugin' : 'wpfg_reinstall_theme';
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-reinstall-status').text(dryRun ? 'Checking...' : 'Reinstalling...');

                self.ajax(action, { slug: slug, dry_run: dryRun }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        var msg = dryRun
                            ? 'Available: ' + resp.data.slug + ' v' + resp.data.version
                            : 'Reinstalled: ' + resp.data.slug + ' v' + resp.data.version;
                        $('#wpfg-reinstall-status').text(msg);
                    } else {
                        $('#wpfg-reinstall-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });
        },

        // --- Logs ---
        bindLogs: function() {
            var self = this;

            $('#wpfg-export-logs').on('click', function() {
                self.ajax('wpfg_export_logs', {}, function(resp) {
                    if (resp.success && resp.data.csv) {
                        var blob = new Blob([resp.data.csv], { type: 'text/csv' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'wpfg-audit-log.csv';
                        a.click();
                        URL.revokeObjectURL(url);
                    }
                });
            });

            $('#wpfg-clear-logs').on('click', function() {
                if (!confirm(wpfg.i18n.confirm_delete)) return;
                self.ajax('wpfg_clear_logs', {}, function(resp) {
                    if (resp.success) location.reload();
                });
            });
        },

        // --- Settings ---
        bindSettings: function() {
            var self = this;
            $('#wpfg-export-settings').on('click', function() {
                self.ajax('wpfg_export_settings', {}, function(resp) {
                    if (resp.success) {
                        var blob = new Blob([resp.data.json], { type: 'application/json' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'wpfg-settings.json';
                        a.click();
                        URL.revokeObjectURL(url);
                    }
                });
            });
        },

        // --- File Action Buttons (scanner results) ---
        bindFileActions: function() {
            var self = this;

            $(document).on('click', '.wpfg-action-btn', function() {
                var btn = $(this);
                var action = btn.data('action');

                if (action === 'quarantine') {
                    var path = btn.data('path');
                    if (!confirm(wpfg.i18n.confirm_quarantine)) return;
                    btn.prop('disabled', true);
                    self.ajax('wpfg_quarantine_file', { path: path, reason: 'Quarantined from scan results' }, function(resp) {
                        if (resp.success) {
                            btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                        } else {
                            alert(resp.data ? resp.data.message : wpfg.i18n.error);
                            btn.prop('disabled', false);
                        }
                    });
                }

                if (action === 'ignore') {
                    var id = btn.data('id');
                    btn.prop('disabled', true);
                    self.ajax('wpfg_ignore_result', { result_id: id, ignore: 1 }, function(resp) {
                        if (resp.success) {
                            btn.closest('tr').addClass('wpfg-ignored');
                        }
                        btn.prop('disabled', false);
                    });
                }

                if (action === 'info') {
                    var path = btn.data('path');
                    self.ajax('wpfg_file_info', { path: path }, function(resp) {
                        if (resp.success) {
                            var d = resp.data;
                            var html = '<table class="widefat">';
                            html += '<tr><td><strong>Path</strong></td><td><code>' + d.relative + '</code></td></tr>';
                            html += '<tr><td><strong>Size</strong></td><td>' + d.size_formatted + '</td></tr>';
                            html += '<tr><td><strong>Modified</strong></td><td>' + d.modified_formatted + '</td></tr>';
                            html += '<tr><td><strong>Permissions</strong></td><td>' + d.permissions + '</td></tr>';
                            html += '<tr><td><strong>Extension</strong></td><td>' + d.extension + '</td></tr>';
                            html += '<tr><td><strong>MD5 Hash</strong></td><td><code>' + d.hash + '</code></td></tr>';
                            html += '<tr><td><strong>Writable</strong></td><td>' + (d.is_writable ? 'Yes' : 'No') + '</td></tr>';
                            html += '</table>';
                            self.showModal(html);
                        } else {
                            alert(resp.data ? resp.data.message : wpfg.i18n.error);
                        }
                    });
                }
            });
        },

        // --- Bulk Actions ---
        bindBulk: function() {
            var self = this;

            // Select all checkbox.
            $('#wpfg-select-all').on('change', function() {
                $('input[name="selected[]"]').prop('checked', this.checked);
            });

            $('#wpfg-apply-bulk').on('click', function() {
                var action = $('#wpfg-bulk-action').val();
                if (!action) return;
                var paths = [];
                $('input[name="selected[]"]:checked').each(function() {
                    paths.push($(this).val());
                });
                if (!paths.length) { alert('No items selected.'); return; }
                if (!confirm(wpfg.i18n.confirm_bulk)) return;

                self.ajax('wpfg_bulk_action', { bulk_action: action, paths: paths }, function(resp) {
                    if (resp.success) {
                        alert('Done: ' + resp.data.success + ' success, ' + resp.data.failed + ' failed.');
                        location.reload();
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });
        },

        // --- Modal ---
        bindModal: function() {
            $(document).on('click', '.wpfg-modal-close', function() {
                $(this).closest('.wpfg-modal').hide();
            });
            $(document).on('click', '.wpfg-modal', function(e) {
                if (e.target === this) $(this).hide();
            });
        },

        showModal: function(html) {
            $('#wpfg-modal-body').html(html);
            $('#wpfg-modal').show();
        },

        // --- DB Scanner ---
        bindDBScanner: function() {
            var self = this;
            var dbSessionId = 0;
            var dbSources = ['posts', 'options', 'comments', 'users', 'cron'];
            var sourceIdx = 0;
            var totalFindings = 0;

            $('#wpfg-start-db-scan').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-db-scan-progress').show();
                $('#wpfg-db-scan-status').text('Starting...');
                sourceIdx = 0;
                totalFindings = 0;

                self.ajax('wpfg_db_scan_start', {}, function(resp) {
                    if (resp.success) {
                        dbSessionId = resp.data.session_id;
                        self.dbScanNext(dbSessionId, dbSources, sourceIdx, totalFindings);
                    } else {
                        btn.prop('disabled', false);
                        $('#wpfg-db-scan-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });
        },

        dbScanNext: function(sessionId, sources, idx, totalFindings) {
            var self = this;
            if (idx >= sources.length) {
                $('#wpfg-db-progress-fill').css('width', '100%');
                $('#wpfg-db-scan-msg').text('Complete! ' + totalFindings + ' findings. Reloading...');
                $('#wpfg-start-db-scan').prop('disabled', false);
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }

            var source = sources[idx];
            var pct = Math.round((idx / sources.length) * 100);
            $('#wpfg-db-progress-fill').css('width', pct + '%');
            $('#wpfg-db-scan-msg').text('Scanning ' + source + '...');

            self.dbScanSource(sessionId, source, 0, sources, idx, totalFindings);
        },

        dbScanSource: function(sessionId, source, offset, sources, idx, totalFindings) {
            var self = this;
            self.ajax('wpfg_db_scan_batch', {
                session_id: sessionId,
                source: source,
                offset: offset
            }, function(resp) {
                if (!resp.success) {
                    $('#wpfg-db-scan-msg').text('Error: ' + (resp.data ? resp.data.message : wpfg.i18n.error));
                    return;
                }
                var d = resp.data;
                totalFindings += d.findings;

                if (d.done) {
                    // Move to next source.
                    self.dbScanNext(sessionId, sources, idx + 1, totalFindings);
                } else {
                    // Continue same source with new offset.
                    self.dbScanSource(sessionId, source, d.processed, sources, idx, totalFindings);
                }
            });
        },

        // --- File Monitor ---
        bindMonitor: function() {
            var self = this;

            $('#wpfg-build-baseline').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-monitor-status').text('Building baseline...');

                self.ajax('wpfg_build_baseline', {}, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        $('#wpfg-monitor-status').text('Baseline built: ' + resp.data.count + ' files indexed.');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $('#wpfg-monitor-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $('#wpfg-compare-files').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-monitor-status').text('Comparing...');

                self.ajax('wpfg_compare_files', {}, function(resp) {
                    btn.prop('disabled', false);
                    $('#wpfg-monitor-status').text('');

                    if (!resp.success) {
                        $('#wpfg-monitor-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                        return;
                    }

                    var d = resp.data;
                    $('#wpfg-compare-results').show();
                    $('#wpfg-mon-added').text(d.total_added);
                    $('#wpfg-mon-modified').text(d.total_modified);
                    $('#wpfg-mon-deleted').text(d.total_deleted);

                    var html = '';
                    if (d.added.length) {
                        html += '<h4>Added Files (' + d.added.length + ')</h4><ul>';
                        d.added.forEach(function(f) { html += '<li><code>' + f.path + '</code></li>'; });
                        html += '</ul>';
                    }
                    if (d.modified.length) {
                        html += '<h4>Modified Files (' + d.modified.length + ')</h4><ul>';
                        d.modified.forEach(function(f) { html += '<li><code>' + f.path + '</code></li>'; });
                        html += '</ul>';
                    }
                    if (d.deleted.length) {
                        html += '<h4>Deleted Files (' + d.deleted.length + ')</h4><ul>';
                        d.deleted.forEach(function(f) { html += '<li><code>' + f.path + '</code></li>'; });
                        html += '</ul>';
                    }
                    if (!d.total_added && !d.total_modified && !d.total_deleted) {
                        html = '<p>No changes detected since last baseline.</p>';
                    }
                    $('#wpfg-mon-details').html(html);
                });
            });

            // View change history details.
            $(document).on('click', '.wpfg-view-changes', function() {
                var details = $(this).data('details');
                if (typeof details === 'string') {
                    try { details = JSON.parse(details); } catch(e) { details = {}; }
                }
                var html = '';
                if (details.added && details.added.length) {
                    html += '<h4>Added</h4><ul>';
                    details.added.forEach(function(f) { html += '<li><code>' + f.path + '</code></li>'; });
                    html += '</ul>';
                }
                if (details.modified && details.modified.length) {
                    html += '<h4>Modified</h4><ul>';
                    details.modified.forEach(function(f) { html += '<li><code>' + f.path + '</code></li>'; });
                    html += '</ul>';
                }
                if (details.deleted && details.deleted.length) {
                    html += '<h4>Deleted</h4><ul>';
                    details.deleted.forEach(function(f) { html += '<li><code>' + f.path + '</code></li>'; });
                    html += '</ul>';
                }
                self.showModal(html || '<p>No details available.</p>');
            });
        }
    };

    $(document).ready(function() {
        WPFG.init();
    });

})(jQuery);
