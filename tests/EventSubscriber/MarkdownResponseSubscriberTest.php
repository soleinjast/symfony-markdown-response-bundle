<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Tests\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Soleinjast\SymfonyMarkdownResponseBundle\Attribute\ProvideMarkdownResponse;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Soleinjast\SymfonyMarkdownResponseBundle\EventSubscriber\MarkdownResponseSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class MarkdownResponseSubscriberTest extends TestCase
{
    private MarkdownConverter&MockObject $converter;
    private AgentDetector&MockObject $detector;
    private MarkdownResponseSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->converter  = $this->createMock(MarkdownConverter::class);
        $this->detector   = $this->createMock(AgentDetector::class);
        $this->subscriber = new MarkdownResponseSubscriber($this->converter, $this->detector);
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function testSubscribesToKernelResponseEvent(): void
    {
        $events = MarkdownResponseSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testSubscribesToKernelRequestEvent(): void
    {
        $events = MarkdownResponseSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testRequestEventHasCorrectPriority(): void
    {
        $events = MarkdownResponseSubscriber::getSubscribedEvents();

        $config = $events[KernelEvents::REQUEST];
        self::assertSame('onKernelRequest', $config[0]);
        self::assertSame(100, $config[1]);
    }

    public function testResponseEventHasCorrectPriority(): void
    {
        $events = MarkdownResponseSubscriber::getSubscribedEvents();

        $config = $events[KernelEvents::RESPONSE];
        self::assertSame('onKernelResponse', $config[0]);
        self::assertSame(-10, $config[1]);
    }

    // --- onKernelRequest tests ---

    public function testOnKernelRequestStripesMdSuffixFromPath(): void
    {
        $request = Request::create('/about.md');
        $event   = $this->createRequestEvent($request);

        $this->subscriber->onKernelRequest($event);

        self::assertSame('/about', $request->getPathInfo());
    }

    public function testOnKernelRequestSetswantsMarkdownAttribute(): void
    {
        $request = Request::create('/about.md');
        $event   = $this->createRequestEvent($request);

        $this->subscriber->onKernelRequest($event);

        self::assertTrue($request->attributes->get('_wants_markdown'));
    }

    public function testOnKernelRequestDoesNothingForNonMdPath(): void
    {
        $request = Request::create('/about');
        $event   = $this->createRequestEvent($request);

        $this->subscriber->onKernelRequest($event);

        self::assertSame('/about', $request->getPathInfo());
        self::assertFalse((bool) $request->attributes->get('_wants_markdown', false));
    }

    public function testOnKernelRequestSkipsSubRequest(): void
    {
        $request = Request::create('/page.md');
        $event   = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Path must remain unchanged for sub-requests
        self::assertSame('/page.md', $request->getPathInfo());
        self::assertFalse((bool) $request->attributes->get('_wants_markdown', false));
    }

    public function testOnKernelRequestUpdatesServerRequestUri(): void
    {
        $request = Request::create('/docs/page.md');
        $event   = $this->createRequestEvent($request);

        $this->subscriber->onKernelRequest($event);

        self::assertSame('/docs/page', $request->server->get('REQUEST_URI'));
    }

    public function testOnKernelRequestHandlesRootMdPath(): void
    {
        $request = Request::create('/index.md');
        $event   = $this->createRequestEvent($request);

        $this->subscriber->onKernelRequest($event);

        self::assertSame('/index', $request->getPathInfo());
    }

    public function testOnKernelRequestPreservesQueryString(): void
    {
        $request = Request::create('/page.md?foo=bar&baz=1');
        $event   = $this->createRequestEvent($request);

        $this->subscriber->onKernelRequest($event);

        self::assertSame('bar', $request->query->get('foo'));
        self::assertSame('1', $request->query->get('baz'));
    }

    // --- onKernelResponse tests ---

    public function testSkipsSubRequests(): void
    {
        $this->converter->expects(self::never())->method('convert');
        $this->detector->expects(self::never())->method('wantsMarkdown');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsNonHtmlResponses(): void
    {
        $this->converter->expects(self::never())->method('convert');

        $event = $this->createResponseEvent(
            new Response('{"key":"value"}', 200, ['Content-Type' => 'application/json']),
            $this->requestWithController(AnnotatedController::class . '::annotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsWhenControllerHasNoMarkdownAttribute(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::never())->method('convert');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(UnannotatedController::class . '::action'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsWhenAgentDoesNotWantMarkdown(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(false);
        $this->converter->expects(self::never())->method('convert');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::annotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testConvertsHtmlToMarkdownWhenAllConditionsMet(): void
    {
        $html     = '<p>Hello World</p>';
        $markdown = 'Hello World';

        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::once())
            ->method('convert')
            ->with($html)
            ->willReturn($markdown);

        $event = $this->createResponseEvent(
            new Response($html, 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::annotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);

        $response = $event->getResponse();
        self::assertSame($markdown, $response->getContent());
        self::assertStringContainsString('text/markdown', $response->headers->get('Content-Type'));
    }

    public function testSetsCorrectContentTypeOnConvertedResponse(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->method('convert')->willReturn('# Markdown');

        $event = $this->createResponseEvent(
            new Response('<h1>Heading</h1>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::annotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);

        self::assertSame(
            'text/markdown; charset=UTF-8',
            $event->getResponse()->headers->get('Content-Type'),
        );
    }

    public function testPreservesStatusCodeOnConvertedResponse(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->method('convert')->willReturn('# Markdown');

        $event = $this->createResponseEvent(
            new Response('<h1>Hello</h1>', 201, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::annotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);

        self::assertSame(201, $event->getResponse()->getStatusCode());
    }

    public function testSkipsEmptyResponseContent(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::never())->method('convert');

        $event = $this->createResponseEvent(
            new Response('', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::annotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testUsesClassLevelAttributeWhenMethodHasNone(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::once())->method('convert')->willReturn('markdown');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::unannotatedAction'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testMethodAttributeOverridesClassAttributeToDisable(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::never())->method('convert');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(AnnotatedController::class . '::disabledAction'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testMethodAttributeCanEnableOnNonAnnotatedClass(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::once())->method('convert')->willReturn('markdown');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(UnannotatedController::class . '::enabledAction'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testHandlesInvokableController(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::once())->method('convert')->willReturn('markdown');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController(InvokableController::class),
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testHandlesArrayControllerCallable(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::once())->method('convert')->willReturn('markdown');

        $request = new Request();
        $request->attributes->set('_controller', [new AnnotatedController(), 'annotatedAction']);

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $request,
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsWhenControllerIsNullOrInvalidType(): void
    {
        $this->converter->expects(self::never())->method('convert');

        $request = new Request();
        $request->attributes->set('_controller', null);

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $request,
        );

        $this->subscriber->onKernelResponse($event);
    }

    public function testHandlesNonExistentControllerClass(): void
    {
        $this->detector->method('wantsMarkdown')->willReturn(true);
        $this->converter->expects(self::never())->method('convert');

        $event = $this->createResponseEvent(
            new Response('<p>Hello</p>', 200, ['Content-Type' => 'text/html']),
            $this->requestWithController('NonExistent\\Controller::action'),
        );

        $this->subscriber->onKernelResponse($event);
    }

    private function createRequestEvent(
        Request $request,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }

    private function createResponseEvent(
        Response $response,
        Request $request,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, $requestType, $response);
    }

    private function requestWithController(string $controller): Request
    {
        $request = new Request();
        $request->attributes->set('_controller', $controller);

        return $request;
    }
}

// ----- Test fixture controllers -----

#[ProvideMarkdownResponse]
class AnnotatedController
{
    public function annotatedAction(): void {}

    public function unannotatedAction(): void {}

    #[ProvideMarkdownResponse(enabled: false)]
    public function disabledAction(): void {}
}

class UnannotatedController
{
    public function action(): void {}

    #[ProvideMarkdownResponse]
    public function enabledAction(): void {}
}

#[ProvideMarkdownResponse]
class InvokableController
{
    public function __invoke(): void {}
}
