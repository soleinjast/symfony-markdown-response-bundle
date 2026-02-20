<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor     = new Processor();
        $this->configuration = new Configuration();
    }

    public function testImplementsConfigurationInterface(): void
    {
        self::assertInstanceOf(ConfigurationInterface::class, $this->configuration);
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processConfig([]);

        self::assertSame('local', $config['driver']);
        self::assertNull($config['cloudflare_endpoint']);
        self::assertTrue($config['cache_enabled']);
        self::assertSame(3600, $config['cache_ttl']);
        self::assertNull($config['cache_service']);
        self::assertIsArray($config['ai_user_agents']);
        self::assertNotEmpty($config['ai_user_agents']);
    }

    public function testDefaultAiUserAgents(): void
    {
        $config = $this->processConfig([]);

        self::assertContains('GPTBot', $config['ai_user_agents']);
        self::assertContains('ChatGPT-User', $config['ai_user_agents']);
        self::assertContains('CCBot', $config['ai_user_agents']);
        self::assertContains('anthropic-ai', $config['ai_user_agents']);
        self::assertContains('Claude-Web', $config['ai_user_agents']);
        self::assertContains('ClaudeBot', $config['ai_user_agents']);
        self::assertContains('PerplexityBot', $config['ai_user_agents']);
    }

    public function testCanSetDriverToLocal(): void
    {
        $config = $this->processConfig(['driver' => 'local']);

        self::assertSame('local', $config['driver']);
    }

    public function testCanSetDriverToCloudflare(): void
    {
        $config = $this->processConfig(['driver' => 'cloudflare']);

        self::assertSame('cloudflare', $config['driver']);
    }

    public function testInvalidDriverThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig(['driver' => 'invalid_driver']);
    }

    public function testCanSetCloudflareEndpoint(): void
    {
        $endpoint = 'https://api.cloudflare.example/convert';
        $config   = $this->processConfig(['cloudflare_endpoint' => $endpoint]);

        self::assertSame($endpoint, $config['cloudflare_endpoint']);
    }

    public function testCanDisableCache(): void
    {
        $config = $this->processConfig(['cache_enabled' => false]);

        self::assertFalse($config['cache_enabled']);
    }

    public function testCanSetCustomCacheTtl(): void
    {
        $config = $this->processConfig(['cache_ttl' => 7200]);

        self::assertSame(7200, $config['cache_ttl']);
    }

    public function testCanSetCustomCacheService(): void
    {
        $config = $this->processConfig(['cache_service' => 'cache.my_pool']);

        self::assertSame('cache.my_pool', $config['cache_service']);
    }

    public function testCanOverrideAiUserAgents(): void
    {
        $agents = ['MyBot', 'AnotherBot'];
        $config = $this->processConfig(['ai_user_agents' => $agents]);

        self::assertSame($agents, $config['ai_user_agents']);
    }

    public function testCanSetEmptyAiUserAgents(): void
    {
        $config = $this->processConfig(['ai_user_agents' => []]);

        self::assertSame([], $config['ai_user_agents']);
    }

    public function testConfigTreeBuilderRootNodeIsCorrect(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        self::assertSame('symfony_markdown_response', $treeBuilder->getRootNode()->getNode(true)->getName());
    }

    public function testMultipleConfigsMergeCorrectly(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['cache_ttl' => 1800],
            ['cache_enabled' => false],
        ]);

        self::assertSame(1800, $config['cache_ttl']);
        self::assertFalse($config['cache_enabled']);
    }

    private function processConfig(array $config): array
    {
        return $this->processor->processConfiguration($this->configuration, [$config]);
    }
}
