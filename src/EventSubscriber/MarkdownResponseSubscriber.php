<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\EventSubscriber;

use ReflectionClass;
use Soleinjast\SymfonyMarkdownResponseBundle\AgentDetector;
use Soleinjast\SymfonyMarkdownResponseBundle\Attribute\ProvideMarkdownResponse;
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class MarkdownResponseSubscriber implements EventSubscriberInterface
{
    private const BODY_SPECIFIC_HEADERS = ['Content-Length', 'ETag', 'Content-MD5'];

    private const NEGOTIATION_HEADERS = ['Accept', 'User-Agent'];

    public function __construct(
        private MarkdownConverter $converter,
        private AgentDetector     $detector,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST    => ['onKernelRequest', 100],
            KernelEvents::CONTROLLER => ['onKernelController', 0],
            KernelEvents::RESPONSE   => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (! str_ends_with($request->getPathInfo(), '.md')) {
            return;
        }

        $requestUri = $request->server->get('REQUEST_URI');
        if (! is_string($requestUri)) {
            return;
        }

        $parts = explode('?', $requestUri, 2);
        if (! str_ends_with($parts[0], '.md')) {
            return;
        }

        $parts[0] = substr($parts[0], 0, -3);

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            array_merge($request->server->all(), ['REQUEST_URI' => implode('?', $parts)]),
            $request->getContent()
        );
        $request->attributes->set('_wants_markdown', true);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (! $request->attributes->getBoolean('_wants_markdown')) {
            return;
        }

        if (! $this->isMarkdownEnabled($request)) {
            throw new NotFoundHttpException(sprintf('No Markdown version is available for "%s".', $request->getPathInfo()));
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

        if (! $this->isMarkdownEnabled($event->getRequest())) {
            return;
        }

        $request = $event->getRequest();

        if (! $request->attributes->getBoolean('_wants_markdown')) {
            $this->addVary($response, self::NEGOTIATION_HEADERS);
        }

        if (! $this->detector->wantsMarkdown($request)) {
            return;
        }

        $html = $response->getContent();
        if ($html === false || $html === '') {
            return;
        }

        $response->setContent($this->converter->convert($html));
        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        foreach (self::BODY_SPECIFIC_HEADERS as $header) {
            $response->headers->remove($header);
        }
    }

    /** @param list<string> $headers */
    private function addVary(Response $response, array $headers): void
    {
        $existing = [];
        foreach ($response->getVary() as $value) {
            if (is_string($value)) {
                $existing[] = strtolower($value);
            }
        }

        foreach ($headers as $header) {
            if (! in_array(strtolower($header), $existing, true)) {
                $response->setVary($header, false);
            }
        }
    }

    private function isMarkdownEnabled(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

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