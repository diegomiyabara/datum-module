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

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Webapi\Controller\Rest\SynchronousRequestProcessor;
use Miyabara\FeaturedProduct\Model\Config;
use Miyabara\FeaturedProduct\Model\StockVersion;
use Miyabara\FeaturedProduct\Plugin\HttpCacheStockUpdate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the conditional GET branch for the stock polling endpoint.
 */
class HttpCacheStockUpdateTest extends TestCase
{
    /** @var Config|MockObject */
    private Config|MockObject $config;

    /** @var StoreManagerInterface|MockObject */
    private StoreManagerInterface|MockObject $storeManager;

    /** @var StockVersion|MockObject */
    private StockVersion|MockObject $stockVersion;

    /** @var Response|MockObject */
    private Response|MockObject $response;

    /** @var SynchronousRequestProcessor|MockObject */
    private SynchronousRequestProcessor|MockObject $processor;

    /** @var HttpCacheStockUpdate */
    private HttpCacheStockUpdate $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->stockVersion = $this->createMock(StockVersion::class);
        $this->response = $this->createMock(Response::class);
        $this->processor = $this->createMock(SynchronousRequestProcessor::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->subject = new HttpCacheStockUpdate(
            $this->config,
            $this->storeManager,
            $this->stockVersion,
            $this->response,
        );
    }

    public function testShouldReturnNotModifiedWhenIfNoneMatchMatchesCurrentVersion(): void
    {
        $request = $this->createRequest('/V1/featured-product/salable-qty', '"v1"');
        $this->mockConfiguredSku();
        $this->stockVersion->method('get')->with('24-MB01')->willReturn('v1');

        $this->response->expects($this->exactly(2))
            ->method('setHeader')
            ->withConsecutive(
                ['ETag', '"v1"', true],
                ['Cache-Control', 'private, no-cache', true],
            )
            ->willReturnSelf();
        $this->response->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(304)
            ->willReturnSelf();
        $this->response->expects($this->once())->method('clearBody')->willReturnSelf();

        $proceeded = false;
        $this->subject->aroundProcess(
            $this->processor,
            function () use (&$proceeded): void {
                $proceeded = true;
            },
            $request,
        );

        $this->assertFalse($proceeded);
    }

    public function testShouldProceedAndSetEtagWhenIfNoneMatchIsStale(): void
    {
        $request = $this->createRequest('/V1/featured-product/salable-qty', '"v1"');
        $this->mockConfiguredSku();
        $this->stockVersion->method('get')->with('24-MB01')->willReturn('v2');

        $this->response->expects($this->exactly(2))
            ->method('setHeader')
            ->withConsecutive(
                ['ETag', '"v2"', true],
                ['Cache-Control', 'private, no-cache', true],
            )
            ->willReturnSelf();
        $this->response->expects($this->never())->method('setHttpResponseCode');
        $this->response->expects($this->never())->method('clearBody');

        $proceeded = false;
        $this->subject->aroundProcess(
            $this->processor,
            function (Request $processedRequest) use (&$proceeded, $request): void {
                $proceeded = $processedRequest === $request;
            },
            $request,
        );

        $this->assertTrue($proceeded);
    }

    public function testShouldIgnoreOtherEndpoints(): void
    {
        $request = $this->createRequest('/V1/products', '"v1"');

        $this->config->expects($this->never())->method('getSku');
        $this->stockVersion->expects($this->never())->method('get');
        $this->response->expects($this->never())->method('setHeader');

        $proceeded = false;
        $this->subject->aroundProcess(
            $this->processor,
            function (Request $processedRequest) use (&$proceeded, $request): void {
                $proceeded = $processedRequest === $request;
            },
            $request,
        );

        $this->assertTrue($proceeded);
    }

    /**
     * @return void
     */
    private function mockConfiguredSku(): void
    {
        $this->config->method('getSku')->with(1)->willReturn('24-MB01');
        $this->config->method('isEnabled')->with(1)->willReturn(true);
    }

    /**
     * @param string $path
     * @param string $ifNoneMatch
     * @return Request|MockObject
     */
    private function createRequest(string $path, string $ifNoneMatch): Request|MockObject
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::HTTP_METHOD_GET);
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getHeader')->with('If-None-Match')->willReturn($ifNoneMatch);

        return $request;
    }
}
