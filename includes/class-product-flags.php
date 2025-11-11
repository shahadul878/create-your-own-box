<?php
/**
 * Product level flags for Create Box.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

/**
 * Manage product meta used by the builder.
 */
class Product_Flags {

    /**
     * Meta key to identify box products.
     */
    const META_IS_BOX = '_create_box_is_box';

    /**
     * Initialise hooks.
     *
     * @return void
     */
    public static function init() {
        if ( is_admin() ) {
            add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_box_checkbox' ) );
            add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_box_checkbox' ), 20, 1 );
        }
    }

    /**
     * Render checkbox in product data panel.
     *
     * @return void
     */
    public static function render_box_checkbox() {
        global $post;

        if ( ! $post ) {
            return;
        }

        echo '<div class="options_group">';

        woocommerce_wp_checkbox(
            array(
                'id'          => self::field_name(),
                'label'       => __( 'Mark as Create Box product', 'create-box' ),
                'description' => __( 'Enable to show this product under the box selector in the Create Box builder.', 'create-box' ),
                'value'       => self::is_box_product( (int) $post->ID ) ? 'yes' : 'no',
            )
        );

        echo '</div>';
    }

    /**
     * Persist checkbox value.
     *
     * @param int $product_id Product identifier.
     * @return void
     */
    public static function save_box_checkbox( $product_id ) {
        $value = isset( $_POST[ self::field_name() ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
        update_post_meta( $product_id, self::META_IS_BOX, $value );
    }

    /**
     * Determine if product flagged as box.
     *
     * @param int $product_id Product identifier.
     * @return bool
     */
    public static function is_box_product( $product_id ) {
        return 'yes' === get_post_meta( $product_id, self::META_IS_BOX, true );
    }

    /**
     * Retrieve all published products flagged as box.
     *
     * @return array<int, \WC_Product>
     */
    public static function get_box_products() {
        $args = array(
            'status'       => array( 'publish' ),
            'limit'        => -1,
            'orderby'      => 'menu_order',
            'order'        => 'ASC',
            'return'       => 'objects',
            'meta_key'     => self::META_IS_BOX,
            'meta_value'   => 'yes',
            'meta_compare' => '=',
        );

        $products = wc_get_products( $args );

        return is_array( $products ) ? $products : array();
    }

    /**
     * Field name for checkbox.
     *
     * @return string
     */
    private static function field_name() {
        return 'create_box_is_box';
    }
}

