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
        ['file' => 'get_license_agreement.js', 'priority' => 450],
    ],
    'favicon' => 'msul-favicon.ico',
    'helpers' => [
        'factories' => [
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'Catalog\View\Helper\Root\RecordDataFormatterFactory',
            'Catalog\View\Helper\Root\Record' => 'Catalog\View\Helper\Root\RecordFactory',
            'Catalog\View\Helper\Root\Auth' => 'VuFind\View\Helper\Root\AuthFactory',
            'Catalog\View\Helper\Root\PrintArrayHtml' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'Catalog\View\Helper\Root\Notices' => 'Catalog\View\Helper\Root\NoticesFactory',
            'Catalog\View\Helper\Root\LocationNotices' => 'Catalog\View\Helper\Root\LocationNoticesFactory',
        ],
        'aliases' => [
            'record' => 'Catalog\View\Helper\Root\Record',
            'printArrayHtml' => 'Catalog\View\Helper\Root\PrintArrayHtml',
            'auth' => 'Catalog\View\Helper\Root\Auth',
            'Notices' => 'Catalog\View\Helper\Root\Notices',
            'locationNotices' => 'Catalog\View\Helper\Root\LocationNotices',
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
