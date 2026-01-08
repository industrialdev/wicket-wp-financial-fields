<?php

declare(strict_types=1);

namespace Wicket\Finance\Support;

use Wicket_Memberships\Membership_Controller;
use Wicket_Memberships\Membership_Config;

/**
 * Membership gateway facade.
 *
 * Interfaces with Wicket Memberships plugin for date calculations.
 * Provides a facade to hide membership plugin implementation details.
 *
 * Key method: Membership_Config::get_membership_dates()
 * Location: wicket-wp-memberships/includes/Membership_Config.php:333
 *
 * @since 1.0.0
 */
class MembershipGateway
{
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Checks if Wicket Memberships plugin is available.
     *
     * @return bool True if available, false otherwise.
     */
    public function is_available(): bool
    {
        return class_exists('Wicket_Memberships\Membership_Config');
    }

    /**
     * Calculates membership dates for a product/order.
     *
     * Calls Membership_Config::get_membership_dates() which handles:
     * - Anniversary vs calendar cycle types
     * - New memberships vs renewals
     * - Returns start_date, end_date, early_renew_at, expires_at in ISO 8601 format
     *
     * @param int   $product_id        Product ID.
     * @param array $existing_membership Optional existing membership data for renewals.
     * @return array|null Array with start_date and end_date, or null on failure.
     */
    public function calculate_membership_dates(int $product_id, array $existing_membership = []): ?array
    {
        if (!$this->is_available()) {
            $this->logger->warning('Membership plugin not available for date calculation', [
                'product_id' => $product_id,
            ]);

            return null;
        }

        try {
            // Get membership config from product
            $config_id = $this->get_config_id_from_product($product_id);

            if (!$config_id) {
                $this->logger->warning('No membership config found for product', [
                    'product_id' => $product_id,
                ]);

                return null;
            }

            // Create Membership_Config instance
            $config = new Membership_Config($config_id);

            // Call get_membership_dates
            $dates = $config->get_membership_dates($existing_membership);

            if (empty($dates['start_date']) || empty($dates['end_date'])) {
                $this->logger->error('Invalid dates returned from membership config', [
                    'product_id' => $product_id,
                    'config_id' => $config_id,
                    'dates' => $dates,
                ]);

                return null;
            }

            $this->logger->debug('Calculated membership dates', [
                'product_id' => $product_id,
                'config_id' => $config_id,
                'is_renewal' => !empty($existing_membership),
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
            ]);

            return [
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'early_renew_at' => $dates['early_renew_at'] ?? '',
                'expires_at' => $dates['expires_at'] ?? '',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception calculating membership dates', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Gets membership data from order for renewal date calculation.
     *
     * Retrieves existing membership post data for calculating renewal dates.
     *
     * @param int $order_id Order ID.
     * @param int $product_id Product ID.
     * @return array|null Membership data array, or null if not found.
     */
    public function get_membership_from_order(int $order_id, int $product_id): ?array
    {
        if (!$this->is_available()) {
            return null;
        }

        try {
            // Use Membership_Controller to get membership data
            if (!class_exists('Wicket_Memberships\Membership_Controller')) {
                return null;
            }

            $membership_array = Membership_Controller::get_membership_array_from_order_id_and_product_id(
                $order_id,
                $product_id
            );

            if (empty($membership_array)) {
                return null;
            }

            return $membership_array;
        } catch (\Exception $e) {
            $this->logger->error('Exception getting membership from order', [
                'order_id' => $order_id,
                'product_id' => $product_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Gets authoritative membership dates from membership post.
     *
     * When membership is created/updated, retrieves the actual membership term dates
     * from the membership post meta (membership_starts_at, membership_ends_at).
     *
     * @param int $membership_post_id Membership post ID.
     * @return array|null Array with start_date and end_date, or null if not found.
     */
    public function get_authoritative_membership_dates(int $membership_post_id): ?array
    {
        if (!$this->is_available()) {
            return null;
        }

        try {
            $starts_at = get_post_meta($membership_post_id, 'membership_starts_at', true);
            $ends_at = get_post_meta($membership_post_id, 'membership_ends_at', true);

            if (empty($starts_at) || empty($ends_at)) {
                $this->logger->warning('Membership post missing date meta', [
                    'membership_post_id' => $membership_post_id,
                ]);

                return null;
            }

            return [
                'start_date' => $starts_at,
                'end_date' => $ends_at,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception getting authoritative membership dates', [
                'membership_post_id' => $membership_post_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Gets membership config ID from product.
     *
     * Attempts to find the membership config associated with a product.
     *
     * @param int $product_id Product ID.
     * @return int|null Config post ID, or null if not found.
     */
    private function get_config_id_from_product(int $product_id): ?int
    {
        // Get product meta for membership tier
        $tier_post_id = get_post_meta($product_id, 'membership_tier_post_id', true);

        if (empty($tier_post_id)) {
            return null;
        }

        // Get config ID from tier
        $config_id = get_post_meta($tier_post_id, 'membership_config_post_id', true);

        if (empty($config_id)) {
            return null;
        }

        return (int) $config_id;
    }
}
