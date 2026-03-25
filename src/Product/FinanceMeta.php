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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_product_admin_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_product_assets']);
        add_action('all_admin_notices', [$this, 'render_product_validation_notices']);

        // Add deferral date fields to General tab (simple products)
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_deferral_dates_simple']);

        // Add deferral date fields to variations
        add_action('woocommerce_product_after_variable_attributes', [$this, 'render_deferral_dates_variation'], 10, 3);

        // Save product meta
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_meta']);

        // Save variation meta
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_meta'], 10, 2);
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
                'wrapper_class' => 'wicket-finance-deferral-date-field wicket-finance-deferral-date-field-start',
                'custom_attributes' => [
                    'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                    'data-wicket-finance-date-role' => 'start',
                    'data-wicket-finance-date-group' => 'simple',
                ],
            ]);

        woocommerce_wp_text_input([
            'id' => '_wicket_finance_deferral_end_date',
            'label' => __('Deferral End Date', 'wicket-finance'),
            'type' => 'date',
            'value' => $product->get_meta('_wicket_finance_deferral_end_date', true),
            'wrapper_class' => 'wicket-finance-deferral-date-field wicket-finance-deferral-date-field-end',
            'custom_attributes' => [
                'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                'data-wicket-finance-date-role' => 'end',
                'data-wicket-finance-date-group' => 'simple',
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
                'wrapper_class' => 'form-row form-row-first wicket-finance-deferral-date-field wicket-finance-deferral-date-field-start',
                'custom_attributes' => [
                    'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                    'data-wicket-finance-date-role' => 'start',
                    'data-wicket-finance-date-group' => (string) $loop,
                ],
            ]);

        woocommerce_wp_text_input([
            'id' => "_wicket_finance_deferral_end_date_{$loop}",
            'name' => "variable_wicket_finance_deferral_end_date[{$loop}]",
            'label' => __('Deferral End Date', 'wicket-finance'),
            'type' => 'date',
            'value' => $variation_obj->get_meta('_wicket_finance_deferral_end_date', true),
            'wrapper_class' => 'form-row form-row-last wicket-finance-deferral-date-field wicket-finance-deferral-date-field-end',
            'custom_attributes' => [
                'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                'data-wicket-finance-date-role' => 'end',
                'data-wicket-finance-date-group' => (string) $loop,
            ],
        ]);
        ?>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets for WooCommerce product deferral date validation.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return void
     */
    public function enqueue_product_admin_assets(string $hook_suffix): void
    {
        if (!$this->should_enqueue_product_admin_assets($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            'wicket-finance-product-admin',
            WICKET_FINANCE_URL . 'assets/css/admin-product-validation.css',
            [],
            WICKET_FINANCE_VERSION
        );

        wp_enqueue_script(
            'wicket-finance-product-admin',
            WICKET_FINANCE_URL . 'assets/js/admin-product-validation.js',
            [],
            WICKET_FINANCE_VERSION,
            true
        );

        wp_add_inline_script(
            'wicket-finance-product-admin',
            'window.wicketFinanceProductValidation = ' . wp_json_encode([
                'missingStartMessage' => __('Deferral Start Date is required when Deferral End Date is set. Add a start date to continue.', 'wicket-finance'),
                'invalidRangeMessage' => __('Deferral End Date must be the same as or later than Deferral Start Date.', 'wicket-finance'),
                'noticeMessage' => __('Some finance deferral dates need attention. Each Deferral End Date requires a Deferral Start Date, and End Date cannot be earlier than Start Date.', 'wicket-finance'),
            ]) . ';',
            'before'
        );
    }

    /**
     * Enqueue product admin assets when WooCommerce loads block-editor assets.
     *
     * @return void
     */
    public function enqueue_block_editor_product_assets(): void
    {
        if (!$this->is_product_editor_screen()) {
            return;
        }

        $this->enqueue_product_admin_assets('');
    }

    /**
     * Render persisted validation notices on product admin screens.
     *
     * @return void
     */
    public function render_product_validation_notices(): void
    {
        if (!$this->is_product_editor_screen()) {
            return;
        }

        $errors = $this->get_persisted_validation_errors();
        if ($errors === []) {
            return;
        }

        if ($this->woocommerce_meta_box_error_store_has_errors()) {
            $this->clear_persisted_validation_errors();

            return;
        }

        echo '<div class="notice notice-error wicket-finance-product-notice is-dismissible">';

        foreach ($errors as $error) {
            echo '<p>' . esc_html($error) . '</p>';
        }

        echo '</div>';

        $this->clear_persisted_validation_errors();
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
            $start_date = isset($_POST['_wicket_finance_deferral_start_date'])
                ? $this->date_formatter->sanitize_date_input(wp_unslash($_POST['_wicket_finance_deferral_start_date']))
                : '';
            $end_date = isset($_POST['_wicket_finance_deferral_end_date'])
                ? $this->date_formatter->sanitize_date_input(wp_unslash($_POST['_wicket_finance_deferral_end_date']))
                : '';

            if (!$this->validate_posted_deferral_dates($start_date, $end_date)) {
                return;
            }

            if (isset($_POST['_wicket_finance_deferral_start_date'])) {
                $product->update_meta_data('_wicket_finance_deferral_start_date', $start_date);
            }

            if (isset($_POST['_wicket_finance_deferral_end_date'])) {
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

        $start_date = isset($_POST['variable_wicket_finance_deferral_start_date'][$loop])
            ? $this->date_formatter->sanitize_date_input(wp_unslash($_POST['variable_wicket_finance_deferral_start_date'][$loop]))
            : '';
        $end_date = isset($_POST['variable_wicket_finance_deferral_end_date'][$loop])
            ? $this->date_formatter->sanitize_date_input(wp_unslash($_POST['variable_wicket_finance_deferral_end_date'][$loop]))
            : '';

        if (!$this->validate_posted_deferral_dates($start_date, $end_date, $loop)) {
            return;
        }

        // Save deferral dates
        if (isset($_POST['variable_wicket_finance_deferral_start_date'][$loop])) {
            $variation->update_meta_data('_wicket_finance_deferral_start_date', $start_date);
        }

        if (isset($_POST['variable_wicket_finance_deferral_end_date'][$loop])) {
            $variation->update_meta_data('_wicket_finance_deferral_end_date', $end_date);
        }

        $variation->save();
    }

    /**
     * Validates posted product deferral dates before save.
     *
     * @param string   $start_date Start date in Y-m-d format.
     * @param string   $end_date   End date in Y-m-d format.
     * @param int|null $loop       Variation loop index, or null for simple products.
     * @return bool True when valid, false otherwise.
     */
    private function validate_posted_deferral_dates(string $start_date, string $end_date, ?int $loop = null): bool
    {
        // Skip if no dates set
        if (empty($start_date) && empty($end_date)) {
            return true;
        }

        if (!empty($end_date) && empty($start_date)) {
            $this->add_product_validation_error($this->get_validation_message(
                __('Finance: Deferral Start Date is required when Deferral End Date is set.', 'wicket-finance'),
                $loop
            ));

            return false;
        }

        // Validate date range
        if (!empty($start_date) && !empty($end_date)) {
            if (!$this->date_formatter->validate_date_range($start_date, $end_date)) {
                $this->add_product_validation_error($this->get_validation_message(
                    __('Finance: Deferral End Date must be greater than or equal to Deferral Start Date.', 'wicket-finance'),
                    $loop
                ));

                return false;
            }
        }

        return true;
    }

    /**
     * Adds a WooCommerce product admin validation error.
     *
     * @param string $message Error message.
     * @return void
     */
    private function add_product_validation_error(string $message): void
    {
        $this->persist_validation_error($message);

        if (class_exists(\WC_Admin_Meta_Boxes::class)) {
            \WC_Admin_Meta_Boxes::add_error($message);

            return;
        }

        wc_add_notice($message, 'error');
    }

    /**
     * Formats a validation message for simple or variation context.
     *
     * @param string   $message Base validation message.
     * @param int|null $loop    Variation loop index, or null for simple products.
     * @return string
     */
    private function get_validation_message(string $message, ?int $loop = null): string
    {
        if ($loop === null) {
            return $message;
        }

        return sprintf(
            /* translators: 1: variation number, 2: validation message */
            __('Variation #%1$d: %2$s', 'wicket-finance'),
            $loop + 1,
            $message
        );
    }

    /**
     * Determine whether admin assets should be loaded for the current screen.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return bool
     */
    private function should_enqueue_product_admin_assets(string $hook_suffix): bool
    {
        if (in_array($hook_suffix, ['post.php', 'post-new.php'], true) && $this->is_product_editor_screen()) {
            return true;
        }

        if ($this->is_product_wc_admin_page()) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the current admin screen is editing a product.
     *
     * @return bool
     */
    private function is_product_editor_screen(): bool
    {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        if ($screen->post_type === 'product') {
            return true;
        }

        return $this->is_product_wc_admin_page();
    }

    /**
     * Check whether the current request is a WooCommerce wc-admin product editor page.
     *
     * @return bool
     */
    private function is_product_wc_admin_page(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $path = isset($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';

        if ($page !== 'wc-admin') {
            return false;
        }

        if (strpos($path, '/product/') === 0 || strpos($path, '/add-product') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Persist a validation error so it can be rendered after the product save redirect.
     *
     * @param string $message Error message.
     * @return void
     */
    private function persist_validation_error(string $message): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $meta_key = 'wicket_finance_product_validation_errors';
        $errors = get_user_meta($user_id, $meta_key, true);
        if (!is_array($errors)) {
            $errors = [];
        }

        if (!in_array($message, $errors, true)) {
            $errors[] = $message;
        }

        update_user_meta($user_id, $meta_key, $errors);
    }

    /**
     * Get persisted validation errors for the current user.
     *
     * @return array<int, string>
     */
    private function get_persisted_validation_errors(): array
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $errors = get_user_meta($user_id, 'wicket_finance_product_validation_errors', true);

        if (!is_array($errors)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $errors)));
    }

    /**
     * Clear persisted validation errors for the current user.
     *
     * @return void
     */
    private function clear_persisted_validation_errors(): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        delete_user_meta($user_id, 'wicket_finance_product_validation_errors');
    }

    /**
     * Check whether WooCommerce already has meta box errors queued for output.
     *
     * @return bool
     */
    private function woocommerce_meta_box_error_store_has_errors(): bool
    {
        if (!class_exists(\WC_Admin_Meta_Boxes::class)) {
            return false;
        }

        $errors = get_option(\WC_Admin_Meta_Boxes::ERROR_STORE, []);

        return is_array($errors) && $errors !== [];
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
