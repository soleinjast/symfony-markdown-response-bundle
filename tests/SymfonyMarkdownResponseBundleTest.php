<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests;

use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection\MarkdownResponsePass;
use Soleinjast\SymfonyMarkdownResponseBundle\SymfonyMarkdownResponseBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyMarkdownResponseBundleTest extends TestCase
{
    private SymfonyMarkdownResponseBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new SymfonyMarkdownResponseBundle();
    }

    public function testExtendsBundle(): void
    {
        self::assertInstanceOf(Bundle::class, $this->bundle);
    }

    public function testBuildAddsMarkdownResponseCompilerPass(): void
    {
        $container = new ContainerBuilder();

        $this->bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $passClasses = array_map(get_class(...), $passes);
        self::assertContains(MarkdownResponsePass::class, $passClasses);
    }

    public function testBuildCanBeCalledMultipleTimes(): void
    {
        $container = new ContainerBuilder();

        $this->bundle->build($container);
        $this->bundle->build($container);

        // Should not throw — duplicate passes are allowed by Symfony
        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        self::assertNotEmpty($passes);
    }
}
