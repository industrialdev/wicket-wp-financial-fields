<?php
/**
 * Plugin Name: Wicket Financial Fields
 * Plugin URI: https://github.com/wicket/wicket-wp-financial-fields
 * Description: Finance mapping and deferred revenue system for WooCommerce orders. Provides GL code mapping, revenue deferral dates, and dynamic membership term date population.
 * Version: 1.0.0
 * Author: Wicket Inc.
 * Author URI: https://wicket.io
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 8.3
 * Requires Plugins: wicket-wp-base-plugin, woocommerce, wicket-wp-memberships
 * WC requires at least: 10.0
 * WC tested up to: 10.0
 * Text Domain: wicket-finance
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('WICKET_FINANCE_VERSION', get_file_data(__FILE__, ['Version' => 'Version'])['Version']);
define('WICKET_FINANCE_FILE', __FILE__);
define('WICKET_FINANCE_PATH', plugin_dir_path(__FILE__));
define('WICKET_FINANCE_URL', plugin_dir_url(__FILE__));
define('WICKET_FINANCE_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader
require_once WICKET_FINANCE_PATH . 'vendor/autoload.php';

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wicket_finance_is_woocommerce_active(): bool
{
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true);
}

/**
 * Check if Wicket Base Plugin is active.
 *
 * @return bool
 */
function wicket_finance_is_base_plugin_active(): bool
{
    return in_array('wicket-wp-base-plugin/wicket.php', apply_filters('active_plugins', get_option('active_plugins')), true);
}

/**
 * Check if Wicket Memberships is active.
 *
 * @return bool
 */
function wicket_finance_is_memberships_active(): bool
{
    return in_array('wicket-wp-memberships/wicket.php', apply_filters('active_plugins', get_option('active_plugins')), true);
}

/**
 * Display admin notice if required plugins are not active.
 *
 * @return void
 */
function wicket_finance_missing_dependencies_notice(): void
{
    $missing = [];

    if (!wicket_finance_is_woocommerce_active()) {
        $missing[] = '<a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">WooCommerce</a>';
    }

    if (!wicket_finance_is_base_plugin_active()) {
        $missing[] = 'Wicket Base Plugin';
    }

    if (!wicket_finance_is_memberships_active()) {
        $missing[] = 'Wicket Memberships';
    }

    if (!empty($missing)) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: %s: Comma-separated list of required plugins */
                        __('<strong>Wicket Financial Fields</strong> requires the following plugins to be installed and activated: %s', 'wicket-finance'),
                        implode(', ', $missing)
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }
}

/*
 * Declare HPOS compatibility
 *
 * @return void
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/*
 * Initialize the plugin
 *
 * @return void
 */
add_action(
    'plugins_loaded',
    function () {
        // Check dependencies
        if (!wicket_finance_is_woocommerce_active() || !wicket_finance_is_base_plugin_active() || !wicket_finance_is_memberships_active()) {
            add_action('admin_notices', 'wicket_finance_missing_dependencies_notice');
            return;
        }

        // Initialize plugin
        \Wicket\Finance\Plugin::get_instance()->plugin_setup();
    }
);

/**
 * Plugin activation hook.
 *
 * @return void
 */
function wicket_finance_activate(): void
{
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.3', '<')) {
        deactivate_plugins(WICKET_FINANCE_BASENAME);
        wp_die(
            esc_html__('Wicket Financial Fields requires PHP 8.3 or higher. Please upgrade your PHP version.', 'wicket-finance'),
            esc_html__('Plugin Activation Error', 'wicket-finance'),
            ['back_link' => true]
        );
    }

    // Check for required plugins
    if (!wicket_finance_is_woocommerce_active()) {
        deactivate_plugins(WICKET_FINANCE_BASENAME);
        wp_die(
            esc_html__('Wicket Financial Fields requires WooCommerce to be installed and activated.', 'wicket-finance'),
            esc_html__('Plugin Activation Error', 'wicket-finance'),
            ['back_link' => true]
        );
    }

    if (!wicket_finance_is_base_plugin_active()) {
        deactivate_plugins(WICKET_FINANCE_BASENAME);
        wp_die(
            esc_html__('Wicket Financial Fields requires Wicket Base Plugin to be installed and activated.', 'wicket-finance'),
            esc_html__('Plugin Activation Error', 'wicket-finance'),
            ['back_link' => true]
        );
    }

    if (!wicket_finance_is_memberships_active()) {
        deactivate_plugins(WICKET_FINANCE_BASENAME);
        wp_die(
            esc_html__('Wicket Financial Fields requires Wicket Memberships to be installed and activated.', 'wicket-finance'),
            esc_html__('Plugin Activation Error', 'wicket-finance'),
            ['back_link' => true]
        );
    }

    // Set activation timestamp
    if (!get_option('wicket_finance_activated_time')) {
        update_option('wicket_finance_activated_time', time());
    }
}

register_activation_hook(__FILE__, 'wicket_finance_activate');

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wicket_finance_deactivate(): void
{
    // Cleanup if needed (currently none required)
}

register_deactivation_hook(__FILE__, 'wicket_finance_deactivate');
