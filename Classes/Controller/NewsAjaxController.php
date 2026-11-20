<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use YoungDogs\AiSeo\Configuration\ManagedField;
use YoungDogs\AiSeo\Service\AiClient;
use YoungDogs\AiSeo\Service\NewsContentHarvester;
use YoungDogs\AiSeo\Service\NewsFieldConfiguration;
use YoungDogs\AiSeo\Service\NewsRecordService;
use YoungDogs\AiSeo\Service\PromptBuilder;

/**
 * AJAX endpoints for the News SEO Overview: reading current values, generating, and applying them.
 */
final class NewsAjaxController
{
    private const LL = 'LLL:EXT:youngdogs_ai_seo/Resources/Private/Language/locallang_news.xlf:';

    public function __construct(
        private readonly NewsFieldConfiguration $fieldConfiguration,
        private readonly NewsRecordService $newsRecordService,
        private readonly NewsContentHarvester $contentHarvester,
        private readonly PromptBuilder $promptBuilder,
        private readonly AiClient $aiClient,
        private readonly SiteFinder $siteFinder,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function rowsAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);
        $pageId = (int)($body['id'] ?? 0);
        $fields = $this->fieldConfiguration->getManagedFields($pageId);

        $rows = [];
        foreach ((array)($body['uids'] ?? []) as $uid) {
            $record = $this->newsRecordService->getByUid((int)$uid);
            if ($record !== null) {
                $rows[(int)$uid] = $this->newsRecordService->readValues($record, $fields);
            }
        }
        return new JsonResponse([
            'fields' => array_values(array_map(static fn(ManagedField $f): array => [
                'name' => $f->name,
                'label' => $f->label,
                'maxLength' => $f->maxLength,
            ], $fields)),
            'rows' => $rows,
        ]);
    }

    public function generateAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);
        $newsUid = (int)($body['uid'] ?? 0);
        $languageId = max(0, (int)($body['lang'] ?? 0));
        $languageService = $this->getLanguageService();

        $record = $this->newsRecordService->getByUid($newsUid);
        if ($record === null || !$this->newsRecordService->canEdit($record)) {
            return $this->error($languageService->sL(self::LL . 'error.noAccess'), 403);
        }

        $pageId = (int)$record['pid'];
        $fields = $this->fieldConfiguration->getManagedFields($pageId);
        if ($fields === []) {
            return $this->error($languageService->sL(self::LL . 'error.noFields'), 400);
        }

        try {
            $content = $this->contentHarvester->harvest($record);
            $messages = $this->promptBuilder->build(
                $fields,
                (string)($record['title'] ?? ''),
                (string)($record['path_segment'] ?? ''),
                $content,
                $this->getLanguageName($pageId, $languageId),
            );
            $generated = $this->aiClient->requestJson($messages);
        } catch (\RuntimeException $e) {
            $this->logger->error(
                'AI SEO news generation failed for news {uid}: {message}',
                ['uid' => $newsUid, 'message' => $e->getMessage()]
            );
            return $this->error($e->getMessage(), 502);
        }

        $generatedValues = [];
        $warnings = [];
        foreach ($fields as $name => $field) {
            $value = $generated[$name] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            [$value, $truncated] = $this->normalize($value, $field);
            $generatedValues[$name] = $value;
            if ($truncated) {
                $warnings[] = sprintf(
                    $languageService->sL(self::LL . 'warning.truncated'),
                    $field->label,
                    $field->maxLength
                );
            }
        }
        if ($generatedValues === []) {
            return $this->error($languageService->sL(self::LL . 'error.emptyResult'), 502);
        }

        return new JsonResponse([
            'success' => true,
            'warnings' => $warnings,
            'existing' => $this->newsRecordService->readValues($record, $fields),
            'generated' => $generatedValues,
        ]);
    }

    public function applyAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);
        $newsUid = (int)($body['uid'] ?? 0);
        $languageService = $this->getLanguageService();

        $record = $this->newsRecordService->getByUid($newsUid);
        if ($record === null || !$this->newsRecordService->canEdit($record)) {
            return $this->error($languageService->sL(self::LL . 'error.noAccess'), 403);
        }

        $fields = $this->fieldConfiguration->getManagedFields((int)$record['pid']);
        $submitted = (array)($body['values'] ?? []);
        $values = [];
        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $submitted) || !is_string($submitted[$name])) {
                continue;
            }
            [$value] = $this->normalize((string)$submitted[$name], $field);
            $values[$name] = $value;
        }
        if ($values === []) {
            return $this->error($languageService->sL(self::LL . 'error.emptyResult'), 400);
        }

        $errors = $this->newsRecordService->save($newsUid, $values);
        if ($errors !== []) {
            return $this->error(implode(' ', $errors), 500);
        }

        $updated = $this->newsRecordService->getByUid($newsUid);
        return new JsonResponse([
            'success' => true,
            'message' => $languageService->sL(self::LL . 'success.generated'),
            'values' => $this->newsRecordService->readValues($updated ?? $record, $fields),
        ]);
    }

    /**
     * @return array{0: string, 1: bool} normalized value and whether it was truncated
     */
    private function normalize(string $value, ManagedField $field): array
    {
        $value = trim(strip_tags($value));
        $value = $field->multiline
            ? (string)preg_replace('/\s*\n\s*/', ' ', $value)
            : (string)preg_replace('/\s+/u', ' ', $value);
        if ($field->maxLength <= 0 || mb_strlen($value) <= $field->maxLength) {
            return [$value, false];
        }
        $cut = mb_substr($value, 0, $field->maxLength + 1);
        $lastSpace = mb_strrpos($cut, ' ');
        $cut = $lastSpace !== false && $lastSpace > $field->maxLength * 0.5
            ? mb_substr($cut, 0, $lastSpace)
            : mb_substr($cut, 0, $field->maxLength);
        return [rtrim($cut, " ,;:-–—"), true];
    }

    private function getLanguageName(int $pageId, int $languageId): string
    {
        try {
            $language = $this->siteFinder->getSiteByPageId($pageId)->getLanguageById($languageId);
            return $language->getTitle() . ' (' . $language->getLocale()->getName() . ')';
        } catch (\Throwable) {
            return 'the language of the news content';
        }
    }

    /** @return array<string, mixed> */
    private function getBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || $body === []) {
            $decoded = json_decode((string)$request->getBody(), true);
            $body = is_array($decoded) ? $decoded : [];
        }
        return $body;
    }

    private function error(string $message, int $status): ResponseInterface
    {
        return new JsonResponse(['success' => false, 'message' => $message], $status);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'] ?? $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
    }
}
