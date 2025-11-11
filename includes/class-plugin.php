<?php
/**
 * Core plugin orchestrator.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    private static $instance;

    /**
     * Retrieve plugin singleton.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialising.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Register core hooks.
     *
     * @return void
     */
    private function register_hooks() {
        register_activation_hook( CREATE_BOX_FILE, array( $this, 'on_activation' ) );
        add_action( 'plugins_loaded', array( $this, 'maybe_bootstrap' ) );
    }

    /**
     * Flush rewrite rules on activation.
     *
     * @return void
     */
    public function on_activation() {
        flush_rewrite_rules();
    }

    /**
     * Bootstrap modules when WooCommerce is ready.
     *
     * @return void
     */
    public function maybe_bootstrap() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( __CLASS__, 'woocommerce_required_notice' ) );
            return;
        }

        Settings::init();
        Product_Flags::init();
        Assets::init();
        Shortcode::init();
        Rest_Routes::init();
    }

    /**
     * Display notice when WooCommerce missing.
     *
     * @return void
     */
    public static function woocommerce_required_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__( 'Create Box Builder requires WooCommerce to be installed and active.', 'create-box' )
        );
    }
}
