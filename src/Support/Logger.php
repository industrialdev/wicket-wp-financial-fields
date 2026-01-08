<?php

declare(strict_types=1);

namespace Wicket\Finance\Support;

/**
 * Handles plugin-specific logging with daily rotation and source-based grouping.
 *
 * Logs are stored in wp-content/uploads/wicket-logs/.
 * Log group/source name: wicket-finance
 *
 * @since 1.0.0
 */
class Logger
{
    public const LOG_LEVEL_DEBUG = 'debug';
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';
    public const LOG_LEVEL_CRITICAL = 'critical';

    /**
     * Log source/group name.
     */
    private const LOG_SOURCE = 'wicket-finance';

    /**
     * Flag to track if log directory setup has been completed.
     *
     * @var bool
     */
    private static bool $log_dir_setup_done = false;

    /**
     * Base directory for log files.
     *
     * @var string|null
     */
    private static ?string $log_base_dir = null;

    /**
     * Debug mode enabled flag.
     *
     * Can be overridden via filter or constant.
     *
     * @var bool|null
     */
    private static ?bool $debug_enabled = null;

    /**
     * Registers a handler to catch and log fatal errors.
     *
     * Should be called once when the plugin initializes.
     *
     * @return void
     */
    public static function register_fatal_error_handler(): void
    {
        register_shutdown_function([new self(), 'handle_fatal_error']);
    }

    /**
     * Handles fatal errors at script shutdown.
     *
     * Registered via register_shutdown_function and should not be called directly.
     *
     * @return void
     */
    public function handle_fatal_error(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            $message = sprintf(
                'Fatal Error: %s in %s on line %d',
                $error['message'],
                $error['file'],
                $error['line']
            );

            $this->log(self::LOG_LEVEL_CRITICAL, $message);
        }
    }

    /**
     * Logs a message to a custom file.
     *
     * Mimics WC_Logger::log functionality with daily rotation and source-based grouping.
     * Logs are stored in wp-content/uploads/wicket-logs/.
     *
     * @param string $level   Log level (e.g., Logger::LOG_LEVEL_DEBUG, 'info', 'error').
     * @param string $message Log message.
     * @param array  $context Context for the log message (optional, for compatibility).
     * @return bool True if the message was logged successfully, false otherwise.
     */
    public function log(string $level, string $message, array $context = []): bool
    {
        // Check if debug mode is enabled
        if (self::$debug_enabled === null) {
            self::$debug_enabled = $this->is_debug_enabled();
        }

        // Log CRITICAL and ERROR messages regardless of debug mode
        // For other levels (DEBUG, INFO, WARNING), log only if debug is enabled
        if (!in_array($level, [self::LOG_LEVEL_CRITICAL, self::LOG_LEVEL_ERROR], true)) {
            if (!self::$debug_enabled) {
                return true; // Debug mode off, skip non-critical messages
            }
        }

        if (!self::$log_dir_setup_done) {
            if (!$this->setup_log_directory()) {
                // Fallback to standard PHP error log if setup fails
                error_log("Wicket Finance Log Directory Setup Failed. Original log: [{$level}] {$message}");

                return false;
            }
            self::$log_dir_setup_done = true;
        }

        $source = self::LOG_SOURCE;
        $date_suffix = gmdate('Y-m-d');
        $file_hash = wp_hash($source);
        $filename = "{$source}-{$date_suffix}-{$file_hash}.log";
        $log_file_path = self::$log_base_dir . $filename;

        $timestamp = gmdate('Y-m-d\TH:i:s\Z'); // ISO 8601 UTC
        $formatted_level = strtoupper($level);

        // Add context to message if provided
        $context_string = '';
        if (!empty($context)) {
            $context_string = ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $log_entry = "{$timestamp} [{$formatted_level}]: {$message}{$context_string}" . PHP_EOL;

        if (!error_log($log_entry, 3, $log_file_path)) {
            // Fallback to standard PHP error log if custom file write fails
            error_log("Wicket Finance Log File Write Failed to {$log_file_path}. Original log: [{$level}] {$message}");

            return false;
        }

        return true;
    }

    /**
     * Logs a CRITICAL message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Logs an ERROR message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }

    /**
     * Logs a WARNING message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }

    /**
     * Logs an INFO message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }

    /**
     * Logs a DEBUG message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }

    /**
     * Checks if debug logging is enabled.
     *
     * Can be controlled via:
     * 1. Constant WICKET_FINANCE_DEBUG (force-on)
     * 2. Filter wicket/finance/debug_enabled
     * 3. WP_DEBUG (fallback)
     *
     * @return bool
     */
    private function is_debug_enabled(): bool
    {
        // Check for constant override
        if (defined('WICKET_FINANCE_DEBUG') && WICKET_FINANCE_DEBUG) {
            return true;
        }

        // Check setting or filter (will be implemented in FinanceSettings)
        $enabled = apply_filters('wicket/finance/debug_enabled', false);

        // Fallback to WP_DEBUG
        if (!$enabled && defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        return (bool) $enabled;
    }

    /**
     * Sets up the log directory, ensuring it exists and is secured.
     *
     * @return bool True if setup was successful, false on failure.
     */
    private function setup_log_directory(): bool
    {
        if (self::$log_base_dir === null) {
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['error'])) {
                error_log('Wicket Finance Log Error: Could not get WordPress upload directory. ' . $upload_dir['error']);

                return false;
            }
            self::$log_base_dir = $upload_dir['basedir'] . '/wicket-logs/';
        }

        if (!is_dir(self::$log_base_dir)) {
            if (!wp_mkdir_p(self::$log_base_dir)) {
                error_log('Wicket Finance Log Error: Could not create log directory: ' . self::$log_base_dir);

                return false;
            }
        }

        // Create .htaccess to deny direct access
        $htaccess_file = self::$log_base_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = 'deny from all' . PHP_EOL . 'Require all denied' . PHP_EOL;
            if (@file_put_contents($htaccess_file, $htaccess_content) === false) {
                error_log('Wicket Finance Log Error: Could not create .htaccess file in ' . self::$log_base_dir);
            }
        }

        // Create index.html for additional security
        $index_html_file = self::$log_base_dir . 'index.html';
        if (!file_exists($index_html_file)) {
            $index_content = '<!-- Silence is golden. -->';
            if (@file_put_contents($index_html_file, $index_content) === false) {
                error_log('Wicket Finance Log Error: Could not create index.html file in ' . self::$log_base_dir);
            }
        }

        return true;
    }
}
