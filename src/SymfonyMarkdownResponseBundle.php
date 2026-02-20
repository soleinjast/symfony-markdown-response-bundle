<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle;

use Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection\MarkdownResponsePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyMarkdownResponseBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new MarkdownResponsePass());
    }
}