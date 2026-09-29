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
            'label' => _x('Finance', 'label', 'wicket-finance'),
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

        $finance_tab = $settings->add_tab(_x('Finance', 'label', 'wicket-finance'));
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

        $feature_control_section = $finance_tab->add_section(__('Revenue Deferral Dates — Feature Control', 'wicket-finance'));

        $feature_control_section->add_option('checkbox', [
            'name' => 'wicket_finance_enable_system',
            'label' => __('Enable Finance Mapping System', 'wicket-finance'),
            'description' => __('Enable the entire finance mapping and deferral dates system.', 'wicket-finance'),
            'default' => '1',
        ]);

        $customer_visibility_section = $finance_tab->add_section(__('Revenue Deferral Dates — Customer Visibility', 'wicket-finance'));

        $customer_visibility_section->add_option('select-multiple', [
            'name' => 'wicket_finance_customer_visible_categories',
            'label' => __('Product Categories for Customer Display', 'wicket-finance'),
            'description' => __('Select product categories that should display deferral dates to customers. Only products in these categories will show deferral dates on customer-facing surfaces.', 'wicket-finance'),
            'options' => $this->get_product_categories(),
            'default' => [],
        ]);

        $customer_visibility_section->add_option('checkbox', [
            'name' => 'wicket_finance_display_order_confirmation',
            'label' => _x('Order Confirmation Page', 'label', 'wicket-finance'),
            'description' => __('Display deferral dates on the order confirmation page.', 'wicket-finance'),
            'default' => '0',
        ]);

        $customer_visibility_section->add_option('checkbox', [
            'name' => 'wicket_finance_display_emails',
            'label' => _x('Email Notifications', 'label', 'wicket-finance'),
            'description' => __('Display deferral dates in email notifications (Pending payment, On hold, Processing, Completed, Renewal).', 'wicket-finance'),
            'default' => '0',
        ]);

        $customer_visibility_section->add_option('checkbox', [
            'name' => 'wicket_finance_display_my_account',
            'label' => _x('My Account › Orders', 'label', 'wicket-finance'),
            'description' => __('Display deferral dates in the My Account order details view.', 'wicket-finance'),
            'default' => '0',
        ]);

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (function_exists('is_plugin_active') && is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')) {
            $customer_visibility_section->add_option('checkbox', [
                'name' => 'wicket_finance_display_subscriptions',
                'label' => _x('Subscriptions Details', 'label', 'wicket-finance'),
                'description' => __('Display deferral dates in subscription details (WooCommerce Subscriptions required).', 'wicket-finance'),
                'default' => '0',
            ]);
        }

        if ($this->has_supported_invoice_plugin()) {
            $customer_visibility_section->add_option('checkbox', [
                'name' => 'wicket_finance_display_pdf_invoices',
                'label' => _x('PDF Invoices', 'label', 'wicket-finance'),
                'description' => __('Display deferral dates in PDF invoices (supported invoice plugin required).', 'wicket-finance'),
                'default' => '0',
            ]);
        }

        $dynamic_trigger_section = $finance_tab->add_section(__('Revenue Deferral Dates — Dynamic Deferral Dates Trigger', 'wicket-finance'));

        $dynamic_trigger_section->add_option('text', [
            'name' => 'wicket_finance_dynamic_trigger_help',
            'render' => function () {
                return '<p><em>' . esc_html__('Determines the WooCommerce order status that triggers dynamic deferral dates to be written. Regardless of this setting, dates will always be written when the order reaches "Processing" status.', 'wicket-finance') . '</em></p>';
            },
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_draft',
            'label' => $this->get_order_status_label(FinanceSettings::STATUS_DRAFT),
            'description' => $this->get_trigger_description(FinanceSettings::STATUS_DRAFT),
            'default' => '0',
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_pending',
            'label' => $this->get_order_status_label(FinanceSettings::STATUS_PENDING),
            'description' => $this->get_trigger_description(FinanceSettings::STATUS_PENDING),
            'default' => '0',
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_on_hold',
            'label' => $this->get_order_status_label(FinanceSettings::STATUS_ON_HOLD),
            'description' => $this->get_trigger_description(FinanceSettings::STATUS_ON_HOLD),
            'default' => '0',
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_processing',
            'label' => sprintf(
                /* translators: %s: order status name. */
                _x('%s (Required)', 'label', 'wicket-finance'),
                $this->get_order_status_label(FinanceSettings::STATUS_PROCESSING)
            ),
            'description' => $this->get_trigger_description(FinanceSettings::STATUS_PROCESSING)
                . ' ' . __('This option is always enabled and cannot be disabled.', 'wicket-finance'),
            'default' => '1',
            'attributes' => [
                'disabled' => 'disabled',
                'checked' => 'checked',
            ],
        ]);

        $dynamic_trigger_section->add_option('checkbox', [
            'name' => 'wicket_finance_trigger_completed',
            'label' => $this->get_order_status_label(FinanceSettings::STATUS_COMPLETED),
            'description' => $this->get_trigger_description(FinanceSettings::STATUS_COMPLETED),
            'default' => '0',
        ]);
    }

    /**
     * Get the translated label for an order status.
     *
     * Uses WooCommerce's own label so sites get WooCommerce's translation.
     *
     * @param string $status Order status without the `wc-` prefix.
     * @return string
     */
    private function get_order_status_label(string $status): string
    {
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];

        if (isset($statuses['wc-' . $status])) {
            return $statuses['wc-' . $status];
        }

        // Draft is only registered by WooCommerce Blocks.
        if ($status === FinanceSettings::STATUS_DRAFT) {
            return _x('Draft', 'order status', 'wicket-finance');
        }

        return $status;
    }

    /**
     * Get the description for a dynamic deferral date trigger option.
     *
     * @param string $status Order status without the `wc-` prefix.
     * @return string
     */
    private function get_trigger_description(string $status): string
    {
        return sprintf(
            /* translators: %s: order status name. */
            __('Write dynamic deferral dates when order status changes to %s.', 'wicket-finance'),
            $this->get_order_status_label($status)
        );
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
