<?php
return [
    'extends' => 'bootstrap5',

    'js' => [
        ['file' => 'get-this-dropdown.js', 'priority' => 450],
        ['file' => 'check_item_statuses.js', 'priority' => 450],
        ['file' => 'get_license_agreement.js', 'priority' => 450],
    ],
    'favicon' => 'msul-favicon.ico',
    'helpers' => [
        'factories' => [
            VuFind\View\Helper\Root\RecordDataFormatter::class => Catalog\View\Helper\Root\RecordDataFormatterFactory::class,
            Catalog\View\Helper\Root\Record::class => Catalog\View\Helper\Root\RecordFactory::class,
            Catalog\View\Helper\Root\Auth::class => Catalog\View\Helper\Root\AuthFactory::class,
            Catalog\View\Helper\Root\PrintArrayHtml::class => Laminas\ServiceManager\Factory\InvokableFactory::class,
            Catalog\View\Helper\Root\Notices::class => Catalog\View\Helper\Root\NoticesFactory::class,
            Catalog\View\Helper\Root\LocationNotices::class => Catalog\View\Helper\Root\LocationNoticesFactory::class,
            Catalog\View\Helper\Root\AlphaBrowse::class => VuFind\View\Helper\Root\AlphaBrowseFactory::class,
        ],
        'aliases' => [
            'record' => Catalog\View\Helper\Root\Record::class,
            'printArrayHtml' => Catalog\View\Helper\Root\PrintArrayHtml::class,
            'auth' => Catalog\View\Helper\Root\Auth::class,
            'Notices' => Catalog\View\Helper\Root\Notices::class,
            'locationNotices' => Catalog\View\Helper\Root\LocationNotices::class,
            'alphabrowse' => Catalog\View\Helper\Root\AlphaBrowse::class,
        ],
    ],

    'icons' => [
        'aliases' => [
            /**
             * Icons can be assigned or overridden here
             *
             * Format: 'icon' => [set:]icon[:extra_classes]
             * Icons assigned without set will use the defaultSet.
             * In order to specify extra CSS classes, you must also specify a set.
             */
            'send-sms' => 'FontAwesome:mobile',
            'user-list-add' => 'FontAwesome:star',
            'export' => 'FontAwesome:arrow-right',
        ],
    ],
];
