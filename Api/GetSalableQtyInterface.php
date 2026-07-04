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

/**
 * Exposes the featured product salable quantity so the storefront can poll it without reloading the page.
 *
 * @api
 */
interface GetSalableQtyInterface
{
    /**
     * Reads the configured SKU server-side, so the endpoint never becomes an arbitrary stock lookup.
     *
     * @return float
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): float;
}
