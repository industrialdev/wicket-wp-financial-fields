<?php

declare(strict_types=1);

namespace Wicket\Finance\Display;

use Wicket\Finance\Settings\FinanceSettings;
use Wicket\Finance\Support\Eligibility;
use Wicket\Finance\Support\DateFormatter;
use Wicket\Finance\Support\Logger;

/**
 * Customer renderer service.
 *
 * Renders finance deferral dates on customer-facing surfaces:
 * - Order confirmation page
 * - Emails (8 types)
 * - My Account > Orders
 * - Subscriptions
 * - PDF invoices
 *
 * @since 1.0.0
 */
class CustomerRenderer
{
    /**
     * Settings facade.
     *
     * @var FinanceSettings
     */
    private $settings;

    /**
     * Eligibility service.
     *
     * @var Eligibility
     */
    private $eligibility;

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
     * @param FinanceSettings $settings       Settings facade.
     * @param Eligibility     $eligibility    Eligibility service.
     * @param DateFormatter   $date_formatter Date formatter.
     * @param Logger          $logger         Logger instance.
     */
    public function __construct(
        FinanceSettings $settings,
        Eligibility $eligibility,
        DateFormatter $date_formatter,
        Logger $logger
    ) {
        $this->settings = $settings;
        $this->eligibility = $eligibility;
        $this->date_formatter = $date_formatter;
        $this->logger = $logger;
    }

    /**
     * Initialize customer rendering hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Single hook handles all surfaces (order confirmation, emails, My Account)
        add_action('woocommerce_order_item_meta_end', [$this, 'render_on_order_items'], 10, 3);

        // PDF invoice integration
        add_action('wpo_wcpdf_after_item_meta', [$this, 'render_on_pdf_invoice'], 10, 3);

        // Subscriptions display (uses same hook as orders, context detection handles it)
        // woocommerce_order_item_meta_end also fires for subscription views
    }

    /**
     * Renders dates on order items (handles all contexts based on eligibility).
     *
     * @param int              $item_id Item ID.
     * @param \WC_Order_Item   $item    Order item.
     * @param \WC_Order        $order   Order object.
     * @return void
     */
    public function render_on_order_items(int $item_id, \WC_Order_Item $item, \WC_Order $order): void
    {
        if (!($item instanceof \WC_Order_Item_Product)) {
            return;
        }

        $product = $item->get_product();
        if (!$product) {
            return;
        }

        // Get line item dates
        $start_date = $item->get_meta('_wicket_finance_start_date', true);
        $end_date = $item->get_meta('_wicket_finance_end_date', true);

        // Determine surface
        $surface = $this->get_current_surface();

        // Check eligibility
        if (!$this->eligibility->is_eligible_for_display($product, $surface, $start_date, $end_date)) {
            return;
        }

        // Render dates
        $this->render_dates($start_date, $end_date);
    }

    /**
     * Renders dates on PDF invoices.
     *
     * @param string           $document_type Document type.
     * @param \WC_Order        $order         Order object.
     * @param \WC_Order_Item   $item          Order item.
     * @return void
     */
    public function render_on_pdf_invoice(string $document_type, \WC_Order $order, \WC_Order_Item $item): void
    {
        // Only render on invoice documents
        if ($document_type !== 'invoice') {
            return;
        }

        if (!($item instanceof \WC_Order_Item_Product)) {
            return;
        }

        $product = $item->get_product();
        if (!$product) {
            return;
        }

        // Get line item dates
        $start_date = $item->get_meta('_wicket_finance_start_date', true);
        $end_date = $item->get_meta('_wicket_finance_end_date', true);

        // Check eligibility for PDF surface
        if (!$this->eligibility->is_eligible_for_display($product, FinanceSettings::SURFACE_PDF_INVOICE, $start_date, $end_date)) {
            return;
        }

        // Render dates
        $this->render_dates($start_date, $end_date);
    }

    /**
     * Renders formatted dates.
     *
     * @param string $start_date Start date in Y-m-d format.
     * @param string $end_date   End date in Y-m-d format.
     * @return void
     */
    private function render_dates(string $start_date, string $end_date): void
    {
        $formatted_start = $this->date_formatter->format_for_display($start_date);
        $formatted_end = $this->date_formatter->format_for_display($end_date);

        ?>
        <div class="wicket-finance-dates" style="margin-top: 5px; font-size: 0.9em;">
            <div><strong><?php esc_html_e('Term Start Date:', 'wicket-finance'); ?></strong> <?php echo esc_html($formatted_start); ?></div>
            <div><strong><?php esc_html_e('Term End Date:', 'wicket-finance'); ?></strong> <?php echo esc_html($formatted_end); ?></div>
        </div>
        <?php
    }

    /**
     * Determines current surface context.
     *
     * @return string Surface identifier.
     */
    private function get_current_surface(): string
    {
        // Check if in email
        if (did_action('woocommerce_email_order_details')) {
            return FinanceSettings::SURFACE_EMAILS;
        }

        // Check if viewing subscriptions (WooCommerce Subscriptions)
        if (function_exists('wcs_is_subscription') && is_account_page()) {
            global $wp;
            if (isset($wp->query_vars['view-subscription'])) {
                return FinanceSettings::SURFACE_SUBSCRIPTIONS;
            }
        }

        // Check if on My Account page
        if (is_account_page()) {
            return FinanceSettings::SURFACE_MY_ACCOUNT;
        }

        // Check if order received page
        if (is_order_received_page()) {
            return FinanceSettings::SURFACE_ORDER_CONFIRMATION;
        }

        // Default to order confirmation
        return FinanceSettings::SURFACE_ORDER_CONFIRMATION;
    }
}
