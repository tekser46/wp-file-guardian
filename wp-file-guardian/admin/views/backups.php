<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Backups', 'wp-file-guardian' ); ?></h1>

    <!-- Create Backup -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Create New Backup', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-backup-form">
            <select id="wpfg-backup-type">
                <option value="full"><?php esc_html_e( 'Full (wp-content)', 'wp-file-guardian' ); ?></option>
                <option value="plugins"><?php esc_html_e( 'Plugins Only', 'wp-file-guardian' ); ?></option>
                <option value="themes"><?php esc_html_e( 'Themes Only', 'wp-file-guardian' ); ?></option>
                <option value="uploads"><?php esc_html_e( 'Uploads Only', 'wp-file-guardian' ); ?></option>
            </select>
            <button type="button" class="button button-primary" id="wpfg-create-backup"><?php esc_html_e( 'Create Backup', 'wp-file-guardian' ); ?></button>
            <span id="wpfg-backup-status" class="wpfg-inline-status"></span>
        </div>
    </div>

    <!-- Backup List -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Existing Backups', 'wp-file-guardian' ); ?></h2>

        <?php if ( ! empty( $list['items'] ) ) : ?>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Type', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Files', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Size', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Notes', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $list['items'] as $item ) : ?>
                <tr>
                    <td><?php echo esc_html( ucfirst( $item->backup_type ) ); ?></td>
                    <td><?php echo esc_html( number_format( $item->file_count ) ); ?></td>
                    <td><?php echo esc_html( WPFG_Helpers::format_bytes( $item->file_size ) ); ?></td>
                    <td>
                        <span class="wpfg-badge wpfg-badge-<?php echo $item->status === 'completed' ? 'info' : 'warning'; ?>">
                            <?php echo esc_html( ucfirst( $item->status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $item->notes ); ?></td>
                    <td><?php echo esc_html( $item->created_at ); ?></td>
                    <td class="wpfg-actions">
                        <?php if ( 'completed' === $item->status ) : ?>
                            <a href="<?php echo esc_url( WPFG_Backup::get_download_url( $item->id ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Download', 'wp-file-guardian' ); ?>
                            </a>
                            <button type="button" class="button button-small wpfg-backup-action" data-action="restore" data-id="<?php echo esc_attr( $item->id ); ?>">
                                <?php esc_html_e( 'Restore', 'wp-file-guardian' ); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="button button-small wpfg-btn-danger wpfg-backup-action" data-action="delete" data-id="<?php echo esc_attr( $item->id ); ?>">
                            <?php esc_html_e( 'Delete', 'wp-file-guardian' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $total_pages = ceil( $list['total'] / 20 );
        $current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $total_pages > 1 ) :
        ?>
        <div class="wpfg-pagination">
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                <?php if ( $i === $current ) : ?>
                    <span class="wpfg-page-current"><?php echo esc_html( $i ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No backups found.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>
</div>
