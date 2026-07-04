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

namespace Miyabara\FeaturedProduct\Test\Unit\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\GetSalableQty;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the guard clauses and the MSI delegation of the salable qty service.
 */
class GetSalableQtyTest extends TestCase
{
    /** @var Config|MockObject */
    private Config|MockObject $config;

    /** @var StockResolverInterface|MockObject */
    private StockResolverInterface|MockObject $stockResolver;

    /** @var GetProductSalableQtyInterface|MockObject */
    private GetProductSalableQtyInterface|MockObject $getProductSalableQty;

    /** @var GetSalableQty */
    private GetSalableQty $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->stockResolver = $this->createMock(StockResolverInterface::class);
        $this->getProductSalableQty = $this->createMock(GetProductSalableQtyInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);

        $this->subject = new GetSalableQty(
            $this->config,
            $storeManager,
            $this->stockResolver,
            $this->getProductSalableQty,
        );
    }

    public function testShouldReturnSalableQtyForConfiguredSku(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('24-MB01');

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(1);

        $this->stockResolver
            ->expects($this->once())
            ->method('execute')
            ->with(SalesChannelInterface::TYPE_WEBSITE, 'base')
            ->willReturn($stock);

        $this->getProductSalableQty
            ->expects($this->once())
            ->method('execute')
            ->with('24-MB01', 1)
            ->willReturn(42.0);

        $this->assertSame(42.0, $this->subject->execute());
    }

    public function testShouldThrowWhenModuleIsDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->config->method('getSku')->willReturn('24-MB01');

        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute();
    }

    public function testShouldThrowWhenNoSkuIsConfigured(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn(null);

        $this->expectException(NoSuchEntityException::class);

        $this->subject->execute();
    }
}
