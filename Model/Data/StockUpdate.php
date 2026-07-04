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

namespace Miyabara\FeaturedProduct\Model\Data;

use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterface;

/**
 * Immutable DTO — built once by the service, so no setters and no state to keep consistent.
 */
class StockUpdate implements StockUpdateInterface
{
    /**
     * @param bool $changed
     * @param string $version
     * @param float $qty
     */
    public function __construct(
        private readonly bool $changed,
        private readonly string $version,
        private readonly float $qty,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getChanged(): bool
    {
        return $this->changed;
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @inheritdoc
     */
    public function getQty(): float
    {
        return $this->qty;
    }
}
