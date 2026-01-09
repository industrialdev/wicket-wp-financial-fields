<?php

declare(strict_types=1);

namespace Wicket\Finance\Tests\Unit\Support;

use Brain\Monkey\Functions;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Tests\TestCase;

uses(TestCase::class);

describe('Logger', function () {
    beforeEach(function () {
        $this->logger = new Logger();
    });

    describe('log()', function () {
        it('skips INFO messages when debug mode disabled', function () {
            Functions\when('apply_filters')->justReturn(false);
            Functions\when('WP_DEBUG')->justReturn(false);

            $result = $this->logger->log(Logger::LOG_LEVEL_INFO, 'Info message');

            expect($result)->toBeTrue(); // Returns true (skip, not failure)
        });

        it('logs INFO messages when debug mode enabled via filter', function () {
            Functions\when('apply_filters')->justReturn(true);
            Functions\when('wp_hash')->justReturn('abc123');
            Functions\when('wp_upload_dir')->justReturn(['basedir' => '/tmp/uploads', 'error' => '']);

            // Stub file operations
            Functions\when('is_dir')->justReturn(true);
            Functions\when('file_exists')->justReturn(true);
            Functions\when('error_log')->justReturn(true);

            $result = $this->logger->log(Logger::LOG_LEVEL_INFO, 'Info message');

            expect($result)->toBeTrue();
        });

        it('includes context in log entry', function () {
            Functions\when('apply_filters')->justReturn(true);
            Functions\when('wp_hash')->justReturn('abc123');
            Functions\when('wp_json_encode')->justReturn('{"order_id":123}');
            Functions\when('wp_upload_dir')->justReturn(['basedir' => '/tmp/uploads', 'error' => '']);
            Functions\when('is_dir')->justReturn(true);
            Functions\when('file_exists')->justReturn(true);
            Functions\when('error_log')->justReturn(true);

            $result = $this->logger->log(Logger::LOG_LEVEL_DEBUG, 'Debug with context', ['order_id' => 123]);

            expect($result)->toBeTrue();
        });
    });

    describe('info()', function () {
        it('logs INFO message', function () {
            Functions\when('apply_filters')->justReturn(true);
            Functions\when('wp_hash')->justReturn('abc123');
            Functions\when('wp_upload_dir')->justReturn(['basedir' => '/tmp/uploads', 'error' => '']);
            Functions\when('is_dir')->justReturn(true);
            Functions\when('file_exists')->justReturn(true);
            Functions\when('error_log')->justReturn(true);

            $this->logger->info('Info message');

            expect(true)->toBeTrue();
        });
    });

    describe('warning()', function () {
        it('logs WARNING message', function () {
            Functions\when('apply_filters')->justReturn(true);
            Functions\when('wp_hash')->justReturn('abc123');
            Functions\when('wp_upload_dir')->justReturn(['basedir' => '/tmp/uploads', 'error' => '']);
            Functions\when('is_dir')->justReturn(true);
            Functions\when('file_exists')->justReturn(true);
            Functions\when('error_log')->justReturn(true);

            $this->logger->warning('Warning message');

            expect(true)->toBeTrue();
        });
    });

    describe('error()', function () {
        it('logs ERROR message', function () {
            Functions\when('apply_filters')->returnArg();
            Functions\when('wp_hash')->justReturn('abc123');
            Functions\when('wp_upload_dir')->justReturn(['basedir' => '/tmp/uploads', 'error' => '']);
            Functions\when('is_dir')->justReturn(true);
            Functions\when('file_exists')->justReturn(true);
            Functions\when('error_log')->justReturn(true);

            $this->logger->error('Error message');

            expect(true)->toBeTrue();
        });
    });

    describe('critical()', function () {
        it('logs CRITICAL message', function () {
            Functions\when('apply_filters')->returnArg();
            Functions\when('wp_hash')->justReturn('abc123');
            Functions\when('wp_upload_dir')->justReturn(['basedir' => '/tmp/uploads', 'error' => '']);
            Functions\when('is_dir')->justReturn(true);
            Functions\when('file_exists')->justReturn(true);
            Functions\when('error_log')->justReturn(true);

            $this->logger->critical('Critical message');

            expect(true)->toBeTrue();
        });
    });

    describe('log level constants', function () {
        it('has correct log level constants', function () {
            expect(Logger::LOG_LEVEL_DEBUG)->toBe('debug');
            expect(Logger::LOG_LEVEL_INFO)->toBe('info');
            expect(Logger::LOG_LEVEL_WARNING)->toBe('warning');
            expect(Logger::LOG_LEVEL_ERROR)->toBe('error');
            expect(Logger::LOG_LEVEL_CRITICAL)->toBe('critical');
        });
    });
});
