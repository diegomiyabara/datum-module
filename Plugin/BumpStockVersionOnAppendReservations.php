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

use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\StockVersion;

/**
 * Salable qty also changes when orders reserve stock — no source item is saved in that flow,
 * so this second interception point is required to keep the storefront token honest.
 */
class BumpStockVersionOnAppendReservations
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
     * Runs after reservations are appended (order placed, canceled, refunded).
     *
     * @param AppendReservationsInterface $subject
     * @param null $result
     * @param ReservationInterface[] $reservations
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        AppendReservationsInterface $subject,
        $result,
        array $reservations,
    ): void {
        $sku = $this->config->getSku();

        if ($sku === null) {
            return;
        }

        foreach ($reservations as $reservation) {
            if (strcasecmp($reservation->getSku(), $sku) === 0) {
                $this->stockVersion->bump($sku);

                break;
            }
        }
    }
}
