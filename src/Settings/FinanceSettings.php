<?php

declare(strict_types=1);

namespace Wicket\Finance\Settings;

/**
 * Finance settings facade.
 *
 * Provides typed getters for all finance-related settings.
 * Settings are stored with wicket_finance_* prefix.
 *
 * @since 1.0.0
 */
class FinanceSettings
{
    /**
     * Surface identifiers.
     */
    public const SURFACE_ORDER_CONFIRMATION = 'order_confirmation';
    public const SURFACE_EMAILS = 'emails';
    public const SURFACE_MY_ACCOUNT = 'my_account';
    public const SURFACE_SUBSCRIPTIONS = 'subscriptions';
    public const SURFACE_PDF_INVOICE = 'pdf_invoice';

    /**
     * Order status identifiers.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ON_HOLD = 'on-hold';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Gets eligible product categories for customer visibility.
     *
     * @return array Array of category IDs.
     */
    public function get_eligible_categories(): array
    {
        if (!$this->is_system_enabled()) {
            return [];
        }

        $categories = $this->get_option('wicket_finance_customer_visible_categories', []);

        if (!is_array($categories)) {
            return [];
        }

        return array_map('intval', $categories);
    }

    /**
     * Gets enabled visibility surfaces.
     *
     * @return array Array of surface identifiers.
     */
    public function get_visibility_surfaces(): array
    {
        if (!$this->is_system_enabled()) {
            return [];
        }

        $surfaces = [];

        if ($this->is_option_enabled('wicket_finance_display_order_confirmation')) {
            $surfaces[] = self::SURFACE_ORDER_CONFIRMATION;
        }

        if ($this->is_option_enabled('wicket_finance_display_emails')) {
            $surfaces[] = self::SURFACE_EMAILS;
        }

        if ($this->is_option_enabled('wicket_finance_display_my_account')) {
            $surfaces[] = self::SURFACE_MY_ACCOUNT;
        }

        if ($this->is_option_enabled('wicket_finance_display_subscriptions')) {
            $surfaces[] = self::SURFACE_SUBSCRIPTIONS;
        }

        if ($this->is_option_enabled('wicket_finance_display_pdf_invoices')) {
            $surfaces[] = self::SURFACE_PDF_INVOICE;
        }

        return $surfaces;
    }

    /**
     * Gets order status triggers for dynamic dates.
     *
     * Processing status is always included per specs.
     *
     * @return array Array of order status identifiers.
     */
    public function get_dynamic_date_triggers(): array
    {
        if (!$this->is_system_enabled()) {
            return [];
        }

        $triggers = [];

        if ($this->is_option_enabled('wicket_finance_trigger_draft')) {
            $triggers[] = self::STATUS_DRAFT;
        }

        if ($this->is_option_enabled('wicket_finance_trigger_pending')) {
            $triggers[] = self::STATUS_PENDING;
        }

        if ($this->is_option_enabled('wicket_finance_trigger_on_hold')) {
            $triggers[] = self::STATUS_ON_HOLD;
        }

        if ($this->is_option_enabled('wicket_finance_trigger_completed')) {
            $triggers[] = self::STATUS_COMPLETED;
        }

        // Ensure processing is always included
        if (!in_array(self::STATUS_PROCESSING, $triggers, true)) {
            $triggers[] = self::STATUS_PROCESSING;
        }

        return $triggers;
    }

    /**
     * Checks if a specific surface is enabled.
     *
     * @param string $surface Surface identifier.
     * @return bool True if enabled, false otherwise.
     */
    public function is_surface_enabled(string $surface): bool
    {
        $surfaces = $this->get_visibility_surfaces();

        return in_array($surface, $surfaces, true);
    }

    /**
     * Checks if a specific status should trigger dynamic dates.
     *
     * @param string $status Order status.
     * @return bool True if trigger enabled, false otherwise.
     */
    public function is_trigger_status(string $status): bool
    {
        $triggers = $this->get_dynamic_date_triggers();

        return in_array($status, $triggers, true);
    }

    /**
     * Checks if the finance system is enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    public function is_system_enabled(): bool
    {
        return $this->is_option_enabled('wicket_finance_enable_system');
    }

    /**
     * Saves eligible categories.
     *
     * @param array $category_ids Array of category IDs.
     * @return bool True on success, false on failure.
     */
    public function save_eligible_categories(array $category_ids): bool
    {
        $sanitized = array_map('intval', $category_ids);

        return update_option('wicket_finance_customer_visible_categories', $sanitized);
    }

    /**
     * Saves visibility surfaces.
     *
     * @param array $surfaces Array of surface identifiers.
     * @return bool True on success, false on failure.
     */
    public function save_visibility_surfaces(array $surfaces): bool
    {
        $surface_map = [
            self::SURFACE_ORDER_CONFIRMATION => 'wicket_finance_display_order_confirmation',
            self::SURFACE_EMAILS => 'wicket_finance_display_emails',
            self::SURFACE_MY_ACCOUNT => 'wicket_finance_display_my_account',
            self::SURFACE_SUBSCRIPTIONS => 'wicket_finance_display_subscriptions',
            self::SURFACE_PDF_INVOICE => 'wicket_finance_display_pdf_invoices',
        ];

        $success = true;

        foreach ($surface_map as $surface => $option_name) {
            $enabled = in_array($surface, $surfaces, true) ? '1' : '0';
            if (!update_option($option_name, $enabled)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Saves dynamic date triggers.
     *
     * Processing status is always included.
     *
     * @param array $statuses Array of order status identifiers.
     * @return bool True on success, false on failure.
     */
    public function save_dynamic_date_triggers(array $statuses): bool
    {
        $status_map = [
            self::STATUS_DRAFT => 'wicket_finance_trigger_draft',
            self::STATUS_PENDING => 'wicket_finance_trigger_pending',
            self::STATUS_ON_HOLD => 'wicket_finance_trigger_on_hold',
            self::STATUS_PROCESSING => 'wicket_finance_trigger_processing',
            self::STATUS_COMPLETED => 'wicket_finance_trigger_completed',
        ];

        $success = true;

        foreach ($status_map as $status => $option_name) {
            $enabled = in_array($status, $statuses, true) ? '1' : '0';

            if ($status === self::STATUS_PROCESSING) {
                $enabled = '1';
            }

            if (!update_option($option_name, $enabled)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Gets default settings for initial setup.
     *
     * @return array Default settings.
     */
    public function get_defaults(): array
    {
        return [
            'eligible_categories' => [],
            'visibility_surfaces' => [],
            'dynamic_date_triggers' => [self::STATUS_PROCESSING],
        ];
    }

    /**
     * Resets all settings to defaults.
     *
     * @return bool True on success, false on failure.
     */
    public function reset_to_defaults(): bool
    {
        $defaults = $this->get_defaults();
        $success = true;

        $option_defaults = [
            'wicket_finance_enable_system' => '0',
            'wicket_finance_customer_visible_categories' => $defaults['eligible_categories'],
            'wicket_finance_display_order_confirmation' => '0',
            'wicket_finance_display_emails' => '0',
            'wicket_finance_display_my_account' => '0',
            'wicket_finance_display_subscriptions' => '0',
            'wicket_finance_display_pdf_invoices' => '0',
            'wicket_finance_trigger_draft' => '0',
            'wicket_finance_trigger_pending' => '0',
            'wicket_finance_trigger_on_hold' => '0',
            'wicket_finance_trigger_processing' => '1',
            'wicket_finance_trigger_completed' => '0',
        ];

        foreach ($option_defaults as $key => $value) {
            if (!update_option($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Gets all settings as array.
     *
     * @return array All settings.
     */
    public function get_all(): array
    {
        return [
            'eligible_categories' => $this->get_eligible_categories(),
            'visibility_surfaces' => $this->get_visibility_surfaces(),
            'dynamic_date_triggers' => $this->get_dynamic_date_triggers(),
        ];
    }

    /**
     * Gets an option value with base plugin compatibility.
     *
     * @param string $option_name Option name.
     * @param mixed  $default     Default value if option doesn't exist.
     * @return mixed
     */
    private function get_option(string $option_name, $default = null)
    {
        if (function_exists('wicket_get_finance_option')) {
            return wicket_get_finance_option($option_name, $default);
        }

        return get_option($option_name, $default);
    }

    /**
     * Checks if an option is enabled.
     *
     * @param string $option_name Option name.
     * @return bool
     */
    private function is_option_enabled(string $option_name): bool
    {
        $value = $this->get_option($option_name, '0');

        return $value === '1' || $value === 1 || $value === true;
    }
}
