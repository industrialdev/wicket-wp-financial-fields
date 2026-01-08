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
     * Option key prefix.
     */
    private const OPTION_PREFIX = 'wicket_finance_';

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
        $categories = get_option(self::OPTION_PREFIX . 'eligible_categories', []);

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
        $surfaces = get_option(self::OPTION_PREFIX . 'visibility_surfaces', []);

        if (!is_array($surfaces)) {
            return [];
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
        $triggers = get_option(self::OPTION_PREFIX . 'dynamic_date_triggers', [self::STATUS_PROCESSING]);

        if (!is_array($triggers)) {
            $triggers = [self::STATUS_PROCESSING];
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
     * Saves eligible categories.
     *
     * @param array $category_ids Array of category IDs.
     * @return bool True on success, false on failure.
     */
    public function save_eligible_categories(array $category_ids): bool
    {
        $sanitized = array_map('intval', $category_ids);

        return update_option(self::OPTION_PREFIX . 'eligible_categories', $sanitized);
    }

    /**
     * Saves visibility surfaces.
     *
     * @param array $surfaces Array of surface identifiers.
     * @return bool True on success, false on failure.
     */
    public function save_visibility_surfaces(array $surfaces): bool
    {
        $valid_surfaces = [
            self::SURFACE_ORDER_CONFIRMATION,
            self::SURFACE_EMAILS,
            self::SURFACE_MY_ACCOUNT,
            self::SURFACE_SUBSCRIPTIONS,
            self::SURFACE_PDF_INVOICE,
        ];

        $sanitized = array_intersect($surfaces, $valid_surfaces);

        return update_option(self::OPTION_PREFIX . 'visibility_surfaces', $sanitized);
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
        $valid_statuses = [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_ON_HOLD,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
        ];

        $sanitized = array_intersect($statuses, $valid_statuses);

        // Ensure processing is always included
        if (!in_array(self::STATUS_PROCESSING, $sanitized, true)) {
            $sanitized[] = self::STATUS_PROCESSING;
        }

        return update_option(self::OPTION_PREFIX . 'dynamic_date_triggers', $sanitized);
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

        foreach ($defaults as $key => $value) {
            $result = update_option(self::OPTION_PREFIX . $key, $value);
            if (!$result) {
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
}
