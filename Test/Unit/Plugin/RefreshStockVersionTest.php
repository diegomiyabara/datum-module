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

namespace Miyabara\FeaturedProduct\Test\Unit\Plugin;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\StockVersion;
use Miyabara\FeaturedProduct\Plugin\RefreshStockVersion;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the SKU matching across store views, since a wrong match silently breaks invalidation.
 */
class RefreshStockVersionTest extends TestCase
{
    /** @var StockVersion|MockObject */
    private StockVersion|MockObject $stockVersion;

    /** @var RefreshStockVersion */
    private RefreshStockVersion $subject;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getSku')->willReturnMap([
            [1, 'MIYA-DEMO'],
            [2, null],
        ]);

        $storeOne = $this->createMock(StoreInterface::class);
        $storeOne->method('getId')->willReturn(1);

        $storeTwo = $this->createMock(StoreInterface::class);
        $storeTwo->method('getId')->willReturn(2);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$storeOne, $storeTwo]);

        $this->stockVersion = $this->createMock(StockVersion::class);

        $this->subject = new RefreshStockVersion($config, $this->stockVersion, $storeManager);
    }

    public function testShouldBumpOnceWhenFeaturedSkuIsSaved(): void
    {
        $this->stockVersion
            ->expects($this->once())
            ->method('bump')
            ->with('MIYA-DEMO');

        $this->subject->afterExecute(
            $this->createMock(SourceItemsSaveInterface::class),
            null,
            [$this->sourceItem('miya-demo'), $this->sourceItem('MIYA-DEMO')],
        );
    }

    public function testShouldNotBumpForUnrelatedSkus(): void
    {
        $this->stockVersion->expects($this->never())->method('bump');

        $this->subject->afterExecute(
            $this->createMock(SourceItemsSaveInterface::class),
            null,
            [$this->sourceItem('OTHER-SKU')],
        );
    }

    /**
     * Builds a source item mock exposing only what the plugin reads.
     *
     * @param string $sku
     * @return SourceItemInterface|MockObject
     */
    private function sourceItem(string $sku): SourceItemInterface|MockObject
    {
        $item = $this->createMock(SourceItemInterface::class);
        $item->method('getSku')->willReturn($sku);

        return $item;
    }
}
