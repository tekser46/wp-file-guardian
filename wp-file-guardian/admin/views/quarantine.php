<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Quarantine', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-card">
        <p><?php esc_html_e( 'Files moved to quarantine are stored safely and cannot be executed. You can restore or permanently delete them.', 'wp-file-guardian' ); ?></p>

        <?php if ( ! empty( $list['items'] ) ) : ?>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Original Path', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Size', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Reason', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Quarantined By', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $list['items'] as $item ) : ?>
                <tr>
                    <td class="wpfg-filepath" title="<?php echo esc_attr( $item->original_path ); ?>">
                        <?php echo esc_html( WPFG_Helpers::relative_path( $item->original_path ) ); ?>
                    </td>
                    <td><?php echo esc_html( WPFG_Helpers::format_bytes( $item->file_size ) ); ?></td>
                    <td><?php echo esc_html( $item->reason ); ?></td>
                    <td>
                        <?php
                        $user = get_userdata( $item->user_id );
                        echo esc_html( $user ? $user->display_name : '#' . $item->user_id );
                        ?>
                    </td>
                    <td><?php echo esc_html( $item->created_at ); ?></td>
                    <td>
                        <span class="wpfg-badge wpfg-badge-<?php echo $item->status === 'quarantined' ? 'warning' : 'info'; ?>">
                            <?php echo esc_html( ucfirst( $item->status ) ); ?>
                        </span>
                    </td>
                    <td class="wpfg-actions">
                        <?php if ( 'quarantined' === $item->status ) : ?>
                            <button type="button" class="button button-small wpfg-q-action" data-action="restore" data-id="<?php echo esc_attr( $item->id ); ?>">
                                <?php esc_html_e( 'Restore', 'wp-file-guardian' ); ?>
                            </button>
                            <button type="button" class="button button-small wpfg-q-action wpfg-btn-danger" data-action="delete" data-id="<?php echo esc_attr( $item->id ); ?>">
                                <?php esc_html_e( 'Delete', 'wp-file-guardian' ); ?>
                            </button>
                        <?php else : ?>
                            <em><?php echo esc_html( ucfirst( $item->status ) ); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
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
            <p><?php esc_html_e( 'No quarantined files.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>
</div>
