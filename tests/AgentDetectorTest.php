<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Soleinjast\SymfonyMarkdownResponseBundle\DependencyInjection\Configuration;
use Symfony\Component\HttpFoundation\Request;

class AgentDetectorTest extends TestCase
{
    private AgentDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new AgentDetector(Configuration::DEFAULT_AI_USER_AGENTS);
    }

    public function testWantsMarkdownViaAcceptHeader(): void
    {
        $request = Request::create('/page');
        $request->headers->set('Accept', 'text/markdown, */*');

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    #[DataProvider('acceptHeaderProvider')]
    public function testAcceptHeaderRespectsQualityValues(string $accept, bool $expected): void
    {
        $request = Request::create('/page');
        $request->headers->set('Accept', $accept);

        self::assertSame($expected, $this->detector->wantsMarkdown($request));
    }

    /** @return array<string, array{string, bool}> */
    public static function acceptHeaderProvider(): array
    {
        return [
            'markdown only'                    => ['text/markdown', true],
            'markdown with params'             => ['text/markdown; charset=UTF-8', true],
            'markdown uppercase'               => ['TEXT/MARKDOWN', true],
            'markdown explicitly refused'      => ['text/markdown;q=0', false],
            'markdown refused, html wanted'    => ['text/html, text/markdown;q=0', false],
            'html preferred over markdown'     => ['text/html, text/markdown;q=0.5', false],
            'markdown preferred over html'     => ['text/markdown, text/html;q=0.9', true],
            'markdown and html equal'          => ['text/html, text/markdown', true],
            'markdown with low q, no html'     => ['text/markdown;q=0.1, */*;q=0.8', true],
            'browser default'                  => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false],
            'wildcard only'                    => ['*/*', false],
            'text wildcard only'               => ['text/*', false],
            'markdown-like but different type' => ['text/x-markdown-ish', false],
        ];
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

    /** @return array<string, array{string}> */
    public static function aiUserAgentProvider(): array
    {
        return [
            'GPTBot'               => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.1; +https://openai.com/gptbot)'],
            'OAI-SearchBot'        => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.3; +https://openai.com/searchbot'],
            'ChatGPT-User'         => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot'],
            'ClaudeBot'            => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            'Claude-SearchBot'     => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-SearchBot/1.0; +Claude-SearchBot@anthropic.com)'],
            'Claude-User'          => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +Claude-User@anthropic.com)'],
            'anthropic-ai'         => ['anthropic-ai/1.0'],
            'Claude-Web'           => ['Claude-Web/1.0'],
            'PerplexityBot'        => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)'],
            'Perplexity-User'      => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)'],
            'meta-externalagent'   => ['meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)'],
            'meta-externalfetcher' => ['meta-externalfetcher/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)'],
            'MistralAI-User'       => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; MistralAI-User/1.0; +https://docs.mistral.ai/robots)'],
            'DuckAssistBot'        => ['DuckAssistBot/1.2; (+http://duckduckgo.com/duckassistbot.html)'],
            'Amazonbot'            => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot) Chrome/119.0.6045.214 Safari/537.36'],
            'Bytespider'           => ['Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)'],
            'CCBot'                => ['CCBot/2.0 (https://commoncrawl.org/faq/)'],
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

    public function testEveryDefaultUserAgentHasARealisticTestCase(): void
    {
        $covered = array_keys(self::aiUserAgentProvider());

        self::assertSame(
            [],
            array_values(array_diff(Configuration::DEFAULT_AI_USER_AGENTS, $covered)),
            'Add a realistic User-Agent string to aiUserAgentProvider() for each default pattern.',
        );
    }

    public function testUserAgentMatchingIsCaseInsensitive(): void
    {
        $request = Request::create('/page');
        $request->headers->set('User-Agent', 'Meta-ExternalAgent/1.1');

        self::assertTrue($this->detector->wantsMarkdown($request));
    }

    #[DataProvider('regularUserAgentProvider')]
    public function testDoesNotMatchRegularBrowsersAndSearchEngines(string $userAgent): void
    {
        $request = Request::create('/page');
        $request->headers->set('User-Agent', $userAgent);

        self::assertFalse($this->detector->wantsMarkdown($request));
    }

    /** @return array<string, array{string}> */
    public static function regularUserAgentProvider(): array
    {
        return [
            'Chrome'     => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'],
            'Firefox'    => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0'],
            'Safari iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1'],
            'Googlebot'  => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'Bingbot'    => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
            'Applebot'   => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)'],
            'facebook link preview' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
        ];
    }

    public function testEmptyPatternNeverMatches(): void
    {
        $detector = new AgentDetector(['']);
        $request  = Request::create('/page');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh) Chrome/141.0.0.0');

        self::assertFalse($detector->wantsMarkdown($request));
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
