<?php

/**
 * Database service for Session.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Megan Schanz <schanzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Catalog\Db\Service;

use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\Feature\DeleteExpiredInterface;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Exception\SessionExpired as SessionExpiredException;
use VuFind\Log\LoggerAwareTrait;

use function in_array;

/**
 * Database service for Session.
 * MSUL Customizations for:
 *   - Skipping updates to the last_used time in the session based on configured paths
 *   - Retrying save attempts to the session when there is a failure
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Megan Schanz <schanzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SessionService extends \VuFind\Db\Service\SessionService implements
    LoggerAwareInterface,
    SessionServiceInterface,
    DeleteExpiredInterface
{
    use LoggerAwareTrait;

    protected $configReader = null;

    /**
     * Constructor
     *
     * @param EntityManager                $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager          $entityPluginManager Database entity plugin manager
     * @param PersistenceManager           $persistenceManager  Entity persistence manager
     * @param \VuFind\Config\PluginManager $configReader        Config reader object
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected EntityPluginManager $entityPluginManager,
        protected PersistenceManager $persistenceManager,
        \VuFind\Config\PluginManager $configReader = null,
    ) {
        // Get the config reader for the skip paths setting
        $this->configReader = $configReader;
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager);
    }

    /**
     * Retrieve data for the given session ID.
     *
     * @param string $sid      Session ID to retrieve
     * @param int    $lifetime Session lifetime (in seconds)
     *
     * @throws SessionExpiredException
     * @return string     Session data
     */
    public function readSession(string $sid, int $lifetime): string
    {
        $s = $this->getSessionById($sid);
        if (!$s) {
            throw new SessionExpiredException("Cannot read session $sid");
        }
        $lastused = $s->getLastUsed();
        // enforce lifetime of this session data
        if (!empty($lastused) && $lastused + $lifetime <= time()) {
            throw new SessionExpiredException('Session expired!');
        }

        $url_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $skip_paths = $this->getSkipPaths();

        // Skip updating the last_used if the request is from one of these urls
        if (!in_array($url_path, $skip_paths)) {
            $maxRetries = 3;
            $retryCount = 0;
            $updated = false;
            while ($retryCount < $maxRetries && !$updated) {
                try {
                    $s->setLastUsed(time());
                    $this->persistEntity($s);
                    $updated = true;
                } catch (\Exception $e) {
                    $this->logException($e, $retryCount, $maxRetries);
                    $retryCount++;
                    usleep(2 ** $retryCount * 1000);
                }
            }
        }
        $data = $s->getData();
        return $data ?? '';
    }

    /**
     * Store data for the given session ID.
     *
     * @param string $sid  Session ID to retrieve
     * @param string $data Data to store
     *
     * @return bool
     */
    public function writeSession(string $sid, string $data): bool
    {
        $maxRetries = 3;
        $retryCount = 0;
        $updated = false;
        while ($retryCount < $maxRetries && !$updated) {
            try {
                $session = $this->getSessionById($sid);
                $session->setLastUsed(time())->setData($data);
                $this->persistEntity($session);
                $updated = true;
            } catch (\Exception $e) {
                $this->logException($e, $retryCount, $maxRetries);
                $retryCount++;
                usleep(2 ** $retryCount * 1000);
            }
        }
        return $updated;
    }

    /**
     * Check the exception for a deadlock error to determine the appropriate
     * log message for debugging.
     *
     * @param Exception $e          Exception object to check how to specifically log
     * @param int       $retryCount Number of retries so far
     * @param int       $maxRetries Max number of retries
     *
     * @return void
     */
    public function logException(\Exception $e, int $retryCount = 0, int $maxRetries = 3): void
    {
        if (str_contains($e->getMessage(), 'Deadlock found') || $e->getCode() == 1213) {
            $msg = 'Deadlock detected saving session. ';
        } else {
            $msg = 'Error saving session. ';
        }
        $this->debug($msg . 'Retrying (Attempt ' . ($retryCount + 1) . '/' . $maxRetries . ')');
    }

    /**
     * Get the set of paths to skip updates to the last_used time for
     * from the config.
     *
     * @return array
     */
    protected function getSkipPaths()
    {
        $skipPaths = [];
        // If this ever turns into a PR, move this from 'msul' -> 'config'
        $msulConfig = $this->configReader->get('msul');
        if (isset($msulConfig) && isset($msulConfig->Session->skip_session_update_path)) {
            $skipPaths = $msulConfig->Session->skip_session_update_path->toArray() ?? [];
        }
        return $skipPaths;
    }
}
