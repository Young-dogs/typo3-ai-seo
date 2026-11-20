<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Collects the visible content of a page as plain text.
 */
final class ContentHarvester
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionSettings $settings,
    ) {}

    public function harvest(int $pageId, int $languageId, int $workspaceId): string
    {
        $now = time();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
            ->add(GeneralUtility::makeInstance(StartTimeRestriction::class, $now))
            ->add(GeneralUtility::makeInstance(EndTimeRestriction::class, $now))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId));
        $rows = $queryBuilder
            ->select('uid', 'header', 'subheader', 'bodytext')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT)),
            )
            ->orderBy('colPos')
            ->addOrderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $blocks = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach (['header', 'subheader', 'bodytext'] as $field) {
                $text = $this->toPlainText((string)($row[$field] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
            if ($parts !== []) {
                $blocks[] = implode("\n", $parts);
            }
        }
        $content = implode("\n\n", $blocks);
        return mb_substr($content, 0, $this->settings->getMaxContentLength());
    }

    private function toPlainText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        // Keep block boundaries as line breaks before stripping the tags
        $html = preg_replace('#<(br\s*/?|/p|/div|/h[1-6]|/li|/tr)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/', "\n", $text) ?? $text;
        return trim($text);
    }
}
