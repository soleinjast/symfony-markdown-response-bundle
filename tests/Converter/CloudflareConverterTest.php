<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\Converter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\CloudflareConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\ConverterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CloudflareConverterTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    public function testImplementsConverterInterface(): void
    {
        $converter = new CloudflareConverter($this->httpClient, 'https://example.com/convert');

        self::assertInstanceOf(ConverterInterface::class, $converter);
    }

    public function testThrowsExceptionWhenEndpointIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cloudflare_endpoint/');

        new CloudflareConverter($this->httpClient, '');
    }

    public function testConvertsHtmlSuccessfully(): void
    {
        $html     = '<h1>Hello</h1>';
        $markdown = '# Hello';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn($markdown);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/convert',
                self::callback(static fn(array $options): bool => isset($options['json']['html'])
                    && $options['json']['html'] === $html
                    && isset($options['headers']['Accept'])
                    && $options['headers']['Accept'] === 'text/plain'),
            )
            ->willReturn($response);

        $converter = new CloudflareConverter($this->httpClient, 'https://example.com/convert');
        $result    = $converter->convert($html);

        self::assertSame($markdown, $result);
    }

    public function testThrowsExceptionOnNon200Response(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $converter = new CloudflareConverter($this->httpClient, 'https://example.com/convert');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/500/');

        $converter->convert('<p>Hello</p>');
    }

    public function testThrowsExceptionOn404Response(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $converter = new CloudflareConverter($this->httpClient, 'https://example.com/convert');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');

        $converter->convert('<p>Hello</p>');
    }

    public function testSendsCorrectRequestFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('# Output');

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('POST', self::anything(), self::callback(static fn(array $options): bool => array_key_exists('json', $options)
                && array_key_exists('headers', $options)
                && $options['headers']['Accept'] === 'text/plain'))
            ->willReturn($response);

        $converter = new CloudflareConverter($this->httpClient, 'https://example.com/convert');
        $converter->convert('<p>Test</p>');
    }

    public function testEndpointUrlIsUsedInRequest(): void
    {
        $endpoint = 'https://api.cloudflare.example/markdown';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('Result');

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with('POST', $endpoint, self::anything())
            ->willReturn($response);

        $converter = new CloudflareConverter($this->httpClient, $endpoint);
        $converter->convert('<p>Test</p>');
    }
}
