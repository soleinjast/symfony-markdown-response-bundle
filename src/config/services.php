<?php

declare(strict_types=1);

use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\CloudflareConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\LocalConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\EventSubscriber\MarkdownResponseSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(LocalConverter::class);

    $services->set(CloudflareConverter::class)
        ->arg('$httpClient', service('http_client'))
        ->arg('$endpoint', param('symfony_markdown_response.cloudflare_endpoint'));

    $services->alias('symfony_markdown_response.driver', LocalConverter::class);

    $services->set(MarkdownConverter::class)
        ->arg('$driver', service('symfony_markdown_response.driver'))
        ->arg('$preprocessors', tagged_iterator('symfony_markdown_response.html_preprocessor'))
        ->arg('$cacheEnabled', param('symfony_markdown_response.cache_enabled'))
        ->arg('$cacheTtl', param('symfony_markdown_response.cache_ttl'))
        ->arg('$cache', null);

    $services->set(AgentDetector::class)
        ->arg('$aiUserAgents', param('symfony_markdown_response.ai_user_agents'));

    $services->set(MarkdownResponseSubscriber::class)
        ->autowire()
        ->tag('kernel.event_subscriber');
};