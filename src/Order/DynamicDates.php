<?php

declare(strict_types=1);

namespace Wicket\Finance\Order;

use Wicket\Finance\Settings\FinanceSettings;
use Wicket\Finance\Support\Eligibility;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Support\MembershipGateway;
use Wicket\Finance\Support\DateFormatter;

/**
 * Dynamic dates service.
 *
 * Writes membership term dates to order line items automatically based on:
 * - Order status triggers (configured in settings)
 * - Membership product detection
 * - Membership creation/update events
 *
 * @since 1.0.0
 */
class DynamicDates
{
    /**
     * Settings facade.
     *
     * @var FinanceSettings
     */
    private $settings;

    /**
     * Line item meta service.
     *
     * @var LineItemMeta
     */
    private $line_item_meta;

    /**
     * Membership gateway.
     *
     * @var MembershipGateway
     */
    private $membership_gateway;

    /**
     * Eligibility service.
     *
     * @var Eligibility
     */
    private $eligibility;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Date formatter.
     *
     * @var DateFormatter
     */
    private $date_formatter;

    /**
     * Constructor.
     *
     * @param FinanceSettings   $settings            Settings facade.
     * @param LineItemMeta      $line_item_meta      Line item meta service.
     * @param MembershipGateway $membership_gateway  Membership gateway.
     * @param Eligibility       $eligibility         Eligibility service.
     * @param Logger            $logger              Logger instance.
     */
    public function __construct(
        FinanceSettings $settings,
        LineItemMeta $line_item_meta,
        MembershipGateway $membership_gateway,
        Eligibility $eligibility,
        Logger $logger
    ) {
        $this->settings = $settings;
        $this->line_item_meta = $line_item_meta;
        $this->membership_gateway = $membership_gateway;
        $this->eligibility = $eligibility;
        $this->logger = $logger;
        $this->date_formatter = new DateFormatter();
    }

    /**
     * Initialize dynamic dates hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Order status change hooks
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 3);

        // Order creation hook (for initial status)
        add_action('woocommerce_new_order', [$this, 'on_order_created'], 10, 2);

        // Membership creation hook - overwrites with authoritative dates
        add_action('wicket_member_create_record', [$this, 'on_membership_created'], 10, 3);
    }

    /**
     * Handles order status changes.
     *
     * @param int    $order_id   Order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     * @return void
     */
    public function on_order_status_changed(int $order_id, string $old_status, string $new_status): void
    {
        if (!$this->settings->is_system_enabled()) {
            return;
        }

        // Check if new status is a trigger
        if (!$this->settings->is_trigger_status($new_status)) {
            return;
        }

        $this->write_dynamic_dates($order_id, $new_status);
    }

    /**
     * Handles new order creation.
     *
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     * @return void
     */
    public function on_order_created(int $order_id, \WC_Order $order): void
    {
        if (!$this->settings->is_system_enabled()) {
            return;
        }

        $status = $order->get_status();

        // Check if initial status is a trigger
        if ($this->settings->is_trigger_status($status)) {
            $this->write_dynamic_dates($order_id, $status);
        }
    }

    /**
     * Handles membership creation - overwrites dates with authoritative membership post meta.
     *
     * @param array $membership         Membership data array.
     * @param bool  $processing_renewal Whether this is a renewal.
     * @param bool  $status_cycled      Whether status cycled.
     * @return void
     */
    public function on_membership_created(array $membership, bool $processing_renewal, bool $status_cycled): void
    {
        if (!$this->settings->is_system_enabled()) {
            return;
        }

        if (!$this->membership_gateway->is_available()) {
            return;
        }

        // Get required data from membership array
        $membership_post_id = $membership['membership_post_id'] ?? null;
        $order_id = $membership['membership_parent_order_id'] ?? null;
        $product_id = $membership['membership_product_id'] ?? null;

        if (empty($membership_post_id) || empty($order_id) || empty($product_id)) {
            $this->logger->warning('Membership created but missing required data', [
                'membership_post_id' => $membership_post_id,
                'order_id' => $order_id,
                'product_id' => $product_id,
            ]);
            return;
        }

        // Get authoritative dates from membership post
        $dates = $this->membership_gateway->get_authoritative_membership_dates($membership_post_id);

        if (!$dates || empty($dates['start_date']) || empty($dates['end_date'])) {
            $this->logger->warning('Membership created but no authoritative dates found', [
                'membership_post_id' => $membership_post_id,
                'order_id' => $order_id,
            ]);
            return;
        }

        // Convert ISO 8601 to Y-m-d
        $start_date = $this->date_formatter->from_iso_8601($dates['start_date']);
        $end_date = $this->date_formatter->from_iso_8601($dates['end_date']);

        if (empty($start_date) || empty($end_date)) {
            $this->logger->error('Failed to convert authoritative dates from ISO 8601', [
                'membership_post_id' => $membership_post_id,
                'raw_dates' => $dates,
            ]);
            return;
        }

        // Find line item for this product in the order
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->logger->error('Order not found for membership', [
                'order_id' => $order_id,
                'membership_post_id' => $membership_post_id,
            ]);
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            if (!($item instanceof \WC_Order_Item_Product)) {
                continue;
            }

            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();

            // Match product or variation
            if ($item_product_id == $product_id || $item_variation_id == $product_id) {
                // Update line item with authoritative dates
                $this->line_item_meta->update_dates(
                    $order_id,
                    $item_id,
                    $start_date,
                    $end_date,
                    'System (Membership Created)'
                );

                $this->logger->info('Authoritative membership dates written to line item', [
                    'membership_post_id' => $membership_post_id,
                    'order_id' => $order_id,
                    'item_id' => $item_id,
                    'product_id' => $product_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'is_renewal' => $processing_renewal,
                ]);

                break; // Only update first matching item
            }
        }
    }

    /**
     * Writes dynamic dates for membership products in order.
     *
     * @param int    $order_id Order ID.
     * @param string $status   Order status that triggered the write.
     * @return void
     */
    private function write_dynamic_dates(int $order_id, string $status): void
    {
        if (!$this->membership_gateway->is_available()) {
            $this->logger->debug('Membership plugin not available, skipping dynamic dates', [
                'order_id' => $order_id,
                'status' => $status,
            ]);
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            if (!($item instanceof \WC_Order_Item_Product)) {
                continue;
            }

            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            // Check if product is membership product
            if (!$this->eligibility->is_membership_product($product)) {
                continue;
            }

            // Check if product has deferred revenue required
            if (!$this->eligibility->is_deferred_revenue_required($product)) {
                continue;
            }

            // Calculate membership dates
            $dates = $this->membership_gateway->calculate_membership_dates($product->get_id());

            if (!$dates || empty($dates['start_date']) || empty($dates['end_date'])) {
                $this->logger->warning('Failed to calculate membership dates', [
                    'order_id' => $order_id,
                    'item_id' => $item_id,
                    'product_id' => $product->get_id(),
                ]);
                continue;
            }

            // Convert ISO 8601 to storage format (Y-m-d)
            $start_date = $this->date_formatter->from_iso_8601($dates['start_date']);
            $end_date = $this->date_formatter->from_iso_8601($dates['end_date']);

            if (empty($start_date) || empty($end_date)) {
                $this->logger->error('Failed to convert dates from ISO 8601', [
                    'order_id' => $order_id,
                    'item_id' => $item_id,
                    'raw_dates' => $dates,
                ]);
                continue;
            }

            // Update line item dates
            $this->line_item_meta->update_dates($order_id, $item_id, $start_date, $end_date, 'System');

            $this->logger->info('Dynamic dates written for membership product', [
                'order_id' => $order_id,
                'item_id' => $item_id,
                'product_id' => $product->get_id(),
                'status' => $status,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ]);
        }
    }
}
