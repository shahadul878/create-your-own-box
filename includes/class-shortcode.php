<?php
/**
 * Shortcode renderer for the Create Box builder.
 *
 * @package CreateBox
 */

namespace CreateBox;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcode
 */
class Shortcode {

    /**
     * Track whether we already rendered the builder.
     *
     * @var bool
     */
    private static $rendered = false;

    /**
     * Shortcode tag.
     */
    const TAG = 'create_box_builder';

    /**
     * Register shortcode.
     *
     * @return void
     */
    public static function init() {
        add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
        add_filter( 'the_content', array( __CLASS__, 'maybe_replace_content' ), 20 );
    }

    /**
     * Render shortcode output.
     *
     * @return string
     */
    public static function render() {
        $payload = Catalog::build_payload();

        if ( empty( $payload['boxes'] ) && empty( $payload['sections'] ) ) {
            return '<div class="create-box-empty">' . esc_html__( 'No products are available for the builder yet. Please configure the plugin settings.', 'create-box' ) . '</div>';
        }

        Assets::enqueue_builder( $payload );

        ob_start();
        ?>
        <div class="create-box" data-create-box-root>
            <div class="create-box__content" data-builder>
                <header class="create-box__intro">
                    <p class="create-box__subtitle" data-builder-subtitle></p>
                </header>

                <section class="create-box__section create-box__section--boxes" data-boxes>
                    <h2 class="create-box__section-title"></h2>
                    <div class="create-box__grid" data-box-grid></div>
                </section>

                <section class="create-box__section create-box__section--catalog" data-catalog></section>
            </div>

            <aside class="create-box__summary" data-summary>
                <div class="create-box__summary-inner">
                    <h5 class="create-box__summary-message" data-summary-items></h5>
                    <h5 class="create-box__summary-message" data-summary-box></h5>
                    <h5 class="create-box__summary-message" data-summary-total></h5>

                    <ul class="create-box__selected" data-selected-list></ul>

                    <div class="create-box__totals">
                        <span class="create-box__totals-label">Total:</span>
                        <span class="create-box__totals-value" data-summary-grand>0</span>
                    </div>

                    <button type="button" class="create-box__submit" data-submit disabled>
                        <span data-submit-label></span>
                        <span class="create-box__submit-total" data-submit-total></span>
                    </button>

                    <div class="create-box__feedback" data-feedback role="alert" hidden></div>
                </div>
            </aside>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Replace page content with the builder markup when configured.
     *
     * @param string $content Original content.
     * @return string
     */
    public static function maybe_replace_content( $content ) {
        if ( self::$rendered || ! self::should_render_on_page() ) {
            return $content;
        }

        self::$rendered = true;

        return self::render();
    }

    /**
     * Determine if the builder should render on the current page request.
     *
     * @return bool
     */
    private static function should_render_on_page() {
        if ( is_admin() ) {
            return false;
        }

        $page_id = Settings::get_builder_page_id();

        if ( ! $page_id ) {
            return false;
        }

        if ( ! is_page() || (int) get_queried_object_id() !== $page_id ) {
            return false;
        }

        if ( ! in_the_loop() || ! is_main_query() ) {
            return false;
        }

        return true;
    }
}
