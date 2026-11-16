<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\CloudflareConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\FallbackConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\HtmlPreprocessorInterface;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\LocalConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection\SymfonyMarkdownResponseExtension;
use Soleinjast\SymfonyMarkdownResponseBundle\EventSubscriber\MarkdownResponseSubscriber;
use Soleinjast\SymfonyMarkdownResponseBundle\FrontMatter\FrontMatterGenerator;
use Soleinjast\SymfonyMarkdownResponseBundle\Html\AbsoluteUrlRewriter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SymfonyMarkdownResponseExtensionTest extends TestCase
{
    private SymfonyMarkdownResponseExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new SymfonyMarkdownResponseExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadSetsDefaultParameters(): void
    {
        $this->extension->load([[]], $this->container);

        self::assertSame('local', $this->container->getParameter('symfony_markdown_response.driver'));
        self::assertNull($this->container->getParameter('symfony_markdown_response.cloudflare_endpoint'));
        self::assertSame(5.0, $this->container->getParameter('symfony_markdown_response.cloudflare_timeout'));
        self::assertTrue($this->container->getParameter('symfony_markdown_response.cloudflare_fallback'));
        self::assertTrue($this->container->getParameter('symfony_markdown_response.cache_enabled'));
        self::assertSame(3600, $this->container->getParameter('symfony_markdown_response.cache_ttl'));
        self::assertNull($this->container->getParameter('symfony_markdown_response.cache_service'));
    }

    public function testLoadSetsDefaultAiUserAgents(): void
    {
        $this->extension->load([[]], $this->container);

        $agents = $this->container->getParameter('symfony_markdown_response.ai_user_agents');
        self::assertIsArray($agents);
        self::assertContains('GPTBot', $agents);
        self::assertContains('ClaudeBot', $agents);
    }

    public function testLoadSetsDriverParameter(): void
    {
        $this->extension->load([['driver' => 'cloudflare']], $this->container);

        self::assertSame('cloudflare', $this->container->getParameter('symfony_markdown_response.driver'));
    }

    public function testLoadSetsCloudflareEndpointParameter(): void
    {
        $endpoint = 'https://example.com/api';
        $this->extension->load([['cloudflare_endpoint' => $endpoint]], $this->container);

        self::assertSame($endpoint, $this->container->getParameter('symfony_markdown_response.cloudflare_endpoint'));
    }

    public function testLoadSetsCloudflareTimeoutAndFallbackParameters(): void
    {
        $this->extension->load([['cloudflare_timeout' => 2.5, 'cloudflare_fallback' => false]], $this->container);

        self::assertSame(2.5, $this->container->getParameter('symfony_markdown_response.cloudflare_timeout'));
        self::assertFalse($this->container->getParameter('symfony_markdown_response.cloudflare_fallback'));
    }

    public function testRegistersCloudflareFallbackConverter(): void
    {
        $this->extension->load([[]], $this->container);

        $definition = $this->container->getDefinition('symfony_markdown_response.cloudflare_with_fallback');

        $primary    = $definition->getArgument('$primary');
        $fallback   = $definition->getArgument('$fallback');

        self::assertSame(FallbackConverter::class, $definition->getClass());
        self::assertInstanceOf(Reference::class, $primary);
        self::assertInstanceOf(Reference::class, $fallback);
        self::assertSame(CloudflareConverter::class, (string) $primary);
        self::assertSame(LocalConverter::class, (string) $fallback);
    }

    public function testLoadSetsCacheEnabledParameter(): void
    {
        $this->extension->load([['cache_enabled' => false]], $this->container);

        self::assertFalse($this->container->getParameter('symfony_markdown_response.cache_enabled'));
    }

    public function testLoadSetsCacheTtlParameter(): void
    {
        $this->extension->load([['cache_ttl' => 7200]], $this->container);

        self::assertSame(7200, $this->container->getParameter('symfony_markdown_response.cache_ttl'));
    }

    public function testLoadSetsCacheServiceParameter(): void
    {
        $this->extension->load([['cache_service' => 'cache.custom']], $this->container);

        self::assertSame('cache.custom', $this->container->getParameter('symfony_markdown_response.cache_service'));
    }

    public function testLoadSetsCustomAiUserAgents(): void
    {
        $agents = ['CustomBot', 'AnotherBot'];
        $this->extension->load([['ai_user_agents' => $agents]], $this->container);

        self::assertSame($agents, $this->container->getParameter('symfony_markdown_response.ai_user_agents'));
    }

    public function testRegistersAutoconfigurationForHtmlPreprocessorInterface(): void
    {
        $this->extension->load([[]], $this->container);

        $autoconfiguredInstanceOfs = $this->container->getAutoconfiguredInstanceof();

        self::assertArrayHasKey(HtmlPreprocessorInterface::class, $autoconfiguredInstanceOfs);

        $childDef = $autoconfiguredInstanceOfs[HtmlPreprocessorInterface::class];
        $tags     = $childDef->getTags();

        self::assertArrayHasKey('symfony_markdown_response.html_preprocessor', $tags);
    }

    public function testWiresFrontMatterGeneratorByDefault(): void
    {
        $this->extension->load([[]], $this->container);

        self::assertTrue($this->container->getParameter('symfony_markdown_response.front_matter'));
        self::assertTrue($this->container->hasDefinition(FrontMatterGenerator::class));

        $argument = $this->container->getDefinition(MarkdownResponseSubscriber::class)->getArgument('$frontMatter');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(FrontMatterGenerator::class, (string) $argument);
    }

    public function testDisablesFrontMatterGenerator(): void
    {
        $this->extension->load([['front_matter' => false]], $this->container);

        self::assertFalse($this->container->getParameter('symfony_markdown_response.front_matter'));
        self::assertNull($this->container->getDefinition(MarkdownResponseSubscriber::class)->getArgument('$frontMatter'));
    }

    public function testWiresAbsoluteUrlRewriterByDefault(): void
    {
        $this->extension->load([[]], $this->container);

        self::assertTrue($this->container->getParameter('symfony_markdown_response.absolute_urls'));

        $argument = $this->container->getDefinition(MarkdownResponseSubscriber::class)->getArgument('$urlRewriter');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(AbsoluteUrlRewriter::class, (string) $argument);
    }

    public function testDisablesAbsoluteUrlRewriter(): void
    {
        $this->extension->load([['absolute_urls' => false]], $this->container);

        self::assertFalse($this->container->getParameter('symfony_markdown_response.absolute_urls'));
        self::assertNull($this->container->getDefinition(MarkdownResponseSubscriber::class)->getArgument('$urlRewriter'));
    }

    public function testExtensionAlias(): void
    {
        self::assertSame('symfony_markdown_response', $this->extension->getAlias());
    }

    public function testMergesMultipleConfigs(): void
    {
        $this->extension->load([
            ['cache_ttl' => 1800],
            ['cache_enabled' => false],
        ], $this->container);

        self::assertSame(1800, $this->container->getParameter('symfony_markdown_response.cache_ttl'));
        self::assertFalse($this->container->getParameter('symfony_markdown_response.cache_enabled'));
    }
}
