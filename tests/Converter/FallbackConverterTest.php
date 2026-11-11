<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\Converter;

use Throwable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\ConverterInterface;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\FallbackConverter;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;

class FallbackConverterTest extends TestCase
{
    public function testImplementsConverterInterface(): void
    {
        $converter = new FallbackConverter($this->stub('# Primary'), $this->stub('# Fallback'));

        self::assertInstanceOf(ConverterInterface::class, $converter);
    }

    public function testUsesPrimaryWhenItSucceeds(): void
    {
        $fallback = $this->createMock(ConverterInterface::class);
        $fallback->expects(self::never())->method('convert');

        $converter = new FallbackConverter($this->stub('# Primary'), $fallback);

        self::assertSame('# Primary', $converter->convert('<h1>Hi</h1>'));
    }

    public function testFallsBackWhenPrimaryReturnsErrorStatus(): void
    {
        $converter = new FallbackConverter(
            $this->failing(new RuntimeException('Cloudflare converter returned HTTP 500.')),
            $this->stub('# Fallback'),
        );

        self::assertSame('# Fallback', $converter->convert('<h1>Hi</h1>'));
    }

    public function testFallsBackOnNetworkError(): void
    {
        $converter = new FallbackConverter(
            $this->failing(new TransportException('Could not resolve host')),
            $this->stub('# Fallback'),
        );

        self::assertSame('# Fallback', $converter->convert('<h1>Hi</h1>'));
    }

    public function testFallsBackOnTimeout(): void
    {
        $converter = new FallbackConverter(
            $this->failing(new TimeoutException('Idle timeout reached')),
            $this->stub('# Fallback'),
        );

        self::assertSame('# Fallback', $converter->convert('<h1>Hi</h1>'));
    }

    public function testPassesSameHtmlToFallback(): void
    {
        $fallback = $this->createMock(ConverterInterface::class);
        $fallback->expects(self::once())->method('convert')->with('<h1>Hi</h1>')->willReturn('# Hi');

        $converter = new FallbackConverter($this->failing(new RuntimeException('down')), $fallback);

        self::assertSame('# Hi', $converter->convert('<h1>Hi</h1>'));
    }

    public function testLogsWarningWhenFallingBack(): void
    {
        $exception = new RuntimeException('Cloudflare converter returned HTTP 503.');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('falling back'),
                self::callback(static fn (array $context): bool => $context['exception'] === $exception
                    && $context['message'] === 'Cloudflare converter returned HTTP 503.'),
            );

        $converter = new FallbackConverter($this->failing($exception), $this->stub('# Fallback'), $logger);
        $converter->convert('<h1>Hi</h1>');
    }

    public function testDoesNotLogWhenPrimarySucceeds(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        $converter = new FallbackConverter($this->stub('# Primary'), $this->stub('# Fallback'), $logger);
        $converter->convert('<h1>Hi</h1>');
    }

    public function testDoesNotSwallowProgrammingErrors(): void
    {
        $converter = new FallbackConverter(
            $this->failing(new LogicException('bug')),
            $this->stub('# Fallback'),
        );

        $this->expectException(LogicException::class);

        $converter->convert('<h1>Hi</h1>');
    }

    private function stub(string $output): ConverterInterface
    {
        $converter = $this->createStub(ConverterInterface::class);
        $converter->method('convert')->willReturn($output);

        return $converter;
    }

    private function failing(Throwable $exception): ConverterInterface
    {
        $converter = $this->createStub(ConverterInterface::class);
        $converter->method('convert')->willThrowException($exception);

        return $converter;
    }
}
