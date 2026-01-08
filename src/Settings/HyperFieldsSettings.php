<?php

declare(strict_types=1);

namespace Wicket\Finance\Settings;

use HyperFields\HyperFields;
use Wicket\Finance\Support\Logger;

/**
 * HyperFields-based Finance settings page.
 *
 * Provides a standalone Finance settings page using HyperPress/HyperFields API.
 *
 * @since 1.0.0
 */
class HyperFieldsSettings
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
     * Initialize HyperFields settings page.
     *
     * @return void
     */
    public function init(): void
    {
        // Register settings page on admin_menu with priority 99 (after all other plugins)
        add_action('admin_menu', [$this, 'register_settings_page'], 99);
    }

    /**
     * Registers the HyperFields-based Finance settings page.
     *
     * @return void
     */
    public function register_settings_page(): void
    {
        // Check if HyperFields is available
        if (!class_exists(HyperFields::class)) {
            $this->logger->warning('HyperFields not available, skipping HyperFields settings page');
            return;
        }

        // Create Finance Settings page
        $options_page = HyperFields::makeOptionPage(
            __('Finance Settings', 'wicket-finance'),
            'wicket-finance-settings'
        )
            ->setMenuTitle(__('Finance', 'wicket-finance'))
            ->setParentSlug('wicket-settings')
            ->setCapability('manage_options')
            ->setOptionName('wicket_finance_settings');

        // Feature Control Section
        $feature_section = $options_page->addSection(
            'feature_control',
            __('Revenue Deferral Dates — Feature Control', 'wicket-finance'),
            __('Enable/disable the finance mapping and deferral dates system.', 'wicket-finance')
        );

        $feature_section->addField(
            HyperFields::makeField('checkbox', 'wicket_finance_enable_system', __('Enable Finance Mapping System', 'wicket-finance'))
                ->setDefault(false)
        );

        // Customer Visibility Section
        $visibility_section = $options_page->addSection(
            'customer_visibility',
            __('Revenue Deferral Dates — Customer Visibility', 'wicket-finance'),
            __('Configure where deferral dates are displayed to customers.', 'wicket-finance')
        );

        // Product categories multi-select
        $product_categories = $this->get_product_categories();
        $visibility_section->addField(
            HyperFields::makeField('select', 'wicket_finance_customer_visible_categories', __('Product Categories for Customer Display', 'wicket-finance'))
                ->setOptions($product_categories)
                ->setMultiple(true)
                ->setDefault([])
        );

        // Visibility surface checkboxes
        $surfaces = [
            'order_confirmation' => __('Order Confirmation Page', 'wicket-finance'),
            'emails' => __('Email Notifications', 'wicket-finance'),
            'my_account' => __('My Account › Orders', 'wicket-finance'),
            'subscriptions' => __('Subscriptions Details', 'wicket-finance'),
            'pdf_invoices' => __('PDF Invoices', 'wicket-finance'),
        ];

        foreach ($surfaces as $key => $label) {
            $field_name = 'wicket_finance_display_' . $key;
            $visibility_section->addField(
                HyperFields::makeField('checkbox', $field_name, $label)
                    ->setDefault(false)
            );
        }

        // Dynamic Triggers Section
        $triggers_section = $options_page->addSection(
            'dynamic_triggers',
            __('Revenue Deferral Dates — Dynamic Deferral Dates Trigger', 'wicket-finance'),
            __('Configure which WooCommerce order statuses trigger automatic deferral date population.', 'wicket-finance')
        );

        // Order status triggers
        $statuses = [
            'draft' => __('Draft', 'wicket-finance'),
            'pending' => __('Pending Payment', 'wicket-finance'),
            'on_hold' => __('On Hold', 'wicket-finance'),
            'processing' => __('Processing (Required)', 'wicket-finance'),
            'completed' => __('Completed', 'wicket-finance'),
        ];

        foreach ($statuses as $key => $label) {
            $field_name = 'wicket_finance_trigger_' . $key;
            $field = HyperFields::makeField('checkbox', $field_name, $label)
                ->setDefault($key === 'processing');

            // Processing is always enabled, make it readonly
            if ($key === 'processing') {
                $field->addArg('disabled', true);
            }

            $triggers_section->addField($field);
        }

        // Register the page
        $options_page->register();
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
}
