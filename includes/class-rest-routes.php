<?php
/**
 * REST endpoints powering the builder.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Rest_Routes
 */
class Rest_Routes {

    /**
     * Namespace.
     */
    const NAMESPACE = 'create-box/v1';

    /**
     * Hook registrations.
     *
     * @return void
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register endpoints.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/catalog',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_catalog' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/add',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'handle_add_bundle' ),
                'permission_callback' => array( __CLASS__, 'verify_nonce' ),
            )
        );
    }

    /**
     * Verify nonce for POST operations while allowing CLI/admin bypass.
     *
     * @return bool
     */
    public static function verify_nonce( $request = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }

        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';

        if ( $nonce ) {
            return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
        }

        // Fallback to referer for environments that strip headers.
        if ( isset( $_REQUEST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
        }

        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Return catalog payload.
     *
     * @return array
     */
    public static function get_catalog() {
        return Catalog::build_payload();
    }

    /**
     * Handle bundle submission.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_add_bundle( WP_REST_Request $request ) {
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return new WP_Error( 'create_box_no_cart', __( 'The cart is not available.', 'create-box' ), array( 'status' => 500 ) );
        }

        $params = $request->get_json_params();

        if ( empty( $params ) || ! is_array( $params ) ) {
            return new WP_Error( 'create_box_invalid_payload', __( 'Invalid request payload.', 'create-box' ), array( 'status' => 400 ) );
        }

        $box   = isset( $params['box'] ) ? self::sanitize_item( $params['box'] ) : null;
        $items = isset( $params['items'] ) && is_array( $params['items'] ) ? array_map( array( __CLASS__, 'sanitize_item' ), $params['items'] ) : array();

        $require_box = Settings::is_box_required();
        $min_items   = Settings::get_min_items();
        $min_total   = Settings::get_min_total();

        if ( $require_box && ( empty( $box['product_id'] ) ) ) {
            return new WP_Error( 'create_box_missing_box', __( 'Please choose a box before continuing.', 'create-box' ), array( 'status' => 400 ) );
        }

        $processed_items = array();
        $grand_total     = 0;

        if ( $box && ! empty( $box['product_id'] ) ) {
            $result = self::hydrate_product( $box, true );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $grand_total       += $result['line_total'];
            $processed_items[]  = $result + array( 'is_box' => true );
        }

        $item_count = 0;

        foreach ( $items as $item ) {
            $result = self::hydrate_product( $item, false );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $grand_total      += $result['line_total'];
            $processed_items[] = $result + array( 'is_box' => false );
            $item_count       += $result['quantity'];
        }

        if ( 0 === $item_count ) {
            $item_count = array_sum( array_map( static function ( $item ) {
                return isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
            }, $items ) );
        }

        if ( $min_items > 0 && $item_count < $min_items ) {
            return new WP_Error( 'create_box_min_items', sprintf( __( 'Add at least %d items to continue.', 'create-box' ), $min_items ), array( 'status' => 400 ) );
        }

        if ( $min_total > 0 && $grand_total < $min_total ) {
            return new WP_Error( 'create_box_min_total', sprintf( __( 'Order total must reach %s.', 'create-box' ), wc_price( $min_total ) ), array( 'status' => 400 ) );
        }

        // Push to cart.
        $cart = WC()->cart;

        $added_keys = array();

        foreach ( $processed_items as $entry ) {
            $product      = $entry['product'];
            $variation_id = $entry['variation_id'];
            $quantity     = $entry['quantity'];
            $attributes   = $entry['attributes'];

            if ( $variation_id ) {
                $product_id = $product->get_parent_id();
            } else {
                $product_id = $product->get_id();
            }

            $cart_item_key = $cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes );

            if ( ! $cart_item_key ) {
                foreach ( $added_keys as $key ) {
                    $cart->remove_cart_item( $key );
                }

                return new WP_Error( 'create_box_add_failed', __( 'Unable to add one of the selected items to the cart.', 'create-box' ), array( 'status' => 500 ) );
            }

            $added_keys[] = $cart_item_key;
        }

        $redirect = Settings::get_redirect();
        $url      = null;

        if ( 'checkout' === $redirect ) {
            $url = wc_get_checkout_url();
        } elseif ( 'cart' === $redirect ) {
            $url = wc_get_cart_url();
        }

        $response = array(
            'success'  => true,
            'total'    => wc_price( $grand_total ),
            'redirect' => $url,
        );

        return rest_ensure_response( $response );
    }

    /**
     * Normalize incoming item data.
     *
     * @param array|null $item Raw item payload.
     * @return array
     */
    private static function sanitize_item( $item ) {
        if ( empty( $item ) || ! is_array( $item ) ) {
            return array();
        }

        return array(
            'product_id'   => isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0,
            'variation_id' => isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0,
            'quantity'     => isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1,
        );
    }

    /**
     * Load product/variation details and validate.
     *
     * @param array $item Item payload.
     * @param bool  $is_box Whether item is the box selection.
     * @return array|WP_Error
     */
    private static function hydrate_product( array $item, $is_box = false ) {
        if ( empty( $item['product_id'] ) ) {
            return new WP_Error( 'create_box_missing_product', __( 'One of the products is missing.', 'create-box' ), array( 'status' => 400 ) );
        }

        $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );

        if ( ! $product || ! $product->exists() ) {
            return new WP_Error( 'create_box_invalid_product', __( 'One of the products could not be found.', 'create-box' ), array( 'status' => 404 ) );
        }

        if ( $item['variation_id'] ) {
            $variation = wc_get_product( $item['variation_id'] );
            if ( ! $variation || ! $variation->exists() ) {
                return new WP_Error( 'create_box_invalid_variation', __( 'Selected product variation is unavailable.', 'create-box' ), array( 'status' => 400 ) );
            }

            $product = $variation;
        }

        if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            return new WP_Error( 'create_box_unpurchasable', __( 'One of the selected items is out of stock.', 'create-box' ), array( 'status' => 400 ) );
        }

        $quantity = max( 1, (int) $item['quantity'] );

        if ( $product->get_stock_quantity() !== null && $product->get_stock_quantity() < $quantity ) {
            return new WP_Error( 'create_box_not_enough_stock', __( 'Not enough stock for one of the selected products.', 'create-box' ), array( 'status' => 400 ) );
        }

        $line_price = wc_get_price_to_display( $product, array( 'qty' => 1 ) );
        $line_total = $line_price * $quantity;

        $attributes = array();

        if ( $item['variation_id'] ) {
            $attributes = $product->get_attributes();
        }

        return array(
            'product'      => $product,
            'product_id'   => $item['product_id'],
            'variation_id' => $item['variation_id'],
            'quantity'     => $quantity,
            'price'        => $line_price,
            'line_total'   => $line_total,
            'attributes'   => $attributes,
            'is_box'       => $is_box,
        );
    }
}
