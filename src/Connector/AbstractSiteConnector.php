<?php

declare(strict_types=1);

namespace App\Connector;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Base class for site connectors with common functionality
 */
abstract class AbstractSiteConnector implements SiteConnectorInterface
{
    // Common feature constants
    public const FEATURE_PUBLISH = 'publish';
    public const FEATURE_STATS = 'stats';
    public const FEATURE_CATEGORIES = 'categories';
    public const FEATURE_TAGS = 'tags';
    public const FEATURE_MEDIA = 'media';
    public const FEATURE_SCHEDULE = 'schedule';
    public const FEATURE_DRAFT = 'draft';
    public const FEATURE_CUSTOM_FIELDS = 'custom_fields';

    protected const TIMEOUT = 30;
    protected const MAX_RETRIES = 2;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get list of supported features for this connector
     * @return array<string>
     */
    abstract protected function getSupportedFeatures(): array;

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->getSupportedFeatures(), true);
    }

    /**
     * Make an HTTP request with retry logic
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        $defaultOptions = [
            'timeout' => static::TIMEOUT,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        $options = array_merge_recursive($defaultOptions, $options);

        for ($attempt = 1; $attempt <= static::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 400) {
                    $this->logger->warning('HTTP error response', [
                        'status' => $statusCode,
                        'url' => $url,
                        'attempt' => $attempt,
                    ]);

                    if ($attempt === static::MAX_RETRIES) {
                        throw new \RuntimeException(
                            sprintf('HTTP error %d from %s', $statusCode, $url)
                        );
                    }
                    continue;
                }

                $content = $response->getContent();
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (\Exception $e) {
                $this->logger->error('Request failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt === static::MAX_RETRIES) {
                    throw $e;
                }

                usleep(500000 * $attempt); // Exponential backoff
            }
        }

        throw new \RuntimeException('All retry attempts failed');
    }
}
