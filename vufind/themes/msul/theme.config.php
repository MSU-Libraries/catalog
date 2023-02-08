<?php
return [
    'extends' => 'bootstrap3',

    'less' => array(
        'active' => true,
        'compiled.less'
    ),

    'helpers' => [
        'factories' => [
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'Catalog\View\Helper\Root\RecordDataFormatterFactory',
            'Catalog\View\Helper\Root\Record' => 'Catalog\View\Helper\Root\RecordFactory',
            'Catalog\View\Helper\Root\PrintArrayHtml' => 'Laminas\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'record' => 'Catalog\View\Helper\Root\Record',
            'printArrayHtml' => 'Catalog\View\Helper\Root\PrintArrayHtml',
        ]
    ],

];
