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
    ],
    'aliases' => [
      VuFind\Controller\RecordController::class => Catalog\Controller\RecordController::class,
      VuFind\Controller\MyResearchController::class => Catalog\Controller\MyResearchController::class,
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
    ],
    'aliases' => [
      VuFind\Form\Form::class => Catalog\Form\Form::class,
    ],
  ],
];
