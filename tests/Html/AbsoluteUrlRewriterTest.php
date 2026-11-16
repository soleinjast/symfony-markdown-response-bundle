<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\Html\AbsoluteUrlRewriter;

class AbsoluteUrlRewriterTest extends TestCase
{
    private AbsoluteUrlRewriter $rewriter;

    protected function setUp(): void
    {
        $this->rewriter = new AbsoluteUrlRewriter();
    }

    /**
     * The normal and abnormal examples from RFC 3986 section 5.4.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function rfc3986Examples(): iterable
    {
        $examples = [
            'g:h'           => 'g:h',
            'g'             => 'http://a/b/c/g',
            './g'           => 'http://a/b/c/g',
            'g/'            => 'http://a/b/c/g/',
            '/g'            => 'http://a/g',
            '//g'           => 'http://g',
            '?y'            => 'http://a/b/c/d;p?y',
            'g?y'           => 'http://a/b/c/g?y',
            '#s'            => 'http://a/b/c/d;p?q#s',
            'g#s'           => 'http://a/b/c/g#s',
            'g?y#s'         => 'http://a/b/c/g?y#s',
            ';x'            => 'http://a/b/c/;x',
            'g;x'           => 'http://a/b/c/g;x',
            '.'             => 'http://a/b/c/',
            './'            => 'http://a/b/c/',
            '..'            => 'http://a/b/',
            '../'           => 'http://a/b/',
            '../g'          => 'http://a/b/g',
            '../..'         => 'http://a/',
            '../../'        => 'http://a/',
            '../../g'       => 'http://a/g',
            '../../../g'    => 'http://a/g',
            '../../../../g' => 'http://a/g',
            '/./g'          => 'http://a/g',
            '/../g'         => 'http://a/g',
            'g.'            => 'http://a/b/c/g.',
            '.g'            => 'http://a/b/c/.g',
            'g..'           => 'http://a/b/c/g..',
            '..g'           => 'http://a/b/c/..g',
            './../g'        => 'http://a/b/g',
            './g/.'         => 'http://a/b/c/g/',
            'g/./h'         => 'http://a/b/c/g/h',
            'g/../h'        => 'http://a/b/c/h',
            'g;x=1/./y'     => 'http://a/b/c/g;x=1/y',
            'g;x=1/../y'    => 'http://a/b/c/y',
            'g?y/./x'       => 'http://a/b/c/g?y/./x',
            'g#s/../x'      => 'http://a/b/c/g#s/../x',
        ];

        foreach ($examples as $reference => $expected) {
            yield $reference => [$reference, $expected];
        }
    }

    #[DataProvider('rfc3986Examples')]
    public function testResolvesRfc3986Examples(string $reference, string $expected): void
    {
        self::assertSame($expected, $this->rewriter->resolve($reference, 'http://a/b/c/d;p?q'));
    }

    public function testResolvesAgainstHostWithoutPath(): void
    {
        self::assertSame('https://example.com/docs', $this->rewriter->resolve('docs', 'https://example.com'));
        self::assertSame('https://example.com/#top', $this->rewriter->resolve('#top', 'https://example.com'));
    }

    public function testKeepsPortAndCredentials(): void
    {
        self::assertSame('http://u:p@example.com:8080/x', $this->rewriter->resolve('/x', 'http://u:p@example.com:8080/a/b'));
    }

    public function testRewritesRelativeLinkAndImageUrls(): void
    {
        $html = '<p><a href="/docs/setup">Setup</a> <a href="faq">FAQ</a> <img src="../img/logo.png" alt="Logo"></p>';

        $result = $this->rewriter->rewrite($html, 'https://example.com/docs/guide/install');

        self::assertSame(
            '<p><a href="https://example.com/docs/setup">Setup</a> <a href="https://example.com/docs/guide/faq">FAQ</a> <img src="https://example.com/docs/img/logo.png" alt="Logo"></p>',
            $result,
        );
    }

    public function testLeavesAbsoluteAndNonHttpUrlsUnchanged(): void
    {
        $html = '<a href="https://other.test/x">A</a>'
            . '<a href="mailto:hi@example.com">B</a>'
            . '<a href="tel:+123">C</a>'
            . '<img src="data:image/png;base64,AAAA">';

        self::assertSame($html, $this->rewriter->rewrite($html, 'https://example.com/page'));
    }

    public function testResolvesProtocolRelativeUrlsWithPageScheme(): void
    {
        $result = $this->rewriter->rewrite('<img src="//cdn.example.com/a.png">', 'https://example.com/page');

        self::assertSame('<img src="https://cdn.example.com/a.png">', $result);
    }

    public function testResolvesFragmentAgainstPageUrl(): void
    {
        $result = $this->rewriter->rewrite('<a href="#usage">Usage</a>', 'https://example.com/docs?v=2');

        self::assertSame('<a href="https://example.com/docs?v=2#usage">Usage</a>', $result);
    }

    public function testHandlesSingleQuotedAndUnquotedAttributes(): void
    {
        $result = $this->rewriter->rewrite("<a href='a'>A</a><a href=b>B</a><a HREF = \"c\">C</a>", 'https://example.com/x/');

        self::assertSame(
            '<a href="https://example.com/x/a">A</a><a href="https://example.com/x/b">B</a><a HREF = "https://example.com/x/c">C</a>',
            $result,
        );
    }

    public function testHandlesGreaterThanInsideOtherAttributes(): void
    {
        $result = $this->rewriter->rewrite('<a title="a > b" href="/x">X</a>', 'https://example.com/');

        self::assertSame('<a title="a > b" href="https://example.com/x">X</a>', $result);
    }

    public function testKeepsEntityEncodingInQueryStrings(): void
    {
        $result = $this->rewriter->rewrite('<a href="/search?a=1&amp;b=2">S</a>', 'https://example.com/');

        self::assertSame('<a href="https://example.com/search?a=1&amp;b=2">S</a>', $result);
    }

    public function testEscapesQuotesFromPageUrl(): void
    {
        $result = $this->rewriter->rewrite('<a href="#x">X</a>', 'https://example.com/p?q="a"&r=1');

        self::assertSame('<a href="https://example.com/p?q=&quot;a&quot;&amp;r=1#x">X</a>', $result);
    }

    public function testDoesNotTouchOtherTagsOrAttributes(): void
    {
        $html = '<link href="/style.css"><script src="/app.js"></script><div data-href="/x"><p>href="/y"</p></div>';

        self::assertSame($html, $this->rewriter->rewrite($html, 'https://example.com/'));
    }

    public function testDoesNotConfuseDataAttributesWithHref(): void
    {
        $result = $this->rewriter->rewrite('<a data-href="/raw" href="/x">X</a>', 'https://example.com/');

        self::assertSame('<a data-href="/raw" href="https://example.com/x">X</a>', $result);
    }

    public function testLeavesEmptyHrefUnchanged(): void
    {
        self::assertSame('<a href="">X</a>', $this->rewriter->rewrite('<a href="">X</a>', 'https://example.com/'));
    }

    public function testHonoursBaseElement(): void
    {
        $html = '<head><base href="/docs/v2/"></head><body><a href="setup">Setup</a></body>';

        $result = $this->rewriter->rewrite($html, 'https://example.com/anything/else');

        self::assertStringContainsString('<a href="https://example.com/docs/v2/setup">', $result);
    }

    public function testIgnoresNonHttpBaseElement(): void
    {
        $html = '<base href="javascript:void(0)"><a href="setup">Setup</a>';

        $result = $this->rewriter->rewrite($html, 'https://example.com/docs/page');

        self::assertStringContainsString('<a href="https://example.com/docs/setup">', $result);
    }

    public function testIgnoresBaseElementWithoutHref(): void
    {
        $html = '<base target="_blank"><a href="setup">Setup</a>';

        $result = $this->rewriter->rewrite($html, 'https://example.com/docs/page');

        self::assertStringContainsString('<a href="https://example.com/docs/setup">', $result);
    }
}
