<?php
/**
 * Plugin Name: Create Box Builder
 * Description: Clone the "Create Your Box" experience with WooCommerce products and dynamic bundle validation.
 * Version: 0.1.0
 * Author: H M Shahadul Islam
 * Author URI: https://github.com/shahadul878
 * License: GPL2+
 * Text Domain: create-box
 *
 * @package CreateBox
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CREATE_BOX_FILE' ) ) {
    define( 'CREATE_BOX_FILE', __FILE__ );
}

if ( ! defined( 'CREATE_BOX_PATH' ) ) {
    define( 'CREATE_BOX_PATH', plugin_dir_path( CREATE_BOX_FILE ) );
}

if ( ! defined( 'CREATE_BOX_URL' ) ) {
    define( 'CREATE_BOX_URL', plugin_dir_url( CREATE_BOX_FILE ) );
}

require_once CREATE_BOX_PATH . 'includes/class-autoloader.php';

CreateBox\Autoloader::init();

/**
 * Return singleton plugin instance.
 *
 * @return \CreateBox\Plugin
 */
function create_box_builder() {
    return CreateBox\Plugin::instance();
}

create_box_builder();
