<?php

declare(strict_types=1);

namespace Wicket\Finance\Settings;

use Wicket\Finance\Support\Logger;

/**
 * WPSettings-based Finance settings integration.
 *
 * Registers Finance tab in Wicket Settings using priority-based tab system.
 *
 * @since 1.0.0
 */
class WPSettingsSettings
{
    /**
     * Settings facade.
     *
     * @var FinanceSettings
     */
    private $settings;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Track if Finance tab was registered via new priority system.
     *
     * @var bool
     */
    private $tab_registered = false;

    /**
     * Constructor.
     *
     * @param FinanceSettings $settings Settings facade.
     * @param Logger          $logger   Logger instance.
     */
    public function __construct(FinanceSettings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Initialize Finance tab registration.
     *
     * Hooks into new priority-based system with fallback for older base plugin versions.
     *
     * @return void
     */
    public function init(): void
    {
        // New priority-based tab system (base plugin 1.1.0+)
        add_filter('wicket_settings_tabs', [$this, 'register_finance_tab']);

        // Fallback for older base plugin versions without priority system
        add_filter('wicket_settings_extend', [$this, 'register_finance_tab_fallback']);
    }

    /**
     * Register Finance tab via priority-based tabs filter.
     *
     * Priority 45 places Finance between Touchpoints (40) and Integrations (50).
     *
     * @param array $tabs Existing tabs configuration.
     * @return array Modified tabs configuration.
     */
    public function register_finance_tab(array $tabs): array
    {
        $this->tab_registered = true;

        $tabs[45] = [
            'key' => 'finance',
            'label' => __('Finance', 'wicket'),
            'callback' => [$this, 'register_finance_tab_sections'],
        ];

        return $tabs;
    }

    /**
     * Fallback registration for older base plugin versions.
     *
     * Only executes if the new priority-based filter was not triggered.
     *
     * @param \Jeffreyvr\WPSettings\WPSettings $settings WPSettings instance.
     * @return \Jeffreyvr\WPSettings\WPSettings Modified settings instance.
     */
    public function register_finance_tab_fallback($settings)
    {
        if ($this->tab_registered) {
            return $settings;
        }

        $finance_tab = $settings->add_tab(__('Finance', 'wicket'));
        $this->register_finance_tab_sections($finance_tab);

        return $settings;
    }

    /**
     * Register Finance sections and options on a tab.
     *
     * @param mixed $finance_tab WPSettings tab instance.
     * @return void
     */
    public function register_finance_tab_sections($finance_tab): void
    {
        if (!$finance_tab) {
            return;
        }

        $feature_control_section = $finance_tab->add_section(__('Revenue Deferral Dates — Feature Control', 'wicket'));

        $feature_control_section->add_option('checkbox', [
            'name' => 'wicket_finance_enable_system',
            'label' => __('Enable Finance Mapping System', 'wicket'),
            'description' => __('Enable the entire finance mapping and deferral dates system.', 'wicket'),
            'default' => '1',
        ]);

        $customer_visibility_section = $finance_tab->add_section(__('Revenue Deferral Dates — Customer Visibility', 'wicket'));

        $customer_visibility_section->add_option('select-multiple', [
            'name' => 'wicket_finance_customer_visible_categories',
            'label' => __('Product Categories for Customer Display', 'wicket'),
            'description' => __('Select product categories that should display deferral dates to customers. Only products in these categories will show deferral dates on customer-facing surfaces.', 'wicket'),
            'options' => $this->get_product_categories(),
            'default' => [],
        ]);

        $customer_visibility_section->add_option('checkbox', [
            'name' => 'wicket_finance_display_order_confirmation',
            'label' => __('Order Confirmation Page', 'wicket'),
            'description' => __('Display deferral dates on the order confirmation page.', 'wicket'),
            'default' => '0',
        ]);

        $customer_visibility_section->add_option('checkbox', [
            'name' => 'wicket_finance_display_emails',
            'label' => __('Email Notifications', 'wicket'),
            'description' => __('Display deferral dates in email notifications (Pending payment, On hold, Processing, Completed, Renewal).', 'wicket'),
            'default' => '0',
        ]);

        $customer_visibility_section->add_option('checkbox', [
            'name' => 'wicket_finance_display_my_account',
            'label' => __('My Account › Orders', 'wicket'),
            'description' => __('Display deferral dates in the My Account order details view.', 'wicket'),
            'default' => '0',
        ]);

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (function_exists('is_plugin_active') && is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')) {
            $customer_visibility_section->add_option('checkbox', [
                'name' => 'wicket_finance_display_subscriptions',
                'label' => __('Subscriptions Details', 'wicket'),
                'description' => __('Display deferral dates in subscription details (WooCommerce Subscriptions required).', 'wicket'),
                'default' => '0',
            ]);
        }

        if ($this->has_supported_invoice_plugin()) {
            $customer_visibility_section->add_option('checkbox', [
                'name' => 'wicket_finance_display_pdf_invoices',
                'label' => __('PDF Invoices', 'wicket'),
                'description' => __('Display deferral dates in PDF invoices (supported invoice plugin required).', 'wicket'),
                'default' => '0',
            ]);
        }

        $dynamic_trigger_section = $finance_tab->add_section(__('Revenue Deferral Dates — Dynamic Deferral Dates Trigger', 'wicket'));

        $dynamic_trigger_section->add_option('text', [
            'name' => 'wicket_finance_dynamic_trigger_help',
            'render' => function () {
                return '<p><em>' . __('Determines the WooCommerce order status that triggers dynamic deferral dates to be written. Regardless of this setting, dates will always be written when the order reaches "Processing" status.', 'wicket') . '</em></p>';
            },
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_draft',
            'label' => __('Draft', 'wicket'),
            'description' => __('Write dynamic deferral dates when order status changes to Draft.', 'wicket'),
            'default' => '0',
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_pending',
            'label' => __('Pending Payment', 'wicket'),
            'description' => __('Write dynamic deferral dates when order status changes to Pending Payment.', 'wicket'),
            'default' => '0',
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_on_hold',
            'label' => __('On Hold', 'wicket'),
            'description' => __('Write dynamic deferral dates when order status changes to On Hold.', 'wicket'),
            'default' => '0',
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_processing',
            'label' => __('Processing (Required)', 'wicket'),
            'description' => __('Write dynamic deferral dates when order status changes to Processing. This option is always enabled and cannot be disabled.', 'wicket'),
            'default' => '1',
            'attributes' => [
                'disabled' => 'disabled',
                'checked' => 'checked',
            ],
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_completed',
            'label' => __('Completed', 'wicket'),
            'description' => __('Write dynamic deferral dates when order status changes to Completed.', 'wicket'),
            'default' => '0',
        ]);
    }

    /**
     * Get product categories for select field.
     *
     * @return array Associative array of term_id => name.
     */
    private function get_product_categories(): array
    {
        $categories = [];

        if (!function_exists('get_terms') || !taxonomy_exists('product_cat')) {
            return $categories;
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return $categories;
        }

        foreach ($terms as $term) {
            $categories[$term->term_id] = $term->name;
        }

        return $categories;
    }

    /**
     * Check for supported invoice plugins.
     *
     * @return bool
     */
    private function has_supported_invoice_plugin(): bool
    {
        $supported_invoice_plugins = [
            'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php',
            'woocommerce-pdf-invoice/woocommerce-pdf-invoice.php',
        ];

        foreach ($supported_invoice_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }

        return false;
    }
}
