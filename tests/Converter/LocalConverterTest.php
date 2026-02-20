<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\Converter;

use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\ConverterInterface;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\LocalConverter;

class LocalConverterTest extends TestCase
{
    private LocalConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new LocalConverter();
    }

    public function testImplementsConverterInterface(): void
    {
        self::assertInstanceOf(ConverterInterface::class, $this->converter);
    }

    public function testConvertsHeadingToMarkdown(): void
    {
        $result = $this->converter->convert('<h1>Hello World</h1>');

        self::assertStringContainsString('Hello World', $result);
        // league/html-to-markdown uses setext style by default (=== underline) for h1
        self::assertMatchesRegularExpression('/Hello World\s*\n[=\-]+|# Hello World/', $result);
    }

    public function testConvertsParagraphToMarkdown(): void
    {
        $result = $this->converter->convert('<p>Some text here.</p>');

        self::assertStringContainsString('Some text here.', $result);
    }

    public function testConvertsLinkToMarkdown(): void
    {
        $result = $this->converter->convert('<a href="https://example.com">Click here</a>');

        self::assertStringContainsString('Click here', $result);
        self::assertStringContainsString('https://example.com', $result);
    }

    public function testStripsTags(): void
    {
        $html   = '<html><head><title>Page</title></head><body><p>Content</p></body></html>';
        $result = $this->converter->convert($html);

        self::assertStringNotContainsString('<html>', $result);
        self::assertStringNotContainsString('<head>', $result);
        self::assertStringNotContainsString('<title>', $result);
        self::assertStringContainsString('Content', $result);
    }

    public function testStripsScriptTags(): void
    {
        $html   = '<body><p>Text</p><script>alert("xss")</script></body>';
        $result = $this->converter->convert($html);

        self::assertStringNotContainsString('alert', $result);
        self::assertStringContainsString('Text', $result);
    }

    public function testStripsStyleTags(): void
    {
        $html   = '<body><style>body { color: red; }</style><p>Text</p></body>';
        $result = $this->converter->convert($html);

        self::assertStringNotContainsString('color: red', $result);
        self::assertStringContainsString('Text', $result);
    }

    public function testStripsNavTags(): void
    {
        $html   = '<body><nav><a href="/">Home</a></nav><p>Main content</p></body>';
        $result = $this->converter->convert($html);

        self::assertStringNotContainsString('<nav>', $result);
        self::assertStringContainsString('Main content', $result);
    }

    public function testStripsFooterTags(): void
    {
        $html   = '<body><p>Content</p><footer><p>Footer text</p></footer></body>';
        $result = $this->converter->convert($html);

        self::assertStringContainsString('Content', $result);
        self::assertStringNotContainsString('Footer text', $result);
    }

    public function testStripsAsideTags(): void
    {
        $html   = '<body><p>Content</p><aside><p>Sidebar</p></aside></body>';
        $result = $this->converter->convert($html);

        self::assertStringContainsString('Content', $result);
        self::assertStringNotContainsString('Sidebar', $result);
    }

    public function testConvertsStrongToMarkdown(): void
    {
        $result = $this->converter->convert('<strong>Bold text</strong>');

        self::assertStringContainsString('Bold text', $result);
        self::assertStringContainsString('**', $result);
    }

    public function testConvertsEmToMarkdown(): void
    {
        $result = $this->converter->convert('<em>Italic text</em>');

        self::assertStringContainsString('Italic text', $result);
    }

    public function testConvertsUnorderedListToMarkdown(): void
    {
        $html   = '<ul><li>Item one</li><li>Item two</li></ul>';
        $result = $this->converter->convert($html);

        self::assertStringContainsString('Item one', $result);
        self::assertStringContainsString('Item two', $result);
        // league/html-to-markdown uses '-' bullets by default
        self::assertMatchesRegularExpression('/[-*]\s+Item one/', $result);
    }

    public function testConvertsOrderedListToMarkdown(): void
    {
        $html   = '<ol><li>First</li><li>Second</li></ol>';
        $result = $this->converter->convert($html);

        self::assertStringContainsString('First', $result);
        self::assertStringContainsString('Second', $result);
    }

    public function testConvertsCodeToMarkdown(): void
    {
        $result = $this->converter->convert('<code>echo "hello";</code>');

        self::assertStringContainsString('echo "hello";', $result);
        self::assertStringContainsString('`', $result);
    }

    public function testReturnsStringForEmptyInput(): void
    {
        $result = $this->converter->convert('');

        self::assertIsString($result);
    }

    public function testConvertsH2ToMarkdown(): void
    {
        $result = $this->converter->convert('<h2>Subheading</h2>');

        self::assertStringContainsString('Subheading', $result);
        // league/html-to-markdown uses setext style (--- underline) for h2 by default
        self::assertMatchesRegularExpression('/Subheading\s*\n[-]+|## Subheading/', $result);
    }
}
