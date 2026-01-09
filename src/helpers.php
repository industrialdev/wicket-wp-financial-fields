<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!function_exists('wicket_get_finance_option')) {
    /**
     * Helper function to get finance setting value.
     *
     * @param string $option_name The option name.
     * @param mixed  $default     Default value if option doesn't exist.
     * @return mixed The option value.
     */
    function wicket_get_finance_option(string $option_name, $default = null)
    {
        if (function_exists('wicket_get_option')) {
            return wicket_get_option($option_name, $default);
        }

        return get_option($option_name, $default);
    }
}

if (!function_exists('wicket_is_finance_system_enabled')) {
    /**
     * Check if finance system is enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    function wicket_is_finance_system_enabled(): bool
    {
        return wicket_get_finance_option('wicket_finance_enable_system', '1') === '1';
    }
}

if (!function_exists('wicket_get_finance_organizations')) {
    /**
     * Helper function to get parsed organizations as array.
     *
     * @return array Array of organizations in format ['slug' => 'Display Name'].
     */
    function wicket_get_finance_organizations(): array
    {
        $organizations_text = wicket_get_finance_option('wicket_finance_organizations', '');
        $organizations = [];

        if (!empty($organizations_text)) {
            $lines = explode("\n", $organizations_text);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '|') !== false) {
                    [$slug, $name] = explode('|', $line, 2);
                    $organizations[trim($slug)] = trim($name);
                }
            }
        }

        return $organizations;
    }
}

if (!function_exists('wicket_get_finance_delivery_vendors')) {
    /**
     * Helper function to get parsed delivery vendors as array.
     *
     * @return array Array of vendors in format ['slug' => 'Display Name'].
     */
    function wicket_get_finance_delivery_vendors(): array
    {
        $vendors_text = wicket_get_finance_option('wicket_finance_delivery_vendors', '');
        $vendors = [];

        if (!empty($vendors_text)) {
            $lines = explode("\n", $vendors_text);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '|') !== false) {
                    [$slug, $name] = explode('|', $line, 2);
                    $vendors[trim($slug)] = trim($name);
                }
            }
        }

        return $vendors;
    }
}

if (!function_exists('wicket_get_finance_sales_reps')) {
    /**
     * Helper function to get parsed sales representatives as array.
     *
     * @return array Array of sales reps in format ['code' => 'Display Name'].
     */
    function wicket_get_finance_sales_reps(): array
    {
        $sales_reps_text = wicket_get_finance_option('wicket_finance_sales_reps', '');
        $sales_reps = [];

        if (!empty($sales_reps_text)) {
            $lines = explode("\n", $sales_reps_text);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '|') !== false) {
                    [$code, $name] = explode('|', $line, 2);
                    $sales_reps[trim($code)] = trim($name);
                }
            }
        }

        return $sales_reps;
    }
}

if (!function_exists('wicket_get_dynamic_deferral_triggers')) {
    /**
     * Get array of order statuses that should trigger dynamic deferral dates.
     *
     * @return array Array of order status slugs.
     */
    function wicket_get_dynamic_deferral_triggers(): array
    {
        $triggers = ['processing'];

        if (wicket_get_finance_option('wicket_finance_trigger_draft', '0') === '1') {
            $triggers[] = 'draft';
        }

        if (wicket_get_finance_option('wicket_finance_trigger_pending', '0') === '1') {
            $triggers[] = 'pending';
        }

        if (wicket_get_finance_option('wicket_finance_trigger_on_hold', '0') === '1') {
            $triggers[] = 'on-hold';
        }

        if (wicket_get_finance_option('wicket_finance_trigger_completed', '0') === '1') {
            $triggers[] = 'completed';
        }

        return $triggers;
    }
}

if (!function_exists('wicket_is_product_category_eligible_for_customer_display')) {
    /**
     * Check if a product category is eligible for customer-facing deferral date display.
     *
     * @param int|array $product_categories Product category ID(s) to check.
     * @return bool True if eligible, false otherwise.
     */
    function wicket_is_product_category_eligible_for_customer_display($product_categories): bool
    {
        if (!wicket_is_finance_system_enabled()) {
            return false;
        }

        $eligible_categories = wicket_get_finance_option('wicket_finance_customer_visible_categories', []);

        if (empty($eligible_categories)) {
            return false;
        }

        if (!is_array($product_categories)) {
            $product_categories = [$product_categories];
        }

        foreach ($product_categories as $category_id) {
            if (in_array($category_id, $eligible_categories, true)) {
                return true;
            }
        }

        return false;
    }
}
