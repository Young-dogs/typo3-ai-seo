<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Calls YOUNG-dogs AI SEO API endpoint and returns the decoded JSON answer.
 */
final class AiClient
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionSettings $settings,
    ) {}

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return array<string, mixed>
     * @throws \RuntimeException when the API fails or returns something that is not a JSON object
     */
    public function requestJson(array $messages): array
    {
        if (!$this->settings->isConfigured()) {
            throw new \RuntimeException('The AI API is not configured (extension settings of youngdogs_ai_seo).', 1763555001);
        }
        try {
            $response = $this->requestFactory->request(
                $this->settings->getApiUrl(),
                'POST',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-API-Key' => $this->settings->getApiKey(),
                    ],
                    'body' => json_encode([
                        'model' => $this->settings->getModel(),
                        'messages' => $messages,
                        'temperature' => 0.4,
                        'response_format' => ['type' => 'json_object'],
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'timeout' => $this->settings->getTimeout(),
                    'http_errors' => false,
                ]
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('The AI API request failed: ' . $e->getMessage(), 1763555002, $e);
        }
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('The AI API answered with HTTP status ' . $response->getStatusCode() . '.', 1763555003);
        }
        $body = json_decode((string)$response->getBody(), true);
        $text = (string)($body['choices'][0]['message']['content'] ?? '');
        // Tolerate models that wrap the JSON in a code fence
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? $text);
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new \RuntimeException('The AI API returned an invalid response.', 1763555004);
        }
        return $data;
    }
}
