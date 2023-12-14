<?php

/**
 * EDS API Backend
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace Catalog\Backend\EDS;

use Laminas\Cache\Storage\StorageInterface as CacheInterface;

/**
 *  EDS API Backend
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends \VuFindSearch\Backend\EDS\Backend
{
    /**
     * Object cache (for storing authentication tokens)
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @param Connector                        $client  EdsApi client to use
     * @param RecordCollectionFactoryInterface $factory Record collection factory
     * @param CacheInterface                   $cache   Object cache
     * @param SessionContainer                 $session Session container
     * @param Config                           $config  Object representing EDS.ini
     * @param bool                             $isGuest Is the current user a guest?
     */
    public function __construct(
        Connector $client,
        RecordCollectionFactoryInterface $factory,
        CacheInterface $cache,
        SessionContainer $session,
        Config $config = null,
        $isGuest = true
    ) {
        // Save dependencies/incoming parameters:
        $this->client = $client;
        $this->setRecordCollectionFactory($factory);
        $this->cache = $cache;
        $this->session = $session;
        $this->isGuest = $isGuest;

        // Extract key values from configuration:
        $this->userName = $config->EBSCO_Account->user_name ?? null;
        $this->password = $config->EBSCO_Account->password ?? null;
        $this->ipAuth = $config->EBSCO_Account->ip_auth ?? false;
        $this->profile = $config->EBSCO_Account->profile ?? null;
        $this->orgId = $config->EBSCO_Account->organization_id ?? null;

        // Save default profile value, since profile property may be overridden:
        $this->defaultProfile = $this->profile;
    }
}
