<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Converter;

use Psr\Cache\CacheItemPoolInterface;

readonly class MarkdownConverter
{
    /** @param iterable<HtmlPreprocessorInterface> $preprocessors */
    public function __construct(
        private ConverterInterface      $driver,
        private iterable                $preprocessors,
        private bool                    $cacheEnabled,
        private int                     $cacheTtl,
        private ?CacheItemPoolInterface $cache = null,
    ) {}

    public function convert(string $html): string
    {
        $html = $this->preprocess($html);

        if ($this->cacheEnabled && $this->cache instanceof CacheItemPoolInterface) {
            $key  = 'symfony_markdown_response_' . hash('xxh3', $html);
            $item = $this->cache->getItem($key);

            if ($item->isHit()) {
                return (string) $item->get();
            }

            $markdown = $this->driver->convert($html);

            $item->set($markdown)->expiresAfter($this->cacheTtl);
            $this->cache->save($item);

            return $markdown;
        }

        return $this->driver->convert($html);
    }

    private function preprocess(string $html): string
    {
        foreach ($this->preprocessors as $preprocessor) {
            $html = $preprocessor->process($html);
        }

        return $html;
    }
}