<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Firewall / WAF', 'wp-file-guardian' ); ?></h1>

    <!-- Stats Cards -->
    <div class="wpfg-card wpfg-card-wide">
        <h2><?php esc_html_e( 'Firewall Overview', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-stats-row">
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-critical"><?php echo esc_html( $stats['blocked_today'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Blocked Today', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-warning"><?php echo esc_html( $stats['blocked_week'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Blocked This Week', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-info"><?php echo esc_html( $stats['total_rules'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Active Rules', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number" style="color:#d63638;"><?php echo esc_html( $stats['auto_banned'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Auto-Banned IPs', 'wp-file-guardian' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Add Rule Form -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Add Rule', 'wp-file-guardian' ); ?></h2>
        <form method="post" class="wpfg-inline-form">
            <?php wp_nonce_field( 'wpfg_firewall_add_rule', 'wpfg_fw_nonce' ); ?>
            <input type="hidden" name="wpfg_action" value="add_firewall_rule" />

            <select name="rule_type" required>
                <option value=""><?php esc_html_e( 'Select Type...', 'wp-file-guardian' ); ?></option>
                <option value="ip_blacklist"><?php esc_html_e( 'IP Blacklist', 'wp-file-guardian' ); ?></option>
                <option value="ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'wp-file-guardian' ); ?></option>
                <option value="country_block"><?php esc_html_e( 'Country Block', 'wp-file-guardian' ); ?></option>
                <option value="ua_block"><?php esc_html_e( 'User Agent Block', 'wp-file-guardian' ); ?></option>
            </select>

            <input type="text" name="rule_value" placeholder="<?php esc_attr_e( 'Value (IP, CIDR, country code, or UA pattern)', 'wp-file-guardian' ); ?>" required style="min-width:280px;" />
            <input type="text" name="rule_notes" placeholder="<?php esc_attr_e( 'Notes (optional)', 'wp-file-guardian' ); ?>" style="min-width:180px;" />

            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Rule', 'wp-file-guardian' ); ?></button>
        </form>
    </div>

    <!-- Active Rules Table -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Active Rules', 'wp-file-guardian' ); ?></h2>

        <?php if ( ! empty( $rules['items'] ) ) : ?>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Type', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Notes', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rules['items'] as $rule ) : ?>
                <tr>
                    <td>
                        <?php
                        $type_labels = array(
                            'ip_blacklist'  => __( 'IP Blacklist', 'wp-file-guardian' ),
                            'ip_whitelist'  => __( 'IP Whitelist', 'wp-file-guardian' ),
                            'country_block' => __( 'Country Block', 'wp-file-guardian' ),
                            'ua_block'      => __( 'UA Block', 'wp-file-guardian' ),
                        );
                        $label = isset( $type_labels[ $rule->rule_type ] ) ? $type_labels[ $rule->rule_type ] : esc_html( $rule->rule_type );
                        ?>
                        <span class="wpfg-badge wpfg-badge-info"><?php echo esc_html( $label ); ?></span>
                    </td>
                    <td><code><?php echo esc_html( $rule->rule_value ); ?></code></td>
                    <td><?php echo esc_html( $rule->notes ); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'wpfg_firewall_toggle_rule', 'wpfg_fw_nonce' ); ?>
                            <input type="hidden" name="wpfg_action" value="toggle_firewall_rule" />
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>" />
                            <input type="hidden" name="rule_active" value="<?php echo $rule->is_active ? '0' : '1'; ?>" />
                            <button type="submit" class="button button-small">
                                <?php if ( $rule->is_active ) : ?>
                                    <span class="wpfg-badge wpfg-badge-info"><?php esc_html_e( 'Active', 'wp-file-guardian' ); ?></span>
                                <?php else : ?>
                                    <span class="wpfg-badge wpfg-badge-critical"><?php esc_html_e( 'Inactive', 'wp-file-guardian' ); ?></span>
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                    <td><?php echo esc_html( $rule->created_at ); ?></td>
                    <td>
                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this rule?', 'wp-file-guardian' ); ?>');">
                            <?php wp_nonce_field( 'wpfg_firewall_delete_rule', 'wpfg_fw_nonce' ); ?>
                            <input type="hidden" name="wpfg_action" value="delete_firewall_rule" />
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>" />
                            <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'wp-file-guardian' ); ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Rules Pagination -->
        <?php
        $rules_total_pages = ceil( $rules['total'] / 20 );
        $rules_current     = isset( $_GET['rules_paged'] ) ? absint( $_GET['rules_paged'] ) : 1;
        if ( $rules_total_pages > 1 ) :
            $range = 2;
            $show  = array();
            for ( $i = 1; $i <= $rules_total_pages; $i++ ) {
                if ( $i === 1 || $i === $rules_total_pages || ( $i >= $rules_current - $range && $i <= $rules_current + $range ) ) {
                    $show[] = $i;
                }
            }
        ?>
        <div class="wpfg-pagination">
            <?php if ( $rules_current > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'rules_paged', $rules_current - 1 ) ); ?>">&laquo;</a>
            <?php endif; ?>
            <?php
            $prev = 0;
            foreach ( $show as $page ) :
                if ( $prev && $page - $prev > 1 ) :
            ?>
                    <span class="wpfg-page-ellipsis">&hellip;</span>
            <?php
                endif;
                if ( $page === $rules_current ) :
            ?>
                    <span class="wpfg-page-current"><?php echo esc_html( $page ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'rules_paged', $page ) ); ?>"><?php echo esc_html( $page ); ?></a>
                <?php endif;
                $prev = $page;
            endforeach; ?>
            <?php if ( $rules_current < $rules_total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'rules_paged', $rules_current + 1 ) ); ?>">&raquo;</a>
            <?php endif; ?>
            <span class="wpfg-pagination-info">
                <?php printf( esc_html__( 'Page %1$d of %2$d (%3$d rules)', 'wp-file-guardian' ), $rules_current, $rules_total_pages, $rules['total'] ); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No firewall rules configured yet.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Recent Blocks Log -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Recent Blocks', 'wp-file-guardian' ); ?></h2>

        <!-- Log Filters -->
        <form method="get" class="wpfg-filters">
            <input type="hidden" name="page" value="wpfg-firewall" />
            <?php if ( ! empty( $_GET['rules_paged'] ) ) : ?>
                <input type="hidden" name="rules_paged" value="<?php echo absint( $_GET['rules_paged'] ); ?>" />
            <?php endif; ?>
            <select name="rule">
                <option value=""><?php esc_html_e( 'All Rules', 'wp-file-guardian' ); ?></option>
                <option value="ip_blacklist" <?php selected( isset( $_GET['rule'] ) && $_GET['rule'] === 'ip_blacklist' ); ?>><?php esc_html_e( 'IP Blacklist', 'wp-file-guardian' ); ?></option>
                <option value="country_block" <?php selected( isset( $_GET['rule'] ) && $_GET['rule'] === 'country_block' ); ?>><?php esc_html_e( 'Country Block', 'wp-file-guardian' ); ?></option>
                <option value="rate_limit" <?php selected( isset( $_GET['rule'] ) && $_GET['rule'] === 'rate_limit' ); ?>><?php esc_html_e( 'Rate Limit', 'wp-file-guardian' ); ?></option>
                <option value="ua_block" <?php selected( isset( $_GET['rule'] ) && $_GET['rule'] === 'ua_block' ); ?>><?php esc_html_e( 'UA Block', 'wp-file-guardian' ); ?></option>
            </select>
            <input type="text" name="ip" value="<?php echo esc_attr( isset( $_GET['ip'] ) ? $_GET['ip'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Filter by IP...', 'wp-file-guardian' ); ?>" />
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-file-guardian' ); ?></button>
        </form>

        <?php if ( ! empty( $log['items'] ) ) : ?>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Time', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'IP', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Rule Matched', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'User Agent', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Country', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Request URI', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $log['items'] as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( $entry->created_at ); ?></td>
                    <td><code><?php echo esc_html( $entry->ip_address ); ?></code></td>
                    <td>
                        <?php
                        $rule_labels = array(
                            'ip_blacklist'  => __( 'IP Blacklist', 'wp-file-guardian' ),
                            'country_block' => __( 'Country Block', 'wp-file-guardian' ),
                            'rate_limit'    => __( 'Rate Limit', 'wp-file-guardian' ),
                            'ua_block'      => __( 'UA Block', 'wp-file-guardian' ),
                        );
                        $rule_label = isset( $rule_labels[ $entry->rule_matched ] ) ? $rule_labels[ $entry->rule_matched ] : esc_html( $entry->rule_matched );
                        ?>
                        <span class="wpfg-badge wpfg-badge-warning"><?php echo esc_html( $rule_label ); ?></span>
                    </td>
                    <td class="wpfg-filepath" title="<?php echo esc_attr( $entry->user_agent ); ?>"><?php echo esc_html( mb_substr( $entry->user_agent, 0, 60 ) ); ?></td>
                    <td><?php echo esc_html( $entry->country ); ?></td>
                    <td class="wpfg-filepath" title="<?php echo esc_attr( $entry->request_uri ); ?>"><?php echo esc_html( mb_substr( $entry->request_uri, 0, 80 ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Log Pagination -->
        <?php
        $log_total_pages = ceil( $log['total'] / 30 );
        $log_current     = isset( $_GET['log_paged'] ) ? absint( $_GET['log_paged'] ) : 1;
        if ( $log_total_pages > 1 ) :
            $range = 2;
            $show  = array();
            for ( $i = 1; $i <= $log_total_pages; $i++ ) {
                if ( $i === 1 || $i === $log_total_pages || ( $i >= $log_current - $range && $i <= $log_current + $range ) ) {
                    $show[] = $i;
                }
            }
        ?>
        <div class="wpfg-pagination">
            <?php if ( $log_current > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'log_paged', $log_current - 1 ) ); ?>">&laquo;</a>
            <?php endif; ?>
            <?php
            $prev = 0;
            foreach ( $show as $page ) :
                if ( $prev && $page - $prev > 1 ) :
            ?>
                    <span class="wpfg-page-ellipsis">&hellip;</span>
            <?php
                endif;
                if ( $page === $log_current ) :
            ?>
                    <span class="wpfg-page-current"><?php echo esc_html( $page ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'log_paged', $page ) ); ?>"><?php echo esc_html( $page ); ?></a>
                <?php endif;
                $prev = $page;
            endforeach; ?>
            <?php if ( $log_current < $log_total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'log_paged', $log_current + 1 ) ); ?>">&raquo;</a>
            <?php endif; ?>
            <span class="wpfg-pagination-info">
                <?php printf( esc_html__( 'Page %1$d of %2$d (%3$d entries)', 'wp-file-guardian' ), $log_current, $log_total_pages, $log['total'] ); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No blocked requests recorded yet.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>
</div>
