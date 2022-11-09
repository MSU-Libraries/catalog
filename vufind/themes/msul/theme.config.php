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
        'VuFind\View\Helper\Root\Record' => 'Catalog\View\Helper\Root\Record',
      ]
    ]

];
