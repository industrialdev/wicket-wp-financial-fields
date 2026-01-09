<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use Wicket\Finance\Settings\FinanceSettings;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('FinanceSettings', function () {
    beforeEach(function () {
        $this->settings = new FinanceSettings();
    });

    describe('Surface constants', function () {
        it('has correct surface identifiers', function () {
            expect(FinanceSettings::SURFACE_ORDER_CONFIRMATION)->toBe('order_confirmation');
            expect(FinanceSettings::SURFACE_EMAILS)->toBe('emails');
            expect(FinanceSettings::SURFACE_MY_ACCOUNT)->toBe('my_account');
            expect(FinanceSettings::SURFACE_SUBSCRIPTIONS)->toBe('subscriptions');
            expect(FinanceSettings::SURFACE_PDF_INVOICE)->toBe('pdf_invoice');
        });
    });

    describe('Status constants', function () {
        it('has correct order status identifiers', function () {
            expect(FinanceSettings::STATUS_DRAFT)->toBe('draft');
            expect(FinanceSettings::STATUS_PENDING)->toBe('pending');
            expect(FinanceSettings::STATUS_ON_HOLD)->toBe('on-hold');
            expect(FinanceSettings::STATUS_PROCESSING)->toBe('processing');
            expect(FinanceSettings::STATUS_COMPLETED)->toBe('completed');
        });
    });

    describe('get_eligible_categories()', function () {
        it('returns empty array when system disabled', function () {
            Functions\when('get_option')->justReturn('0');

            expect($this->settings->get_eligible_categories())->toBe([]);
        });

        it('returns array of category IDs when enabled', function () {
            $callCount = 0;
            Functions\when('get_option')->alias(function (string $option, $default = null) use (&$callCount) {
                $callCount++;
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return ['5', '10', '15'];
            });

            $result = $this->settings->get_eligible_categories();

            expect($result)->toBe([5, 10, 15]);
        });

        it('returns empty array for non-array values', function () {
            $callCount = 0;
            Functions\when('get_option')->alias(function (string $option, $default = null) use (&$callCount) {
                $callCount++;
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return 'invalid';
            });

            expect($this->settings->get_eligible_categories())->toBe([]);
        });
    });

    describe('get_visibility_surfaces()', function () {
        it('returns empty array when system disabled', function () {
            Functions\when('get_option')->justReturn('0');

            expect($this->settings->get_visibility_surfaces())->toBe([]);
        });

        it('returns enabled surfaces', function () {
            Functions\when('get_option')->alias(function (string $option, $default = null) {
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return in_array($option, [
                    'wicket_finance_display_order_confirmation',
                    'wicket_finance_display_my_account',
                    'wicket_finance_display_pdf_invoices',
                ], true) ? '1' : '0';
            });

            $result = $this->settings->get_visibility_surfaces();

            expect($result)->toBe([
                'order_confirmation',
                'my_account',
                'pdf_invoice',
            ]);
        });
    });

    describe('get_dynamic_date_triggers()', function () {
        it('returns empty array when system disabled', function () {
            Functions\when('get_option')->justReturn('0');

            expect($this->settings->get_dynamic_date_triggers())->toBe([]);
        });

        it('always includes processing status', function () {
            Functions\when('get_option')->alias(function (string $option, $default = null) {
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return '0';
            });

            $result = $this->settings->get_dynamic_date_triggers();

            expect($result)->toBe(['processing']);
        });

        it('includes all enabled triggers', function () {
            Functions\when('get_option')->alias(function (string $option, $default = null) {
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return in_array($option, [
                    'wicket_finance_trigger_draft',
                    'wicket_finance_trigger_pending',
                    'wicket_finance_trigger_completed',
                ], true) ? '1' : '0';
            });

            $result = $this->settings->get_dynamic_date_triggers();

            expect($result)->toBe([
                'draft',
                'pending',
                'completed',
                'processing',
            ]);
        });
    });

    describe('is_surface_enabled()', function () {
        it('checks if surface is enabled', function () {
            Functions\when('get_option')->alias(function (string $option, $default = null) {
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return $option === 'wicket_finance_display_order_confirmation' ? '1' : '0';
            });

            expect($this->settings->is_surface_enabled('order_confirmation'))->toBeTrue();
            expect($this->settings->is_surface_enabled('emails'))->toBeFalse();
        });
    });

    describe('is_trigger_status()', function () {
        it('checks if status is a trigger', function () {
            Functions\when('get_option')->alias(function (string $option, $default = null) {
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }
                return $option === 'wicket_finance_trigger_draft' ? '1' : '0';
            });

            expect($this->settings->is_trigger_status('draft'))->toBeTrue();
            expect($this->settings->is_trigger_status('pending'))->toBeFalse();
        });
    });

    describe('is_system_enabled()', function () {
        it('checks if system is enabled', function () {
            Functions\when('get_option')->justReturn('1');

            expect($this->settings->is_system_enabled())->toBeTrue();
        });

        it('returns false when system is disabled', function () {
            Functions\when('get_option')->justReturn('0');

            expect($this->settings->is_system_enabled())->toBeFalse();
        });
    });

    describe('save_eligible_categories()', function () {
        it('sanitizes and saves category IDs', function () {
            Functions\expect('update_option')
                ->once()
                ->with('wicket_finance_customer_visible_categories', [5, 10, 15])
                ->andReturn(true);

            expect($this->settings->save_eligible_categories(['5', '10', '15']))->toBeTrue();
        });
    });

    describe('save_visibility_surfaces()', function () {
        it('saves surface settings', function () {
            Functions\expect('update_option')
                ->with('wicket_finance_display_order_confirmation', '1')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_display_emails', '0')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_display_my_account', '1')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_display_subscriptions', '0')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_display_pdf_invoices', '0')
                ->andReturn(true);

            $result = $this->settings->save_visibility_surfaces([
                'order_confirmation',
                'my_account',
            ]);

            expect($result)->toBeTrue();
        });
    });

    describe('save_dynamic_date_triggers()', function () {
        it('saves status triggers and always includes processing', function () {
            Functions\expect('update_option')
                ->with('wicket_finance_trigger_draft', '1')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_trigger_pending', '0')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_trigger_on_hold', '1')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_trigger_processing', '1')
                ->andReturn(true);

            Functions\expect('update_option')
                ->with('wicket_finance_trigger_completed', '0')
                ->andReturn(true);

            $result = $this->settings->save_dynamic_date_triggers([
                'draft',
                'on-hold',
            ]);

            expect($result)->toBeTrue();
        });
    });

    describe('get_defaults()', function () {
        it('returns default settings', function () {
            $defaults = $this->settings->get_defaults();

            expect($defaults)->toBe([
                'eligible_categories' => [],
                'visibility_surfaces' => [],
                'dynamic_date_triggers' => ['processing'],
            ]);
        });
    });

    describe('reset_to_defaults()', function () {
        it('resets all settings to defaults', function () {
            Functions\expect('update_option')->andReturn(true);

            expect($this->settings->reset_to_defaults())->toBeTrue();
        });
    });

    describe('get_all()', function () {
        it('returns all settings as array', function () {
            Functions\when('get_option')->alias(function (string $option, $default = null) {
                if ($option === 'wicket_finance_enable_system') {
                    return '1';
                }

                $enabled = [
                    'wicket_finance_display_order_confirmation',
                    'wicket_finance_display_my_account',
                ];

                if ($option === 'wicket_finance_customer_visible_categories') {
                    return ['5', '10'];
                }

                if (in_array($option, $enabled, true)) {
                    return '1';
                }

                return '0';
            });

            $result = $this->settings->get_all();

            expect($result['eligible_categories'])->toBe([5, 10]);
            expect($result['visibility_surfaces'])->toBe([
                'order_confirmation',
                'my_account',
            ]);
            expect($result['dynamic_date_triggers'])->toBe(['processing']);
        });
    });
});
