<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Symfony\Component\HttpFoundation\Request;

class AgentDetectorTest extends TestCase
{
    private AgentDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new AgentDetector([
            'GPTBot',
            'ChatGPT-User',
            'CCBot',
            'anthropic-ai',
            'Claude-Web',
            'ClaudeBot',
            'PerplexityBot',
        ]);
    }

    public function testWantsMarkdownViaAcceptHeader(): void
    {
        $request = Request::create('/page');
        $request->headers->set('Accept', 'text/markdown, */*');

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    public function testDoesNotWantMarkdownWithHtmlAcceptHeader(): void
    {
        $request = Request::create('/page');
        $request->headers->set('Accept', 'text/html,application/xhtml+xml');

        self::assertFalse($this->detector->wantsMarkdown($request));
    }

    public function testWantsMarkdownViaMdUrlSuffix(): void
    {
        $request = Request::create('/page.md');

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    public function testDoesNotWantMarkdownForNonMdUrl(): void
    {
        $request = Request::create('/page.html');

        self::assertFalse($this->detector->wantsMarkdown($request));
    }

    #[DataProvider('aiUserAgentProvider')]
    public function testWantsMarkdownViaKnownAiUserAgent(string $userAgent): void
    {
        $request = Request::create('/page');
        $request->headers->set('User-Agent', $userAgent);

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    public static function aiUserAgentProvider(): array
    {
        return [
            'GPTBot'        => ['GPTBot/1.0'],
            'ChatGPT-User'  => ['ChatGPT-User/1.0'],
            'CCBot'         => ['CCBot/2.0 (https://commoncrawl.org/faq/)'],
            'anthropic-ai'  => ['anthropic-ai/1.0'],
            'Claude-Web'    => ['Claude-Web/1.0'],
            'ClaudeBot'     => ['ClaudeBot/0.1'],
            'PerplexityBot' => ['PerplexityBot/1.0'],
        ];
    }

    public function testDoesNotWantMarkdownForUnknownUserAgent(): void
    {
        $request = Request::create('/page');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)');

        self::assertFalse($this->detector->wantsMarkdown($request));
    }

    public function testWantsMarkdownWhenMultipleConditionsMatch(): void
    {
        $request = Request::create('/page.md');
        $request->headers->set('Accept', 'text/markdown');
        $request->headers->set('User-Agent', 'GPTBot/1.0');

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    public function testEmptyUserAgentListNeverMatchesAgent(): void
    {
        $detector = new AgentDetector([]);
        $request  = Request::create('/page');
        $request->headers->set('User-Agent', 'GPTBot/1.0');

        self::assertFalse($detector->wantsMarkdown($request));
    }

    public function testUserAgentPartialMatch(): void
    {
        $request = Request::create('/page');
        $request->headers->set('User-Agent', 'MyCustomClaudeBotWrapper/2.0');

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    public function testRequestWithNoHeadersAndNormalUrl(): void
    {
        $request = Request::create('/about');

        self::assertFalse($this->detector->wantsMarkdown($request));
    }

    public function testWantsMarkdownViaWantsMarkdownAttribute(): void
    {
        $request = Request::create('/page');
        $request->attributes->set('_wants_markdown', true);

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    public function testDoesNotWantMarkdownWhenAttributeIsFalse(): void
    {
        $request = Request::create('/page');
        $request->attributes->set('_wants_markdown', false);

        self::assertFalse($this->detector->wantsMarkdown($request));
    }

    public function testDoesNotWantMarkdownWhenAttributeIsAbsent(): void
    {
        $request = Request::create('/page');

        self::assertFalse($this->detector->wantsMarkdown($request));
    }
}
