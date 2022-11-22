<?php

return array (
  'router' => [
    'routes' => [
      'record-getthis' => [
        'type'    => 'Laminas\Router\Http\Segment',
        'options' => [
          'route'    => '/Record/[:id]/GetThis',
          'defaults' => [
              'controller' => 'Record',
              'action'     => 'GetThis',
          ],
        ],
      ],
    ],
  ],
  'controllers' => [
    'factories' => [
      'Catalog\\Controller\\RecordController' => 'VuFind\\Controller\\AbstractBaseWithConfigFactory',
    ],
    'aliases' => [
      'VuFind\\Controller\\RecordController' => 'Catalog\\Controller\\RecordController',
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
