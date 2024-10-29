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
      Catalog\Controller\MyResearchController::class => VuFind\Controller\AbstractBaseFactory::class,
      // TODO PC-895 To remove after PR - START
      Catalog\Controller\SearchController::class => VuFind\Controller\AbstractBaseFactory::class,
      Catalog\Controller\EdsController::class => VuFind\Controller\AbstractBaseFactory::class,
      Catalog\Controller\AlphabrowseController::class => VuFind\Controller\AbstractBaseFactory::class,
      // TODO PC-895 To remove after PR - END
    ],
    'aliases' => [
      VuFind\Controller\RecordController::class => Catalog\Controller\RecordController::class,
      VuFind\Controller\MyResearchController::class => Catalog\Controller\MyResearchController::class,
      // TODO PC-895 To remove after PR - START
      VuFind\Controller\SearchController::class => Catalog\Controller\SearchController::class,
      VuFind\Controller\EdsController::class => Catalog\Controller\EdsController::class,
      'EDS' => Catalog\Controller\EdsController::class,
      'eds' => Catalog\Controller\EdsController::class,
      VuFind\Controller\AlphabrowseController::class => Catalog\Controller\AlphabrowseController::class,
      // TODO PC-895 To remove after PR - END
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
          Catalog\Autocomplete\Solr::class => SolrFactory::class,
        ],
        'aliases' => [
          'solr' => Catalog\Autocomplete\Solr::class,
        ],
      ],
      'command' => [
        'factories' => [
          Catalog\Command\Util\IndexReservesCommand::class => VuFindConsole\Command\Util\AbstractSolrAndIlsCommandFactory::class,
        ],
        'aliases' => [
          'util/index_reserves' => Catalog\Command\Util\IndexReservesCommand::class,
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
          Catalog\ILS\Driver\Folio::class => VuFind\ILS\Driver\FolioFactory::class,
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
          'EDS' => Catalog\Search\Factory\EdsBackendFactory::class,
        ],
      ],
    ],
  ],
  'service_manager' => [
    'factories' => [
      Catalog\Form\Form::class => VuFind\Form\FormFactory::class,
      Catalog\Session\SessionManager::class => Catalog\Session\ManagerFactory::class,
    ],
    'aliases' => [
      VuFind\Form\Form::class => Catalog\Form\Form::class,
      Laminas\Session\SessionManager::class => Catalog\Session\SessionManager::class,
      VuFind\Session\ManagerFactory::class => Catalog\Session\ManagerFactory::class,
    ],
  ],
];
