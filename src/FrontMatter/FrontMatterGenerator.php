<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\FrontMatter;

use DOMDocument;
use DOMElement;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds a YAML front matter block from the page's <head> metadata, which
 * is otherwise lost because <head> is stripped before conversion.
 */
readonly class FrontMatterGenerator
{
    public function generate(string $html, Request $request): string
    {
        $metadata = $this->extract($html);

        $fields = [
            'title'       => $metadata['title'] ?? $metadata['og:title'] ?? null,
            'description' => $metadata['description'] ?? $metadata['og:description'] ?? null,
            'url'         => $this->resolveUrl($metadata['canonical'] ?? null, $request),
            'lang'        => $metadata['lang'] ?? null,
        ];

        $lines = ['---'];
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = $key . ': ' . $this->quote($value);
            }
        }
        $lines[] = '---';

        return implode("\n", $lines) . "\n\n";
    }

    /** @return array<string, string> */
    private function extract(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the document as UTF-8.
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $metadata = [];

        $html = $document->getElementsByTagName('html')->item(0);
        if ($html instanceof DOMElement) {
            $this->set($metadata, 'lang', $html->getAttribute('lang'));
        }

        $title = $document->getElementsByTagName('title')->item(0);
        if ($title !== null) {
            $this->set($metadata, 'title', $title->textContent);
        }

        foreach ($document->getElementsByTagName('meta') as $meta) {
            $name = strtolower($meta->getAttribute('name') ?: $meta->getAttribute('property'));
            if (in_array($name, ['description', 'og:title', 'og:description'], true)) {
                $this->set($metadata, $name, $meta->getAttribute('content'));
            }
        }

        foreach ($document->getElementsByTagName('link') as $link) {
            $rels = preg_split('/\s+/', strtolower($link->getAttribute('rel'))) ?: [];
            if (in_array('canonical', $rels, true)) {
                $this->set($metadata, 'canonical', $link->getAttribute('href'));
            }
        }

        return $metadata;
    }

    /** @param array<string, string> $metadata */
    private function set(array &$metadata, string $key, string $value): void
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        // First occurrence wins, matching how browsers and crawlers read <head>.
        if ($value !== '' && ! isset($metadata[$key])) {
            $metadata[$key] = $value;
        }
    }

    private function resolveUrl(?string $canonical, Request $request): string
    {
        if ($canonical !== null) {
            if (preg_match('#^https?://#i', $canonical) === 1) {
                return $canonical;
            }

            if (str_starts_with($canonical, '/') && ! str_starts_with($canonical, '//')) {
                return $request->getSchemeAndHttpHost() . $canonical;
            }
        }

        return $request->getUri();
    }

    /**
     * A JSON string is a valid YAML double-quoted scalar, so this escapes
     * quotes, backslashes and control characters safely.
     */
    private function quote(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
