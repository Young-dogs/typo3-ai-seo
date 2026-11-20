<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\EventListener;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Info\Controller\Event\ModifyInfoModuleContentEvent;

/**
 * Activates the AI SEO columns and generate buttons when the "AI SEO" view
 * of the Pagetree Overview is selected.
 */
#[AsEventListener(identifier: 'youngdogs-ai-seo/info-module-content')]
final class InjectAiSeoView
{
    private const LL = 'LLL:EXT:youngdogs_ai_seo/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly UriBuilder $uriBuilder,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function __invoke(ModifyInfoModuleContentEvent $event): void
    {
        $request = $event->getRequest();
        $moduleData = $request->getAttribute('moduleData');
        $pageId = (int)($request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? 0);
        if (!$event->hasAccess() || $pageId === 0 || $moduleData?->get('pages') !== 'ai_seo') {
            return;
        }
        $languageService = $GLOBALS['LANG'] ?? $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
        $config = [
            'pageId' => $pageId,
            'lang' => max(0, (int)$moduleData->get('lang')),
            'rowsUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ai_seo_rows'),
            'generateUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ai_seo_generate'),
            'applyUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ai_seo_apply'),
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
                'noFields' => $languageService->sL(self::LL . 'error.noFields'),
            ],
        ];
        $this->pageRenderer->loadJavaScriptModule('@youngdogs/ai-seo/ai-seo-status-preview-v5.js');
        $event->addHeaderContent(
            '<div id="ai-seo-config" hidden data-config="'
            . htmlspecialchars(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES)
            . '"></div>'
        );
    }
}
