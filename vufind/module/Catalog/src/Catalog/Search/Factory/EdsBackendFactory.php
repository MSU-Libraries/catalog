<?php

/**
 * Factory for EDS backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Search\Factory;

use Catalog\Backend\EDS\QueryBuilder;
use Catalog\Backend\EDS\Backend;
use VuFindSearch\Backend\EDS\Connector;

/**
 * Extending the backend to customize the builder for our query param overrides
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EdsBackendFactory extends \VuFind\Search\Factory\EdsBackendFactory
{
    /**
     * Create the EDS query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();
        return $builder;
    }

    /**
     * Create the EDS backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
     protected function createBackend(Connector $connector)
    {
        // Mimic what createBackend does in \VuFind\Search\Factory\EdsBackendFactory

        $auth = $this->getService(\Lmc\Rbac\Mvc\Service\AuthorizationService::class);
        $isGuest = !$auth->isGranted('access.EDSExtendedResults');
        $session = new \Laminas\Session\Container(
            'EBSCO',
            $this->getService(\Laminas\Session\SessionManager::class)
        );

        // Instantiate our module's custom Backend class
        $backend = new Backend(
            $connector,
            $this->createRecordCollectionFactory(),
            $this->getService(\VuFind\Cache\Manager::class)->getCache('object'),
            $session,
            $this->edsConfig,
            $isGuest
        );

        $backend->setAuthManager(
            $this->getService(\VuFind\Auth\Manager::class)
        );
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        $backend->setBackendType($this->getServiceName());

        return $backend;
    }
}
