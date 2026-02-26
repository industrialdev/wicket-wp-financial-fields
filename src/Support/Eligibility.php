<?php

declare(strict_types=1);

namespace Wicket\Finance\Support;

use Wicket\Finance\Settings\FinanceSettings;

/**
 * Eligibility checks for finance field display and processing.
 *
 * Handles:
 * - Product category eligibility (parent categories for variations)
 * - Membership category detection
 * - Surface visibility checks
 *
 * @since 1.0.0
 */
class Eligibility
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
     * Cached membership category IDs.
     *
     * @var array|null
     */
    private $membership_category_ids = null;

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
     * Checks if a product is eligible for customer-facing date display.
     *
     * Requirements:
     * - Product (or parent for variations) must be in at least one selected category
     * - Surface must be enabled in settings
     * - Line item must have both start and end dates set
     *
     * @param int|\WC_Product $product     Product ID or object.
     * @param string          $surface     Surface name (order_confirmation, emails, my_account, subscriptions, pdf_invoice).
     * @param string          $start_date  Start date value.
     * @param string          $end_date    End date value.
     * @return bool True if eligible, false otherwise.
     */
    public function is_eligible_for_display($product, string $surface, string $start_date, string $end_date): bool
    {
        // Check if both dates are set
        if (empty($start_date) || empty($end_date)) {
            return false;
        }

        // Check if surface is enabled
        if (!$this->is_surface_enabled($surface)) {
            return false;
        }

        // Get product object if ID provided
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return false;
        }

        // Check product category eligibility
        return $this->is_product_in_eligible_categories($product);
    }

    /**
     * Checks if a product is in eligible categories for customer display.
     *
     * For variable products, checks parent product categories.
     *
     * @param \WC_Product $product Product object.
     * @return bool True if in eligible categories, false otherwise.
     */
    public function is_product_in_eligible_categories(\WC_Product $product): bool
    {
        $eligible_category_ids = $this->settings->get_eligible_categories();

        if (empty($eligible_category_ids)) {
            return false;
        }

        // For variations, get parent product
        $check_product = $product;
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $check_product = wc_get_product($parent_id);
            }
        }

        if (!$check_product) {
            return false;
        }

        // Get product category IDs
        $product_category_ids = $check_product->get_category_ids();

        if (empty($product_category_ids)) {
            return false;
        }

        // Check for intersection
        $intersection = array_intersect($eligible_category_ids, $product_category_ids);

        return !empty($intersection);
    }

    /**
     * Checks if a surface is enabled for displaying dates.
     *
     * @param string $surface Surface name.
     * @return bool True if enabled, false otherwise.
     */
    public function is_surface_enabled(string $surface): bool
    {
        $enabled_surfaces = $this->settings->get_visibility_surfaces();

        return in_array($surface, $enabled_surfaces, true);
    }

    /**
     * Checks if a product is in a membership category.
     *
     * Used to determine if dynamic dates should be written.
     *
     * @param int|\WC_Product $product Product ID or object.
     * @return bool True if in membership category, false otherwise.
     */
    public function is_membership_product($product): bool
    {
        // Get product object if ID provided
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return false;
        }

        // For variations, get parent product
        $check_product = $product;
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $check_product = wc_get_product($parent_id);
            }
        }

        if (!$check_product) {
            return false;
        }

        // Get membership category IDs
        $membership_category_ids = $this->get_membership_category_ids();

        // Get product categories
        $category_ids = $check_product->get_category_ids();

        if (empty($category_ids)) {
            return false;
        }

        // Check if any product category ID matches membership categories
        $intersection = array_intersect($membership_category_ids, $category_ids);

        return !empty($intersection);
    }

    /**
     * Gets membership category IDs from the membership plugin options.
     *
     * Reads wicket_membership_plugin_options[wicket_show_mship_order_org_search][categorychoice][]
     * and caches the result for reuse.
     *
     * @return array Array of category IDs.
     */
    public function get_membership_category_ids(): array
    {
        if ($this->membership_category_ids !== null) {
            return $this->membership_category_ids;
        }

        $options = get_option('wicket_membership_plugin_options', []);

        $selected_categories = $options['wicket_show_mship_order_org_search']['categorychoice'] ?? [];

        // Normalize and ensure we only return valid IDs.
        $this->membership_category_ids = array_values(array_filter(array_map('absint', (array) $selected_categories)));

        return $this->membership_category_ids;
    }

    /**
     * Checks if a product has deferred revenue required.
     *
     * @param int|\WC_Product $product Product ID or object.
     * @return bool True if deferred revenue required, false otherwise.
     */
    public function is_deferred_revenue_required($product): bool
    {
        // Get product object if ID provided
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return false;
        }

        // For variations, check parent product
        $check_product = $product;
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $check_product = wc_get_product($parent_id);
            }
        }

        if (!$check_product) {
            return false;
        }

        $deferred_required = $check_product->get_meta('_wicket_finance_deferred_required', true);

        return $deferred_required === 'yes';
    }
}
