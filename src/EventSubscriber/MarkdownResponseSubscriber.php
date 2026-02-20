<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\EventSubscriber;

use ReflectionException;
use ReflectionClass;
use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Soleinjast\SymfonyMarkdownResponseBundle\Attribute\ProvideMarkdownResponse;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class MarkdownResponseSubscriber implements EventSubscriberInterface
{
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

        $event->setResponse(new Response(
            $this->converter->convert($html),
            $response->getStatusCode(),
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        ));
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

        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (ReflectionException) {
            return false;
        }

        $classAttrs   = $reflectionClass->getAttributes(ProvideMarkdownResponse::class);
        $classEnabled = $classAttrs === [] ? null : $classAttrs[0]->newInstance()->enabled;

        $methodEnabled = null;
        if ($method !== null) {
            try {
                $methodAttrs = $reflectionClass->getMethod($method)->getAttributes(ProvideMarkdownResponse::class);
                if (! empty($methodAttrs)) {
                    $methodEnabled = $methodAttrs[0]->newInstance()->enabled;
                }
            } catch (ReflectionException) {
            }
        }

        return $methodEnabled ?? $classEnabled ?? false;
    }

    /** @return array{0: class-string|null, 1: string|null} */
    private function resolveControllerCallable(string|array $controller): array
    {
        if (is_array($controller)) {
            $class = is_object($controller[0]) ? $controller[0]::class : $controller[0];
            return [$class, $controller[1] ?? null];
        }

        if (str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller, 2);
            return [$class, $method];
        }

        return [$controller, '__invoke'];
    }
}