<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Support;

use Brain\Monkey\Functions;
use Mockery;
use Wicket\Finance\Settings\FinanceSettings;
use Wicket\Finance\Support\Eligibility;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('Eligibility', function () {
    beforeEach(function () {
        Functions\when('absint')->alias(function ($value) {
            return abs((int) $value);
        });
        Functions\when('get_option')->justReturn([]);

        $this->settings = Mockery::mock(FinanceSettings::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->eligibility = new Eligibility($this->settings, $this->logger);
    });

    describe('is_eligible_for_display()', function () {
        it('returns false when dates are empty', function () {
            $product = Mockery::mock('WC_Product');

            expect($this->eligibility->is_eligible_for_display($product, 'order_confirmation', '', ''))->toBeFalse();
        });

        it('returns false when surface is disabled', function () {
            $this->settings->shouldReceive('get_visibility_surfaces')->andReturn([]);

            $product = Mockery::mock('WC_Product');

            expect($this->eligibility->is_eligible_for_display($product, 'order_confirmation', '2024-01-01', '2024-12-31'))->toBeFalse();
        });

        it('returns false when product not in eligible categories', function () {
            $this->settings->shouldReceive('get_visibility_surfaces')->andReturn(['order_confirmation']);
            $this->settings->shouldReceive('get_eligible_categories')->andReturn([5, 10]);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_category_ids')->andReturn([15, 20]);

            expect($this->eligibility->is_eligible_for_display($product, 'order_confirmation', '2024-01-01', '2024-12-31'))->toBeFalse();
        });

        it('returns true when all conditions met', function () {
            $this->settings->shouldReceive('get_visibility_surfaces')->andReturn(['order_confirmation']);
            $this->settings->shouldReceive('get_eligible_categories')->andReturn([5, 10]);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_category_ids')->andReturn([5, 15]);

            expect($this->eligibility->is_eligible_for_display($product, 'order_confirmation', '2024-01-01', '2024-12-31'))->toBeTrue();
        });
    });

    describe('is_product_in_eligible_categories()', function () {
        it('returns false when no eligible categories set', function () {
            $this->settings->shouldReceive('get_eligible_categories')->andReturn([]);

            $product = Mockery::mock('WC_Product');

            expect($this->eligibility->is_product_in_eligible_categories($product))->toBeFalse();
        });

        it('checks parent categories for variations', function () {
            $this->settings->shouldReceive('get_eligible_categories')->andReturn([5, 10]);

            $variation = Mockery::mock('WC_Product');
            $variation->shouldReceive('is_type')->with('variation')->andReturn(true);
            $variation->shouldReceive('get_parent_id')->andReturn(100);

            $parent = Mockery::mock('WC_Product');
            $parent->shouldReceive('get_category_ids')->andReturn([5, 15]);

            Functions\when('wc_get_product')->justReturn($parent);

            expect($this->eligibility->is_product_in_eligible_categories($variation))->toBeTrue();
        });

        it('returns true for product in eligible category', function () {
            $this->settings->shouldReceive('get_eligible_categories')->andReturn([5, 10]);

            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_category_ids')->andReturn([5, 15]);

            expect($this->eligibility->is_product_in_eligible_categories($product))->toBeTrue();
        });
    });

    describe('is_surface_enabled()', function () {
        it('checks if surface is in enabled list', function () {
            $this->settings->shouldReceive('get_visibility_surfaces')->andReturn(['order_confirmation', 'my_account']);

            expect($this->eligibility->is_surface_enabled('order_confirmation'))->toBeTrue();
            expect($this->eligibility->is_surface_enabled('emails'))->toBeFalse();
        });
    });

    describe('is_membership_product()', function () {
        it('returns false for invalid product', function () {
            Functions\when('wc_get_product')->justReturn(null);

            expect($this->eligibility->is_membership_product(999))->toBeFalse();
        });

        it('checks parent categories for variations', function () {
            $variation = Mockery::mock('WC_Product');
            $variation->shouldReceive('is_type')->with('variation')->andReturn(true);
            $variation->shouldReceive('get_parent_id')->andReturn(100);

            $parent = Mockery::mock('WC_Product');
            $parent->shouldReceive('is_type')->with('variation')->andReturn(false);
            $parent->shouldReceive('get_parent_id')->andReturn(0);
            $parent->shouldReceive('get_category_ids')->andReturn([5]);

            Functions\when('wc_get_product')->justReturn($parent);
            Functions\when('get_option')->justReturn([
                'wicket_show_mship_order_org_search' => [
                    'categorychoice' => [5],
                ],
            ]);

            expect($this->eligibility->is_membership_product($variation))->toBeTrue();
        });

        it('returns true when product in membership category', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_parent_id')->andReturn(0);
            $product->shouldReceive('get_category_ids')->andReturn([5]);

            Functions\when('get_option')->justReturn([
                'wicket_show_mship_order_org_search' => [
                    'categorychoice' => [5],
                ],
            ]);

            expect($this->eligibility->is_membership_product($product))->toBeTrue();
        });

        it('uses membership category IDs from options', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_parent_id')->andReturn(0);
            $product->shouldReceive('get_category_ids')->andReturn([25]);
            Functions\when('get_option')->justReturn([
                'wicket_show_mship_order_org_search' => [
                    'categorychoice' => [10, 25],
                ],
            ]);

            expect($this->eligibility->is_membership_product($product))->toBeTrue();
        });
    });

    describe('get_membership_category_ids()', function () {
        it('returns empty array when no option exists', function () {
            Functions\when('get_option')->justReturn([]);

            expect($this->eligibility->get_membership_category_ids())->toBe([]);
        });

        it('normalizes and filters invalid category IDs', function () {
            Functions\when('get_option')->justReturn([
                'wicket_show_mship_order_org_search' => [
                    'categorychoice' => ['7', 'foo', 0, -3, 15],
                ],
            ]);

            expect($this->eligibility->get_membership_category_ids())->toBe([7, 3, 15]);
        });

        it('caches result', function () {
            $callCount = 0;
            Functions\when('get_option')->alias(function () use (&$callCount) {
                $callCount++;

                return [
                    'wicket_show_mship_order_org_search' => [
                        'categorychoice' => [7],
                    ],
                ];
            });

            $this->eligibility->get_membership_category_ids();
            $this->eligibility->get_membership_category_ids();

            expect($callCount)->toBe(1);
        });
    });

    describe('is_deferred_revenue_required()', function () {
        it('returns false for invalid product', function () {
            Functions\when('wc_get_product')->justReturn(null);

            expect($this->eligibility->is_deferred_revenue_required(999))->toBeFalse();
        });

        it('checks parent for variations', function () {
            $variation = Mockery::mock('WC_Product');
            $variation->shouldReceive('is_type')->with('variation')->andReturn(true);
            $variation->shouldReceive('get_parent_id')->andReturn(100);

            $parent = Mockery::mock('WC_Product');
            $parent->shouldReceive('is_type')->with('variation')->andReturn(false);
            $parent->shouldReceive('get_parent_id')->andReturn(0);
            $parent->shouldReceive('get_meta')
                ->with('_wicket_finance_deferred_required', true)
                ->andReturn('yes');

            Functions\when('wc_get_product')->justReturn($parent);

            expect($this->eligibility->is_deferred_revenue_required($variation))->toBeTrue();
        });

        it('returns true when deferred required is yes', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_parent_id')->andReturn(0);
            $product->shouldReceive('get_meta')
                ->with('_wicket_finance_deferred_required', true)
                ->andReturn('yes');

            expect($this->eligibility->is_deferred_revenue_required($product))->toBeTrue();
        });

        it('returns false when deferred required is not yes', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_parent_id')->andReturn(0);
            $product->shouldReceive('get_meta')
                ->with('_wicket_finance_deferred_required', true)
                ->andReturn('no');

            expect($this->eligibility->is_deferred_revenue_required($product))->toBeFalse();
        });
    });
});
