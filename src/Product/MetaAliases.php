<?php

declare(strict_types=1);

namespace Wicket\Finance\Product;

use Wicket\Finance\Support\Logger;

/**
 * Redirects writes of site-declared legacy meta keys to their canonical finance keys.
 *
 * Sites declare the map through the `wicket/finance/meta_aliases` filter:
 *
 *     add_filter('wicket/finance/meta_aliases', function (array $aliases): array {
 *         return ['GL_Deferred' => '_wicket_finance_gl_deferred'];
 *     });
 *
 * WHY
 * ---
 * Import tools (WP All Import, custom REST writers) write product meta directly, so they
 * never pass through FinanceMeta::save_product_meta() and cannot be remapped there.
 * Intercepting the metadata layer (add_post_metadata / update_post_metadata) catches every
 * writer at one choke point: the legacy row never exists, reads need no fallback, and each
 * site keeps its legacy names out of the product.
 */
class MetaAliases
{
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Initialize the metadata-layer interception.
     *
     * @return void
     */
    public function init(): void
    {
        add_filter('add_post_metadata', [$this, 'redirect'], 10, 4);
        add_filter('update_post_metadata', [$this, 'redirect'], 10, 4);
    }

    /**
     * Redirect a legacy meta write to its canonical key.
     *
     * Writes on anything other than products (or product variations) and keys absent from
     * the map pass through untouched.
     *
     * @param mixed  $check      Null to proceed with the original write, anything else short-circuits it.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key being written.
     * @param mixed  $meta_value Meta value being written.
     * @return mixed True when redirected (short-circuits the original write), otherwise the untouched $check.
     */
    public function redirect($check, int $object_id, string $meta_key, $meta_value)
    {
        $aliases = apply_filters('wicket/finance/meta_aliases', []);

        if (!isset($aliases[$meta_key])) {
            return $check;
        }

        $post_type = get_post_type($object_id);

        if (!in_array($post_type, ['product', 'product_variation'], true)) {
            return $check;
        }

        $canonical = $aliases[$meta_key];

        update_post_meta($object_id, $canonical, $meta_value);

        $this->logger->debug('Redirected legacy finance meta write.', [
            'from' => $meta_key,
            'to' => $canonical,
            'post_id' => $object_id,
        ]);

        return true;
    }
}
