<?php

declare(strict_types=1);

namespace App\Service;

use App\Connector\GenericApiConnector;
use App\Connector\GitConnector;
use App\Connector\SiteConnectorInterface;
use App\Connector\WordPressConnector;
use App\Entity\Site;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory for creating site connectors based on site type
 */
class ConnectorFactory
{
    /** @var array<string, class-string<SiteConnectorInterface>> */
    private array $connectorClasses = [
        'wordpress' => WordPressConnector::class,
        'api' => GenericApiConnector::class,
        'git' => GitConnector::class,
    ];

    /** @var array<string, SiteConnectorInterface> */
    private array $connectorInstances = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get connector for a specific site
     */
    public function getConnector(Site $site): SiteConnectorInterface
    {
        return $this->getConnectorByType($site->getType());
    }

    /**
     * Get connector by type
     */
    public function getConnectorByType(string $type): SiteConnectorInterface
    {
        if (!isset($this->connectorInstances[$type])) {
            $this->connectorInstances[$type] = $this->createConnector($type);
        }

        return $this->connectorInstances[$type];
    }

    /**
     * Get all available connector types
     * @return array<string, array{type: string, name: string, description: string, icon: string}>
     */
    public function getAvailableConnectors(): array
    {
        $connectors = [];

        foreach ($this->connectorClasses as $type => $class) {
            $connectors[$type] = [
                'type' => $class::getType(),
                'name' => $class::getDisplayName(),
                'description' => $class::getDescription(),
                'icon' => $class::getIcon(),
                'fields' => $class::getConfigurationFields(),
            ];
        }

        return $connectors;
    }

    /**
     * Get configuration fields for a connector type
     * @return array<string, array{label: string, type: string, required: bool, placeholder?: string, help?: string}>
     */
    public function getConfigurationFields(string $type): array
    {
        if (!isset($this->connectorClasses[$type])) {
            return [];
        }

        return $this->connectorClasses[$type]::getConfigurationFields();
    }

    /**
     * Check if a connector type exists
     */
    public function hasConnector(string $type): bool
    {
        return isset($this->connectorClasses[$type]);
    }

    /**
     * Register a new connector type
     * @param class-string<SiteConnectorInterface> $class
     */
    public function registerConnector(string $type, string $class): void
    {
        $this->connectorClasses[$type] = $class;
        // Clear cached instance if exists
        unset($this->connectorInstances[$type]);
    }

    /**
     * Create a connector instance
     */
    private function createConnector(string $type): SiteConnectorInterface
    {
        if (!isset($this->connectorClasses[$type])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown connector type: %s. Available types: %s', $type, implode(', ', array_keys($this->connectorClasses)))
            );
        }

        $class = $this->connectorClasses[$type];
        return new $class($this->httpClient, $this->logger);
    }
}
