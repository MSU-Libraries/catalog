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
      VuFind\Controller\RecordController::class => Catalog\Controller\RecordController::class,
      'VuFind\\Controller\\MyResearchController' => 'Catalog\\Controller\\MyResearchController',
    ],
  ],
  'vufind' => [
    'plugin_managers' => [
      'ajaxhandler' => [
        'factories' => [
          'Catalog\\AjaxHandler\\GetItemStatuses' => 'VuFind\\AjaxHandler\\GetItemStatusesFactory',
          'Catalog\\AjaxHandler\\GetLicenseAgreement' => 'Catalog\\AjaxHandler\\GetLicenseAgreementFactory',
        ],
        'aliases' => [
          'getItemStatuses' => 'Catalog\\AjaxHandler\\GetItemStatuses',
          'getLicenseAgreement' => 'Catalog\\AjaxHandler\\GetLicenseAgreement',
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
      'recordtab' => [
        'factories' => [
            Catalog\RecordTab\Description::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            Catalog\RecordTab\HoldingsILS::class => VuFind\RecordTab\HoldingsILSFactory::class,
            Catalog\RecordTab\HoldingsWorldCat::class => VuFind\RecordTab\HoldingsWorldCatFactory::class,
            Catalog\RecordTab\TOC::class => VuFind\RecordTab\TOCFactory::class,
        ],
        'aliases' => [
          VuFind\RecordTab\Description::class => Catalog\RecordTab\Description::class,
          VuFind\RecordTab\HoldingsILS::class => Catalog\RecordTab\HoldingsILS::class,
          VuFind\RecordTab\HoldingsWorldCat::class => Catalog\RecordTab\HoldingsWorldCat::class,
          VuFind\RecordTab\TOC::class => Catalog\RecordTab\TOC::class,
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
