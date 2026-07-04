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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Single typed gateway to the module settings, so config paths never leak into services or templates.
 */
class Config
{
    /**
     * @var string
     */
    private const XML_PATH_ENABLED = 'featured_product/general/enabled';

    /**
     * @var string
     */
    private const XML_PATH_SKU = 'featured_product/general/sku';

    /**
     * @var string
     */
    private const XML_PATH_REFRESH_INTERVAL = 'featured_product/general/refresh_interval';

    /**
     * @var int
     */
    private const MIN_REFRESH_INTERVAL = 5;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Store scope, so the box can be turned on per store view.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Empty admin value comes back as null, giving callers a single "not configured" signal.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getSku(?int $storeId = null): ?string
    {
        $sku = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SKU,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        ));

        return $sku !== '' ? $sku : null;
    }

    /**
     * Clamped to a floor so a bad admin value can never make the storefront hammer the API.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getRefreshInterval(?int $storeId = null): int
    {
        $interval = (int) $this->scopeConfig->getValue(
            self::XML_PATH_REFRESH_INTERVAL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );

        return max(self::MIN_REFRESH_INTERVAL, $interval);
    }
}
