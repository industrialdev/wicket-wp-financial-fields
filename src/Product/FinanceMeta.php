<?php

declare(strict_types=1);

namespace Wicket\Finance\Product;

use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Logger;

/**
 * Product finance meta service.
 *
 * Manages Finance Mapping tab in WooCommerce products:
 * - GL Code (parent level)
 * - Deferred revenue required (parent level)
 * - Deferral start/end dates (Simple: General tab, Variable: per variation)
 *
 * Meta keys:
 * - _wicket_finance_gl_code
 * - _wicket_finance_deferred_required
 * - _wicket_finance_deferral_start_date
 * - _wicket_finance_deferral_end_date
 *
 * @since 1.0.0
 */
class FinanceMeta
{
    /**
     * Date formatter.
     *
     * @var DateFormatter
     */
    private $date_formatter;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param DateFormatter $date_formatter Date formatter.
     * @param Logger        $logger         Logger instance.
     */
    public function __construct(DateFormatter $date_formatter, Logger $logger)
    {
        $this->date_formatter = $date_formatter;
        $this->logger = $logger;
    }

    /**
     * Initialize product meta hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Add Finance Mapping tab
        add_filter('woocommerce_product_data_tabs', [$this, 'add_finance_mapping_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_finance_mapping_panel']);

        // Add deferral date fields to General tab (simple products)
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_deferral_dates_simple']);

        // Add deferral date fields to variations
        add_action('woocommerce_product_after_variable_attributes', [$this, 'render_deferral_dates_variation'], 10, 3);

        // Save product meta
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_meta']);

        // Save variation meta
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_meta'], 10, 2);

        // Validation
        add_filter('woocommerce_product_data_store_cpt_prepare_props_to_update', [$this, 'validate_product_dates'], 10, 2);
    }

    /**
     * Adds Finance Mapping tab to product data.
     *
     * @param array $tabs Existing tabs.
     * @return array Modified tabs.
     */
    public function add_finance_mapping_tab(array $tabs): array
    {
        $tabs['wicket_finance_mapping'] = [
            'label' => __('Finance Mapping', 'wicket-finance'),
            'target' => 'wicket_finance_mapping_data',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_subscription', 'show_if_variable-subscription'],
            'priority' => 60,
        ];

        return $tabs;
    }

    /**
     * Renders Finance Mapping panel content.
     *
     * @return void
     */
    public function render_finance_mapping_panel(): void
    {
        global $post;

        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        ?>
        <div id="wicket_finance_mapping_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_text_input([
                    'id' => '_wicket_finance_gl_code',
                    'label' => __('GL Code', 'wicket-finance'),
                    'desc_tip' => true,
                    'description' => __('GL mapping from your financial management system.', 'wicket-finance'),
                    'type' => 'text',
                    'value' => $product->get_meta('_wicket_finance_gl_code', true),
                ]);

        woocommerce_wp_checkbox([
            'id' => '_wicket_finance_deferred_required',
            'label' => __('Deferred revenue required', 'wicket-finance'),
            'description' => __('Select if this product will use a deferred revenue schedule in your financial management system.', 'wicket-finance'),
            'value' => $product->get_meta('_wicket_finance_deferred_required', true) === 'yes' ? 'yes' : 'no',
        ]);
        ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders deferral date fields in General tab for simple products.
     *
     * @return void
     */
    public function render_deferral_dates_simple(): void
    {
        global $post;

        $product = wc_get_product($post->ID);
        if (!$product || $product->is_type('variable')) {
            return;
        }

        $deferred_required = $product->get_meta('_wicket_finance_deferred_required', true);
        if ($deferred_required !== 'yes') {
            return;
        }

        ?>
        <div class="options_group show_if_simple show_if_subscription wicket_finance_deferral_dates">
            <?php
            woocommerce_wp_text_input([
                'id' => '_wicket_finance_deferral_start_date',
                'label' => __('Deferral Start Date', 'wicket-finance'),
                'type' => 'date',
                'value' => $product->get_meta('_wicket_finance_deferral_start_date', true),
                'custom_attributes' => [
                    'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                ],
            ]);

        woocommerce_wp_text_input([
            'id' => '_wicket_finance_deferral_end_date',
            'label' => __('Deferral End Date', 'wicket-finance'),
            'type' => 'date',
            'value' => $product->get_meta('_wicket_finance_deferral_end_date', true),
            'custom_attributes' => [
                'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
            ],
        ]);
        ?>
        </div>
        <?php
    }

    /**
     * Renders deferral date fields for variations.
     *
     * @param int     $loop           Variation loop index.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Variation post object.
     * @return void
     */
    public function render_deferral_dates_variation(int $loop, array $variation_data, $variation): void
    {
        $variation_obj = wc_get_product($variation->ID);
        if (!$variation_obj) {
            return;
        }

        $parent = wc_get_product($variation_obj->get_parent_id());
        if (!$parent || $parent->get_meta('_wicket_finance_deferred_required', true) !== 'yes') {
            return;
        }

        ?>
        <div class="wicket_finance_variation_deferral">
            <?php
            woocommerce_wp_text_input([
                'id' => "_wicket_finance_deferral_start_date_{$loop}",
                'name' => "variable_wicket_finance_deferral_start_date[{$loop}]",
                'label' => __('Deferral Start Date', 'wicket-finance'),
                'type' => 'date',
                'value' => $variation_obj->get_meta('_wicket_finance_deferral_start_date', true),
                'wrapper_class' => 'form-row form-row-first',
                'custom_attributes' => [
                    'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                ],
            ]);

        woocommerce_wp_text_input([
            'id' => "_wicket_finance_deferral_end_date_{$loop}",
            'name' => "variable_wicket_finance_deferral_end_date[{$loop}]",
            'label' => __('Deferral End Date', 'wicket-finance'),
            'type' => 'date',
            'value' => $variation_obj->get_meta('_wicket_finance_deferral_end_date', true),
            'wrapper_class' => 'form-row form-row-last',
            'custom_attributes' => [
                'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
            ],
        ]);
        ?>
        </div>
        <?php
    }

    /**
     * Saves product meta.
     *
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function save_product_meta(\WC_Product $product): void
    {
        // Save GL Code
        if (isset($_POST['_wicket_finance_gl_code'])) {
            $gl_code = sanitize_text_field(wp_unslash($_POST['_wicket_finance_gl_code']));
            $product->update_meta_data('_wicket_finance_gl_code', $gl_code);
        }

        // Save deferred required flag
        $deferred_required = isset($_POST['_wicket_finance_deferred_required']) ? 'yes' : 'no';
        $product->update_meta_data('_wicket_finance_deferred_required', $deferred_required);

        // Save deferral dates (simple products only)
        if (!$product->is_type('variable')) {
            if (isset($_POST['_wicket_finance_deferral_start_date'])) {
                $start_date = $this->date_formatter->sanitize_date_input(wp_unslash($_POST['_wicket_finance_deferral_start_date']));
                $product->update_meta_data('_wicket_finance_deferral_start_date', $start_date);
            }

            if (isset($_POST['_wicket_finance_deferral_end_date'])) {
                $end_date = $this->date_formatter->sanitize_date_input(wp_unslash($_POST['_wicket_finance_deferral_end_date']));
                $product->update_meta_data('_wicket_finance_deferral_end_date', $end_date);
            }
        }
    }

    /**
     * Saves variation meta.
     *
     * @param int $variation_id Variation ID.
     * @param int $loop         Loop index.
     * @return void
     */
    public function save_variation_meta(int $variation_id, int $loop): void
    {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }

        // Save deferral dates
        if (isset($_POST['variable_wicket_finance_deferral_start_date'][$loop])) {
            $start_date = $this->date_formatter->sanitize_date_input(wp_unslash($_POST['variable_wicket_finance_deferral_start_date'][$loop]));
            $variation->update_meta_data('_wicket_finance_deferral_start_date', $start_date);
        }

        if (isset($_POST['variable_wicket_finance_deferral_end_date'][$loop])) {
            $end_date = $this->date_formatter->sanitize_date_input(wp_unslash($_POST['variable_wicket_finance_deferral_end_date'][$loop]));
            $variation->update_meta_data('_wicket_finance_deferral_end_date', $end_date);
        }

        $variation->save();
    }

    /**
     * Validates product deferral dates before save.
     *
     * @param array       $props_to_update Props being updated.
     * @param \WC_Product $product         Product object.
     * @return array Props to update.
     */
    public function validate_product_dates(array $props_to_update, \WC_Product $product): array
    {
        $start_date = $product->get_meta('_wicket_finance_deferral_start_date', true);
        $end_date = $product->get_meta('_wicket_finance_deferral_end_date', true);

        // Skip if no dates set
        if (empty($start_date) && empty($end_date)) {
            return $props_to_update;
        }

        // If start date is set, end date is required
        if (!empty($start_date) && empty($end_date)) {
            wc_add_notice(__('Finance: End date is required when start date is set.', 'wicket-finance'), 'error');
        }

        // Validate date range
        if (!empty($start_date) && !empty($end_date)) {
            if (!$this->date_formatter->validate_date_range($start_date, $end_date)) {
                wc_add_notice(__('Finance: End date must be greater than or equal to start date.', 'wicket-finance'), 'error');
            }
        }

        return $props_to_update;
    }

    /**
     * Gets product deferral dates (handles variation inheritance).
     *
     * @param int|\WC_Product $product Product ID or object.
     * @return array Array with start_date and end_date, or empty values.
     */
    public function get_deferral_dates($product): array
    {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return ['start_date' => '', 'end_date' => ''];
        }

        $start_date = $product->get_meta('_wicket_finance_deferral_start_date', true);
        $end_date = $product->get_meta('_wicket_finance_deferral_end_date', true);

        // For variations, inherit from parent if empty
        if ($product->is_type('variation') && (empty($start_date) || empty($end_date))) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                if (empty($start_date)) {
                    $start_date = $parent->get_meta('_wicket_finance_deferral_start_date', true);
                }
                if (empty($end_date)) {
                    $end_date = $parent->get_meta('_wicket_finance_deferral_end_date', true);
                }
            }
        }

        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
    }

    /**
     * Gets product GL code.
     *
     * @param int|\WC_Product $product Product ID or object.
     * @return string GL code or empty string.
     */
    public function get_gl_code($product): string
    {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return '';
        }

        // For variations, get from parent
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                return (string) $parent->get_meta('_wicket_finance_gl_code', true);
            }
        }

        return (string) $product->get_meta('_wicket_finance_gl_code', true);
    }
}
