<?php

use YoungDogs\AiSeo\Controller\GenerateController;
use YoungDogs\AiSeo\Controller\NewsAjaxController;

return [
    'ai_seo_rows' => [
        'path' => '/ai-seo/rows',
        'methods' => ['POST'],
        'target' => GenerateController::class . '::rowsAction',
        'inheritAccessFromModule' => 'web_info_overview',
    ],
    'ai_seo_generate' => [
        'path' => '/ai-seo/generate',
        'methods' => ['POST'],
        'target' => GenerateController::class . '::generateAction',
        'inheritAccessFromModule' => 'web_info_overview',
    ],
    'ai_seo_apply' => [
        'path' => '/ai-seo/apply',
        'methods' => ['POST'],
        'target' => GenerateController::class . '::applyAction',
        'inheritAccessFromModule' => 'web_info_overview',
    ],
    'ai_seo_news_rows' => [
        'path' => '/ai-seo/news/rows',
        'methods' => ['POST'],
        'target' => NewsAjaxController::class . '::rowsAction',
        'inheritAccessFromModule' => 'youngdogs_ai_seo_news',
    ],
    'ai_seo_news_generate' => [
        'path' => '/ai-seo/news/generate',
        'methods' => ['POST'],
        'target' => NewsAjaxController::class . '::generateAction',
        'inheritAccessFromModule' => 'youngdogs_ai_seo_news',
    ],
    'ai_seo_news_apply' => [
        'path' => '/ai-seo/news/apply',
        'methods' => ['POST'],
        'target' => NewsAjaxController::class . '::applyAction',
        'inheritAccessFromModule' => 'youngdogs_ai_seo_news',
    ],
];
