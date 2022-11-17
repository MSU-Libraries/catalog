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
        ],
        'aliases' => [
            'record' => 'Catalog\View\Helper\Root\Record',
        ]
    ],

];
