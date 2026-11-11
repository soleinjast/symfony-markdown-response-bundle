<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_AI_USER_AGENTS = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-SearchBot',
        'Claude-User',
        'anthropic-ai',
        'Claude-Web',
        'PerplexityBot',
        'Perplexity-User',
        'meta-externalagent',
        'meta-externalfetcher',
        'MistralAI-User',
        'DuckAssistBot',
        'Amazonbot',
        'Bytespider',
        'CCBot',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfony_markdown_response');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->enumNode('driver')
            ->values(['local', 'cloudflare'])
            ->defaultValue('local')
            ->info('Conversion driver: "local" uses league/html-to-markdown, "cloudflare" uses Cloudflare Workers AI.')
            ->end()
            ->scalarNode('cloudflare_endpoint')
            ->defaultNull()
            ->info('Cloudflare Workers AI endpoint URL (required when driver is "cloudflare").')
            ->end()
            ->floatNode('cloudflare_timeout')
            ->defaultValue(5.0)
            ->min(0.1)
            ->info('Seconds to wait for the Cloudflare endpoint before giving up.')
            ->end()
            ->booleanNode('cloudflare_fallback')
            ->defaultTrue()
            ->info('Convert with the local driver when the Cloudflare endpoint fails or times out.')
            ->end()
            ->booleanNode('cache_enabled')
            ->defaultTrue()
            ->info('Cache converted markdown responses.')
            ->end()
            ->integerNode('cache_ttl')
            ->defaultValue(3600)
            ->info('Cache TTL in seconds.')
            ->end()
            ->scalarNode('cache_service')
            ->defaultNull()
            ->info('Service ID of a PSR-6 cache pool. Defaults to cache.app when available.')
            ->end()
            ->arrayNode('ai_user_agents')
            ->scalarPrototype()->end()
            ->defaultValue(self::DEFAULT_AI_USER_AGENTS)
            ->info('User agent substrings (case-insensitive) that trigger markdown responses.')
            ->end()
            ->end();

        return $treeBuilder;
    }
}