<?php
/**
 * Miyabara_FeaturedProduct
 *
 * @vendor    Miyabara
 * @package   FeaturedProduct
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@gmail.com>
 */

declare(strict_types=1);

namespace Miyabara\FeaturedProduct\Model;

use Magento\Framework\Cache\FrontendInterface;

/**
 * Keeps a per-SKU change token in a dedicated tag-scoped cache (virtual type in di.xml), so
 * "did the stock change?" is answered from memory instead of hitting MSI on every storefront poll.
 */
class StockVersion
{
    private const CACHE_KEY_PREFIX = 'miyabara_featured_stock_version_';

    /**
     * @param FrontendInterface $cache
     */
    public function __construct(
        private readonly FrontendInterface $cache,
    ) {
    }

    /**
     * A cache miss (flush, eviction) initializes a fresh token.
     *
     * Clients treat the fresh token as a change — the worst case is one extra
     * qty lookup, never a stale value.
     *
     * @param string $sku
     * @return string
     */
    public function get(string $sku): string
    {
        $version = $this->cache->load($this->cacheKey($sku));

        if (!$version) {
            $version = $this->generate();
            $this->cache->save($version, $this->cacheKey($sku));
        }

        return (string) $version;
    }

    /**
     * Called by the plugins whenever the SKU stock may have changed.
     *
     * @param string $sku
     * @return void
     */
    public function bump(string $sku): void
    {
        $this->cache->save($this->generate(), $this->cacheKey($sku));
    }

    /**
     * Keyed per SKU so switching the featured product in the admin naturally invalidates clients.
     *
     * @param string $sku
     * @return string
     */
    private function cacheKey(string $sku): string
    {
        return self::CACHE_KEY_PREFIX . strtolower($sku);
    }

    /**
     * Uniqueness matters more than meaning — clients only compare tokens for equality.
     *
     * @return string
     */
    private function generate(): string
    {
        return (string) (int) (microtime(true) * 1000);
    }
}
