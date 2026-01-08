<?php

declare(strict_types=1);

namespace Wicket\Finance\Export;

use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Logger;

/**
 * WooCommerce export adapter service.
 *
 * Exposes finance line item meta to WooCommerce CSV exports.
 *
 * Meta exposed:
 * - _wicket_finance_start_date (Term Start Date)
 * - _wicket_finance_end_date (Term End Date)
 * - _wicket_finance_gl_code (GL Code)
 *
 * @since 1.0.0
 */
class WooExportAdapter
{
    /**
     * Date formatter.
     *
     * @var DateFormatter
     */
    private $date_formatter;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param DateFormatter $date_formatter Date formatter.
     * @param Logger        $logger         Logger instance.
     */
    public function __construct(DateFormatter $date_formatter, Logger $logger)
    {
        $this->date_formatter = $date_formatter;
        $this->logger = $logger;
    }

    /**
     * Initialize export adapter hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Add custom columns to order export
        add_filter('woocommerce_order_export_column_names', [$this, 'add_export_columns']);
        add_filter('woocommerce_order_export_product_column_names', [$this, 'add_product_export_columns']);

        // Add data to export
        add_filter('woocommerce_order_export_product_data', [$this, 'add_export_data'], 10, 3);
    }

    /**
     * Adds finance columns to order export.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_export_columns(array $columns): array
    {
        $columns['wicket_finance_gl_code'] = __('Finance GL Code', 'wicket-finance');
        $columns['wicket_finance_start_date'] = __('Finance Term Start Date', 'wicket-finance');
        $columns['wicket_finance_end_date'] = __('Finance Term End Date', 'wicket-finance');

        return $columns;
    }

    /**
     * Adds finance columns to product export.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_product_export_columns(array $columns): array
    {
        $columns['wicket_finance_gl_code'] = __('GL Code', 'wicket-finance');
        $columns['wicket_finance_term_start'] = __('Term Start Date', 'wicket-finance');
        $columns['wicket_finance_term_end'] = __('Term End Date', 'wicket-finance');

        return $columns;
    }

    /**
     * Adds finance data to product export.
     *
     * @param array              $data    Export data.
     * @param \WC_Order_Item     $item    Order item.
     * @param \WC_Product        $product Product object.
     * @return array Modified data.
     */
    public function add_export_data(array $data, \WC_Order_Item $item, \WC_Product $product): array
    {
        if (!($item instanceof \WC_Order_Item_Product)) {
            return $data;
        }

        // Get finance meta
        $gl_code = $item->get_meta('_wicket_finance_gl_code', true);
        $start_date = $item->get_meta('_wicket_finance_start_date', true);
        $end_date = $item->get_meta('_wicket_finance_end_date', true);

        // Format dates for export (use display format for readability)
        $formatted_start = !empty($start_date) ? $this->date_formatter->format_for_display($start_date) : '';
        $formatted_end = !empty($end_date) ? $this->date_formatter->format_for_display($end_date) : '';

        $data['wicket_finance_gl_code'] = $gl_code;
        $data['wicket_finance_term_start'] = $formatted_start;
        $data['wicket_finance_term_end'] = $formatted_end;

        return $data;
    }
}
