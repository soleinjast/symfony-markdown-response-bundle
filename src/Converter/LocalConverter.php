<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Converter;

use League\HTMLToMarkdown\HtmlConverter;

class LocalConverter implements ConverterInterface
{
    private readonly HtmlConverter $converter;
    public function __construct()
    {
        $this->converter = new HtmlConverter([
            'strip_tags'   => true,
            'remove_nodes' => 'head script style nav footer aside',
        ]);
    }

    public function convert(string $html): string
    {
        return $this->converter->convert($html);
    }
}