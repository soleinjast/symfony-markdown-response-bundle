<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\FrontMatter;

use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\FrontMatter\FrontMatterGenerator;
use Symfony\Component\HttpFoundation\Request;

class FrontMatterGeneratorTest extends TestCase
{
    private FrontMatterGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FrontMatterGenerator();
    }

    public function testGeneratesAllFieldsFromHead(): void
    {
        $html = '<!doctype html><html lang="en"><head>'
            . '<title>Getting Started</title>'
            . '<meta name="description" content="How to install the bundle.">'
            . '<link rel="canonical" href="https://example.com/docs/start">'
            . '</head><body><h1>Hi</h1></body></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/docs/start?ref=x'));

        self::assertSame(
            "---\n"
            . "title: \"Getting Started\"\n"
            . "description: \"How to install the bundle.\"\n"
            . "url: \"https://example.com/docs/start\"\n"
            . "lang: \"en\"\n"
            . "---\n\n",
            $result,
        );
    }

    public function testFallsBackToRequestUrlWithoutCanonical(): void
    {
        $result = $this->generator->generate('<p>Fragment</p>', Request::create('https://example.com/page?a=1'));

        self::assertSame("---\nurl: \"https://example.com/page?a=1\"\n---\n\n", $result);
    }

    public function testFallsBackToRequestUrlForEmptyHtml(): void
    {
        $result = $this->generator->generate('', Request::create('https://example.com/page'));

        self::assertSame("---\nurl: \"https://example.com/page\"\n---\n\n", $result);
    }

    public function testResolvesRootRelativeCanonicalAgainstRequestHost(): void
    {
        $html = '<html><head><link rel="canonical" href="/docs/start"></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/docs/start?ref=x'));

        self::assertStringContainsString('url: "https://example.com/docs/start"', $result);
    }

    public function testIgnoresUnresolvableCanonical(): void
    {
        $html = '<html><head><link rel="canonical" href="javascript:alert(1)"></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/page'));

        self::assertStringContainsString('url: "https://example.com/page"', $result);
    }

    public function testIgnoresProtocolRelativeCanonical(): void
    {
        $html = '<html><head><link rel="canonical" href="//evil.example/page"></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/page'));

        self::assertStringContainsString('url: "https://example.com/page"', $result);
    }

    public function testFallsBackToOpenGraphTitleAndDescription(): void
    {
        $html = '<html><head>'
            . '<meta property="og:title" content="OG Title">'
            . '<meta property="og:description" content="OG description">'
            . '</head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "OG Title"', $result);
        self::assertStringContainsString('description: "OG description"', $result);
    }

    public function testPrefersTitleTagOverOpenGraph(): void
    {
        $html = '<html><head><title>Real</title><meta property="og:title" content="OG"></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "Real"', $result);
        self::assertStringNotContainsString('OG', $result);
    }

    public function testMatchesMetaNameCaseInsensitively(): void
    {
        $html = '<html><head><meta name="Description" content="Mixed case"></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('description: "Mixed case"', $result);
    }

    public function testCollapsesWhitespace(): void
    {
        $html = "<html><head><title>\n   Multi\n   line   title  \n</title></head></html>";

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "Multi line title"', $result);
    }

    public function testEscapesQuotesAndBackslashes(): void
    {
        $html = '<html><head><title>Say "hi" \\ bye: now</title></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "Say \\"hi\\" \\\\ bye: now"', $result);
    }

    public function testKeepsUtf8Characters(): void
    {
        $html = '<html lang="fr"><head><title>Café — été</title></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "Café — été"', $result);
        self::assertStringContainsString('lang: "fr"', $result);
    }

    public function testDecodesHtmlEntities(): void
    {
        $html = '<html><head><title>Tom &amp; Jerry</title></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "Tom & Jerry"', $result);
    }

    public function testSkipsEmptyValues(): void
    {
        $html = '<html lang=""><head><title>   </title><meta name="description" content=""></head></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertSame("---\nurl: \"https://example.com/\"\n---\n\n", $result);
    }

    public function testUsesFirstTitleOnly(): void
    {
        $html = '<html><head><title>First</title></head><body><svg><title>Icon</title></svg></body></html>';

        $result = $this->generator->generate($html, Request::create('https://example.com/'));

        self::assertStringContainsString('title: "First"', $result);
        self::assertStringNotContainsString('Icon', $result);
    }
}
