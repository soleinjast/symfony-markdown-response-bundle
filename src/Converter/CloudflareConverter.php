<?php

declare(strict_types=1);

namespace Soleinjast\SymfonyMarkdownResponseBundle\Converter;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class CloudflareConverter implements ConverterInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $endpoint,
        private float               $timeout = 5.0,
    ) {
        if ($this->endpoint === '' || $this->endpoint === '0') {
            throw new RuntimeException(
                'symfony_markdown_response.cloudflare_endpoint must be configured when using the "cloudflare" driver.'
            );
        }
    }

    public function convert(string $html): string
    {
        $response = $this->httpClient->request('POST', $this->endpoint, [
            'json'         => ['html' => $html],
            'headers'      => ['Accept' => 'text/plain'],
            'timeout'      => $this->timeout,
            'max_duration' => $this->timeout,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                sprintf('Cloudflare converter returned HTTP %d.', $response->getStatusCode())
            );
        }

        return $response->getContent();
    }
}
