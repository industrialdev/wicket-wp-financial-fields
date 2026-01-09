<?php

declare(strict_types=1);

namespace Wicket\Finance;

use Wicket\Finance\Display\CustomerRenderer;
use Wicket\Finance\Export\WooExportAdapter;
use Wicket\Finance\Order\DynamicDates;
use Wicket\Finance\Order\LineItemMeta;
use Wicket\Finance\Product\FinanceMeta;
use Wicket\Finance\Settings\FinanceSettings;
use Wicket\Finance\Settings\WPSettingsSettings;
use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Eligibility;
use Wicket\Finance\Support\Logger;
use Wicket\Finance\Support\MembershipGateway;

/**
 * Main plugin class.
 *
 * Coordinates and initializes the entire finance mapping and deferral system.
 *
 * @since 1.0.0
 */
class Plugin
{
    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = '';

    /**
     * Plugin path.
     *
     * @var string
     */
    public $plugin_path = '';

    /**
     * Plugin URL.
     *
     * @var string
     */
    public $plugin_url = '';

    /**
     * Logger instance.
     *
     * @var Logger|null
     */
    private $logger;

    /**
     * Settings facade.
     *
     * @var FinanceSettings|null
     */
    private $settings;

    /**
     * WPSettings settings service (Wicket Settings page).
     *
     * @var WPSettingsSettings|null
     */
    private $wpsettings_settings;

    /**
     * Product meta service.
     *
     * @var FinanceMeta|null
     */
    private $product_meta;

    /**
     * Line item meta service.
     *
     * @var LineItemMeta|null
     */
    private $line_item_meta;

    /**
     * Dynamic dates service.
     *
     * @var DynamicDates|null
     */
    private $dynamic_dates;

    /**
     * Customer renderer service.
     *
     * @var CustomerRenderer|null
     */
    private $customer_renderer;

    /**
     * Export adapter service.
     *
     * @var WooExportAdapter|null
     */
    private $export_adapter;

    /**
     * Get singleton instance.
     *
     * @return Plugin
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Private to enforce singleton pattern.
     */
    private function __construct()
    {
        $this->version = WICKET_FINANCE_VERSION;
        $this->plugin_path = WICKET_FINANCE_PATH;
        $this->plugin_url = WICKET_FINANCE_URL;
    }

    /**
     * Setup plugin hooks and initialize components.
     *
     * This method is called on plugins_loaded.
     *
     * @return void
     */
    public function plugin_setup(): void
    {
        // Initialize logger first
        $this->logger = new Logger();

        // Initialize settings facade
        $this->settings = new FinanceSettings();

        // Initialize support services
        $eligibility = new Eligibility($this->settings, $this->logger);
        $date_formatter = new DateFormatter();
        $membership_gateway = new MembershipGateway($this->logger);

        // Initialize WPSettings settings page (Wicket Settings page)
        $this->wpsettings_settings = new WPSettingsSettings($this->settings, $this->logger);
        $this->wpsettings_settings->init();

        // Initialize product meta (Finance Mapping tab)
        $this->product_meta = new FinanceMeta($date_formatter, $this->logger);
        $this->product_meta->init();

        // Initialize order line item meta
        $this->line_item_meta = new LineItemMeta($this->product_meta, $date_formatter, $this->logger);
        $this->line_item_meta->init();

        // Initialize dynamic dates (membership integration)
        $this->dynamic_dates = new DynamicDates(
            $this->settings,
            $this->line_item_meta,
            $membership_gateway,
            $eligibility,
            $this->logger
        );
        $this->dynamic_dates->init();

        // Initialize customer display
        $this->customer_renderer = new CustomerRenderer(
            $this->settings,
            $eligibility,
            $date_formatter,
            $this->logger
        );
        $this->customer_renderer->init();

        // Initialize export adapter
        $this->export_adapter = new WooExportAdapter($date_formatter, $this->logger);
        $this->export_adapter->init();

        // Load text domain
        add_action('init', [$this, 'load_textdomain']);

        // Log plugin initialization
        $this->logger->info('Wicket Financial Fields plugin initialized', [
            'version' => $this->version,
        ]);
    }

    /**
     * Load plugin text domain for translations.
     *
     * @return void
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'wicket-finance',
            false,
            dirname(plugin_basename(WICKET_FINANCE_FILE)) . '/languages'
        );
    }

    /**
     * Get logger instance.
     *
     * @return Logger
     */
    public function get_logger(): Logger
    {
        return $this->logger;
    }

    /**
     * Get settings instance.
     *
     * @return FinanceSettings
     */
    public function get_settings(): FinanceSettings
    {
        return $this->settings;
    }
}
