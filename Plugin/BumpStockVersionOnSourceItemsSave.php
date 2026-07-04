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

namespace Miyabara\FeaturedProduct\Plugin;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\StockVersion;

/**
 * Invalidates the featured product stock token when its source items are saved (admin, import, API),
 * so storefront polls detect the change without querying MSI on every request.
 */
class BumpStockVersionOnSourceItemsSave
{
    /**
     * @param Config $config
     * @param StockVersion $stockVersion
     */
    public function __construct(
        private readonly Config $config,
        private readonly StockVersion $stockVersion,
    ) {
    }

    /**
     * Runs after every source item save; only reacts when the featured SKU is among the items.
     *
     * @param SourceItemsSaveInterface $subject
     * @param null $result
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SourceItemsSaveInterface $subject,
        $result,
        array $sourceItems,
    ): void {
        $sku = $this->config->getSku();

        if ($sku === null) {
            return;
        }

        foreach ($sourceItems as $sourceItem) {
            if (strcasecmp((string) $sourceItem->getSku(), $sku) === 0) {
                $this->stockVersion->bump($sku);

                break;
            }
        }
    }
}
