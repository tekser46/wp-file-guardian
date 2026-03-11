<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'System Information', 'wp-file-guardian' ); ?></h1>

    <?php foreach ( $info as $section => $items ) : ?>
    <div class="wpfg-card">
        <h2><?php echo esc_html( $section ); ?></h2>
        <table class="widefat striped">
            <tbody>
                <?php foreach ( $items as $label => $value ) : ?>
                <tr>
                    <td style="width:40%;"><strong><?php echo esc_html( $label ); ?></strong></td>
                    <td><?php echo esc_html( $value ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Copy System Info', 'wp-file-guardian' ); ?></h2>
        <textarea id="wpfg-sysinfo-text" class="large-text" rows="10" readonly><?php
        foreach ( $info as $section => $items ) {
            echo "=== " . $section . " ===\n";
            foreach ( $items as $label => $value ) {
                echo $label . ': ' . $value . "\n";
            }
            echo "\n";
        }
        ?></textarea>
        <p><button type="button" class="button" onclick="document.getElementById('wpfg-sysinfo-text').select();document.execCommand('copy');"><?php esc_html_e( 'Copy to Clipboard', 'wp-file-guardian' ); ?></button></p>
    </div>
</div>
