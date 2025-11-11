<?php
/**
 * Admin settings management.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

use WC_Admin_Settings;

/**
 * Class Settings
 */
class Settings {

    /**
     * Menu slug for settings page.
     */
    const MENU_SLUG = 'create-box-settings';

    /**
     * Option prefix.
     */
    const OPTION_PREFIX = 'create_box_';

    /**
     * Initialise settings hooks.
     *
     * @return void
     */
    public static function init() {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
            add_action( 'admin_init', array( __CLASS__, 'maybe_save_settings' ) );
            add_filter( 'plugin_action_links_' . plugin_basename( CREATE_BOX_FILE ), array( __CLASS__, 'add_plugin_action_link' ) );
        }
    }

    /**
     * Register submenu under WooCommerce menu.
     *
     * @return void
     */
    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Create Box Builder', 'create-box' ),
            __( 'Create Box', 'create-box' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Handle settings save request.
     *
     * @return void
     */
    public static function maybe_save_settings() {
        if ( empty( $_GET['page'] ) || self::MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'create_box_save_settings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) || ! class_exists( 'WC_Admin_Settings' ) ) {
            return;
        }

        WC_Admin_Settings::save_fields( self::get_fields() );
        add_settings_error( 'create_box', 'settings_saved', __( 'Settings saved.', 'create-box' ), 'updated' );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'create-box' ) );
        }

        echo '<div class="wrap woocommerce">';
        echo '<h1>' . esc_html__( 'Create Box Builder', 'create-box' ) . '</h1>';

        settings_errors( 'create_box' );

        if ( ! class_exists( 'WC_Admin_Settings' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce must be active to configure Create Box Builder.', 'create-box' ) . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="">';
        wp_nonce_field( 'create_box_save_settings' );
        WC_Admin_Settings::output_fields( self::get_fields() );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Add quick link from plugins list.
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public static function add_plugin_action_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
            esc_html__( 'Settings', 'create-box' )
        );

        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Build WooCommerce-style settings fields.
     *
     * @return array
     */
    private static function get_fields() {
        $fields = array(
            array(
                'title' => __( 'Catalog Sources', 'create-box' ),
                'type'  => 'title',
                'desc'  => __( 'Mark any WooCommerce product as a box within its product editor. Content sections still rely on product categories.', 'create-box' ),
                'id'    => self::option_key( 'catalog_title' ),
            ),
            array(
                'title'       => __( 'Builder Page', 'create-box' ),
                'desc'        => __( 'Enter the WordPress page ID or slug where the Create Box builder should render automatically. Leave blank to disable automatic output.', 'create-box' ),
                'id'          => self::option_key( 'builder_page' ),
                'type'        => 'text',
                'placeholder' => __( 'e.g. 42 or build-your-box', 'create-box' ),
            ),
            array(
                'title'             => __( 'Content Sections', 'create-box' ),
                'desc'              => __( 'One section per line. Use either "Label | term-id" or "Label | term-slug". Example: "Summer Fabric | summer-fabric".', 'create-box' ),
                'id'                => self::option_key( 'sections_map' ),
                'type'              => 'textarea',
                'css'               => 'min-width: 320px; min-height: 140px;',
                'placeholder'       => "Summer Fabric | summer-fabric\nWinter Fabric | winter-fabric",
            ),
            array(
                'type' => 'sectionend',
                'id'   => self::option_key( 'catalog_title' ),
            ),
            array(
                'title' => __( 'Bundle Rules', 'create-box' ),
                'type'  => 'title',
                'desc'  => __( 'Gate the Add to Cart action until shoppers meet these requirements.', 'create-box' ),
                'id'    => self::option_key( 'rules_title' ),
            ),
            array(
                'title'             => __( 'Minimum Items', 'create-box' ),
                'desc_tip'          => __( 'Number of non-box items required before checkout.', 'create-box' ),
                'id'                => self::option_key( 'min_items' ),
                'type'              => 'number',
                'default'           => 3,
                'css'               => 'max-width: 120px;',
                'custom_attributes' => array( 'min' => 0 ),
            ),
            array(
                'title'             => __( 'Minimum Total (store currency)', 'create-box' ),
                'desc_tip'          => __( 'Order total must reach this amount (e.g. 1900).', 'create-box' ),
                'id'                => self::option_key( 'min_total' ),
                'type'              => 'number',
                'default'           => 1900,
                'css'               => 'max-width: 120px;',
                'custom_attributes' => array( 'min' => 0, 'step' => '0.01' ),
            ),
            array(
                'title'   => __( 'Require Box Selection', 'create-box' ),
                'desc'    => __( 'Force shoppers to choose a box product before checkout.', 'create-box' ),
                'id'      => self::option_key( 'require_box' ),
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title'   => __( 'Redirect After Add', 'create-box' ),
                'desc'    => __( 'Destination once the bundle is pushed to WooCommerce.', 'create-box' ),
                'id'      => self::option_key( 'redirect_to' ),
                'type'    => 'select',
                'default' => 'checkout',
                'options' => array(
                    'checkout' => __( 'Checkout', 'create-box' ),
                    'cart'     => __( 'Cart', 'create-box' ),
                    'stay'     => __( 'Stay on page', 'create-box' ),
                ),
            ),
            array(
                'type' => 'sectionend',
                'id'   => self::option_key( 'rules_title' ),
            ),
        );

        return $fields;
    }

    /**
     * Helper to build option key.
     *
     * @param string $suffix Key suffix.
     * @return string
     */
    public static function option_key( $suffix ) {
        return self::OPTION_PREFIX . $suffix;
    }

    /**
     * Retrieve option with default fallback.
     *
     * @param string $key Option suffix.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get_option( $key, $default = '' ) {
        $value = get_option( self::option_key( $key ), null );

        if ( null === $value || '' === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Determine if box selection required.
     *
     * @return bool
     */
    public static function is_box_required() {
        return 'yes' === self::get_option( 'require_box', 'yes' );
    }

    /**
     * Retrieve minimum item count.
     *
     * @return int
     */
    public static function get_min_items() {
        return max( 0, absint( self::get_option( 'min_items', 3 ) ) );
    }

    /**
     * Retrieve minimum total.
     *
     * @return float
     */
    public static function get_min_total() {
        return max( 0, (float) self::get_option( 'min_total', 1900 ) );
    }

    /**
     * Return redirect destination.
     *
     * @return string
     */
    public static function get_redirect() {
        $allowed = array( 'checkout', 'cart', 'stay' );
        $value   = self::get_option( 'redirect_to', 'checkout' );

        if ( ! in_array( $value, $allowed, true ) ) {
            $value = 'checkout';
        }

        return $value;
    }

    /**
     * Retrieve configured builder page ID.
     *
     * @return int
     */
    public static function get_builder_page_id() {
        $value = trim( (string) self::get_option( 'builder_page', '' ) );

        if ( '' === $value ) {
            return 0;
        }

        if ( is_numeric( $value ) ) {
            return absint( $value );
        }

        $page = get_page_by_path( $value );

        if ( ! $page ) {
            $page = get_page_by_path( sanitize_title( $value ) );
        }

        if ( ! $page ) {
            $page = get_page_by_title( $value );
        }

        return $page ? (int) $page->ID : 0;
    }

    /**
     * Resolve configured sections.
     *
     * @return array<int, array{label:string, term:\WP_Term}>
     */
    public static function get_sections() {
        $raw = self::get_option( 'sections_map', '' );

        if ( empty( $raw ) ) {
            return array();
        }

        $lines    = preg_split( '/\r\n|\r|\n/', $raw );
        $sections = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }

            $parts = array_map( 'trim', explode( '|', $line ) );

            $label    = '';
            $term_ref = '';

            if ( count( $parts ) > 1 ) {
                if ( is_numeric( $parts[0] ) ) {
                    $term_ref = $parts[0];
                    $label    = $parts[1] ?? '';
                } else {
                    $label    = $parts[0];
                    $term_ref = $parts[1];
                }
            } else {
                $term_ref = $parts[0];
            }

            $term = self::resolve_term( $term_ref );
            if ( ! $term ) {
                continue;
            }

            if ( '' === $label ) {
                $label = $term->name;
            }

            $sections[] = array(
                'label' => $label,
                'term'  => $term,
            );
        }

        return $sections;
    }

    /**
     * Resolve WooCommerce product category by id or slug.
     *
     * @param string $ref Reference.
     * @return \WP_Term|null
     */
    private static function resolve_term( $ref ) {
        if ( '' === $ref ) {
            return null;
        }

        if ( is_numeric( $ref ) ) {
            $term = get_term_by( 'id', (int) $ref, 'product_cat' );
        } else {
            $term = get_term_by( 'slug', sanitize_title( $ref ), 'product_cat' );
        }

        return ( $term && ! is_wp_error( $term ) ) ? $term : null;
    }

}
