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
use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterface;
use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterfaceFactory;
use Miyabara\FeaturedProduct\Api\GetSalableQtyInterface;

/**
 * Resolves the quantity through MSI so reservations and multi-source stocks are respected —
 * but only when the client token is stale; current tokens are answered from cache alone.
 */
class GetSalableQty implements GetSalableQtyInterface
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param StockVersion $stockVersion
     * @param StockUpdateInterfaceFactory $stockUpdateFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockVersion $stockVersion,
        private readonly StockUpdateInterfaceFactory $stockUpdateFactory,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(?string $version = null): StockUpdateInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $sku = $this->config->getSku($storeId);

        if (!$this->config->isEnabled($storeId) || $sku === null) {
            throw new NoSuchEntityException(__('No featured product is configured.'));
        }

        $currentVersion = $this->stockVersion->get($sku);

        if ($version === $currentVersion) {
            return $this->stockUpdateFactory->create([
                'changed' => false,
                'version' => $currentVersion,
                'qty' => 0.0,
            ]);
        }

        $stock = $this->stockResolver->execute(
            SalesChannelInterface::TYPE_WEBSITE,
            $this->storeManager->getWebsite()->getCode(),
        );

        return $this->stockUpdateFactory->create([
            'changed' => true,
            'version' => $currentVersion,
            'qty' => $this->getProductSalableQty->execute($sku, (int) $stock->getStockId()),
        ]);
    }
}
