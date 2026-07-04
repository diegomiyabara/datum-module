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

use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Api\Data\StockUpdateInterfaceFactory;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\Data\StockUpdate;
use Miyabara\FeaturedProduct\Model\GetStockUpdate;
use Miyabara\FeaturedProduct\Model\StockVersion;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the guard clauses, the version short-circuit, the qty micro-cache and the MSI delegation.
 */
class GetStockUpdateTest extends TestCase
{
    /** @var Config|MockObject */
    private Config|MockObject $config;

    /** @var StockResolverInterface|MockObject */
    private StockResolverInterface|MockObject $stockResolver;

    /** @var GetProductSalableQtyInterface|MockObject */
    private GetProductSalableQtyInterface|MockObject $getProductSalableQty;

    /** @var StockVersion|MockObject */
    private StockVersion|MockObject $stockVersion;

    /** @var FrontendInterface|MockObject */
    private FrontendInterface|MockObject $cache;

    /** @var GetStockUpdate */
    private GetStockUpdate $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->stockResolver = $this->createMock(StockResolverInterface::class);
        $this->getProductSalableQty = $this->createMock(GetProductSalableQtyInterface::class);
        $this->stockVersion = $this->createMock(StockVersion::class);
        $this->cache = $this->createMock(FrontendInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);

        $stockUpdateFactory = $this->createMock(StockUpdateInterfaceFactory::class);
        $stockUpdateFactory->method('create')->willReturnCallback(
            static fn (array $data) => new StockUpdate($data['changed'], $data['version'], $data['qty']),
        );

        $this->subject = new GetStockUpdate(
            $this->config,
            $storeManager,
            $this->stockResolver,
            $this->getProductSalableQty,
            $this->stockVersion,
            $stockUpdateFactory,
            $this->cache,
        );
    }

    public function testShouldReturnQtyWhenClientVersionIsStale(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('24-MB01');
        $this->stockVersion->method('get')->willReturn('v2');
        $this->cache->method('load')->willReturn(false);

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

        $result = $this->subject->execute('v1');

        $this->assertTrue($result->isChanged());
        $this->assertSame('v2', $result->getVersion());
        $this->assertSame(42.0, $result->getQty());
    }

    public function testShouldSkipMsiLookupWhenClientVersionIsCurrent(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('24-MB01');
        $this->stockVersion->method('get')->willReturn('v1');

        $this->stockResolver->expects($this->never())->method('execute');
        $this->getProductSalableQty->expects($this->never())->method('execute');

        $result = $this->subject->execute('v1');

        $this->assertFalse($result->isChanged());
        $this->assertSame('v1', $result->getVersion());
    }

    public function testShouldServeQtyFromMicroCacheWithoutMsiLookup(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('24-MB01');
        $this->stockVersion->method('get')->willReturn('v2');
        $this->cache->method('load')->willReturn('33');

        $this->stockResolver->expects($this->never())->method('execute');
        $this->getProductSalableQty->expects($this->never())->method('execute');

        $result = $this->subject->execute('v1');

        $this->assertTrue($result->isChanged());
        $this->assertSame(33.0, $result->getQty());
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
