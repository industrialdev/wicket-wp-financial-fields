<?php

declare(strict_types=1);

namespace Wicket\Finance\Support;

/**
 * Thin wrapper around the centralized WicketWP\Log.
 *
 * All log entries are written via the base plugin's logger under the
 * 'wicket-finance' source. The existing DI contract (Logger injected into
 * every service class) is preserved — no callsite changes required.
 *
 * Debug-mode control specific to this plugin is retained here:
 *   1. Constant WICKET_FINANCE_DEBUG (force-on)
 *   2. Filter  wicket/finance/debug_enabled
 *   3. Fallback to WP_DEBUG (handled by WicketWP\Log itself)
 *
 * CRITICAL and ERROR are always forwarded regardless of debug mode.
 *
 * @see WicketWP\Log
 */
class Logger
{
    public const LOG_LEVEL_DEBUG    = \WicketWP\Log::LOG_LEVEL_DEBUG;
    public const LOG_LEVEL_INFO     = \WicketWP\Log::LOG_LEVEL_INFO;
    public const LOG_LEVEL_WARNING  = \WicketWP\Log::LOG_LEVEL_WARNING;
    public const LOG_LEVEL_ERROR    = \WicketWP\Log::LOG_LEVEL_ERROR;
    public const LOG_LEVEL_CRITICAL = \WicketWP\Log::LOG_LEVEL_CRITICAL;

    private const LOG_SOURCE = 'wicket-finance';

    private static ?bool $debug_enabled = null;

    public function log(string $level, string $message, array $context = []): bool
    {
        // CRITICAL and ERROR always pass through; all others require debug mode.
        if (!in_array($level, [self::LOG_LEVEL_CRITICAL, self::LOG_LEVEL_ERROR], true)) {
            if (!$this->is_debug_enabled()) {
                return true;
            }
        }

        $context['source'] = self::LOG_SOURCE;

        return \Wicket()->log()->log($level, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }

    /**
     * Checks if debug logging is enabled for this plugin.
     *
     * Resolution order:
     *   1. WICKET_FINANCE_DEBUG constant (force-on)
     *   2. wicket/finance/debug_enabled filter
     *   3. WP_DEBUG (delegated to WicketWP\Log)
     */
    private function is_debug_enabled(): bool
    {
        if (self::$debug_enabled !== null) {
            return self::$debug_enabled;
        }

        if (defined('WICKET_FINANCE_DEBUG') && WICKET_FINANCE_DEBUG) {
            return self::$debug_enabled = true;
        }

        $via_filter = apply_filters('wicket/finance/debug_enabled', false);
        if ($via_filter) {
            return self::$debug_enabled = true;
        }

        // Fall through to WP_DEBUG — WicketWP\Log handles that check itself.
        return self::$debug_enabled = false;
    }
}
