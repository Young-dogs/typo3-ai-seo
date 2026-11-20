<?php

declare(strict_types=1);

use YoungDogs\AiSeo\Controller\NewsOverviewController;

return [
    'youngdogs_ai_seo_news' => [
        'parent' => 'content_status',
        'position' => ['after' => 'web_info_translations'],
        'access' => 'user',
        'path' => '/module/youngdogs/ai-seo/news',
        'packageName' => 'young-dogs/ai-seo',
        'iconIdentifier' => 'actions-wand-sparkles',
        'labels' => 'LLL:EXT:youngdogs_ai_seo/Resources/Private/Language/locallang_news.xlf',
        'routes' => [
            '_default' => [
                'target' => NewsOverviewController::class . '::handleRequest',
            ],
        ],
        'moduleData' => [
            'depth' => 0,
            'lang' => 0,
        ],
    ],
];
