<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Html;

/**
 * Rewrites relative link and image URLs to absolute ones, so links in the
 * Markdown still work once an agent reads it outside the page's context.
 *
 * Resolution follows RFC 3986 section 5, honouring a <base href> element.
 */
readonly class AbsoluteUrlRewriter
{
    /** Matches an <a> or <img> start tag, allowing ">" inside quoted attribute values. */
    private const TAG_PATTERN = '/<(?:a|img)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';

    /** Matches an href or src attribute with a double-quoted, single-quoted or unquoted value. */
    private const ATTRIBUTE_PATTERN = '/(?<prefix>\s(?<name>href|src)\s*=\s*)(?:"(?<dq>[^"]*)"|\'(?<sq>[^\']*)\'|(?<uq>[^\s"\'=<>`]+))/i';

    private const BASE_PATTERN = '/<base\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';

    public function rewrite(string $html, string $pageUrl): string
    {
        $base = $this->resolveBase($html, $pageUrl);

        return (string) preg_replace_callback(
            self::TAG_PATTERN,
            fn (array $tag): string => (string) preg_replace_callback(
                self::ATTRIBUTE_PATTERN,
                fn (array $attribute): string => $attribute['prefix'] . '"' . $this->rewriteValue($this->valueOf($attribute), $base) . '"',
                $tag[0],
                flags: PREG_UNMATCHED_AS_NULL,
            ),
            $html,
        );
    }

    /** Resolves a reference against an absolute base URL (RFC 3986 section 5.2). */
    public function resolve(string $reference, string $base): string
    {
        $reference = trim($reference);

        if ($reference === '' || preg_match('/^[a-z][a-z0-9+.\-]*:/i', $reference) === 1) {
            return $reference;
        }

        $parts = parse_url($base);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $reference;
        }

        $scheme = $parts['scheme'];
        if (str_starts_with($reference, '//')) {
            return $scheme . ':' . $reference;
        }

        $authority = $scheme . '://'
            . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
            . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $basePath  = $parts['path'] ?? '';
        $baseQuery = isset($parts['query']) ? '?' . $parts['query'] : '';

        if (str_starts_with($reference, '#')) {
            return $authority . ($basePath === '' ? '/' : $basePath) . $baseQuery . $reference;
        }

        if (str_starts_with($reference, '?')) {
            return $authority . ($basePath === '' ? '/' : $basePath) . $reference;
        }

        $suffix = '';
        if (preg_match('/^([^?#]*)(.*)$/s', $reference, $match) === 1) {
            [, $path, $suffix] = $match;
        } else {
            $path = $reference;
        }

        if (! str_starts_with($path, '/')) {
            $directory = $basePath === '' ? '/' : substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
            $path      = $directory . $path;
        }

        return $authority . $this->removeDotSegments($path) . $suffix;
    }

    private function resolveBase(string $html, string $pageUrl): string
    {
        if (preg_match(self::BASE_PATTERN, $html, $tag) !== 1) {
            return $pageUrl;
        }

        if (preg_match(self::ATTRIBUTE_PATTERN, $tag[0], $attribute, PREG_UNMATCHED_AS_NULL) !== 1 || strtolower((string) $attribute['name']) !== 'href') {
            return $pageUrl;
        }

        $href     = html_entity_decode($this->valueOf($attribute), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $resolved = $this->resolve($href, $pageUrl);

        return preg_match('#^https?://#i', $resolved) === 1 ? $resolved : $pageUrl;
    }

    /** @param array<int|string, string|null> $attribute */
    private function valueOf(array $attribute): string
    {
        return $attribute['dq'] ?? $attribute['sq'] ?? $attribute['uq'] ?? '';
    }

    private function rewriteValue(string $value, string $base): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($this->resolve($decoded, $base), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function removeDotSegments(string $path): string
    {
        $output = [];
        $segments = explode('/', $path);
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($segment === '.' || $segment === '..') {
                if ($segment === '..' && count($output) > 1) {
                    array_pop($output);
                }
                if ($index === $last) {
                    $output[] = '';
                }
                continue;
            }

            $output[] = $segment;
        }

        $result = implode('/', $output);

        return str_starts_with($result, '/') ? $result : '/' . $result;
    }
}
