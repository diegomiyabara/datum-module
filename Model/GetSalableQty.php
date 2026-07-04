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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Api\GetSalableQtyInterface;

/**
 * Resolves the quantity through MSI so reservations and multi-source stocks are respected,
 * instead of reading the raw qty from the legacy CatalogInventory tables.
 */
class GetSalableQty implements GetSalableQtyInterface
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param GetProductSalableQtyInterface $getProductSalableQty
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): float
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $sku = $this->config->getSku($storeId);

        if (!$this->config->isEnabled($storeId) || $sku === null) {
            throw new NoSuchEntityException(__('No featured product is configured.'));
        }

        $stock = $this->stockResolver->execute(
            SalesChannelInterface::TYPE_WEBSITE,
            $this->storeManager->getWebsite()->getCode(),
        );

        return $this->getProductSalableQty->execute($sku, (int) $stock->getStockId());
    }
}
