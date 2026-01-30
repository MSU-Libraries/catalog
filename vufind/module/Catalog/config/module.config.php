<?php
return [
  'router' => [
    'routes' => [
      'record-getthis' => [
        'type' => Laminas\Router\Http\Segment::class,
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
      Catalog\Controller\RecordController::class => VuFind\Controller\AbstractBaseWithConfigFactory::class,
      Catalog\Controller\MyResearchController::class => VuFind\Controller\MyResearchControllerFactory::class,
    ],
    'aliases' => [
      VuFind\Controller\RecordController::class => Catalog\Controller\RecordController::class,
    ],
  ],
  'vufind' => [
    'plugin_managers' => [
      'ajaxhandler' => [
        'factories' => [
          Catalog\AjaxHandler\GetItemStatuses::class => VuFind\AjaxHandler\GetItemStatusesFactory::class,
          Catalog\AjaxHandler\GetLicenseAgreement::class => Catalog\AjaxHandler\GetLicenseAgreementFactory::class,
        ],
        'aliases' => [
          'getItemStatuses' => Catalog\AjaxHandler\GetItemStatuses::class,
          'getLicenseAgreement' => Catalog\AjaxHandler\GetLicenseAgreement::class,
        ],
      ],
      'auth' => [
        'factories' => [
          Catalog\Auth\SAML::class => Catalog\Auth\SAMLFactory::class,
          Catalog\Auth\Okapi::class => Catalog\Auth\OkapiFactory::class,
        ],
        'aliases' => [
          'saml' => Catalog\Auth\SAML::class,
          'okapi' => Catalog\Auth\Okapi::class,
        ],
      ],
      'autocomplete' => [
        'factories' => [
          Catalog\Autocomplete\Solr::class => VuFind\Autocomplete\SolrFactory::class,
        ],
        'aliases' => [
          'solr' => Catalog\Autocomplete\Solr::class,
        ],
      ],
      'form_handler' => [
        'factories' => [
          Catalog\Form\Handler\FeedbackEmail::class => VuFind\Form\Handler\EmailFactory::class,
        ],
        'aliases' => [
          'FeedbackEmail' => Catalog\Form\Handler\FeedbackEmail::class,
        ],
      ],
      'ils_driver' => [
        'factories' => [
          Catalog\ILS\Driver\Folio::class => Catalog\ILS\Driver\FolioFactory::class,
          Catalog\ILS\Driver\MultiBackend::class => VuFind\ILS\Driver\MultiBackendFactory::class,
        ],
        'aliases' => [
          'folio' => Catalog\ILS\Driver\Folio::class,
          'multibackend' => Catalog\ILS\Driver\MultiBackend::class,
        ],
      ],
      'recorddriver' => [
        'factories' => [
          Catalog\RecordDriver\SolrMarc::class => VuFind\RecordDriver\SolrDefaultFactory::class,
          Catalog\RecordDriver\SolrDefault::class => VuFind\RecordDriver\SolrDefaultFactory::class,
        ],
        'aliases' => [
          VuFind\RecordDriver\SolrMarc::class => Catalog\RecordDriver\SolrMarc::class,
          VuFind\RecordDriver\SolrDefault::class => Catalog\RecordDriver\SolrDefault::class,
        ],
        'delegators' => [
          Catalog\RecordDriver\SolrMarc::class => [
            0 => VuFind\RecordDriver\IlsAwareDelegatorFactory::class,
          ],
        ],
      ],
      'recordtab' => [
        'factories' => [
            Catalog\RecordTab\Description::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            Catalog\RecordTab\HoldingsILS::class => VuFind\RecordTab\HoldingsILSFactory::class,
            Catalog\RecordTab\HoldingsWorldCat2::class => VuFind\RecordTab\HoldingsWorldCat2Factory::class,
            Catalog\RecordTab\TOC::class => VuFind\RecordTab\TOCFactory::class,
        ],
        'aliases' => [
            VuFind\RecordTab\Description::class => Catalog\RecordTab\Description::class,
            VuFind\RecordTab\HoldingsILS::class => Catalog\RecordTab\HoldingsILS::class,
            VuFind\RecordTab\HoldingsWorldCat2::class => Catalog\RecordTab\HoldingsWorldCat2::class,
            VuFind\RecordTab\TOC::class => Catalog\RecordTab\TOC::class,
        ],
      ],
      'search_backend' => [
        'factories' => [
          'EDS' => Catalog\Search\Factory\EdsBackendFactory::class,
          'Solr' => Catalog\Search\Factory\SolrDefaultBackendFactory::class,
        ],
      ],
      'search_results' => [
        'factories' => [
          Catalog\Search\Solr\Results::class => VuFind\Search\Solr\ResultsFactory::class,
          Catalog\Search\EDS\Results::class => Catalog\Search\EDS\EDSSearchResultsFactory::class,
        ],
        'aliases' => [
          VuFind\Search\Solr\Results::class => Catalog\Search\Solr\Results::class,
          'solr' => Catalog\Search\Solr\Results::class,
          VuFind\Search\EDS\Results::class => Catalog\Search\EDS\Results::class,
          'eds' => Catalog\Search\EDS\Results::class,
        ],
      ],
      'db_service' => [
        'factories' => [
           \Catalog\Db\Service\SessionService::class => \Catalog\Db\Service\AbstractDbServiceFactory::class,
        ],
        'aliases' => [
          \VuFind\Db\Service\SessionService::class => \Catalog\Db\Service\SessionService::class,
          'session' => \Catalog\Db\Service\SessionService::class,
        ],
      ],
    ],
  ],
  'service_manager' => [
    'factories' => [
      Catalog\Form\Form::class => VuFind\Form\FormFactory::class,
    ],
    'aliases' => [
      VuFind\Form\Form::class => Catalog\Form\Form::class,
    ],
  ],
];
