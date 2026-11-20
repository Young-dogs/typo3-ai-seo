<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use YoungDogs\AiSeo\Configuration\ManagedField;

/**
 * Resolves mod.ai_seo_status.managedFields from page TSconfig for a page.
 */
final class FieldConfiguration
{
    public function __construct(
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * Only fields that exist in the pages TCA and are editable by the current backend user are returned.
     *
     * @return array<string, ManagedField> keyed by field name
     */
    public function getManagedFields(int $pageId): array
    {
        $configuration = BackendUtility::getPagesTSconfig($pageId)['mod.']['ai_seo_status.']['managedFields.'] ?? [];
        if (!is_array($configuration)) {
            return [];
        }
        $schema = $this->tcaSchemaFactory->get('pages');
        $backendUser = $this->getBackendUser();
        $languageService = $this->getLanguageService();
        $fields = [];
        foreach ($configuration as $key => $settings) {
            if (!is_array($settings) || !str_ends_with((string)$key, '.')) {
                continue;
            }
            $name = substr((string)$key, 0, -1);
            if (!empty($settings['disabled']) || !$schema->hasField($name)) {
                continue;
            }
            if (!$backendUser->check('tables_modify', 'pages') || !$backendUser->check('non_exclude_fields', 'pages:' . $name)) {
                continue;
            }
            $label = trim((string)($settings['label'] ?? ''));
            if ($label === '') {
                $label = (string)$schema->getField($name)->getLabel();
            }
            $label = rtrim(trim($languageService->sL($label)), ':');
            $fields[$name] = new ManagedField(
                $name,
                $label !== '' ? $label : $name,
                max(0, (int)($settings['maxLength'] ?? 0)),
                ($settings['type'] ?? 'string') === 'text',
                trim((string)($settings['instruction'] ?? '')),
            );
        }
        return $fields;
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
