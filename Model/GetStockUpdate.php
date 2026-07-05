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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterface;
use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterfaceFactory;
use Miyabara\FeaturedProduct\Api\GetStockUpdateInterface;

/**
 * Resolves the quantity through MSI so reservations and multi-source stocks are respected;
 * the qty is micro-cached per change token so polling clients cannot stampede MSI —
 * unchanged clients never even reach this class (304 short-circuit in the webapi plugin).
 */
class GetStockUpdate implements GetStockUpdateInterface
{
    /**
     * @var string
     */
    private const QTY_CACHE_PREFIX = 'miyabara_featured_stock_qty_';

    /**
     * @var int
     */
    private const QTY_CACHE_LIFETIME = 60;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param StockVersion $stockVersion
     * @param StockUpdateInterfaceFactory $stockUpdateFactory
     * @param FrontendInterface $cache
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockVersion $stockVersion,
        private readonly StockUpdateInterfaceFactory $stockUpdateFactory,
        private readonly FrontendInterface $cache,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): StockUpdateInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $sku = $this->config->getSku($storeId);

        if (!$this->config->isEnabled($storeId) || $sku === null) {
            throw new NoSuchEntityException(__('No featured product is configured.'));
        }

        $version = $this->stockVersion->get($sku);

        return $this->stockUpdateFactory->create([
            'version' => $version,
            'qty' => $this->getQty($sku, $version),
        ]);
    }

    /**
     * Micro-caches the qty per token.
     *
     * Within one token window the value cannot legitimately differ, so at most
     * one MSI lookup happens per window no matter how many clients call.
     *
     * @param string $sku
     * @param string $token
     * @return float
     */
    private function getQty(string $sku, string $token): float
    {
        $cacheKey = self::QTY_CACHE_PREFIX . hash('md5', $sku . '|' . $token);
        $cached = $this->cache->load($cacheKey);

        if ($cached !== false) {
            return (float) $cached;
        }

        $stock = $this->stockResolver->execute(
            SalesChannelInterface::TYPE_WEBSITE,
            $this->storeManager->getWebsite()->getCode(),
        );
        $qty = $this->getProductSalableQty->execute($sku, (int) $stock->getStockId());

        $this->cache->save((string) $qty, $cacheKey, [], self::QTY_CACHE_LIFETIME);

        return $qty;
    }
}
