<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection;

use Soleinjast\SymfonyMarkdownResponseBundle\Converter\CloudflareConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\LocalConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MarkdownResponsePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->wireDriver($container);
        $this->wireCache($container);
    }

    private function wireDriver(ContainerBuilder $container): void
    {
        $driver = $container->getParameter('symfony_markdown_response.driver');

        $driverServiceId = match ($driver) {
            'cloudflare' => CloudflareConverter::class,
            default      => LocalConverter::class,
        };

        $container->setAlias('symfony_markdown_response.driver', $driverServiceId);
    }

    private function wireCache(ContainerBuilder $container): void
    {
        if (! $container->getParameter('symfony_markdown_response.cache_enabled')) {
            return;
        }

        $cacheServiceId = $container->getParameter('symfony_markdown_response.cache_service')
            ?? $this->resolveDefaultCachePool($container);

        if ($cacheServiceId !== null && $container->has($cacheServiceId)) {
            $container->getDefinition(MarkdownConverter::class)
                ->replaceArgument('$cache', new Reference($cacheServiceId));
        }
    }

    private function resolveDefaultCachePool(ContainerBuilder $container): ?string
    {
        foreach (['cache.app', 'cache.system'] as $candidate) {
            if ($container->has($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
