<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class ExtensionSettings
{
    private readonly string $apiUrl;
    private readonly string $apiKey;
    private readonly string $model;
    private readonly int $timeout;
    private readonly int $maxContentLength;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $config = (array)$extensionConfiguration->get('youngdogs_ai_seo');
        $this->apiUrl = trim((string)($config['apiUrl'] ?? ''));
        $this->apiKey = trim((string)($config['apiKey'] ?? ''));
        $this->model = trim((string)($config['model'] ?? '')) ?: 'gpt-4o-mini';
        $this->timeout = max(1, (int)($config['timeout'] ?? 60));
        $this->maxContentLength = max(500, (int)($config['maxContentLength'] ?? 12000));
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getMaxContentLength(): int
    {
        return $this->maxContentLength;
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->apiKey !== '';
    }
}
