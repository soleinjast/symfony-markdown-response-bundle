<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Attribute;

use Attribute as PhpAttribute;

#[PhpAttribute(PhpAttribute::TARGET_CLASS | PhpAttribute::TARGET_METHOD)]
readonly class ProvideMarkdownResponse
{
    public function __construct(
        public bool $enabled = true,
    ) {}
}