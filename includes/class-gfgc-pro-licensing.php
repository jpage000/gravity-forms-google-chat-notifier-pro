<?php
/**
 * GFGC Pro Licensing
 *
 * Validates a license key against goat-getter.com and sets the
 * `gfgc_is_pro_active` filter to true when the key is active.
 *
 * Pattern mirrors class-gp-pro-licensing.php used by Gravity Pipeline Pro.
 *
 * SETUP NOTE: You must create a new product called "GF Google Chat Notifier Pro"
 * in the EDD store on goat-getter.com and ensure the `/wp-json/gp-license/v1/activate`
 * endpoint supports it (or create a new endpoint slug if needed).
 */

defined( 'ABSPATH' ) || exit;

class GFGC_Pro_Licensing {

    /** GF add-on stores plugin settings at this option key. */
    const SETTINGS_OPTION = 'gravityformsaddon_gf-google-chat_settings';

    public function __construct() {
        // Inject license key field into GF's plugin settings page.
        add_filter( 'gfgc_plugin_settings_fields', [ $this, 'add_license_field' ] );

        // Set the Pro-active filter when license is valid.
        add_filter( 'gfgc_is_pro_active', [ $this, 'check_pro_status' ] );

        // Re-validate whenever settings are saved.
        add_action( 'update_option_' . self::SETTINGS_OPTION, [ $this, 'on_settings_saved' ], 10, 2 );
        add_action( 'add_option_'    . self::SETTINGS_OPTION, [ $this, 'on_settings_added' ], 10, 2 );

        // Admin notice showing live license status on our settings page.
        add_action( 'admin_notices', [ $this, 'show_license_notice' ] );
    }

    // -------------------------------------------------------------------------
    // Settings saved hooks
    // -------------------------------------------------------------------------

    public function on_settings_saved( $old, $new ): void {
        $key = trim( $new['gfgc_license_key'] ?? '' );
        if ( $key ) {
            $this->validate_license( $key );
        }
    }

    public function on_settings_added( $option_name, $new ): void {
        $key = trim( $new['gfgc_license_key'] ?? '' );
        if ( $key ) {
            $this->validate_license( $key );
        }
    }

    // -------------------------------------------------------------------------
    // Settings field definition
    // -------------------------------------------------------------------------

    public function add_license_field( array $fields ): array {
        $fields[] = [
            'title'  => 'Pro License',
            'fields' => [
                [
                    'name'              => 'gfgc_license_key',
                    'label'             => 'License Key',
                    'type'              => 'text',
                    'class'             => 'medium',
                    'tooltip'           => 'Enter your GF Google Chat Notifier Pro license key from goat-getter.com.',
                    'feedback_callback' => [ $this, 'validate_license' ],
                ],
            ],
        ];
        return $fields;
    }

    // -------------------------------------------------------------------------
    // Admin notice
    // -------------------------------------------------------------------------

    public function show_license_notice(): void {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'gf-google-chat' ) === false ) {
            return;
        }

        $status = get_option( 'gfgc_license_status', '' );
        $token  = get_option( 'gfgc_license_token', '' );
        $error  = get_transient( 'gfgc_license_error' );

        if ( $status === 'active' && $token ) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>GF Google Chat Pro:</strong> License is active.</p></div>';
            return;
        }

        if ( $error ) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>GF Google Chat Pro license error:</strong> ' . esc_html( $error ) . '</p></div>';
            return;
        }

        if ( $status && $status !== 'active' ) {
            echo '<div class="notice notice-warning is-dismissible"><p>⚠️ <strong>GF Google Chat Pro:</strong> License status is <em>' . esc_html( $status ) . '</em>. Please check your license at <a href="https://goat-getter.com" target="_blank">goat-getter.com</a>.</p></div>';
        }
    }

    // -------------------------------------------------------------------------
    // Core validation
    // -------------------------------------------------------------------------

    /**
     * Validate and activate the license key.
     * Used as feedback_callback (returns bool) and called from settings-saved hook.
     */
    public function validate_license( $value ): bool {
        $value = is_string( $value ) ? trim( $value ) : '';

        if ( empty( $value ) ) {
            delete_option( 'gfgc_license_status' );
            delete_option( 'gfgc_license_token' );
            return false;
        }

        // Development bypass — keys starting with GFGC-DEV- activate Pro locally.
        if ( str_starts_with( $value, 'GFGC-DEV-' ) ) {
            update_option( 'gfgc_license_status', 'active' );
            update_option( 'gfgc_license_token', 'dev_token_' . time() );
            delete_transient( 'gfgc_license_error' );
            return true;
        }

        $response = wp_remote_post(
            'https://goat-getter.com/wp-json/gp-license/v1/activate',
            [
                'body'    => [
                    'license_key' => $value,
                    'site_url'    => get_site_url(),
                    'product'     => 'gf-google-chat-pro',
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            set_transient( 'gfgc_license_error', 'Connection error: ' . $response->get_error_message(), HOUR_IN_SECONDS );
            return false;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $http_code >= 400 || empty( $body['success'] ) ) {
            $message = $body['message'] ?? ( 'Activation failed (HTTP ' . $http_code . ').' );
            set_transient( 'gfgc_license_error', $message, HOUR_IN_SECONDS );
            delete_option( 'gfgc_license_status' );
            delete_option( 'gfgc_license_token' );
            return false;
        }

        update_option( 'gfgc_license_status', $body['status'] ?? 'active' );
        update_option( 'gfgc_license_token',  $body['activation_token'] ?? wp_generate_password( 20, false ) );
        delete_transient( 'gfgc_license_error' );
        return true;
    }

    // -------------------------------------------------------------------------
    // gfgc_is_pro_active filter
    // -------------------------------------------------------------------------

    public function check_pro_status( bool $current ): bool {
        $status = get_option( 'gfgc_license_status', '' );
        $token  = get_option( 'gfgc_license_token', '' );
        return ( $status === 'active' && $token !== '' ) ? true : $current;
    }
}
