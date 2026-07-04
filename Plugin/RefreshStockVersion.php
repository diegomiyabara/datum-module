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
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\StockVersion;

/**
 * Refreshes the featured product change token on both stock write paths: source item saves
 * (admin, import, API) and reservations (orders), which change salable qty without saving items.
 */
class RefreshStockVersion
{
    /**
     * @param Config $config
     * @param StockVersion $stockVersion
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly StockVersion $stockVersion,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Both intercepted services receive a list of objects exposing getSku(), which is all we need.
     *
     * @param object $subject
     * @param mixed $result
     * @param SourceItemInterface[]|ReservationInterface[] $items
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        object $subject,
        $result,
        array $items,
    ): void {
        $configuredSkus = $this->getConfiguredSkus();

        if (!$configuredSkus) {
            return;
        }

        $bumped = [];

        foreach ($items as $item) {
            $sku = strtolower((string) $item->getSku());

            if (isset($configuredSkus[$sku]) && !isset($bumped[$sku])) {
                $this->stockVersion->bump($configuredSkus[$sku]);
                $bumped[$sku] = true;
            }
        }
    }

    /**
     * The featured SKU is store-scoped and may differ per store view.
     *
     * This plugin runs in admin/webapi context, so it must react to every
     * configured value, not just the current scope's.
     *
     * @return array<string, string> lowercased sku => configured sku
     */
    private function getConfiguredSkus(): array
    {
        $skus = [];

        foreach ($this->storeManager->getStores() as $store) {
            $sku = $this->config->getSku((int) $store->getId());

            if ($sku !== null) {
                $skus[strtolower($sku)] = $sku;
            }
        }

        return $skus;
    }
}
