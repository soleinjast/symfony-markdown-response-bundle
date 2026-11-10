<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\EventSubscriber;

use ReflectionClass;
use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Soleinjast\SymfonyMarkdownResponseBundle\Attribute\ProvideMarkdownResponse;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class MarkdownResponseSubscriber implements EventSubscriberInterface
{
    /** Headers that describe the original HTML body and become wrong once it is replaced. */
    private const BODY_SPECIFIC_HEADERS = ['Content-Length', 'ETag', 'Content-MD5'];

    public function __construct(
        private MarkdownConverter $converter,
        private AgentDetector     $detector,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request  = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (str_ends_with($pathInfo, '.md')) {
            $newPath = substr($pathInfo, 0, -3);
            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                array_merge($request->server->all(), ['REQUEST_URI' => $newPath]),
                $request->getContent()
            );
            $request->attributes->set('_wants_markdown', true);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (! str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }

        if (! $this->isMarkdownEnabled($event)) {
            return;
        }

        if (! $this->detector->wantsMarkdown($event->getRequest())) {
            return;
        }

        $html = $response->getContent();
        if ($html === false || $html === '') {
            return;
        }

        // Mutate the existing response so headers set by the controller or
        // other listeners (Set-Cookie, Cache-Control, Location, security
        // headers...) survive. Only headers describing the HTML body are reset.
        $response->setContent($this->converter->convert($html));
        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        foreach (self::BODY_SPECIFIC_HEADERS as $header) {
            $response->headers->remove($header);
        }
    }

    private function isMarkdownEnabled(ResponseEvent $event): bool
    {
        $controller = $event->getRequest()->attributes->get('_controller');

        if (! is_string($controller) && ! is_array($controller)) {
            return false;
        }

        [$class, $method] = $this->resolveControllerCallable($controller);

        if ($class === null) {
            return false;
        }

        if (! class_exists($class)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($class);

        $classAttrs   = $reflectionClass->getAttributes(ProvideMarkdownResponse::class);
        $classEnabled = $classAttrs === [] ? null : $classAttrs[0]->newInstance()->enabled;

        $methodEnabled = null;
        if ($method !== null && $reflectionClass->hasMethod($method)) {
            $methodAttrs = $reflectionClass->getMethod($method)->getAttributes(ProvideMarkdownResponse::class);
            if ($methodAttrs !== []) {
                $methodEnabled = $methodAttrs[0]->newInstance()->enabled;
            }
        }

        return $methodEnabled ?? $classEnabled ?? false;
    }

    /**
     * @param string|array<mixed> $controller
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveControllerCallable(string|array $controller): array
    {
        if (is_array($controller)) {
            $target = $controller[0] ?? null;
            $class  = match (true) {
                is_object($target) => $target::class,
                is_string($target) => $target,
                default            => null,
            };
            $method = $controller[1] ?? null;

            return [$class, is_string($method) ? $method : null];
        }

        if (str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller, 2);
            return [$class, $method];
        }

        return [$controller, '__invoke'];
    }
}