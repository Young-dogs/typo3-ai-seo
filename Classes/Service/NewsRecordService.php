<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Localization\State;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use YoungDogs\AiSeo\Configuration\ManagedField;

/**
 * Finds news records in a page tree branch and persists generated values through DataHandler.
 */
final class NewsRecordService
{
    private const TABLE = 'tx_news_domain_model_news';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Returns all PIDs of the given page and its descendants up to $depth.
     * depth 0 = only $pageId, depth 1 = $pageId + direct children, 999 = all descendants.
     *
     * @return list<int>
     */
    public function collectPageUids(int $pageId, int $depth): array
    {
        return $this->collectRecursive($pageId, $depth, 0);
    }

    /**
     * @param list<int> $pids
     * @return list<array<string, mixed>>
     */
    public function findByPids(array $pids, int $languageId): array
    {
        if ($pids === []) {
            return [];
        }
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $constraints = [
            $qb->expr()->in('pid', $qb->createNamedParameter($pids, Connection::PARAM_INT_ARRAY)),
            $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageId, Connection::PARAM_INT)),
        ];
        // For the default language, exclude translation overlays
        if ($languageId === 0) {
            $constraints[] = $qb->expr()->eq(
                'l10n_parent',
                $qb->createNamedParameter(0, Connection::PARAM_INT)
            );
        }

        return $qb
            ->select(
                'uid', 'pid', 'title', 'datetime', 'hidden',
                'sys_language_uid', 'l10n_parent', 'path_segment'
            )
            ->from(self::TABLE)
            ->where(...$constraints)
            ->orderBy('datetime', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Gets a single record by UID with workspace overlay applied.
     *
     * @return array<string, mixed>|null
     */
    public function getByUid(int $uid): ?array
    {
        $record = BackendUtility::getRecordWSOL(self::TABLE, $uid);
        return is_array($record) ? $record : null;
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

    public function canEdit(array $record): bool
    {
        $backendUser = $this->getBackendUser();
        if (!$backendUser->check('tables_modify', self::TABLE)) {
            return false;
        }
        $pageRow = BackendUtility::getRecord('pages', (int)$record['pid']);
        if (!is_array($pageRow)) {
            return false;
        }
        return $backendUser->doesUserHaveAccess($pageRow, Permission::CONTENT_EDIT);
    }

    /**
     * @param array<string, string> $values
     * @return list<string> DataHandler error messages, empty on success
     */
    public function save(int $uid, array $values): array
    {
        // DataMapProcessor (invoked by DataHandler) builds l10n_state from PERSISTED DB values only
        // for existing records — submitted state changes are ignored. So we update l10n_state
        // directly in the DB first (setting the saved fields to 'custom') so DataHandler reads
        // 'custom' as the persisted state and does not overwrite our values with the parent language.
        $meta = BackendUtility::getRecord(self::TABLE, $uid, 'sys_language_uid, l10n_state');
        if (is_array($meta) && (int)$meta['sys_language_uid'] > 0) {
            $l10nState = json_decode((string)($meta['l10n_state'] ?? '{}'), true);
            if (!is_array($l10nState)) {
                $l10nState = [];
            }
            foreach (array_keys($values) as $fieldName) {
                $l10nState[$fieldName] = State::STATE_CUSTOM;
            }
            $this->connectionPool->getConnectionForTable(self::TABLE)->update(
                self::TABLE,
                ['l10n_state' => json_encode($l10nState, JSON_THROW_ON_ERROR)],
                ['uid' => $uid]
            );
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE => [$uid => $values]], []);
        $dataHandler->process_datamap();
        return array_values(array_map('strval', $dataHandler->errorLog));
    }

    /** @return list<int> */
    private function collectRecursive(int $pageId, int $depth, int $current): array
    {
        $pids = [$pageId];
        if ($depth <= 0 || $current >= 20) {
            return $pids;
        }
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $childUids = $qb
            ->select('uid')
            ->from('pages')
            ->where($qb->expr()->eq('pid', $qb->createNamedParameter($pageId, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($childUids as $childUid) {
            $pids = array_merge(
                $pids,
                $this->collectRecursive((int)$childUid, $depth === 999 ? 999 : $depth - 1, $current + 1)
            );
        }
        return $pids;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
