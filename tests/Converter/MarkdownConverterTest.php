<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\Converter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\ConverterInterface;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\HtmlPreprocessorInterface;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;

class MarkdownConverterTest extends TestCase
{
    private ConverterInterface&MockObject $driver;

    protected function setUp(): void
    {
        $this->driver = $this->createMock(ConverterInterface::class);
    }

    public function testConvertsHtmlWithNoPreprocessorsAndNoCache(): void
    {
        $html     = '<p>Hello</p>';
        $markdown = 'Hello';

        $this->driver->expects(self::once())
            ->method('convert')
            ->with($html)
            ->willReturn($markdown);

        $converter = new MarkdownConverter($this->driver, [], false, 3600);
        $result    = $converter->convert($html);

        self::assertSame($markdown, $result);
    }

    public function testAppliesPreprocessorsBeforeConversion(): void
    {
        $html      = '<p>Hello World</p>';
        $processed = '<p>Hello</p>';
        $markdown  = 'Hello';

        $preprocessor = $this->createMock(HtmlPreprocessorInterface::class);
        $preprocessor->expects(self::once())
            ->method('process')
            ->with($html)
            ->willReturn($processed);

        $this->driver->expects(self::once())
            ->method('convert')
            ->with($processed)
            ->willReturn($markdown);

        $converter = new MarkdownConverter($this->driver, [$preprocessor], false, 3600);
        $result    = $converter->convert($html);

        self::assertSame($markdown, $result);
    }

    public function testAppliesMultiplePreprocessorsInOrder(): void
    {
        $html   = '<p>Original</p>';
        $calls  = [];

        $preprocessorA = $this->createMock(HtmlPreprocessorInterface::class);
        $preprocessorA->method('process')->willReturnCallback(static function (string $html) use (&$calls): string {
            $calls[] = 'A';
            return $html . '-A';
        });

        $preprocessorB = $this->createMock(HtmlPreprocessorInterface::class);
        $preprocessorB->method('process')->willReturnCallback(static function (string $html) use (&$calls): string {
            $calls[] = 'B';
            return $html . '-B';
        });

        $this->driver->method('convert')->willReturn('result');

        $converter = new MarkdownConverter($this->driver, [$preprocessorA, $preprocessorB], false, 3600);
        $converter->convert($html);

        self::assertSame(['A', 'B'], $calls);
    }

    public function testCacheIsSkippedWhenDisabled(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::never())->method('getItem');
        $cache->expects(self::never())->method('save');

        $this->driver->method('convert')->willReturn('markdown');

        $converter = new MarkdownConverter($this->driver, [], false, 3600, $cache);
        $converter->convert('<p>Hello</p>');
    }

    public function testCacheIsSkippedWhenCachePoolIsNull(): void
    {
        $this->driver->expects(self::once())->method('convert')->willReturn('markdown');

        $converter = new MarkdownConverter($this->driver, [], true, 3600);
        $result    = $converter->convert('<p>Hello</p>');

        self::assertSame('markdown', $result);
    }

    public function testReturnsCachedMarkdownOnCacheHit(): void
    {
        $html            = '<p>Cached</p>';
        $cachedMarkdown  = 'Cached markdown';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedMarkdown);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects(self::never())->method('save');

        $this->driver->expects(self::never())->method('convert');

        $converter = new MarkdownConverter($this->driver, [], true, 3600, $cache);
        $result    = $converter->convert($html);

        self::assertSame($cachedMarkdown, $result);
    }

    public function testConvertsAndSavesToCacheOnCacheMiss(): void
    {
        $html     = '<p>New content</p>';
        $markdown = 'New content';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with($markdown)->willReturnSelf();
        $cacheItem->expects(self::once())->method('expiresAfter')->with(3600)->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects(self::once())->method('save')->with($cacheItem);

        $this->driver->expects(self::once())->method('convert')->with($html)->willReturn($markdown);

        $converter = new MarkdownConverter($this->driver, [], true, 3600, $cache);
        $result    = $converter->convert($html);

        self::assertSame($markdown, $result);
    }

    public function testCacheKeyIsBasedOnHtmlContent(): void
    {
        $html1 = '<p>First</p>';
        $html2 = '<p>Second</p>';

        $capturedKeys = [];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturnCallback(static function (string $key) use (&$capturedKeys, $cacheItem): CacheItemInterface {
            $capturedKeys[] = $key;
            return $cacheItem;
        });
        $cache->method('save');

        $this->driver->method('convert')->willReturn('result');

        $converter = new MarkdownConverter($this->driver, [], true, 3600, $cache);
        $converter->convert($html1);
        $converter->convert($html2);

        self::assertCount(2, $capturedKeys);
        self::assertNotSame($capturedKeys[0], $capturedKeys[1]);
        self::assertStringStartsWith('symfony_markdown_response_', $capturedKeys[0]);
    }

    public function testCacheTtlIsRespected(): void
    {
        $ttl = 7200;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->expects(self::once())->method('expiresAfter')->with($ttl)->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->method('save');

        $this->driver->method('convert')->willReturn('result');

        $converter = new MarkdownConverter($this->driver, [], true, $ttl, $cache);
        $converter->convert('<p>Test</p>');
    }

    public function testSameHtmlProducesSameCacheKey(): void
    {
        $html = '<p>Same content</p>';

        $capturedKeys = [];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturnCallback(static function (string $key) use (&$capturedKeys, $cacheItem): CacheItemInterface {
            $capturedKeys[] = $key;
            return $cacheItem;
        });
        $cache->method('save');

        $this->driver->method('convert')->willReturn('result');

        $converter = new MarkdownConverter($this->driver, [], true, 3600, $cache);
        $converter->convert($html);
        $converter->convert($html);

        self::assertSame($capturedKeys[0], $capturedKeys[1]);
    }
}
