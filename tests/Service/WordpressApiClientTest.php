<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\ArticleDTO;
use App\DTO\StatsDTO;
use App\Entity\Site;
use App\Exception\WordpressApiException;
use App\Service\WordpressApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WordpressApiClientTest extends TestCase
{
    private function createSite(): Site
    {
        $site = new Site();
        $site->setName('Test WordPress Site');
        $site->setUrl('https://test-wordpress.com');
        $site->setApiToken('test-api-token-12345');

        return $site;
    }

    private function createMockLogger(): LoggerInterface
    {
        return $this->createMock(LoggerInterface::class);
    }

    public function testFetchStatsSuccess(): void
    {
        $responseBody = json_encode([
            'total_posts' => 42,
            'total_categories' => 5,
            'total_tags' => 15,
            'total_pages' => 10,
            'site_title' => 'Test WordPress Site',
            'wordpress_version' => '6.4.2',
        ]);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $stats = $apiClient->fetchStats($site);

        $this->assertInstanceOf(StatsDTO::class, $stats);
        $this->assertEquals(42, $stats->totalPosts);
        $this->assertEquals(5, $stats->totalCategories);
        $this->assertEquals(15, $stats->totalTags);
        $this->assertEquals('Test WordPress Site', $stats->siteTitle);
        $this->assertEquals('6.4.2', $stats->wordpressVersion);
    }

    public function testFetchStatsWithInvalidToken(): void
    {
        $responseBody = json_encode([
            'message' => 'Invalid API token',
        ]);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 401,
            'response_headers' => ['Content-Type: application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $this->expectException(WordpressApiException::class);
        $this->expectExceptionCode(WordpressApiException::INVALID_TOKEN);

        $apiClient->fetchStats($site);
    }

    public function testFetchStatsWithServerError(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $this->expectException(WordpressApiException::class);
        $this->expectExceptionCode(WordpressApiException::SERVER_ERROR);

        $apiClient->fetchStats($site);
    }

    public function testFetchStatsWithNotFoundError(): void
    {
        $mockResponse = new MockResponse('Not Found', [
            'http_code' => 404,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $this->expectException(WordpressApiException::class);
        $this->expectExceptionCode(WordpressApiException::ENDPOINT_NOT_FOUND);

        $apiClient->fetchStats($site);
    }

    public function testPublishArticleSuccess(): void
    {
        $responseBody = json_encode([
            'success' => true,
            'post_id' => 123,
            'post_url' => 'https://test-wordpress.com/2024/01/test-article/',
        ]);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $article = new ArticleDTO(
            title: 'Test Article Title',
            content: '<p>This is the test article content.</p>',
            categories: ['Technology', 'News'],
            tags: ['test', 'article'],
            status: 'publish'
        );

        $result = $apiClient->publishArticle($site, $article);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['post_id']);
    }

    public function testPublishArticleWithInvalidJson(): void
    {
        $mockResponse = new MockResponse('This is not valid JSON', [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $article = new ArticleDTO(
            title: 'Test Article',
            content: 'Content',
        );

        $this->expectException(WordpressApiException::class);
        $this->expectExceptionCode(WordpressApiException::INVALID_RESPONSE);

        $apiClient->publishArticle($site, $article);
    }

    public function testTestConnectionSuccess(): void
    {
        $responseBody = json_encode([
            'total_posts' => 10,
            'total_categories' => 3,
            'total_tags' => 5,
        ]);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
            'response_headers' => ['Content-Type: application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $result = $apiClient->testConnection($site);

        $this->assertTrue($result);
    }

    public function testTestConnectionFailure(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 500,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMockLogger();

        $apiClient = new WordpressApiClient($httpClient, $logger);
        $site = $this->createSite();

        $result = $apiClient->testConnection($site);

        $this->assertFalse($result);
    }

    public function testArticleDtoToArray(): void
    {
        $article = new ArticleDTO(
            title: 'My Article',
            content: '<p>Content here</p>',
            categories: ['Cat1', 'Cat2'],
            tags: ['tag1', 'tag2'],
            status: 'draft',
            excerpt: 'Short description'
        );

        $array = $article->toArray();

        $this->assertEquals('My Article', $array['title']);
        $this->assertEquals('<p>Content here</p>', $array['content']);
        $this->assertEquals(['Cat1', 'Cat2'], $array['categories']);
        $this->assertEquals(['tag1', 'tag2'], $array['tags']);
        $this->assertEquals('draft', $array['status']);
        $this->assertEquals('Short description', $array['excerpt']);
    }

    public function testStatsDtoFromApiResponse(): void
    {
        $apiData = [
            'total_posts' => 100,
            'total_categories' => 10,
            'total_tags' => 50,
            'total_pages' => 5,
            'site_title' => 'My Blog',
            'wordpress_version' => '6.4',
        ];

        $stats = StatsDTO::fromApiResponse($apiData);

        $this->assertEquals(100, $stats->totalPosts);
        $this->assertEquals(10, $stats->totalCategories);
        $this->assertEquals(50, $stats->totalTags);
        $this->assertEquals(5, $stats->totalPages);
        $this->assertEquals('My Blog', $stats->siteTitle);
        $this->assertEquals('6.4', $stats->wordpressVersion);
        $this->assertNotNull($stats->fetchedAt);
    }

    public function testWordpressApiExceptionUserMessages(): void
    {
        $timeoutException = new WordpressApiException('Timeout', WordpressApiException::TIMEOUT);
        $this->assertStringContainsString('ne repond pas', $timeoutException->getUserMessage());
        $this->assertTrue($timeoutException->isRetryable());

        $tokenException = new WordpressApiException('Invalid token', WordpressApiException::INVALID_TOKEN);
        $this->assertStringContainsString('invalide', $tokenException->getUserMessage());
        $this->assertFalse($tokenException->isRetryable());

        $connectionException = new WordpressApiException('Connection failed', WordpressApiException::CONNECTION_ERROR);
        $this->assertStringContainsString('connecter', $connectionException->getUserMessage());
        $this->assertTrue($connectionException->isRetryable());
    }
}
