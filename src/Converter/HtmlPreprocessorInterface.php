<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Converter;

interface HtmlPreprocessorInterface
{
    /**
     * Process HTML before it is converted to Markdown.
     */
    public function process(string $html): string;
}