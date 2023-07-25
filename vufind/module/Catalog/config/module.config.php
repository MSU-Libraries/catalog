<?php

return [
  'router' => [
    'routes' => [
      'record-getthis' => [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' => [
          'route' => '/Record/:id/GetThis',
          'defaults' => [
            'controller' => 'Record',
            'action' => 'GetThis',
          ],
        ],
      ],
    ],
  ],
  'controllers' => [
    'factories' => [
      'Catalog\\Controller\\RecordController' => 'VuFind\\Controller\\AbstractBaseWithConfigFactory',
      'Catalog\\Controller\\MyResearchController' => 'VuFind\\Controller\\AbstractBaseFactory',
    ],
    'aliases' => [
      'VuFind\\Controller\\RecordController' => 'Catalog\\Controller\\RecordController',
      'VuFind\\Controller\\MyResearchController' => 'Catalog\\Controller\\MyResearchController',
    ],
  ],
  'vufind' => [
    'plugin_managers' => [
      'search_backend' => [
        'factories' => [
          'EDS' => 'Catalog\\Search\\Factory\\EdsBackendFactory',
        ],
      ],
      'recorddriver' => [
        'factories' => [
          'Catalog\\RecordDriver\\SolrMarc' => 'VuFind\\RecordDriver\\SolrDefaultFactory',
          'Catalog\\RecordDriver\\SolrDefault' => 'VuFind\\RecordDriver\\SolrDefaultFactory',
        ],
        'aliases' => [
          'VuFind\\RecordDriver\\SolrMarc' => 'Catalog\\RecordDriver\\SolrMarc',
          'VuFind\\RecordDriver\\SolrDefault' => 'Catalog\\RecordDriver\\SolrDefault',
        ],
        'delegators' => [
          'Catalog\\RecordDriver\\SolrMarc' => [
            0 => 'VuFind\\RecordDriver\\IlsAwareDelegatorFactory',
          ],
        ],
      ],
      'auth' => [
        'factories' => [
          'Catalog\\Auth\\SAML' => 'Catalog\\Auth\\SAMLFactory',
          'Catalog\\Auth\\Okapi' => 'Catalog\\Auth\\OkapiFactory',
        ],
        'aliases' => [
          'saml' => 'Catalog\\Auth\\SAML',
          'okapi' => 'Catalog\\Auth\\Okapi',
        ],
      ],
      'ils_driver' => [
        'factories' => [
          'Catalog\\ILS\\Driver\\Folio' => 'VuFind\\ILS\\Driver\\FolioFactory',
          'Catalog\\ILS\\Driver\\MultiBackend' => 'VuFind\\ILS\\Driver\\MultiBackendFactory',
        ],
        'aliases' => [
          'folio' => 'Catalog\\ILS\\Driver\\Folio',
          'multibackend' => 'Catalog\\ILS\\Driver\\MultiBackend',
        ],
      ],
      'command' => [
        'factories' => [
          'Catalog\\Command\\Util\\IndexReservesCommand' => 'VuFindConsole\\Command\\Util\\AbstractSolrAndIlsCommandFactory',
        ],
        'aliases' => [
          'util/index_reserves' => 'Catalog\\Command\\Util\\IndexReservesCommand',
        ],
      ],
      'form_handler' => [
        'factories' => [
          'Catalog\\Form\\Handler\\Email' => 'VuFind\\Form\\Handler\\EmailFactory',
        ],
        'aliases' => [
          'VuFind\\Form\\Handler\\Email' => 'Catalog\\Form\\Handler\\Email',
        ],
      ],
    ],
  ],
  # Example override for VuFind service
  #'service_manager' => [
  #  'factories' => [
  #    'Catalog\\Mailer\\Mailer' => 'VuFind\\Mailer\\Factory',
  #  ],
  #  'aliases' => [
  #    'VuFind\\Mailer\\Mailer' => 'Catalog\\Mailer\\Mailer',
  #  ],
  #],
];
