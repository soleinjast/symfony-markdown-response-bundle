<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Converter;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

readonly class FallbackConverter implements ConverterInterface
{
    public function __construct(
        private ConverterInterface $primary,
        private ConverterInterface $fallback,
        private ?LoggerInterface   $logger = null,
    ) {}

    public function convert(string $html): string
    {
        try {
            return $this->primary->convert($html);
        } catch (RuntimeException|HttpClientExceptionInterface $exception) {
            $this->logger?->warning('Markdown conversion failed with {primary}, falling back to {fallback}: {message}', [
                'primary'   => $this->primary::class,
                'fallback'  => $this->fallback::class,
                'message'   => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $this->fallback->convert($html);
        }
    }
}
