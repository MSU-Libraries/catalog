<?php
return [
    'extends' => 'bootstrap3',

    'less' => [
        'active' => true,
        'compiled.less',
    ],
    'js' => [
        ['file' => 'get-this-dropdown.js', 'priority' => 450],
        ['file' => 'check_item_statuses.js', 'priority' => 450],
    ],

    'helpers' => [
        'factories' => [
            'Catalog\View\Helper\Root\Auth' => 'VuFind\View\Helper\Root\AuthFactory',
            'Catalog\View\Helper\Root\BannerNotices' => 'Catalog\View\Helper\Root\BannerNoticesFactory',
            'Catalog\View\Helper\Root\PrintArrayHtml' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'Catalog\View\Helper\Root\Record' => 'Catalog\View\Helper\Root\RecordFactory',
            'Catalog\View\Helper\Root\SearchBox' => 'VuFind\View\Helper\Root\SearchBoxFactory',
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'Catalog\View\Helper\Root\RecordDataFormatterFactory',
        ],
        'aliases' => [
            'auth' => 'Catalog\View\Helper\Root\Auth',
            'bannerNotices' => 'Catalog\View\Helper\Root\BannerNotices',
            'printArrayHtml' => 'Catalog\View\Helper\Root\PrintArrayHtml',
            'record' => 'Catalog\View\Helper\Root\Record',
            'searchbox' => 'Catalog\View\Helper\Root\SearchBox',
        ],
    ],

    'icons' => [
        'aliases' => [
            /**
             * Icons can be assigned or overriden here
             *
             * Format: 'icon' => [set:]icon[:extra_classes]
             * Icons assigned without set will use the defaultSet.
             * In order to specify extra CSS classes, you must also specify a set.
             */
            'send-sms' => 'FontAwesome:mobile',
            'user-list-add' => 'FontAwesome:star',

        ],
    ],

];
