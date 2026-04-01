<?php
/**
 * Plugin Name:  Gravity Forms Google Chat Notifier Pro
 * Plugin URI:   https://goat-getter.com/gf-google-chat-pro
 * Description:  Pro features for GF Google Chat Notifier: unlimited feeds, WP Editor, custom buttons, card icon, conditional logic.
 * Version:      1.0.1
 * Author:       Goat Getter
 * Author URI:   https://goat-getter.com
 * License:      GPL-2.0+
 * Text Domain:  gf-google-chat-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'GFGCP_VERSION',    '1.0.1' );
define( 'GFGCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'gform_loaded', 'gfgcp_load', 10 );

function gfgcp_load() {
    // Require the free plugin to be active first.
    // Note: this hook fires after GFForms has fully loaded, so GF_Google_Chat_AddOn
    // will be defined if the free plugin is active and properly installed.
    if ( ! class_exists( 'GF_Google_Chat_AddOn' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
               . '<strong>Gravity Forms Google Chat Notifier Pro</strong> requires the free '
               . '<em>Gravity Forms Google Chat Notifier</em> plugin to be installed and active.'
               . '</p></div>';
        } );
        return;
    }

    require_once GFGCP_PLUGIN_DIR . 'includes/class-gfgc-pro-licensing.php';

    new GFGC_Pro_Licensing();
}
