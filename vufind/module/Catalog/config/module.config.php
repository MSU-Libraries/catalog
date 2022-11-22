<?php

return array (
  'router' => [
    'routes' => [
      'getthis' => [
        'type' => 'Laminas\Router\Http\Segment',
        'options' => [
          'route' => '/CatalogRecord/GetThis/[:id]',
          'constraints' => [
            'id' => '[a-zA-Z][a-zA-Z0-9._-]*',
          ],
          'defaults' => [
            'controller' => 'Catalog\Controller\CatalogRecordController',
            'action' => 'GetThis',
          ],
        ],
      ],
    ],
  ],
  'controllers' => [
    'factories' => [
      'Catalog\Controller\CatalogRecordController' => 'VuFind\Controller\AbstractBaseFactory',
    ],
  ],
  'vufind' => 
  array (
    'plugin_managers' => 
    array (
      'recorddriver' => 
      array (
        'factories' => 
        array (
          'Catalog\\RecordDriver\\SolrMarc' => 'VuFind\\RecordDriver\\SolrDefaultFactory',
        ),
        'aliases' => 
        array (
          'VuFind\\RecordDriver\\SolrMarc' => 'Catalog\\RecordDriver\\SolrMarc',
        ),
        'delegators' => 
        array (
          'Catalog\\RecordDriver\\SolrMarc' => 
          array (
            0 => 'VuFind\\RecordDriver\\IlsAwareDelegatorFactory',
          ),
        ),
      ),
      'auth' => 
      array (
        'factories' => 
        array (
          'Catalog\\Auth\\SAML' => 'Catalog\\Auth\\SAMLFactory',
          'Catalog\\Auth\\Okapi' => 'Catalog\\Auth\\OkapiFactory',
        ),
        'aliases' => 
        array (
          'saml' => 'Catalog\\Auth\\SAML',
          'okapi' => 'Catalog\\Auth\\Okapi',
        )
      ),
      'ils_driver' =>
      array (
        'factories' =>
        array (
          'Catalog\\ILS\\Driver\\Folio' => 'VuFind\\ILS\\Driver\\FolioFactory',
        ),
        'aliases' => 
        array (
          'folio' => 'Catalog\\ILS\\Driver\\Folio',
        ),
      ),
      'command' =>
      array (
        'factories' => 
        array (
          'Catalog\\VuFindConsole\\Command\\Util\\IndexReservesCommand' =>
            'VuFindConsole\\Command\\Util\\AbstractSolrAndIlsCommandFactory',
        ),
        'aliases' => 
        array (
          'util/index_reserves' => 'Catalog\\VuFindConsole\\Command\\Util\\IndexReservesCommand',
        ),
      ),
    ),
  ),
);
