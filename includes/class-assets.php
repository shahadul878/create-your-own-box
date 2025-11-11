<?php
/**
 * Front-end asset loading.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
class Assets {

    /**
     * Script handle.
     */
    const SCRIPT_HANDLE = 'create-box-builder';

    /**
     * Style handle.
     */
    const STYLE_HANDLE = 'create-box-builder';

    /**
     * Hook registrations.
     *
     * @return void
     */
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
    }

    /**
     * Register, but do not enqueue, styles/scripts.
     *
     * @return void
     */
    public static function register_frontend_assets() {
        wp_register_style(
            self::STYLE_HANDLE,
            CREATE_BOX_URL . 'assets/css/create-box.css',
            array(),
            filemtime( CREATE_BOX_PATH . 'assets/css/create-box.css' )
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            CREATE_BOX_URL . 'assets/js/create-box.js',
            array(),
            filemtime( CREATE_BOX_PATH . 'assets/js/create-box.js' ),
            true
        );
    }

    /**
     * Enqueue builder assets with contextual data.
     *
     * @param array $payload Catalog payload.
     * @return void
     */
    public static function enqueue_builder( array $payload ) {
        if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
            self::register_frontend_assets();
        }

        wp_enqueue_style( self::STYLE_HANDLE );
        wp_enqueue_script( self::SCRIPT_HANDLE );

        $data = array(
            'payload'      => $payload,
            'restBase'     => untrailingslashit( esc_url_raw( rest_url( 'create-box/v1' ) ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'redirect'     => Settings::get_redirect(),
            'cartUrl'      => wc_get_cart_url(),
            'checkoutUrl'  => wc_get_checkout_url(),
        );

        wp_localize_script( self::SCRIPT_HANDLE, 'CreateBoxData', $data );
    }
}
