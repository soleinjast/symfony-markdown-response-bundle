<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Converter;

interface ConverterInterface
{
    public function convert(string $html): string;
}