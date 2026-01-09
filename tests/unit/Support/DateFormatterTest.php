<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Support;

use Brain\Monkey\Functions;
use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('DateFormatter', function () {
    beforeEach(function () {
        $this->formatter = new DateFormatter();
    });

    describe('validate()', function () {
        it('validates correct Y-m-d dates', function () {
            expect($this->formatter->validate('2024-01-15'))->toBeTrue();
            expect($this->formatter->validate('2020-12-31'))->toBeTrue();
            expect($this->formatter->validate('1999-01-01'))->toBeTrue();
        });

        it('rejects invalid formats', function () {
            expect($this->formatter->validate('01-15-2024'))->toBeFalse();
            expect($this->formatter->validate('15/01/2024'))->toBeFalse();
            expect($this->formatter->validate('2024/01/15'))->toBeFalse();
            expect($this->formatter->validate('not-a-date'))->toBeFalse();
        });

        it('rejects empty strings', function () {
            expect($this->formatter->validate(''))->toBeFalse();
        });

        it('rejects invalid dates', function () {
            expect($this->formatter->validate('2024-02-30'))->toBeFalse();
            expect($this->formatter->validate('2024-13-01'))->toBeFalse();
            expect($this->formatter->validate('2024-00-01'))->toBeFalse();
        });
    });

    describe('validate_date_range()', function () {
        it('validates when end date >= start date', function () {
            expect($this->formatter->validate_date_range('2024-01-01', '2024-12-31'))->toBeTrue();
            expect($this->formatter->validate_date_range('2024-01-01', '2024-01-01'))->toBeTrue();
        });

        it('rejects when end date < start date', function () {
            expect($this->formatter->validate_date_range('2024-12-31', '2024-01-01'))->toBeFalse();
        });

        it('rejects invalid dates', function () {
            expect($this->formatter->validate_date_range('not-valid', '2024-12-31'))->toBeFalse();
            expect($this->formatter->validate_date_range('2024-01-01', 'not-valid'))->toBeFalse();
        });
    });

    describe('format_for_display()', function () {
        it('formats valid dates using WordPress date format', function () {
            Functions\when('get_option')->justReturn('Y-m-d');
            Functions\when('date_i18n')->justReturn('2024-01-15');

            expect($this->formatter->format_for_display('2024-01-15'))->toBe('2024-01-15');
        });

        it('returns empty string for invalid dates', function () {
            expect($this->formatter->format_for_display('invalid'))->toBe('');
            expect($this->formatter->format_for_display(''))->toBe('');
        });
    });

    describe('to_storage_format()', function () {
        it('returns same string if already in Y-m-d', function () {
            expect($this->formatter->to_storage_format('2024-01-15'))->toBe('2024-01-15');
        });

        it('converts various formats to Y-m-d', function () {
            expect($this->formatter->to_storage_format('January 15, 2024'))->toBe('2024-01-15');
            expect($this->formatter->to_storage_format('15 Jan 2024'))->toBe('2024-01-15');
        });

        it('returns empty string for invalid dates', function () {
            expect($this->formatter->to_storage_format('not-a-date'))->toBe('');
        });

        it('returns empty string for empty input', function () {
            expect($this->formatter->to_storage_format(''))->toBe('');
        });
    });

    describe('sanitize_date_input()', function () {
        it('sanitizes and validates date input', function () {
            Functions\when('sanitize_text_field')->returnArg();

            expect($this->formatter->sanitize_date_input('2024-01-15'))->toBe('2024-01-15');
        });

        it('returns empty string for non-string input', function () {
            expect($this->formatter->sanitize_date_input(123))->toBe('');
            expect($this->formatter->sanitize_date_input(null))->toBe('');
        });

        it('returns empty string for invalid dates', function () {
            Functions\when('sanitize_text_field')->returnArg();

            expect($this->formatter->sanitize_date_input('invalid'))->toBe('');
        });
    });

    describe('get_current_date()', function () {
        it('returns current date in Y-m-d format', function () {
            $result = $this->formatter->get_current_date();
            expect($result)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
        });
    });

    describe('from_iso_8601()', function () {
        it('extracts Y-m-d from ISO 8601 format', function () {
            expect($this->formatter->from_iso_8601('2024-01-15T00:00:00+00:00'))->toBe('2024-01-15');
            expect($this->formatter->from_iso_8601('2024-12-31T23:59:59-05:00'))->toBe('2024-12-31');
        });

        it('returns empty string for invalid ISO 8601', function () {
            expect($this->formatter->from_iso_8601('not-iso'))->toBe('');
        });

        it('returns empty string for empty input', function () {
            expect($this->formatter->from_iso_8601(''))->toBe('');
        });
    });

    describe('get_storage_format()', function () {
        it('returns Y-m-d format string', function () {
            expect($this->formatter->get_storage_format())->toBe('Y-m-d');
        });
    });
});
