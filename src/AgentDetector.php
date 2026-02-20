<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle;

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
        return str_contains((string) $request->headers->get('Accept', ''), 'text/markdown');
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