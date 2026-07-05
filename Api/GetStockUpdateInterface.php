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
 * Returns the featured product salable qty; "did it change?" is answered at HTTP level
 * via ETag / If-None-Match (304), keeping a single validator for the whole flow.
 *
 * @api
 */
interface GetStockUpdateInterface
{
    /**
     * Single source of the endpoint path — webapi.xml, the storefront URL and the
     * conditional GET plugin must always agree on it.
     */
    public const ROUTE = '/V1/featured-product/salable-qty';

    /**
     * Reads the configured SKU server-side, so the endpoint never becomes an arbitrary stock lookup.
     *
     * @return StockUpdateInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): StockUpdateInterface;
}
