<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use YoungDogs\AiSeo\Configuration\ManagedField;

/**
 * Finds the page record for a language and persists generated values through the DataHandler,
 * so permissions, workspaces, history and cache clearing are handled by the core.
 */
final class PageRecordService
{
    public function __construct(
        private readonly LocalizationRepository $localizationRepository,
    ) {}

    /**
     * Resolves the page record to read or write for a (default language) page uid and a language.
     * Returns null if the page does not exist, is not editable or has no translation.
     *
     * @return array{origin: int, record: array<string, mixed>}|null
     */
    public function resolve(int $pageUid, int $languageId, bool $requireEditAccess): ?array
    {
        $backendUser = $this->getBackendUser();
        $row = BackendUtility::getRecordWSOL('pages', $pageUid);
        if (!is_array($row)) {
            return null;
        }
        // Rows of translated views carry the uid of the translation
        $originUid = (int)($row['l10n_parent'] ?? 0) > 0 ? (int)$row['l10n_parent'] : (int)$row['uid'];
        $originRow = $originUid === (int)$row['uid'] ? $row : BackendUtility::getRecordWSOL('pages', $originUid);
        if (!is_array($originRow)) {
            return null;
        }
        $permission = $requireEditAccess ? Permission::PAGE_EDIT : Permission::PAGE_SHOW;
        if (!$backendUser->doesUserHaveAccess($originRow, $permission)) {
            return null;
        }
        if ($languageId <= 0) {
            return ['origin' => $originUid, 'record' => $originRow];
        }
        $translations = $this->localizationRepository->getPageTranslations($originUid, [$languageId], $backendUser->workspace);
        if (!isset($translations[$languageId])) {
            return null;
        }
        return ['origin' => $originUid, 'record' => $translations[$languageId]->toArray()];
    }

    /**
     * @param array<string, ManagedField> $fields
     * @return array<string, string>
     */
    public function readValues(array $record, array $fields): array
    {
        $values = [];
        foreach ($fields as $name => $field) {
            $values[$name] = (string)($record[$name] ?? '');
        }
        return $values;
    }

    /**
     * @param array<string, string> $values
     * @return list<string> DataHandler error messages, empty on success
     */
    public function save(int $recordUid, array $values): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [$recordUid => $values]], []);
        $dataHandler->process_datamap();
        return array_values(array_map('strval', $dataHandler->errorLog));
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
