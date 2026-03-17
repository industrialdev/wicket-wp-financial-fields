<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Order;

use Brain\Monkey\Functions;
use Mockery;
use Wicket\Finance\Order\DynamicDates;
use Wicket\Finance\Order\LineItemMeta;
use Wicket\Finance\Settings\FinanceSettings;
use Wicket\Finance\Support\Eligibility;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Support\MembershipGateway;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('DynamicDates', function () {
    beforeEach(function () {
        $this->settings = Mockery::mock(FinanceSettings::class);
        $this->line_item_meta = Mockery::mock(LineItemMeta::class);
        $this->membership_gateway = Mockery::mock(MembershipGateway::class);
        $this->eligibility = Mockery::mock(Eligibility::class);
        $this->logger = Mockery::mock(Logger::class);

        // Default to non-renewal behavior unless explicitly overridden per test.
        Functions\when('wc_get_order_item_meta')->justReturn(null);

        $this->dynamic_dates = new DynamicDates(
            $this->settings,
            $this->line_item_meta,
            $this->membership_gateway,
            $this->eligibility,
            $this->logger
        );
    });

    describe('on_order_status_changed()', function () {
        it('returns early when system disabled', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(false);

            $this->settings->shouldNotReceive('is_trigger_status');

            $this->dynamic_dates->on_order_status_changed(999, 'pending', 'processing');
        });

        it('returns early when new status is not a trigger', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('pending')->once()->andReturn(false);

            $this->dynamic_dates->on_order_status_changed(999, 'draft', 'pending');
        });

        it('writes dynamic dates when status is trigger and membership available', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_id')->andReturn(100);
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_deferred_revenue_required')->with($product)->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('calculate_membership_dates')
                ->once()
                ->with(100, [])
                ->andReturn([
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2024-12-31T23:59:59+00:00',
                ]);
            $this->line_item_meta->shouldReceive('update_dates')
                ->once()
                ->with(999, 1, '2024-01-01', '2024-12-31', 'System');
            $this->logger->shouldReceive('info')->once();

            $this->dynamic_dates->on_order_status_changed(999, 'pending', 'processing');
        });
    });

    describe('on_order_created()', function () {
        it('returns early when system disabled', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(false);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_status')->never();

            $this->dynamic_dates->on_order_created(999, $order);
        });

        it('writes dynamic dates for initial trigger status', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_id')->andReturn(100);
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_status')->once()->andReturn('processing');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_deferred_revenue_required')->with($product)->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('calculate_membership_dates')
                ->once()
                ->with(100, [])
                ->andReturn([
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2024-12-31T23:59:59+00:00',
                ]);
            $this->line_item_meta->shouldReceive('update_dates')
                ->once()
                ->with(999, 1, '2024-01-01', '2024-12-31', 'System');
            $this->logger->shouldReceive('info')->once();

            $this->dynamic_dates->on_order_created(999, $order);
        });

        it('does not write dates for non-trigger status', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('pending')->once()->andReturn(false);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_status')->once()->andReturn('pending');

            $this->dynamic_dates->on_order_created(999, $order);
        });
    });

    describe('on_pending_parent_order_created()', function () {
        it('returns early when system disabled', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(false);

            $subscription = Mockery::mock('WC_Subscription');
            $subscription->shouldReceive('get_parent_id')->never();

            $this->dynamic_dates->on_pending_parent_order_created($subscription);
        });

        it('writes dynamic dates for trigger status on parent order', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('pending')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_id')->andReturn(100);
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $subscription = Mockery::mock('WC_Subscription');
            $subscription->shouldReceive('get_parent_id')->once()->andReturn(999);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_status')->once()->andReturn('pending');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_deferred_revenue_required')->with($product)->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('calculate_membership_dates')
                ->once()
                ->with(100, [])
                ->andReturn([
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2024-12-31T23:59:59+00:00',
                ]);
            $this->line_item_meta->shouldReceive('update_dates')
                ->once()
                ->with(999, 1, '2024-01-01', '2024-12-31', 'System');
            $this->logger->shouldReceive('info')->once();

            $this->dynamic_dates->on_pending_parent_order_created($subscription);
        });
    });

    describe('on_renewal_order_created()', function () {
        it('returns the order unchanged when system disabled', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(false);

            $order = Mockery::mock('WC_Order');
            $subscription = Mockery::mock('WC_Subscription');

            $result = $this->dynamic_dates->on_renewal_order_created($order, $subscription);

            expect($result)->toBe($order);
        });

        it('writes dynamic dates for initial trigger status and returns order', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_id')->andReturn(100);
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_status')->once()->andReturn('processing');
            $order->shouldReceive('get_id')->once()->andReturn(999);
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            $subscription = Mockery::mock('WC_Subscription');

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_deferred_revenue_required')->with($product)->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('calculate_membership_dates')
                ->once()
                ->with(100, [])
                ->andReturn([
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2024-12-31T23:59:59+00:00',
                ]);
            $this->line_item_meta->shouldReceive('update_dates')
                ->once()
                ->with(999, 1, '2024-01-01', '2024-12-31', 'System');
            $this->logger->shouldReceive('info')->once();

            $result = $this->dynamic_dates->on_renewal_order_created($order, $subscription);

            expect($result)->toBe($order);
        });
    });

    describe('on_membership_created()', function () {
        it('returns early when system disabled', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(false);

            $this->membership_gateway->shouldNotReceive('is_available');

            $this->dynamic_dates->on_membership_created([], false, false);
        });

        it('returns early when membership gateway unavailable', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(false);

            $this->dynamic_dates->on_membership_created([
                'membership_post_id' => 100,
                'membership_parent_order_id' => 200,
                'membership_product_id' => 300,
            ], false, false);
        });

        it('returns early when missing required data', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);

            $this->logger->shouldReceive('warning')->once();

            $this->dynamic_dates->on_membership_created([
                'membership_post_id' => 100,
            ], false, false);
        });

        it('updates line item with authoritative dates', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('get_authoritative_membership_dates')
                ->once()
                ->with(100)
                ->andReturn([
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2024-12-31T23:59:59+00:00',
                ]);

            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product_id')->andReturn(300);
            $item->shouldReceive('get_variation_id')->andReturn(0);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            Functions\when('wc_get_order')->justReturn($order);

            $this->line_item_meta->shouldReceive('update_dates')
                ->once()
                ->with(200, 1, '2024-01-01', '2024-12-31', 'System (Membership Created)')
                ->andReturn(true);

            $this->logger->shouldReceive('info')->once();

            $this->dynamic_dates->on_membership_created([
                'membership_post_id' => 100,
                'membership_parent_order_id' => 200,
                'membership_product_id' => 300,
            ], false, false);
        });

        it('logs warning when authoritative dates not found', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('get_authoritative_membership_dates')
                ->once()
                ->with(100)
                ->andReturn(null);

            $this->logger->shouldReceive('warning')->once();

            $this->dynamic_dates->on_membership_created([
                'membership_post_id' => 100,
                'membership_parent_order_id' => 200,
                'membership_product_id' => 300,
            ], false, false);
        });

        it('logs error when date conversion fails', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('get_authoritative_membership_dates')
                ->once()
                ->with(100)
                ->andReturn([
                    'start_date' => 'invalid-date',
                    'end_date' => '2024-12-31T23:59:59+00:00',
                ]);

            $this->logger->shouldReceive('error')->once();

            $this->dynamic_dates->on_membership_created([
                'membership_post_id' => 100,
                'membership_parent_order_id' => 200,
                'membership_product_id' => 300,
            ], false, false);
        });
    });

    describe('membership product detection behavior', function () {
        it('passes existing membership data for renewal calculations', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_id')->andReturn(100);
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            $existing_membership_data = [
                'membership_starts_at' => '2024-01-01T00:00:00+00:00',
                'membership_ends_at' => '2024-12-31T23:59:59+00:00',
            ];

            Functions\when('wc_get_order')->justReturn($order);
            Functions\when('wc_get_order_item_meta')->alias(function ($item_id, $key, $single) {
                if ($item_id === 1 && $key === '_membership_post_id_renew' && $single === true) {
                    return 12345;
                }

                return null;
            });

            $helper = Mockery::mock('alias:Wicket_Memberships\Helper');
            $helper->shouldReceive('get_post_meta')->once()->with(12345)->andReturn($existing_membership_data);

            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_deferred_revenue_required')->with($product)->once()->andReturn(true);
            $this->membership_gateway->shouldReceive('calculate_membership_dates')
                ->once()
                ->with(100, $existing_membership_data)
                ->andReturn([
                    'start_date' => '2025-01-01T00:00:00+00:00',
                    'end_date' => '2025-12-31T23:59:59+00:00',
                ]);
            $this->line_item_meta->shouldReceive('update_dates')
                ->once()
                ->with(999, 1, '2025-01-01', '2025-12-31', 'System');
            $this->logger->shouldReceive('info')->once();

            $this->dynamic_dates->on_order_status_changed(999, 'pending', 'processing');
        });

        it('skips non-membership products', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(false);

            $this->eligibility->shouldNotReceive('is_deferred_revenue_required');

            $this->dynamic_dates->on_order_status_changed(999, 'pending', 'processing');
        });

        it('skips products without deferred revenue required', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $product = Mockery::mock('WC_Product');
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_product')->andReturn($product);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_items')->andReturn([1 => $item]);

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_membership_product')->with($product)->once()->andReturn(true);
            $this->eligibility->shouldReceive('is_deferred_revenue_required')->with($product)->once()->andReturn(false);

            $this->membership_gateway->shouldNotReceive('calculate_membership_dates');

            $this->dynamic_dates->on_order_status_changed(999, 'pending', 'processing');
        });

        it('skips when membership gateway not available', function () {
            $this->settings->shouldReceive('is_system_enabled')->once()->andReturn(true);
            $this->settings->shouldReceive('is_trigger_status')->with('processing')->once()->andReturn(true);

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_items')->andReturn([]);

            Functions\when('wc_get_order')->justReturn($order);
            $this->membership_gateway->shouldReceive('is_available')->once()->andReturn(false);
            $this->logger->shouldReceive('debug')->once();

            $this->dynamic_dates->on_order_status_changed(999, 'pending', 'processing');
        });
    });
});
