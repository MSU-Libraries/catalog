<?php

/**
 * Factory for record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Record_Data_Formatter
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter Wiki
 */

namespace Catalog\View\Helper\Root;

use Psr\Container\ContainerInterface;
use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

use function count;

/**
 * Extends the default display of the record page display
 *
 * @category VuFind
 * @package  Record_Data_Formatter
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter Wiki
 */
class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory
{
    /**
     *  MSUL PC-1347 Change order of TOC tab
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $helper = parent::__invoke($container, $requestedName, $options);
        $helper->setDefaults('toc', [$this, 'getDefaultTocSpecs']); // MSUL
        return $helper;
    }

    /**
     * MSUL - PC-936 add 'link' to the author output
     * Get the callback function for processing authors.
     *
     * @return callable
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    protected function getAuthorFunction(): callable
    {
        return function ($data, $options) {
            // Lookup array of singular/plural labels (note that Other is always
            // plural right now due to lack of translation strings).
            $labels = [
                'primary' => ['Main Author', 'Main Authors'],
                'corporate' => ['Corporate Author', 'Corporate Authors'],
                'secondary' => ['Other Authors', 'Other Authors'],
            ];
            // Lookup array of schema labels.
            $schemaLabels = [
                'primary' => 'author',
                'corporate' => 'creator',
                'secondary' => 'contributor',
            ];

            // Sort the data:
            $final = [];
            foreach ($data as $type => $values) {
                $final[] = [
                    'label' => $labels[$type][count($values) == 1 ? 0 : 1],
                    'values' => [$type => $values],
                    'options' => [
                        'pos' => $options['pos'] + $this->authorOrder[$type],
                        'renderType' => 'RecordDriverTemplate',
                        'template' => 'data-authors.phtml',
                        'context' => [
                            'type' => $type,
                            'schemaLabel' => $schemaLabels[$type],
                            'requiredDataFields' => [
                                ['name' => 'role', 'prefix' => 'CreatorRoles::'],
                                ['name' => 'link', 'prefix' => ''], // MSUL - PC-936 add 'link' to the author output
                            ],
                        ],
                    ],
                ];
            }
            return $final;
        };
    }

    /**
     * Get default specifications for displaying data in the toc tab.
     *
     * @return array
     */
    public function getDefaultTocSpecs()
    {
        $spec = new SpecBuilder();
        $spec->setTemplateLine('Summary', true, 'data-notes.phtml');  // MSUL
        return $spec->getArray();
    }
}
