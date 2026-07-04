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
 * Response of the stock endpoint: carries the qty only when it changed, so unchanged polls stay cheap.
 *
 * @api
 */
interface StockUpdateInterface
{
    /**
     * False means the client version is current and qty was not recalculated.
     *
     * @return bool
     */
    public function isChanged(): bool;

    /**
     * Opaque token the client sends back on the next poll.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Salable quantity; only meaningful when changed is true.
     *
     * @return float
     */
    public function getQty(): float;
}
