<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;

readonly class AgentDetector
{
    /** @param string[] $aiUserAgents */
    public function __construct(
        private array $aiUserAgents,
    ) {}

    public function wantsMarkdown(Request $request): bool
    {
        return $this->hasMarkdownAcceptHeader($request)
            || $this->hasMarkdownUrlSuffix($request)
            || $this->hasMarkdownAttribute($request)
            || $this->isAiUserAgent($request);
    }

    private function hasMarkdownAcceptHeader(Request $request): bool
    {
        $accept   = AcceptHeader::fromString($request->headers->get('Accept'));
        $markdown = $this->qualityOf($accept, 'text/markdown');

        return $markdown > 0 && $markdown >= $this->qualityOf($accept, 'text/html');
    }

    private function qualityOf(AcceptHeader $accept, string $mediaType): float
    {
        foreach ($accept->all() as $item) {
            if (strtolower($item->getValue()) === $mediaType) {
                return $item->getQuality();
            }
        }

        return 0.0;
    }

    private function hasMarkdownUrlSuffix(Request $request): bool
    {
        return str_ends_with($request->getPathInfo(), '.md');
    }

    private function hasMarkdownAttribute(Request $request): bool
    {
        return (bool) $request->attributes->get('_wants_markdown', false);
    }

    private function isAiUserAgent(Request $request): bool
    {
        $userAgent = $request->headers->get('User-Agent', '');

        foreach ($this->aiUserAgents as $pattern) {
            if (str_contains((string) $userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }
}