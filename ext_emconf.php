<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Young Dogs AI SEO',
    'description' => 'Adds an AI SEO view with one-click metadata generation to the Content > Status > Pagetree Overview module.',
    'version' => '1.0.1',
    'category' => 'backend',
    'state' => 'stable',
    'author' => 'Young Dogs',
    'author_email' => 'dev@young-dogs.com',
    'author_company' => 'Young Dogs',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '14.3.0-14.3.99',
            'backend' => '14.3.0-14.3.99',
            'info' => '14.3.0-14.3.99',
        ],
    ],
];
