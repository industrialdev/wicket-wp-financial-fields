<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Order;

use Brain\Monkey\Functions;
use Mockery;
use Wicket\Finance\Order\LineItemMeta;
use Wicket\Finance\Product\FinanceMeta;
use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('LineItemMeta', function () {
    beforeEach(function () {
        $this->product_meta = Mockery::mock(FinanceMeta::class);
        $this->date_formatter = Mockery::mock(DateFormatter::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->line_item_meta = new LineItemMeta($this->product_meta, $this->date_formatter, $this->logger);
    });

    describe('populate_line_item_meta()', function () {
        it('populates GL code from product', function () {
            $product = Mockery::mock('WC_Product');
            $item = Mockery::mock('WC_Order_Item_Product');
            $order = Mockery::mock('WC_Order');

            $item->shouldReceive('get_product')->andReturn($product);
            $this->product_meta->shouldReceive('get_gl_code')->with($product)->andReturn('GL-123');
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_gl_code', 'GL-123')->once();
            $this->product_meta->shouldReceive('get_deferral_dates')->with($product)->andReturn([]);

            $this->line_item_meta->populate_line_item_meta($item, 'key', [], $order);
        });

        it('populates deferral dates from product', function () {
            $product = Mockery::mock('WC_Product');
            $item = Mockery::mock('WC_Order_Item_Product');
            $order = Mockery::mock('WC_Order');

            $item->shouldReceive('get_product')->andReturn($product);
            $this->product_meta->shouldReceive('get_gl_code')->with($product)->andReturn('');
            $this->product_meta->shouldReceive('get_deferral_dates')->with($product)->andReturn([
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]);
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_start_date', '2024-01-01')->once();
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_end_date', '2024-12-31')->once();

            $this->line_item_meta->populate_line_item_meta($item, 'key', [], $order);
        });

        it('returns early when product is null', function () {
            $item = Mockery::mock('WC_Order_Item_Product');
            $order = Mockery::mock('WC_Order');

            $item->shouldReceive('get_product')->andReturn(null);
            $item->shouldReceive('update_meta_data')->never();

            $this->line_item_meta->populate_line_item_meta($item, 'key', [], $order);
        });

        it('does not populate empty dates', function () {
            $product = Mockery::mock('WC_Product');
            $item = Mockery::mock('WC_Order_Item_Product');
            $order = Mockery::mock('WC_Order');

            $item->shouldReceive('get_product')->andReturn($product);
            $this->product_meta->shouldReceive('get_gl_code')->with($product)->andReturn('');
            $this->product_meta->shouldReceive('get_deferral_dates')->with($product)->andReturn([
                'start_date' => '',
                'end_date' => '',
            ]);
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_start_date', '')->never();
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_end_date', '')->never();

            $this->line_item_meta->populate_line_item_meta($item, 'key', [], $order);
        });
    });

    describe('update_dates()', function () {
        it('returns false when order not found', function () {
            Functions\when('wc_get_order')->justReturn(null);

            $result = $this->line_item_meta->update_dates(999, 1, '2024-01-01', '2024-12-31');

            expect($result)->toBeFalse();
        });

        it('returns false when item not found', function () {
            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_item')->with(1)->andReturn(null);

            Functions\when('wc_get_order')->justReturn($order);

            $result = $this->line_item_meta->update_dates(999, 1, '2024-01-01', '2024-12-31');

            expect($result)->toBeFalse();
        });

        it('updates dates and adds order note', function () {
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_meta')->with('_wicket_finance_start_date', true)->andReturn('2023-01-01');
            $item->shouldReceive('get_meta')->with('_wicket_finance_end_date', true)->andReturn('2023-12-31');
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_start_date', '2024-01-01')->once();
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_end_date', '2024-12-31')->once();
            $item->shouldReceive('save')->once();

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_item')->with(1)->andReturn($item);
            $order->shouldReceive('add_order_note')->once()->with(
                '[System] changed Term Start Date: 2023-01-01 → 2024-01-01, Term End Date: 2023-12-31 → 2024-12-31'
            );
            $order->shouldReceive('save')->once();

            Functions\when('wc_get_order')->justReturn($order);

            $this->logger->shouldReceive('info')->once();

            $result = $this->line_item_meta->update_dates(999, 1, '2024-01-01', '2024-12-31');

            expect($result)->toBeTrue();
        });

        it('uses custom source in order note', function () {
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_meta')->with('_wicket_finance_start_date', true)->andReturn('');
            $item->shouldReceive('get_meta')->with('_wicket_finance_end_date', true)->andReturn('');
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_start_date', '2024-01-01')->once();
            $item->shouldReceive('update_meta_data')->with('_wicket_finance_end_date', '2024-12-31')->once();
            $item->shouldReceive('save')->once();

            $order = Mockery::mock('WC_Order');
            $order->shouldReceive('get_item')->with(1)->andReturn($item);
            $order->shouldReceive('add_order_note')->once()->with(
                '[Membership] changed Term Start Date: empty → 2024-01-01, Term End Date: empty → 2024-12-31'
            );
            $order->shouldReceive('save')->once();

            Functions\when('wc_get_order')->justReturn($order);

            $this->logger->shouldReceive('info')->once();

            $result = $this->line_item_meta->update_dates(999, 1, '2024-01-01', '2024-12-31', 'Membership');

            expect($result)->toBeTrue();
        });
    });

    describe('get_line_item_data()', function () {
        it('returns line item finance data', function () {
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_meta')->with('_wicket_finance_start_date', true)->andReturn('2024-01-01');
            $item->shouldReceive('get_meta')->with('_wicket_finance_end_date', true)->andReturn('2024-12-31');
            $item->shouldReceive('get_meta')->with('_wicket_finance_gl_code', true)->andReturn('GL-123');

            $result = $this->line_item_meta->get_line_item_data($item);

            expect($result)->toBe([
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'gl_code' => 'GL-123',
            ]);
        });

        it('returns empty strings when meta not set', function () {
            $item = Mockery::mock('WC_Order_Item_Product');
            $item->shouldReceive('get_meta')->with('_wicket_finance_start_date', true)->andReturn('');
            $item->shouldReceive('get_meta')->with('_wicket_finance_end_date', true)->andReturn('');
            $item->shouldReceive('get_meta')->with('_wicket_finance_gl_code', true)->andReturn('');

            $result = $this->line_item_meta->get_line_item_data($item);

            expect($result)->toBe([
                'start_date' => '',
                'end_date' => '',
                'gl_code' => '',
            ]);
        });
    });
});
