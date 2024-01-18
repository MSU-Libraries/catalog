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
      'ajaxhandler' => [
        'factories' => [
          'Catalog\\AjaxHandler\\GetItemStatuses' => 'VuFind\\AjaxHandler\\GetItemStatusesFactory',
        ],
        'aliases' => [
          'getItemStatuses' => 'Catalog\\AjaxHandler\\GetItemStatuses',
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
          'Catalog\\Form\\Handler\\FeedbackEmail' => 'VuFind\\Form\\Handler\\EmailFactory',
        ],
        'aliases' => [
          'VuFind\\Form\\Handler\\FeedbackEmail' => 'Catalog\\Form\\Handler\\FeedbackEmail',
          'FeedbackEmail' => 'Catalog\\Form\\Handler\\FeedbackEmail',
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
      'search_backend' => [
        'factories' => [
          'EDS' => 'Catalog\\Search\\Factory\\EdsBackendFactory',
        ],
      ],
    ],
  ],
  'service_manager' => [
    'factories' => [
      'Catalog\\Form\\Form' => 'VuFind\\Form\\FormFactory',
    ],
    'aliases' => [
      'VuFind\\Form\\Form' => 'Catalog\\Form\\Form',
    ],
  ],
];
