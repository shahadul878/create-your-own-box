<?php
/**
 * Catalog data assembler.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

use WC_Product;

/**
 * Class Catalog
 */
class Catalog {

    /**
     * Build catalog payload consumed by the front-end.
     *
     * @return array
     */
    public static function build_payload() {
        $charset  = get_bloginfo( 'charset' );
        $currency = array(
            'code'              => get_woocommerce_currency(),
            'symbol'            => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, $charset ? $charset : 'UTF-8' ),
            'decimals'          => wc_get_price_decimals(),
            'decimalSeparator'  => wc_get_price_decimal_separator(),
            'thousandSeparator' => wc_get_price_thousand_separator(),
            'format'            => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, $charset ? $charset : 'UTF-8' ),
        );

        $boxes       = self::collect_box_products();
        $sections    = self::collect_sections();
        $min_items   = Settings::get_min_items();
        $min_total   = Settings::get_min_total();
        $require_box = Settings::is_box_required();

        return array(
            'currency'     => $currency,
            'boxes'        => $boxes,
            'sections'     => $sections,
            'rules'        => array(
                'min_items'   => $min_items,
                'min_total'   => $min_total,
                'require_box' => $require_box,
            ),
            'intro'        => sprintf(
                /* translators: %s: minimum total formatted price */
                __( 'Select a Box and any of our products, then pay at once. Ensure your order totals at least %s to avoid delivery issues!', 'create-box' ),
                wc_price( $min_total )
            ),
            'boxes_title'  => __( 'Choose your box', 'create-box' ),
            'i18n'         => array(
                'select_box'        => __( 'Select Box', 'create-box' ),
                'box_selected'      => __( 'Selected', 'create-box' ),
                'view_product'      => __( 'View product', 'create-box' ),
                'view_more'         => __( 'View more', 'create-box' ),
                'box_label'         => __( 'Box', 'create-box' ),
                'choose_box'        => __( 'Choose your box', 'create-box' ),
                'or_add'            => __( 'Or add from', 'create-box' ),
                'add_to_box'        => __( 'Add to the Box', 'create-box' ),
                'select_variation'  => __( 'Select an option', 'create-box' ),
                'quantity'          => __( 'Quantity', 'create-box' ),
                'remove'            => __( 'Remove', 'create-box' ),
                'unit_price'        => __( 'Unit price:', 'create-box' ),
                'subtotal'          => __( 'Subtotal:', 'create-box' ),
                'items_needed'      => __( 'Your bundle needs at least %d more item(s).', 'create-box' ),
                'box_required'      => __( 'Add a required single product from the box collection to proceed.', 'create-box' ),
                'total_required'    => __( 'Your total amount needs to be at least %s to proceed.', 'create-box' ),
                'button_label'      => __( 'Add to Cart', 'create-box' ),
                'button_pending'    => __( 'Adding…', 'create-box' ),
                'added'             => __( 'Bundle added! Redirecting…', 'create-box' ),
                'error_generic'     => __( 'Something went wrong. Please try again.', 'create-box' ),
                'empty_section'     => __( 'No products found in this section yet.', 'create-box' ),
            ),
        );
    }

    /**
     * Collect products flagged as boxes.
     *
     * @return array
     */
    private static function collect_box_products() {
        $products = Product_Flags::get_box_products();

        if ( empty( $products ) ) {
            return array();
        }

        return array_map( array( __CLASS__, 'map_product' ), $products );
    }

    /**
     * Collect box data by term.
     *
     * @param \WP_Term $term Category term.
     * @return array
     */
    private static function collect_products_for_term( $term ) {
        $args = array(
            'status'       => array( 'publish' ),
            'limit'        => -1,
            'orderby'      => 'menu_order',
            'order'        => 'ASC',
            'return'       => 'objects',
            'tax_query'    => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array( $term->term_id ),
                ),
            ),
        );

        $products = wc_get_products( $args );

        if ( empty( $products ) ) {
            return array();
        }

        return array_map( array( __CLASS__, 'map_product' ), $products );
    }

    /**
     * Collect configured sections with their products.
     *
     * @return array
     */
    private static function collect_sections() {
        $sections = array();

        foreach ( Settings::get_sections() as $section ) {
            $products = self::collect_products_for_term( $section['term'] );
            $permalink = get_term_link( $section['term'] );
            if ( is_wp_error( $permalink ) ) {
                $permalink = '';
            }

            $sections[] = array(
                'id'          => $section['term']->slug,
                'label'       => $section['label'],
                'description' => $section['term']->description,
                'permalink'   => $permalink,
                'products'    => $products,
            );
        }

        return $sections;
    }

    /**
     * Map WooCommerce product to lightweight payload.
     *
     * @param WC_Product $product Product object.
     * @return array
     */
    private static function map_product( WC_Product $product ) {
        $image_id = $product->get_image_id();
        $image    = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );

        $data = array(
            'id'           => $product->get_id(),
            'name'         => $product->get_name(),
            'permalink'    => $product->get_permalink(),
            'image'        => $image,
            'type'         => $product->get_type(),
            'price'        => (float) wc_get_price_to_display( $product ),
            'price_html'   => $product->get_price_html(),
            'stock_status' => $product->get_stock_status(),
            'stock_qty'    => $product->get_stock_quantity(),
            'purchasable'  => $product->is_purchasable(),
            'variations'   => array(),
        );

        if ( $product->is_type( 'variable' ) ) {
            $data['variations'] = self::map_variations( $product );
        }

        return $data;
    }

    /**
     * Map product variations.
     *
     * @param \WC_Product_Variable $product Variable product.
     * @return array
     */
    private static function map_variations( $product ) {
        $variations = array();

        foreach ( $product->get_available_variations() as $variation_data ) {
            $variation = wc_get_product( $variation_data['variation_id'] );
            if ( ! $variation ) {
                continue;
            }

            $attributes = array();
            foreach ( $variation->get_attributes() as $name => $value ) {
                $attributes[ $name ] = $value;
            }

            $variations[] = array(
                'id'           => $variation->get_id(),
                'name'         => wc_get_formatted_variation( $variation, true, false, true ),
                'price'        => (float) wc_get_price_to_display( $variation ),
                'price_html'   => $variation->get_price_html(),
                'purchasable'  => $variation->is_purchasable(),
                'stock_status' => $variation->get_stock_status(),
                'stock_qty'    => $variation->get_stock_quantity(),
                'attributes'   => $attributes,
            );
        }

        return $variations;
    }
}
