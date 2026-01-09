<?php

declare(strict_types=1);

namespace Wicket\Finance\Settings;

use Wicket\Finance\Support\Logger;

/**
 * WPSettings-based Finance settings page.
 *
 * Registers Finance settings under the Wicket Settings UI.
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
     * Finance settings page instance.
     *
     * @var \Jeffreyvr\WPSettings\WPSettings|null
     */
    private $finance_settings_page;

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
     * Initialize WPSettings settings page.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('init', [$this, 'register_finance_settings_page']);
        add_action('admin_menu', [$this, 'register_finance_submenu'], 999);
        add_action('admin_menu', [$this, 'reorder_submenu'], 999);
    }

    /**
     * Ensure Finance appears after Wicket Settings in the submenu.
     *
     * @return void
     */
    public function reorder_submenu(): void
    {
        global $submenu;

        if (empty($submenu) || !is_array($submenu)) {
            return;
        }

        $parent_slug = $this->get_wicket_parent_slug();

        if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            return;
        }

        $items = $submenu[$parent_slug];
        $finance_index = null;
        $settings_index = null;

        foreach ($items as $index => $item) {
            if (!isset($item[2])) {
                continue;
            }

            if ($item[2] === 'wicket-settings-financial') {
                $finance_index = $index;
            } elseif ($item[2] === 'wicket-settings') {
                $settings_index = $index;
            }
        }

        if ($finance_index === null || $settings_index === null) {
            return;
        }

        if ($finance_index === $settings_index + 1) {
            return;
        }

        $finance_item = $items[$finance_index];
        unset($items[$finance_index]);
        $items = array_values($items);

        $insert_at = $settings_index + 1;
        array_splice($items, $insert_at, 0, [$finance_item]);

        $submenu[$parent_slug] = $items;
    }

    /**
     * Register Finance submenu under Wicket Settings.
     *
     * @return void
     */
    public function register_finance_submenu(): void
    {
        if (!$this->finance_settings_page || !function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'wicket-settings',
            __('Finance', 'wicket'),
            __('Finance', 'wicket'),
            'manage_options',
            'wicket-settings-financial',
            [$this, 'render_finance_settings_page']
        );
    }

    /**
     * Render the Finance settings page.
     *
     * @return void
     */
    public function render_finance_settings_page(): void
    {
        if (!$this->finance_settings_page) {
            return;
        }

        $this->finance_settings_page->render();
    }

    /**
     * Resolve the Wicket parent menu slug.
     *
     * @return string
     */
    private function get_wicket_parent_slug(): string
    {
        $menu = $GLOBALS['menu'] ?? [];

        foreach ($menu as $item) {
            if (!isset($item[2])) {
                continue;
            }

            if ($item[2] === 'wicket') {
                return 'wicket';
            }
        }

        foreach ($menu as $item) {
            if (!isset($item[2])) {
                continue;
            }

            if ($item[2] === 'wicket-settings') {
                return 'wicket-settings';
            }
        }

        return 'wicket-settings';
    }

    /**
     * Register the Finance settings page using WPSettings.
     *
     * @return void
     */
    public function register_finance_settings_page(): void
    {
        if (!class_exists(\Jeffreyvr\WPSettings\WPSettings::class)) {
            return;
        }

        if ($this->finance_settings_page) {
            return;
        }

        $settings = new \Jeffreyvr\WPSettings\WPSettings(__('Finance Settings', 'wicket'), 'wicket-settings-financial');
        $settings->set_menu_title(__('Finance', 'wicket'));
        $settings->set_capability('manage_options');
        $settings->set_option_name('wicket_settings');

        $finance_tab = $settings->add_tab(__('Finance', 'wicket'), 'finance');
        $this->register_finance_tab_sections($finance_tab);

        $settings->errors = new \Jeffreyvr\WPSettings\Error($settings);
        $settings->flash = new \Jeffreyvr\WPSettings\Flash($settings);

        add_action('admin_init', [$settings, 'save'], 20);
        add_action('admin_head', [$settings, 'styling'], 20);

        $this->finance_settings_page = $settings;
    }

    /**
     * Register Finance sections and options on a tab.
     *
     * @param mixed $finance_tab WPSettings tab instance.
     * @return void
     */
    private function register_finance_tab_sections($finance_tab): void
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
