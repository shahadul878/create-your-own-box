<?php
/**
 * Lightweight PSR-4 style autoloader.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

/**
 * Register basic autoloader for plugin classes.
 */
class Autoloader {

    /**
     * Base namespace for plugin classes.
     */
    const NAMESPACE_PREFIX = __NAMESPACE__ . '\\';

    /**
     * Initialise autoloader.
     *
     * @return void
     */
    public static function init() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Attempt to autoload class.
     *
     * @param string $class Fully-qualified class name.
     * @return void
     */
    private static function autoload( $class ) {
        if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
            return;
        }

        $relative = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
        $relative = strtolower( $relative );
        $relative = str_replace( '_', '-', $relative );
        $file     = CREATE_BOX_PATH . 'includes/class-' . $relative . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
