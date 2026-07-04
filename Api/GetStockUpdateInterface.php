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

namespace Miyabara\FeaturedProduct\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterface;

/**
 * Answers "did the featured product stock change, and if so what is the qty?" for storefront polling.
 *
 * @api
 */
interface GetStockUpdateInterface
{
    /**
     * Reads the configured SKU server-side, so the endpoint never becomes an arbitrary stock lookup.
     *
     * When the client version is still current, MSI is not queried at all — the answer comes from cache.
     *
     * @param string|null $version Token from the previous response; null forces a full lookup
     * @return StockUpdateInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(?string $version = null): StockUpdateInterface;
}
