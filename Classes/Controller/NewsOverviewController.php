<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use YoungDogs\AiSeo\Service\NewsRecordService;

/**
 * Backend module controller for the News SEO Overview (Content → Status → News SEO).
 */
#[AsController]
readonly class NewsOverviewController
{
    private const LL = 'LLL:EXT:youngdogs_ai_seo/Resources/Private/Language/locallang_news.xlf:';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private NewsRecordService $newsRecordService,
        private SiteFinder $siteFinder,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $this->getBackendUser();
        $languageService = $this->getLanguageService();
        $module = $request->getAttribute('module');
        $moduleData = $request->getAttribute('moduleData');
        $pageId = (int)($request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? 0);

        $hasAccess = false;
        $pageInfo = [];
        if ($pageId > 0) {
            $pageInfo = BackendUtility::readPageAccess(
                $pageId,
                $backendUser->getPagePermsClause(Permission::PAGE_SHOW)
            ) ?: [];
            $hasAccess = $pageInfo !== [];
        } elseif ($pageId === 0 && $backendUser->isAdmin()) {
            $pageInfo = ['title' => '[root-level]', 'uid' => 0, 'pid' => 0];
            $hasAccess = true;
        }

        // Collect available site languages
        $siteLanguages = [];
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId > 0 ? $pageId : 1);
            foreach ($site->getAvailableLanguages($backendUser, false, $pageId) as $language) {
                $siteLanguages[$language->getLanguageId()] = $language->getTitle();
            }
        } catch (\Throwable) {
            $siteLanguages[0] = $languageService->sL(
                'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.default_value'
            );
        }

        $allowedModuleOptions = [
            'depth' => array_fill_keys([0, 1, 2, 3, 4, 999], ''),
            'lang' => array_fill_keys(array_keys($siteLanguages), ''),
        ];
        if ($moduleData->cleanUp($allowedModuleOptions)) {
            $backendUser->pushModuleData($moduleData->getModuleIdentifier(), $moduleData->toArray());
        }

        $selectedDepth = (int)($moduleData->get('depth') ?? 0);
        $selectedLanguage = (int)($moduleData->get('lang') ?? 0);

        $depthDropdownOptions = [
            0 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
            1 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
            2 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
            3 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
            4 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
            999 => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi'),
        ];

        $newsRecords = [];
        $configJson = '';
        if ($hasAccess && $pageId > 0) {
            $pids = $this->newsRecordService->collectPageUids($pageId, $selectedDepth);
            $newsRecords = $this->newsRecordService->findByPids($pids, $selectedLanguage);

            $returnUrl = (string)$this->uriBuilder->buildUriFromRoute(
                $module->getIdentifier(),
                ['id' => $pageId]
            );
            foreach ($newsRecords as &$record) {
                $record['editUrl'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                    'edit' => ['tx_news_domain_model_news' => [$record['uid'] => 'edit']],
                    'returnUrl' => $returnUrl,
                ]);
            }
            unset($record);

            $configJson = json_encode([
                'pageId' => $pageId,
                'lang' => $selectedLanguage,
                'rowsUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ai_seo_news_rows'),
                'generateUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ai_seo_news_generate'),
                'applyUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ai_seo_news_apply'),
                'labels' => [
                    'generate' => $languageService->sL(self::LL . 'button.generate'),
                    'previewTitle' => $languageService->sL(self::LL . 'preview.title'),
                    'select' => $languageService->sL(self::LL . 'preview.select'),
                    'selectAll' => $languageService->sL(self::LL . 'preview.selectAll'),
                    'oldValue' => $languageService->sL(self::LL . 'preview.oldValue'),
                    'newValue' => $languageService->sL(self::LL . 'preview.newValue'),
                    'apply' => $languageService->sL(self::LL . 'button.apply'),
                    'cancel' => $languageService->sL(self::LL . 'button.cancel'),
                    'successTitle' => $languageService->sL(self::LL . 'toast.successTitle'),
                    'warningTitle' => $languageService->sL(self::LL . 'toast.warningTitle'),
                    'errorTitle' => $languageService->sL(self::LL . 'toast.errorTitle'),
                    'errorGeneric' => $languageService->sL(self::LL . 'error.generic'),
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $view = $this->moduleTemplateFactory->create($request);
        if ($hasAccess && $pageInfo !== []) {
            $view->setTitle($languageService->sL($module->getTitle()), $pageInfo['title'] ?? '');
            $view->getDocHeaderComponent()->setPageBreadcrumb($pageInfo);
            $view->makeDocHeaderModuleMenu(['id' => $pageId]);
        }
        $view->assignMultiple([
            'hasAccess' => $hasAccess,
            'pageUid' => $pageId,
            'newsRecords' => $newsRecords,
            'configJson' => $configJson,
            'depthDropdownOptions' => $depthDropdownOptions,
            'depthDropdownCurrentValue' => $selectedDepth,
            'langDropdownOptions' => $siteLanguages,
            'langDropdownCurrentValue' => $selectedLanguage,
            'moduleUrl' => (string)$this->uriBuilder->buildUriFromRoute(
                $module->getIdentifier(),
                ['id' => $pageId]
            ),
        ]);

        return $view->renderResponse('NewsOverview');
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
