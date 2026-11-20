<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extracts plain text from a news record (title, teaser, bodytext, categories, tags)
 * to feed into the AI prompt.
 */
final class NewsContentHarvester
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionSettings $settings,
    ) {}

    /** @param array<string, mixed> $newsRecord */
    public function harvest(array $newsRecord): string
    {
        // For translations, categories/tags live on the original record
        $originUid = (int)$newsRecord['l10n_parent'] > 0
            ? (int)$newsRecord['l10n_parent']
            : (int)$newsRecord['uid'];

        $parts = [];
        if (!empty($newsRecord['title'])) {
            $parts[] = 'Title: ' . trim((string)$newsRecord['title']);
        }
        if (!empty($newsRecord['teaser'])) {
            $parts[] = 'Teaser: ' . $this->toPlainText((string)$newsRecord['teaser']);
        }
        if (!empty($newsRecord['bodytext'])) {
            $parts[] = 'Content: ' . $this->toPlainText((string)$newsRecord['bodytext']);
        }

        $categories = $this->getCategories($originUid);
        if ($categories !== []) {
            $parts[] = 'Categories: ' . implode(', ', $categories);
        }
        $tags = $this->getTags($originUid);
        if ($tags !== []) {
            $parts[] = 'Tags: ' . implode(', ', $tags);
        }

        $text = implode("\n\n", $parts);
        return mb_substr($text, 0, $this->settings->getMaxContentLength());
    }

    /** @return list<string> */
    private function getCategories(int $newsUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $qb
            ->select('c.title')
            ->from('sys_category', 'c')
            ->join(
                'c', 'sys_category_record_mm', 'mm',
                $qb->expr()->eq('mm.uid_local', $qb->quoteIdentifier('c.uid'))
            )
            ->where(
                $qb->expr()->eq('mm.uid_foreign', $qb->createNamedParameter($newsUid, Connection::PARAM_INT)),
                $qb->expr()->eq('mm.tablenames', $qb->createNamedParameter('tx_news_domain_model_news')),
                $qb->expr()->eq('mm.fieldname', $qb->createNamedParameter('categories'))
            )
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /** @return list<string> */
    private function getTags(int $newsUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_news_domain_model_tag');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $qb
            ->select('t.title')
            ->from('tx_news_domain_model_tag', 't')
            ->join(
                't', 'tx_news_domain_model_news_tag_mm', 'mm',
                $qb->expr()->eq('mm.uid_foreign', $qb->quoteIdentifier('t.uid'))
            )
            ->where(
                $qb->expr()->eq('mm.uid_local', $qb->createNamedParameter($newsUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchFirstColumn();
    }

    private function toPlainText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(br\s*/?|/p|/div|/h[1-6]|/li|/tr)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/', "\n", $text) ?? $text;
        return trim($text);
    }
}
