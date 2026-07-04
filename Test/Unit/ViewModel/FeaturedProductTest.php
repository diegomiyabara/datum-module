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

namespace Miyabara\FeaturedProduct\Test\Unit\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\ViewModel\FeaturedProduct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers the "nothing to show" paths — the homepage must never break because of configuration.
 */
class FeaturedProductTest extends TestCase
{
    /** @var Config|MockObject */
    private Config|MockObject $config;

    /** @var ProductRepositoryInterface|MockObject */
    private ProductRepositoryInterface|MockObject $productRepository;

    /** @var LoggerInterface|MockObject */
    private LoggerInterface|MockObject $logger;

    /** @var FeaturedProduct */
    private FeaturedProduct $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->subject = new FeaturedProduct(
            $this->config,
            $this->productRepository,
            $storeManager,
            $this->createMock(ImageFactory::class),
            $this->createMock(PriceCurrencyInterface::class),
            $this->createMock(UrlInterface::class),
            $this->logger,
        );
    }

    public function testShouldHaveNoProductWhenModuleIsDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->productRepository->expects($this->never())->method('get');

        $this->assertFalse($this->subject->hasProduct());
    }

    public function testShouldHaveNoProductAndLogWhenSkuDoesNotExist(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('GHOST-SKU');
        $this->productRepository
            ->method('get')
            ->willThrowException(new NoSuchEntityException(__('nope')));

        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->subject->hasProduct());
        $this->assertSame([], $this->subject->getProductIdentities());
    }
}
