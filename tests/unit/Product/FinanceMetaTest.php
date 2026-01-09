<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Product;

use Brain\Monkey\Functions;
use Mockery;
use Wicket\Finance\Product\FinanceMeta;
use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('FinanceMeta', function () {
    beforeEach(function () {
        $this->date_formatter = Mockery::mock(DateFormatter::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->finance_meta = new FinanceMeta($this->date_formatter, $this->logger);

        // Stub WordPress translation function
        Functions\when('__')->returnArg();
    });

    describe('add_finance_mapping_tab()', function () {
        it('adds finance mapping tab to product data tabs', function () {
            $tabs = [];
            $result = $this->finance_meta->add_finance_mapping_tab($tabs);

            expect($result)->toHaveKey('wicket_finance_mapping');
            expect($result['wicket_finance_mapping']['label'])->toBe('Finance Mapping');
            expect($result['wicket_finance_mapping']['target'])->toBe('wicket_finance_mapping_data');
        });
    });

    describe('validate_product_dates()', function () {
        it('returns props unchanged when no dates set', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('');

            Functions\when('wc_add_notice')->justReturn(null);

            $props = ['name' => 'Test Product'];
            $result = $this->finance_meta->validate_product_dates($props, $product);

            expect($result)->toBe($props);
        });

        it('adds error when start date is set but end date is empty', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('2024-01-01');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('');

            $notice_added = false;
            Functions\when('wc_add_notice')->alias(function () use (&$notice_added) {
                $notice_added = true;
            });

            $props = [];
            $this->finance_meta->validate_product_dates($props, $product);

            expect($notice_added)->toBeTrue();
        });

        it('adds error when date range is invalid', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('2024-12-31');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('2024-01-01');

            $this->date_formatter->shouldReceive('validate_date_range')
                ->with('2024-12-31', '2024-01-01')
                ->andReturn(false);

            $notice_added = false;
            Functions\when('wc_add_notice')->alias(function () use (&$notice_added) {
                $notice_added = true;
            });

            $props = [];
            $this->finance_meta->validate_product_dates($props, $product);

            expect($notice_added)->toBeTrue();
        });

        it('does not add error when date range is valid', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('2024-01-01');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('2024-12-31');

            $this->date_formatter->shouldReceive('validate_date_range')
                ->with('2024-01-01', '2024-12-31')
                ->andReturn(true);

            $notice_added = false;
            Functions\when('wc_add_notice')->alias(function () use (&$notice_added) {
                $notice_added = true;
            });

            $props = [];
            $this->finance_meta->validate_product_dates($props, $product);

            expect($notice_added)->toBeFalse();
        });
    });

    describe('get_deferral_dates()', function () {
        it('returns empty array for invalid product', function () {
            Functions\when('wc_get_product')->justReturn(null);

            $result = $this->finance_meta->get_deferral_dates(999);

            expect($result)->toBe(['start_date' => '', 'end_date' => '']);
        });

        it('returns dates from simple product', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('2024-01-01');
            $product->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('2024-12-31');

            $result = $this->finance_meta->get_deferral_dates($product);

            expect($result)->toBe([
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]);
        });

        it('inherits dates from parent for variation when empty', function () {
            $variation = Mockery::mock('WC_Product');
            $variation->shouldReceive('is_type')->with('variation')->andReturn(true);
            $variation->shouldReceive('get_parent_id')->andReturn(100);
            $variation->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('');
            $variation->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('');

            $parent = Mockery::mock('WC_Product');
            $parent->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('2024-01-01');
            $parent->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('2024-12-31');

            Functions\when('wc_get_product')->justReturn($parent);

            $result = $this->finance_meta->get_deferral_dates($variation);

            expect($result)->toBe([
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]);
        });

        it('uses variation dates when set', function () {
            $variation = Mockery::mock('WC_Product');
            $variation->shouldReceive('is_type')->with('variation')->andReturn(true);
            $variation->shouldReceive('get_parent_id')->andReturn(100);
            $variation->shouldReceive('get_meta')->with('_wicket_finance_deferral_start_date', true)->andReturn('2024-06-01');
            $variation->shouldReceive('get_meta')->with('_wicket_finance_deferral_end_date', true)->andReturn('2024-06-30');

            $result = $this->finance_meta->get_deferral_dates($variation);

            expect($result)->toBe([
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-30',
            ]);
        });
    });

    describe('get_gl_code()', function () {
        it('returns empty string for invalid product', function () {
            Functions\when('wc_get_product')->justReturn(null);

            $result = $this->finance_meta->get_gl_code(999);

            expect($result)->toBe('');
        });

        it('returns GL code from simple product', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_meta')->with('_wicket_finance_gl_code', true)->andReturn('GL-123');

            $result = $this->finance_meta->get_gl_code($product);

            expect($result)->toBe('GL-123');
        });

        it('returns GL code from parent for variation', function () {
            $variation = Mockery::mock('WC_Product');
            $variation->shouldReceive('is_type')->with('variation')->andReturn(true);
            $variation->shouldReceive('get_parent_id')->andReturn(100);

            $parent = Mockery::mock('WC_Product');
            $parent->shouldReceive('get_meta')->with('_wicket_finance_gl_code', true)->andReturn('GL-456');

            Functions\when('wc_get_product')->justReturn($parent);

            $result = $this->finance_meta->get_gl_code($variation);

            expect($result)->toBe('GL-456');
        });

        it('returns empty string when GL code not set', function () {
            $product = Mockery::mock('WC_Product');
            $product->shouldReceive('is_type')->with('variation')->andReturn(false);
            $product->shouldReceive('get_meta')->with('_wicket_finance_gl_code', true)->andReturn('');

            $result = $this->finance_meta->get_gl_code($product);

            expect($result)->toBe('');
        });
    });
});
