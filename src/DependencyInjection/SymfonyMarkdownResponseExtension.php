<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection;

use Soleinjast\SymfonyMarkdownResponseBundle\Converter\HtmlPreprocessorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class SymfonyMarkdownResponseExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../config'));
        $loader->load('services.php');

        $container->setParameter('symfony_markdown_response.driver',              $config['driver']);
        $container->setParameter('symfony_markdown_response.cloudflare_endpoint', $config['cloudflare_endpoint']);
        $container->setParameter('symfony_markdown_response.cache_enabled',       $config['cache_enabled']);
        $container->setParameter('symfony_markdown_response.cache_ttl',           $config['cache_ttl']);
        $container->setParameter('symfony_markdown_response.cache_service',       $config['cache_service']);
        $container->setParameter('symfony_markdown_response.ai_user_agents',      $config['ai_user_agents']);

        $container->registerForAutoconfiguration(HtmlPreprocessorInterface::class)
            ->addTag('symfony_markdown_response.html_preprocessor');
    }
}