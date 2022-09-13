<?php
return [
    'extends' => 'bootstrap3',

	'less' => array(
	  'active' => true,
	  'compiled.less'
	),

    'helpers' => [
      'factories' => [
        'Vufind\View\Helper\Root\RecordDataFormatter' => 'Catalog\View\Helper\Root\RecordDataFormatterFactory'
      ]
    ]

];
