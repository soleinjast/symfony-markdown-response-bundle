<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\DependencyInjection;

use stdClass;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\CloudflareConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\LocalConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection\MarkdownResponsePass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MarkdownResponsePassTest extends TestCase
{
    private MarkdownResponsePass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass      = new MarkdownResponsePass();
        $this->container = new ContainerBuilder();

        // Register the required services with their definitions
        $this->container->setDefinition(LocalConverter::class, new Definition(LocalConverter::class));
        $this->container->setDefinition(CloudflareConverter::class, new Definition(CloudflareConverter::class));

        $markdownConverterDef = new Definition(MarkdownConverter::class);
        $markdownConverterDef->setArguments([
            '$driver'       => null,
            '$preprocessors' => [],
            '$cacheEnabled' => true,
            '$cacheTtl'     => 3600,
            '$cache'        => null,
        ]);
        $this->container->setDefinition(MarkdownConverter::class, $markdownConverterDef);

        $this->container->setAlias('symfony_markdown_response.driver', LocalConverter::class);
    }

    public function testImplementsCompilerPassInterface(): void
    {
        self::assertInstanceOf(CompilerPassInterface::class, $this->pass);
    }

    public function testWiresLocalDriverByDefault(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', false);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        $this->pass->process($this->container);

        $alias = $this->container->getAlias('symfony_markdown_response.driver');
        self::assertSame(LocalConverter::class, (string) $alias);
    }

    public function testWiresCloudflareDriver(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'cloudflare');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', false);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        $this->pass->process($this->container);

        $alias = $this->container->getAlias('symfony_markdown_response.driver');
        self::assertSame(CloudflareConverter::class, (string) $alias);
    }

    public function testSkipsCacheWiringWhenCacheDisabled(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', false);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        $this->pass->process($this->container);

        $def       = $this->container->getDefinition(MarkdownConverter::class);
        $cacheArg  = $def->getArgument('$cache');

        self::assertNull($cacheArg);
    }

    public function testWiresCacheFromExplicitCacheService(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', true);
        $this->container->setParameter('symfony_markdown_response.cache_service', 'cache.custom');

        // Register the custom cache service so the pass can find it
        $this->container->setDefinition('cache.custom', new Definition(stdClass::class));

        $this->pass->process($this->container);

        $def      = $this->container->getDefinition(MarkdownConverter::class);
        $cacheArg = $def->getArgument('$cache');

        self::assertNotNull($cacheArg);
        self::assertSame('cache.custom', (string) $cacheArg);
    }

    public function testWiresCacheFromDefaultCacheApp(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', true);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        // Register the default cache.app pool
        $this->container->setDefinition('cache.app', new Definition(stdClass::class));

        $this->pass->process($this->container);

        $def      = $this->container->getDefinition(MarkdownConverter::class);
        $cacheArg = $def->getArgument('$cache');

        self::assertNotNull($cacheArg);
        self::assertSame('cache.app', (string) $cacheArg);
    }

    public function testWiresCacheFromFallbackCacheSystem(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', true);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        // Only cache.system is available
        $this->container->setDefinition('cache.system', new Definition(stdClass::class));

        $this->pass->process($this->container);

        $def      = $this->container->getDefinition(MarkdownConverter::class);
        $cacheArg = $def->getArgument('$cache');

        self::assertNotNull($cacheArg);
        self::assertSame('cache.system', (string) $cacheArg);
    }

    public function testDoesNotWireCacheWhenNoCachePoolAvailable(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', true);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        // No cache pool registered at all

        $this->pass->process($this->container);

        $def      = $this->container->getDefinition(MarkdownConverter::class);
        $cacheArg = $def->getArgument('$cache');

        self::assertNull($cacheArg);
    }

    public function testCacheAppTakesPrecedenceOverCacheSystem(): void
    {
        $this->container->setParameter('symfony_markdown_response.driver', 'local');
        $this->container->setParameter('symfony_markdown_response.cache_enabled', true);
        $this->container->setParameter('symfony_markdown_response.cache_service', null);

        $this->container->setDefinition('cache.app', new Definition(stdClass::class));
        $this->container->setDefinition('cache.system', new Definition(stdClass::class));

        $this->pass->process($this->container);

        $def      = $this->container->getDefinition(MarkdownConverter::class);
        $cacheArg = $def->getArgument('$cache');

        self::assertSame('cache.app', (string) $cacheArg);
    }
}
