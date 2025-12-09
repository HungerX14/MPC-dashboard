<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ArticleDTO;
use App\DTO\StatsDTO;
use App\Entity\Site;
use App\Exception\WordpressApiException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for communicating with WordPress sites via the custom plugin API
 */
class WordpressApiClient
{
    private const TIMEOUT = 30;
    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Publish an article to a WordPress site
     *
     * @throws WordpressApiException
     */
    public function publishArticle(Site $site, ArticleDTO $article): array
    {
        $endpoint = $site->getApiEndpoint('publish');

        $this->logger->info('Publishing article to WordPress', [
            'site' => $site->getName(),
            'url' => $endpoint,
            'title' => $article->title,
        ]);

        try {
            $response = $this->makeRequest('POST', $endpoint, $site->getApiToken(), [
                'json' => $article->toArray(),
            ]);

            $this->logger->info('Article published successfully', [
                'site' => $site->getName(),
                'response' => $response,
            ]);

            return $response;
        } catch (WordpressApiException $e) {
            $this->logger->error('Failed to publish article', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch statistics from a WordPress site
     *
     * @throws WordpressApiException
     */
    public function fetchStats(Site $site): StatsDTO
    {
        $endpoint = $site->getApiEndpoint('stats');

        $this->logger->info('Fetching stats from WordPress', [
            'site' => $site->getName(),
            'url' => $endpoint,
        ]);

        try {
            $response = $this->makeRequest('GET', $endpoint, $site->getApiToken());

            $this->logger->info('Stats fetched successfully', [
                'site' => $site->getName(),
            ]);

            return StatsDTO::fromApiResponse($response);
        } catch (WordpressApiException $e) {
            $this->logger->error('Failed to fetch stats', [
                'site' => $site->getName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Test connection to a WordPress site
     *
     * @throws WordpressApiException
     */
    public function testConnection(Site $site): bool
    {
        try {
            $this->fetchStats($site);
            return true;
        } catch (WordpressApiException) {
            return false;
        }
    }

    /**
     * Make an HTTP request to the WordPress API
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws WordpressApiException
     */
    private function makeRequest(string $method, string $url, string $token, array $options = []): array
    {
        $defaultOptions = [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        $options = array_merge_recursive($defaultOptions, $options);

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 400) {
                    $content = $response->getContent(false);
                    $this->handleErrorResponse($statusCode, $content, $url);
                }

                $content = $response->getContent();
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($data)) {
                    throw new WordpressApiException(
                        'Invalid JSON response from WordPress API',
                        WordpressApiException::INVALID_RESPONSE
                    );
                }

                return $data;
            } catch (ExceptionInterface $e) {
                $lastException = $this->handleHttpException($e, $url, $attempt);

                if ($attempt < self::MAX_RETRIES) {
                    usleep(500000 * $attempt); // Exponential backoff
                    continue;
                }
            } catch (\JsonException $e) {
                throw new WordpressApiException(
                    sprintf('Invalid JSON response: %s', $e->getMessage()),
                    WordpressApiException::INVALID_RESPONSE,
                    $e
                );
            }
        }

        throw $lastException ?? new WordpressApiException(
            'Unknown error occurred',
            WordpressApiException::UNKNOWN_ERROR
        );
    }

    /**
     * Handle HTTP error responses
     *
     * @throws WordpressApiException
     */
    private function handleErrorResponse(int $statusCode, string $content, string $url): void
    {
        $errorMessage = 'Unknown error';

        try {
            $errorData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $errorMessage = $errorData['message'] ?? $errorData['error'] ?? $content;
        } catch (\JsonException) {
            $errorMessage = $content ?: 'No error message provided';
        }

        match (true) {
            $statusCode === 401 => throw new WordpressApiException(
                sprintf('Invalid API token for %s: %s', $url, $errorMessage),
                WordpressApiException::INVALID_TOKEN
            ),
            $statusCode === 403 => throw new WordpressApiException(
                sprintf('Access forbidden for %s: %s', $url, $errorMessage),
                WordpressApiException::ACCESS_FORBIDDEN
            ),
            $statusCode === 404 => throw new WordpressApiException(
                sprintf('Endpoint not found: %s', $url),
                WordpressApiException::ENDPOINT_NOT_FOUND
            ),
            $statusCode >= 500 => throw new WordpressApiException(
                sprintf('WordPress server error (%d): %s', $statusCode, $errorMessage),
                WordpressApiException::SERVER_ERROR
            ),
            default => throw new WordpressApiException(
                sprintf('HTTP error %d: %s', $statusCode, $errorMessage),
                WordpressApiException::HTTP_ERROR
            ),
        };
    }

    /**
     * Handle HTTP client exceptions
     */
    private function handleHttpException(ExceptionInterface $e, string $url, int $attempt): WordpressApiException
    {
        $message = $e->getMessage();

        // Detect timeout
        if (str_contains(strtolower($message), 'timeout')) {
            return new WordpressApiException(
                sprintf('Connection timeout to %s (attempt %d/%d)', $url, $attempt, self::MAX_RETRIES),
                WordpressApiException::TIMEOUT,
                $e
            );
        }

        // Detect connection errors
        if (str_contains(strtolower($message), 'could not resolve') ||
            str_contains(strtolower($message), 'connection refused')) {
            return new WordpressApiException(
                sprintf('Cannot connect to %s: %s', $url, $message),
                WordpressApiException::CONNECTION_ERROR,
                $e
            );
        }

        return new WordpressApiException(
            sprintf('HTTP error for %s: %s', $url, $message),
            WordpressApiException::HTTP_ERROR,
            $e
        );
    }
}
