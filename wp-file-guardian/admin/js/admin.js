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
            this.bindDbActions();
            this.bindMonitor();
            this.bindHardening();
            this.bindFirewall();
            this.bindVulnScanner();
            this.bindSecurityScore();
            this.bind2FA();
            this.bindPermissions();
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
            var seenThreats = {};

            $('.wpfg-start-scan').on('click', function() {
                if (scanning) return;
                scanning = true;
                seenThreats = {};
                var type = $(this).data('scan-type') || 'full';
                $('.wpfg-start-scan').prop('disabled', true);
                $(this).addClass('wpfg-btn-scanning');
                $('#wpfg-cancel-scan').show();
                $('#wpfg-scan-progress').slideDown(300);
                $('#wpfg-live-threats').hide();
                $('#wpfg-live-threats-list').empty();
                $('#wpfg-scan-status').text(wpfg.i18n.scanning);
                $('#wpfg-progress-fill').css('width', '0%');
                self.updateRing(0);
                $('#wpfg-scan-files-count').text('0');
                $('#wpfg-scan-issues-count').text('0');
                $('#wpfg-live-file-path').text('');

                self.ajax('wpfg_start_scan', { scan_type: type }, function(resp) {
                    if (resp.success) {
                        sessionId = resp.data.session_id;
                        self.processBatch(sessionId, 0, resp.data.total);
                    } else {
                        scanning = false;
                        $('.wpfg-start-scan').prop('disabled', false).removeClass('wpfg-btn-scanning');
                        $('#wpfg-scan-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $('#wpfg-cancel-scan').on('click', function() {
                if (!sessionId) return;
                self.ajax('wpfg_cancel_scan', { session_id: sessionId }, function() {
                    scanning = false;
                    $('.wpfg-start-scan').prop('disabled', false).removeClass('wpfg-btn-scanning');
                    $('#wpfg-cancel-scan').hide();
                    $('#wpfg-scan-status').text('Cancelled.');
                    $('#wpfg-live-file-path').text('');
                });
            });
        },

        updateRing: function(pct) {
            var circumference = 2 * Math.PI * 50; // r=50
            var offset = circumference - (pct / 100) * circumference;
            $('#wpfg-ring-fill').css('stroke-dashoffset', offset);
            $('#wpfg-progress-pct').text(pct);
        },

        processBatch: function(sessionId, offset, total) {
            var self = this;
            this.ajax('wpfg_scan_batch', { session_id: sessionId, offset: offset }, function(resp) {
                if (!resp.success) {
                    $('#wpfg-scan-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    $('.wpfg-start-scan').prop('disabled', false).removeClass('wpfg-btn-scanning');
                    return;
                }
                var d = resp.data;
                var pct = total > 0 ? Math.round((d.processed / total) * 100) : 100;
                $('#wpfg-progress-fill').css('width', pct + '%');
                self.updateRing(pct);
                $('#wpfg-scan-status').text(d.processed.toLocaleString() + ' / ' + total.toLocaleString() + ' files');
                $('#wpfg-scan-files-count').text(d.processed.toLocaleString());
                $('#wpfg-scan-issues-count').text((d.total_issues || 0).toLocaleString());

                // Live file path ticker.
                if (d.current_file) {
                    $('#wpfg-live-file-path').text(d.current_file);
                }

                // Pulse the issues counter when threats found.
                if (d.total_issues > 0) {
                    $('#wpfg-scan-issues-count').addClass('wpfg-pulse');
                    setTimeout(function() { $('#wpfg-scan-issues-count').removeClass('wpfg-pulse'); }, 600);
                }

                // Live threat feed — show new threats as they are found.
                if (d.batch_threats && d.batch_threats.length) {
                    $('#wpfg-live-threats').slideDown(200);
                    d.batch_threats.forEach(function(t) {
                        if (self._seenThreats && self._seenThreats[t.file]) return;
                        if (!self._seenThreats) self._seenThreats = {};
                        self._seenThreats[t.file] = true;

                        var sevClass = t.severity === 'critical' ? 'wpfg-threat-critical' : 'wpfg-threat-warning';
                        var sevLabel = t.severity === 'critical' ? 'CRITICAL' : 'WARNING';
                        var $row = $('<div class="wpfg-threat-item ' + sevClass + '">' +
                            '<span class="wpfg-threat-badge">' + sevLabel + '</span>' +
                            '<code class="wpfg-threat-file">' + self.escHtml(t.file) + '</code>' +
                            '<span class="wpfg-threat-desc">' + self.escHtml(t.desc) + '</span>' +
                            '</div>').hide();
                        $('#wpfg-live-threats-list').prepend($row);
                        $row.slideDown(200);

                        // Keep max 10 items visible.
                        var items = $('#wpfg-live-threats-list .wpfg-threat-item');
                        if (items.length > 10) items.last().slideUp(200, function() { $(this).remove(); });
                    });
                }

                if (d.done) {
                    self.updateRing(100);
                    var doneMsg = wpfg.i18n.scan_complete + ' — ' + (d.total_issues || 0) + ' threat' + ((d.total_issues || 0) !== 1 ? 's' : '') + ' found.';
                    $('#wpfg-scan-status').html('<strong>' + doneMsg + '</strong>');
                    $('#wpfg-live-file-path').text('✓ Scan complete');
                    $('.wpfg-start-scan').prop('disabled', false).removeClass('wpfg-btn-scanning');
                    $('#wpfg-cancel-scan').hide();
                    setTimeout(function() {
                        window.location.href = wpfg.ajax_url.replace('admin-ajax.php', 'admin.php') + '?page=wpfg-scanner&session=' + sessionId;
                    }, 1500);
                } else {
                    self.processBatch(sessionId, d.processed, total);
                }
            });
        },

        escHtml: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
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
                $('#wpfg-backup-status').text('Creating backup... This may take several minutes for large sites. Please wait.');

                $.ajax({
                    url: wpfg.ajax_url,
                    type: 'POST',
                    data: { action: 'wpfg_create_backup', nonce: wpfg.nonce, backup_type: type },
                    timeout: 600000, // 10 minute timeout for large backups.
                    success: function(resp) {
                        btn.prop('disabled', false);
                        if (resp.success) {
                            $('#wpfg-backup-status').text('Backup created! Refreshing...');
                            location.reload();
                        } else {
                            $('#wpfg-backup-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                        }
                    },
                    error: function(xhr, status) {
                        btn.prop('disabled', false);
                        if (status === 'timeout') {
                            $('#wpfg-backup-status').text('Backup timed out. Try a smaller backup type (Plugins, Themes, or Uploads).');
                        } else {
                            $('#wpfg-backup-status').text('Backup failed. The server may have run out of time or memory.');
                        }
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

            // Revision cleanup.
            $('#wpfg-check-revisions').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-revision-status').text('Checking...');
                self.ajax('wpfg_revision_stats', {}, function(resp) {
                    btn.prop('disabled', false);
                    $('#wpfg-revision-status').text('');
                    if (resp.success) {
                        var d = resp.data;
                        $('#wpfg-revision-stats').show();
                        $('#wpfg-rev-total span').text(d.total_revisions.toLocaleString());
                        var sizeMB = (d.total_size / 1024 / 1024).toFixed(2);
                        $('#wpfg-rev-size span').text(sizeMB + ' MB');
                        $('#wpfg-rev-drafts span').text(d.auto_drafts.toLocaleString());
                    } else {
                        $('#wpfg-revision-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $('#wpfg-cleanup-revisions').on('click', function() {
                var keep = parseInt($('#wpfg-keep-revisions').val(), 10);
                var msg = keep > 0
                    ? 'Her gönderi için ' + keep + ' revizyon tutulacak, geri kalanlar silinecek. Devam edilsin mi?'
                    : 'Tüm revizyonlar silinecek. Bu işlem geri alınamaz. Devam edilsin mi?';
                if (!confirm(msg)) return;

                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-revision-status').text('Cleaning up...');
                self.ajax('wpfg_cleanup_revisions', { keep_per_post: keep }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        var d = resp.data;
                        var freedMB = (d.freed_estimate / 1024 / 1024).toFixed(2);
                        $('#wpfg-revision-status').text(
                            d.deleted_revisions + ' revision deleted, ~' + freedMB + ' MB freed.'
                        );
                        // Refresh stats.
                        $('#wpfg-check-revisions').trigger('click');
                    } else {
                        $('#wpfg-revision-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

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

        // --- DB Scanner Result Actions ---
        bindDbActions: function() {
            var self = this;

            // Ignore finding.
            $(document).on('click', '.wpfg-db-ignore-item', function() {
                var btn = $(this);
                var id = btn.data('id');
                btn.prop('disabled', true);
                self.ajax('wpfg_db_ignore_finding', { id: id }, function(resp) {
                    if (resp.success) {
                        $('#wpfg-db-row-' + id).fadeOut(300, function() { $(this).remove(); });
                    } else {
                        btn.prop('disabled', false);
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // View item details.
            $(document).on('click', '.wpfg-db-view-item', function() {
                var btn = $(this);
                var source = btn.data('source');
                var rowId = btn.data('row-id');
                btn.prop('disabled', true);
                self.ajax('wpfg_db_view_item', { source: source, row_id: rowId }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        alert(resp.data.content);
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Clean item.
            $(document).on('click', '.wpfg-db-clean-item', function() {
                if (!confirm(wpfg.i18n.confirm_delete || 'Are you sure? This will permanently delete this item.')) return;
                var btn = $(this);
                var source = btn.data('source');
                var rowId = btn.data('row-id');
                var row = btn.closest('tr');
                btn.prop('disabled', true);
                self.ajax('wpfg_db_clean_item', { source: source, row_id: rowId }, function(resp) {
                    if (resp.success) {
                        row.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        btn.prop('disabled', false);
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Ignore All — dismiss every visible finding with a single bulk request.
            $('#wpfg-db-ignore-all').on('click', function() {
                var rows = $('table.wpfg-table tbody tr:visible');
                if (!rows.length) return;
                if (!confirm('Ignore all ' + rows.length + ' findings?')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('Ignoring...');
                var ids = [];
                rows.each(function() {
                    var ignoreBtn = $(this).find('.wpfg-db-ignore-item');
                    if (ignoreBtn.length) ids.push(ignoreBtn.data('id'));
                });
                self.ajax('wpfg_db_ignore_finding', { 'ids[]': ids }, function(resp) {
                    if (resp.success) {
                        rows.fadeOut(300);
                        btn.text('Done! Refreshing...');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        btn.prop('disabled', false).text('Ignore All');
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Ignore All Info — dismiss only INFO-level findings.
            $('#wpfg-db-ignore-all-info').on('click', function() {
                var rows = $('table.wpfg-table tbody tr.wpfg-row-info:visible');
                if (!rows.length) { alert('No INFO findings to ignore.'); return; }
                if (!confirm('Ignore all ' + rows.length + ' INFO findings?')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('Ignoring...');
                var ids = [];
                rows.each(function() {
                    var ignoreBtn = $(this).find('.wpfg-db-ignore-item');
                    if (ignoreBtn.length) ids.push(ignoreBtn.data('id'));
                });
                self.ajax('wpfg_db_ignore_finding', { 'ids[]': ids }, function(resp) {
                    if (resp.success) {
                        rows.fadeOut(300);
                        btn.text('Done! Refreshing...');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        btn.prop('disabled', false).text('Ignore All Info');
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
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

            // Delete single history entry.
            $(document).on('click', '.wpfg-delete-history', function() {
                if (!confirm(wpfg.i18n.confirm_delete)) return;
                var btn = $(this);
                var id = btn.data('id');
                self.ajax('wpfg_delete_change_history', { id: id }, function(resp) {
                    if (resp.success) {
                        $('#wpfg-history-row-' + id).fadeOut(300, function() { $(this).remove(); });
                    }
                });
            });

            // Clear all history.
            $('#wpfg-clear-history').on('click', function() {
                if (!confirm(wpfg.i18n.confirm_delete)) return;
                var btn = $(this);
                btn.prop('disabled', true);
                self.ajax('wpfg_clear_change_history', {}, function(resp) {
                    if (resp.success) {
                        location.reload();
                    } else {
                        btn.prop('disabled', false);
                    }
                });
            });
        },

        // --- v3: Hardening ---
        bindHardening: function() {
            var self = this;

            $('#wpfg-apply-hardening').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-hardening-status').text('Applying...');

                var settings = {};
                $('.wpfg-hardening-toggle').each(function() {
                    settings[$(this).data('key')] = $(this).is(':checked') ? 1 : 0;
                });
                // Collect select values.
                $('.wpfg-hardening-select').each(function() {
                    settings[$(this).data('key')] = $(this).val();
                });

                self.ajax('wpfg_apply_hardening', { settings: settings }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        $('#wpfg-hardening-status').text('Settings applied successfully.');
                    } else {
                        $('#wpfg-hardening-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            $('#wpfg-test-hardening').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-hardening-status').text('Testing...');

                self.ajax('wpfg_test_hardening', {}, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        var html = '<h3>Hardening Status</h3><ul>';
                        $.each(resp.data, function(key, val) {
                            var icon = val.active ? '&#10004;' : '&#10006;';
                            var cls = val.active ? 'wpfg-text-info' : 'wpfg-text-critical';
                            html += '<li class="' + cls + '">' + icon + ' ' + val.label + '</li>';
                        });
                        html += '</ul>';
                        self.showModal(html);
                    } else {
                        $('#wpfg-hardening-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });
        },

        // --- v3: Firewall ---
        bindFirewall: function() {
            var self = this;

            // Add rule.
            $('#wpfg-firewall-add-rule').on('click', function() {
                var btn = $(this);
                var ruleType = $('#wpfg-fw-rule-type').val();
                var value = $('#wpfg-fw-rule-value').val().trim();
                var notes = $('#wpfg-fw-rule-notes').val().trim();

                if (!ruleType || !value) {
                    alert('Rule type and value are required.');
                    return;
                }

                btn.prop('disabled', true);
                self.ajax('wpfg_firewall_add_rule', {
                    rule_type: ruleType,
                    value: value,
                    notes: notes
                }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        $('#wpfg-fw-rule-value').val('');
                        $('#wpfg-fw-rule-notes').val('');
                        location.reload();
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Delete rule.
            $(document).on('click', '.wpfg-fw-delete-rule', function() {
                if (!confirm('Delete this rule?')) return;
                var btn = $(this);
                var id = btn.data('id');
                btn.prop('disabled', true);
                self.ajax('wpfg_firewall_delete_rule', { id: id }, function(resp) {
                    if (resp.success) {
                        btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                        btn.prop('disabled', false);
                    }
                });
            });

            // Toggle rule.
            $(document).on('click', '.wpfg-fw-toggle-rule', function() {
                var btn = $(this);
                var id = btn.data('id');
                var active = btn.data('active') ? 0 : 1;
                self.ajax('wpfg_firewall_toggle_rule', { id: id, is_active: active }, function(resp) {
                    if (resp.success) {
                        btn.data('active', active);
                        btn.text(active ? 'Disable' : 'Enable');
                        location.reload();
                    }
                });
            });

            // Clear log.
            $('#wpfg-firewall-clear-log').on('click', function() {
                if (!confirm('Clear the entire firewall log?')) return;
                self.ajax('wpfg_firewall_clear_log', {}, function(resp) {
                    if (resp.success) location.reload();
                });
            });

            // Refresh stats.
            $('#wpfg-firewall-refresh-stats').on('click', function() {
                self.ajax('wpfg_firewall_get_stats', {}, function(resp) {
                    if (resp.success) {
                        var d = resp.data;
                        $('#wpfg-fw-blocked-today').text(d.blocked_today || 0);
                        $('#wpfg-fw-blocked-week').text(d.blocked_week || 0);
                        $('#wpfg-fw-total-rules').text(d.total_rules || 0);
                    }
                });
            });
        },

        // --- v3: Vulnerability Scanner ---
        bindVulnScanner: function() {
            var self = this;

            $('#wpfg-start-vuln-scan').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-vuln-progress').slideDown(300);
                $('#wpfg-vuln-status').text('Scanning plugins and themes...');

                self.ajax('wpfg_vuln_scan_start', {}, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        $('#wpfg-vuln-status').text('Scan complete. Reloading...');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $('#wpfg-vuln-status').text(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Update item.
            $(document).on('click', '.wpfg-vuln-update', function() {
                var btn = $(this);
                var type = btn.data('type');
                var slug = btn.data('slug');
                btn.prop('disabled', true).text('Updating...');

                self.ajax('wpfg_vuln_update_item', { item_type: type, item_slug: slug }, function(resp) {
                    if (resp.success) {
                        btn.text('Updated!').addClass('button-disabled');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                        btn.prop('disabled', false).text('Update');
                    }
                });
            });

            // Ignore vuln.
            $(document).on('click', '.wpfg-vuln-ignore', function() {
                var btn = $(this);
                var id = btn.data('id');
                btn.prop('disabled', true);
                self.ajax('wpfg_vuln_ignore', { id: id }, function(resp) {
                    if (resp.success) {
                        btn.closest('tr').addClass('wpfg-ignored');
                    }
                    btn.prop('disabled', false);
                });
            });
        },

        // --- v3: Security Score ---
        bindSecurityScore: function() {
            var self = this;

            $('#wpfg-refresh-score').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-score-status').text('Calculating...');

                self.ajax('wpfg_get_security_score', {}, function(resp) {
                    btn.prop('disabled', false);
                    $('#wpfg-score-status').text('');
                    if (resp.success) {
                        var d = resp.data;
                        // Update gauge.
                        var gauge = document.querySelector('.wpfg-gauge-circle');
                        if (gauge) {
                            gauge.style.setProperty('--gauge-pct', d.score);
                            gauge.style.setProperty('--gauge-color', self.gradeColor(d.grade));
                        }
                        $('.wpfg-gauge-score').text(d.score);
                        $('.wpfg-gauge-grade').text(d.grade);

                        // Update factors list.
                        var html = '';
                        $.each(d.factors, function(key, f) {
                            var cls = 'wpfg-factor-' + f.status;
                            html += '<li class="' + cls + '">' + f.label;
                            if (f.deduction > 0) {
                                html += ' <span class="wpfg-factor-pts">-' + f.deduction + '</span>';
                            }
                            html += '</li>';
                        });
                        $('.wpfg-risk-factors').html(html);

                        // Update recommendations.
                        if (d.recommendations && d.recommendations.length) {
                            var recHtml = '';
                            d.recommendations.forEach(function(r) {
                                var cls = r.deduction >= 10 ? 'wpfg-rec-critical' : (r.deduction >= 5 ? 'wpfg-rec-warning' : 'wpfg-rec-ok');
                                recHtml += '<li class="' + cls + '">' + r.label + ' <small>(-' + r.deduction + ' pts)</small></li>';
                            });
                            $('.wpfg-recommendations').html(recHtml);
                        }
                    }
                });
            });

            $('#wpfg-send-test-summary').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                self.ajax('wpfg_send_test_summary', {}, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        alert(resp.data.message);
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });
        },

        gradeColor: function(grade) {
            var colors = { A: '#00a32a', B: '#2271b1', C: '#dba617', D: '#d63638', F: '#8b0000' };
            return colors[grade] || '#666';
        },

        // --- v3: Two-Factor Auth ---
        bind2FA: function() {
            var self = this;

            // Generate secret / show QR.
            $('#wpfg-2fa-setup').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#wpfg-2fa-status').text('Generating...');

                self.ajax('wpfg_2fa_generate_secret', {}, function(resp) {
                    btn.prop('disabled', false);
                    $('#wpfg-2fa-status').text('');
                    if (resp.success) {
                        var d = resp.data;
                        $('#wpfg-2fa-secret-key').text(d.secret);
                        $('#wpfg-2fa-setup-section').show();
                        // Generate QR code.
                        if (typeof QRCode !== 'undefined') {
                            $('#wpfg-2fa-qr').html('');
                            new QRCode(document.getElementById('wpfg-2fa-qr'), {
                                text: d.otpauth_url,
                                width: 200,
                                height: 200
                            });
                        }
                    }
                });
            });

            // Verify TOTP code (test).
            $('#wpfg-2fa-verify').on('click', function() {
                var code = $('#wpfg-2fa-test-code').val().trim();
                if (!code || code.length < 6) {
                    alert('Please enter the 6-digit code from your authenticator app.');
                    return;
                }
                var btn = $(this);
                btn.prop('disabled', true);

                self.ajax('wpfg_2fa_verify_setup', { code: code }, function(resp) {
                    btn.prop('disabled', false);
                    var $result = $('#wpfg-2fa-verify-result');
                    if (resp.success) {
                        $result.html('<span style="color:#00a32a;font-weight:500;">&#10003; Code is valid!</span>').show();
                    } else {
                        $result.html('<span style="color:#d63638;font-weight:500;">&#10007; ' + (resp.data ? resp.data.message : 'Invalid code.') + '</span>').show();
                    }
                });
            });

            // Disable 2FA.
            $(document).on('click', '.wpfg-2fa-disable', function() {
                if (!confirm('Disable Two-Factor Authentication?')) return;
                var btn = $(this);
                var userId = btn.data('user-id') || 0;
                btn.prop('disabled', true);

                self.ajax('wpfg_2fa_disable', { user_id: userId }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });

            // Regenerate backup codes.
            $('#wpfg-2fa-regen-backup').on('click', function() {
                if (!confirm('Generate new backup codes? Old codes will stop working.')) return;
                var btn = $(this);
                btn.prop('disabled', true);

                self.ajax('wpfg_2fa_regenerate_backup', {}, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success && resp.data.codes) {
                        var codesHtml = '<h3>New Backup Codes</h3><p>Save these codes securely:</p><pre>';
                        resp.data.codes.forEach(function(c) { codesHtml += c + '\n'; });
                        codesHtml += '</pre>';
                        self.showModal(codesHtml);
                    }
                });
            });

            // Load users 2FA status.
            $('#wpfg-2fa-refresh-users').on('click', function() {
                self.ajax('wpfg_2fa_get_users_status', {}, function(resp) {
                    if (resp.success && resp.data.users) {
                        var html = '';
                        resp.data.users.forEach(function(u) {
                            html += '<tr>';
                            html += '<td>' + u.display_name + '</td>';
                            html += '<td>' + u.role + '</td>';
                            html += '<td>' + (u.enabled ? '<span class="wpfg-badge wpfg-badge-info">Active</span>' : '<span class="wpfg-badge">Inactive</span>') + '</td>';
                            html += '<td>';
                            if (u.enabled) {
                                html += '<button class="button wpfg-2fa-disable" data-user-id="' + u.id + '">Disable</button>';
                            }
                            html += '</td>';
                            html += '</tr>';
                        });
                        $('#wpfg-2fa-users-tbody').html(html);
                    }
                });
            });
        },

        // --- v3: Permission Checker ---
        bindPermissions: function() {
            var self = this;

            $('#wpfg-check-permissions').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Scanning...');
                $('#wpfg-perm-status').text('Scanning file permissions...').show();
                $('#wpfg-perm-results').hide();

                self.ajax('wpfg_check_permissions', {}, function(resp) {
                    btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Scan Permissions');
                    $('#wpfg-perm-status').text('');
                    if (resp.success) {
                        var d = resp.data;
                        if (d.windows) {
                            $('#wpfg-perm-status').html('<span style="color:#dba617;font-weight:500;">&#9888; ' + (d.message || 'Permission checking is not available on Windows servers.') + '</span>').show();
                            return;
                        }
                        var issues = d.issues || [];
                        $('#wpfg-perm-results').show();
                        $('#wpfg-perm-total').text(issues.length);
                        $('#wpfg-perm-issues').text(issues.length);

                        if (issues.length) {
                            var html = '';
                            issues.forEach(function(item) {
                                html += '<tr>';
                                html += '<td><code title="' + item.path + '">' + (item.relative || item.path) + '</code></td>';
                                html += '<td><code style="color:#d63638;">' + (item.current_str || item.current) + '</code></td>';
                                html += '<td><code style="color:#00a32a;">' + (item.rec_str || item.recommended) + '</code></td>';
                                html += '<td>' + (item.type === 'directory' ? 'Directory' : 'File') + '</td>';
                                html += '<td>';
                                html += '<button class="wpfg-btn wpfg-btn-primary wpfg-btn-sm wpfg-fix-perm" data-path="' + item.path + '" data-perm="' + (item.recommended || item.rec_str) + '">Fix</button>';
                                html += '</td>';
                                html += '</tr>';
                            });
                            $('#wpfg-perm-tbody').html(html);
                            $('#wpfg-perm-fix-all').show();
                        } else {
                            $('#wpfg-perm-tbody').html('<tr><td colspan="5" style="text-align:center; padding:20px; color:#00a32a; font-weight:500;">&#10003; All file permissions look good!</td></tr>');
                            $('#wpfg-perm-fix-all').hide();
                        }
                    } else {
                        $('#wpfg-perm-status').text(resp.data ? resp.data.message : wpfg.i18n.error).show();
                    }
                });
            });

            // Fix single permission.
            $(document).on('click', '.wpfg-fix-perm', function() {
                var btn = $(this);
                var path = btn.data('path');
                var perm = btn.data('perm');
                btn.prop('disabled', true).text('Fixing...');

                self.ajax('wpfg_fix_permission', { path: path, permission: perm }, function(resp) {
                    if (resp.success) {
                        btn.text('Fixed!').addClass('button-disabled');
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                        btn.prop('disabled', false).text('Fix');
                    }
                });
            });

            // Fix all permissions.
            $('#wpfg-perm-fix-all').on('click', function() {
                if (!confirm('Fix all flagged file permissions?')) return;
                var btn = $(this);
                btn.prop('disabled', true);
                var items = [];
                $('#wpfg-perm-tbody .wpfg-fix-perm').each(function() {
                    items.push({ path: $(this).data('path'), permission: $(this).data('perm') });
                });

                self.ajax('wpfg_fix_permissions_bulk', { items: items }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        alert('Fixed ' + (resp.data.success || 0) + ' items, ' + (resp.data.failed || 0) + ' failed.');
                        $('#wpfg-check-permissions').trigger('click');
                    } else {
                        alert(resp.data ? resp.data.message : wpfg.i18n.error);
                    }
                });
            });
        }
    };

    $(document).ready(function() {
        WPFG.init();
    });

})(jQuery);
