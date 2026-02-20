![Symfony Markdown Response Bundle](https://raw.githubusercontent.com/soleinjast/symfony-markdown-response-bundle/main/art.png)

A Symfony bundle that automatically serves Markdown versions of your HTML pages to AI agents, bots, and any client that requests it. Controllers opt in with a single PHP attribute — the bundle handles detection, conversion, and caching transparently.

[![Latest Stable Version](https://poser.pugx.org/soleinjast/symfony-markdown-response-bundle/v/stable)](https://packagist.org/packages/soleinjast/symfony-markdown-response-bundle)
[![Total Downloads](https://poser.pugx.org/soleinjast/symfony-markdown-response-bundle/downloads)](https://packagist.org/packages/soleinjast/symfony-markdown-response-bundle)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x%20%7C%208.x-black)](https://symfony.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Why

AI assistants (ChatGPT, Claude, Perplexity, etc.) and autonomous agents consume web content better as plain Markdown than as HTML. Stripping navigation, scripts, styles, and markup noise reduces token usage and improves comprehension — without maintaining a separate content pipeline.

This bundle intercepts the Symfony HTTP kernel at two points:

1. **`kernel.request` (priority 100)** — rewrites `.md` URL suffixes to the canonical path before routing, so `/docs/setup.md` resolves to the same controller as `/docs/setup`, and sets a `_wants_markdown` flag on the request.
2. **`kernel.response` (priority −10)** — converts the HTML response to Markdown when the controller opts in via `#[ProvideMarkdownResponse]` and the client is detected as wanting Markdown.

---

## Installation

```bash
composer require soleinjast/symfony-markdown-response-bundle
```

If you are not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Soleinjast\SymfonyMarkdownResponseBundle\SymfonyMarkdownResponseBundle::class => ['all' => true],
];
```

---

## Quick Start

### 1. Opt a controller in

Apply the `#[ProvideMarkdownResponse]` attribute to a controller class or to individual action methods.

```php
use Soleinjast\SymfonyMarkdownResponseBundle\Attribute\ProvideMarkdownResponse;

#[ProvideMarkdownResponse]
class DocsController extends AbstractController
{
    public function index(): Response { /* ... */ }
    public function show(): Response  { /* ... */ }
}
```

Or enable it per-method and disable it on specific actions:

```php
#[ProvideMarkdownResponse]
class BlogController extends AbstractController
{
    public function index(): Response { /* ... */ }

    #[ProvideMarkdownResponse(enabled: false)]
    public function edit(): Response { /* only humans edit posts */ }
}
```

### 2. That's it

The bundle detects AI clients automatically. No changes to routes, templates, or responses are required.

---

## How Detection Works

A request is considered to want Markdown if **any** of the following is true:

| Signal | Example |
|---|---|
| `Accept: text/markdown` header | `Accept: text/markdown, */*` |
| `.md` URL suffix | `GET /docs/setup.md` |
| Known AI User-Agent substring | `User-Agent: GPTBot/1.0` |
| `_wants_markdown` request attribute | Set internally after `.md` rewrite |

The `.md` suffix is stripped before routing so your route definitions remain unchanged. The `_wants_markdown` attribute is set on the request to carry the intent forward to the response phase.

Default User-Agent patterns:

```
GPTBot, ChatGPT-User, CCBot, anthropic-ai, Claude-Web, ClaudeBot, PerplexityBot
```

---

## Configuration

Publish the configuration file (optional — all keys have defaults):

```yaml
# config/packages/symfony_markdown_response.yaml
symfony_markdown_response:
    driver: local               # "local" or "cloudflare"
    cloudflare_endpoint: ~      # required when driver is "cloudflare"
    cache_enabled: true
    cache_ttl: 3600             # seconds
    cache_service: ~            # PSR-6 service ID; defaults to cache.app
    ai_user_agents:
        - GPTBot
        - ChatGPT-User
        - CCBot
        - anthropic-ai
        - Claude-Web
        - ClaudeBot
        - PerplexityBot
```

### Configuration reference

| Key | Type | Default | Description |
|---|---|---|---|
| `driver` | `local\|cloudflare` | `local` | Conversion backend |
| `cloudflare_endpoint` | `string\|null` | `null` | Cloudflare Workers AI URL (required for `cloudflare` driver) |
| `cache_enabled` | `bool` | `true` | Cache converted Markdown |
| `cache_ttl` | `int` | `3600` | Cache TTL in seconds |
| `cache_service` | `string\|null` | `null` | PSR-6 cache pool service ID; falls back to `cache.app` then `cache.system` |
| `ai_user_agents` | `string[]` | *(see above)* | User-Agent substrings that trigger Markdown responses |

---

## Conversion Drivers

### `local` (default)

Uses [`league/html-to-markdown`](https://github.com/thephpleague/html-to-markdown). Conversion happens in-process with no external dependencies. The following HTML nodes are stripped before conversion: `head`, `script`, `style`, `nav`, `footer`, `aside`.

### `cloudflare`

Posts the HTML to a Cloudflare Workers AI endpoint and returns the response body as Markdown. Requires `symfony/http-client` and a configured endpoint URL.

```yaml
symfony_markdown_response:
    driver: cloudflare
    cloudflare_endpoint: 'https://your-worker.example.workers.dev/to-markdown'
```

---

## Caching

Converted Markdown is cached by default using a PSR-6 cache pool. The cache key is derived from an `xxh3` hash of the pre-processed HTML, so distinct page content produces distinct cache entries.

Resolution order for the cache pool:

1. `cache_service` if explicitly configured
2. `cache.app` if present in the container
3. `cache.system` if present in the container
4. No caching (conversion runs on every request)

To disable caching entirely:

```yaml
symfony_markdown_response:
    cache_enabled: false
```

---

## Preprocessing Pipeline

You can strip or transform HTML before conversion by implementing `HtmlPreprocessorInterface`. Services implementing this interface are auto-configured via the `symfony_markdown_response.html_preprocessor` tag.

```php
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\HtmlPreprocessorInterface;

class RemoveCookieBannerPreprocessor implements HtmlPreprocessorInterface
{
    public function process(string $html): string
    {
        // Remove cookie consent banners, ads, or any other noise
        return preg_replace('/<div[^>]+class="[^"]*cookie[^"]*".*?<\/div>/si', '', $html);
    }
}
```

Register it as a service (or rely on autowiring/autoconfiguration):

```yaml
# config/services.yaml
App\Preprocessor\RemoveCookieBannerPreprocessor:
    tags:
        - { name: symfony_markdown_response.html_preprocessor }
```

Multiple preprocessors are applied in the order they are resolved from the container.

---

## Accessing Markdown Programmatically

The `MarkdownConverter` service is available for injection if you need to convert HTML outside of the HTTP cycle:

```php
use Soleinjast\SymfonyMarkdownResponseBundle\Converter\MarkdownConverter;

class SitemapExporter
{
    public function __construct(private readonly MarkdownConverter $converter) {}

    public function export(string $html): string
    {
        return $this->converter->convert($html);
    }
}
```

---

## Requesting Markdown as a Client

There are three ways to request a Markdown response:

**1. URL suffix**
```
GET /docs/getting-started.md HTTP/1.1
```

**2. Accept header**
```
GET /docs/getting-started HTTP/1.1
Accept: text/markdown
```

**3. Known AI User-Agent** (automatic — no client changes required)
```
GET /docs/getting-started HTTP/1.1
User-Agent: GPTBot/1.0
```

All three methods produce a response with `Content-Type: text/markdown; charset=UTF-8`.

---

## Contributing

Contributions are welcome. Please open an issue before submitting a pull request for significant changes so the approach can be discussed first.

1. Fork the repository
2. Create a feature branch: `git checkout -b feat/my-feature`
3. Write tests for your changes
4. Submit a pull request

---

## License

Released under the [MIT License](LICENSE).
