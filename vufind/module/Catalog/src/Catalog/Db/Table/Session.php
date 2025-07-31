<?php

/**
 * Table Definition for session
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Db_Table
 * @author   Megan Schanz <schanzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use VuFind\Exception\SessionExpired as SessionExpiredException;

use function in_array;

/**
 * Table Definition for session
 * MSUL Customizations for:
 *   - Skipping updates to the last_used time in the session based on configured paths
 *   - Retrying save attempts to the session when there is a failure
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Megan Schanz <schanzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Session extends \VuFind\Db\Table\Session implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    protected $configReader = null;

    /**
     * Constructor
     *
     * @param Adapter                      $adapter      Database adapter
     * @param PluginManager                $tm           Table manager
     * @param array                        $cfg          Laminas configuration
     * @param RowGateway                   $rowObj       Row prototype object (null for default)
     * @param \VuFind\Config\PluginManager $configReader Config reader object
     * @param string                       $table        Name of database table to interface with
     */
    public function __construct(
        \Laminas\Db\Adapter\Adapter $adapter,
        \VuFind\Db\Table\PluginManager $tm,
        $cfg,
        ?\VuFind\Db\Row\RowGateway $rowObj = null,
        \VuFind\Config\PluginManager $configReader = null,
        $table = 'session'
    ) {
        // Get the config reader for the skip paths setting
        $this->configReader = $configReader;
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
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
    public function readSession($sid, $lifetime)
    {
        $s = $this->getBySessionId($sid);

        // enforce lifetime of this session data
        if (!empty($s->last_used) && $s->last_used + $lifetime <= time()) {
            throw new SessionExpiredException('Session expired!');
        }

        $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $skip_paths = $this->getSkipPaths();

        // Skip updating the last_used if the request is from one of these urls
        if (!in_array($url_path, $skip_paths)) {
            $maxRetries = 3;
            $retryCount = 0;
            $updated = false;
            while ($retryCount < $maxRetries && !$updated) {
                try {
                    $s->last_used = time();
                    $s->save();
                    $updated = true;
                } catch (Exception $e) {
                    $this->logException($e);
                    $retryCount++;
                    usleep(2 ** $retryCount * 1000);
                }
            }
        }
        return empty($s->data) ? '' : $s->data;
    }

    /**
     * Store data for the given session ID.
     *
     * @param string $sid  Session ID to retrieve
     * @param string $data Data to store
     *
     * @return void
     */
    public function writeSession($sid, $data)
    {
        $maxRetries = 3;
        $retryCount = 0;
        $updated = false;
        while ($retryCount < $maxRetries && !$updated) {
            try {
                $s = $this->getBySessionId($sid);
                $s->last_used = time();
                $s->data = $data;
                $s->save();
                $updated = true;
            } catch (Exception $e) {
                $this->logException($e);
                $retryCount++;
                usleep(2 ** $retryCount * 1000);
            }
        }
    }

    /**
     * Check the exception for a deadlock error to determine the appropriate
     * log message for debugging.
     *
     * @param Exception $e Exception object to check how to specifically log
     *
     * @return void
     */
    protected function logException(Exception $e)
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
