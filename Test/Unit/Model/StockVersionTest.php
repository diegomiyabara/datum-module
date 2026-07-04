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
use Miyabara\FeaturedProduct\Model\StockVersion;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers token initialization on cache miss, cached reads and bump uniqueness.
 */
class StockVersionTest extends TestCase
{
    /** @var FrontendInterface|MockObject */
    private FrontendInterface|MockObject $cache;

    /** @var StockVersion */
    private StockVersion $subject;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(FrontendInterface::class);
        $this->subject = new StockVersion($this->cache);
    }

    public function testShouldInitializeTokenOnCacheMiss(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($this->isType('string'), $this->stringContains('miya-demo'));

        $this->assertNotSame('', $this->subject->get('MIYA-DEMO'));
    }

    public function testShouldReturnCachedTokenWithoutSaving(): void
    {
        $this->cache->method('load')->willReturn('token-1');
        $this->cache->expects($this->never())->method('save');

        $this->assertSame('token-1', $this->subject->get('MIYA-DEMO'));
    }

    public function testShouldGenerateUniqueTokensOnConsecutiveBumps(): void
    {
        $saved = [];
        $this->cache->method('save')->willReturnCallback(
            static function (string $data) use (&$saved): bool {
                $saved[] = $data;

                return true;
            },
        );

        $this->subject->bump('MIYA-DEMO');
        $this->subject->bump('MIYA-DEMO');

        $this->assertCount(2, $saved);
        $this->assertNotSame($saved[0], $saved[1]);
    }
}
