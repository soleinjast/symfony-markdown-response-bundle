<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class ProvideMarkdownResponse
{
    public function __construct(
        public bool $enabled = true,
    ) {}
}