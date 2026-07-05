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

namespace Miyabara\FeaturedProduct\Api\Data;

/**
 * Response of the stock endpoint; "unchanged" is signaled at HTTP level (304), not in the body.
 *
 * @api
 */
interface StockUpdateInterface
{
    /**
     * Opaque change token — the same value travels as the ETag response header.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Salable quantity for the configured featured product.
     *
     * @return float
     */
    public function getQty(): float;
}
