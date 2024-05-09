<?php

/**
 * Factory for instantiating Session Manager
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2024.
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
 * @package  Session_Handlers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\Session;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for instantiating Session Manager
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ManagerFactory extends \VuFind\Session\ManagerFactory
{
    /**
     * Create an object
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
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        // Build configuration:
        $sessionConfig = new \Laminas\Session\Config\SessionConfig();
        $sessionConfig->setOptions($this->getOptions($container));

        // Build session manager and attach handler:
        $sessionManager = new $requestedName($sessionConfig);
        $sessionManager->setSaveHandler($this->getHandler($container));

        // MSUL Start
        $config = $container->get(\VuFind\Config\PluginManager::class)->get('config');
        $sessionManager->setBotNames(
            isset($config->Session->bot_agent)
            ? $config->Session->bot_agent->toArray()
            : []
        );
        $sessionManager->setBotSalt($config->Session->bot_salt ?? '');
        // MSUL End

        // Start up the session:
        $sessionManager->start();

        // Verify that any existing session has the correct path to avoid using
        // a cookie from a service higher up in the path hierarchy.
        $storage = new \Laminas\Session\Container('SessionState', $sessionManager);
        if (null !== $storage->cookiePath) {
            if ($storage->cookiePath != $sessionConfig->getCookiePath()) {
                // Disable writes temporarily to keep the existing session intact
                $sessionManager->getSaveHandler()->disableWrites();
                // Regenerate session ID and reset the session data
                $sessionManager->regenerateId(false);
                session_unset();
                $sessionManager->getSaveHandler()->enableWrites();
                $storage->cookiePath = $sessionConfig->getCookiePath();
            }
        } else {
            $storage->cookiePath = $sessionConfig->getCookiePath();
        }

        // Set session start time:
        if (empty($storage->sessionStartTime)) {
            $storage->sessionStartTime = time();
        }

        // Check if we need to immediately stop it based on the settings object
        // (which may have been informed by a controller that sessions should not
        // be written as part of the current process):
        $settings = $container->get(\VuFind\Session\Settings::class);
        if ($settings->setSessionManager($sessionManager)->isWriteDisabled()) {
            $sessionManager->getSaveHandler()->disableWrites();
        } else {
            // If the session is not disabled, we should set up the normal
            // shutdown function:
            $this->registerShutdownFunction($sessionManager);
        }

        return $sessionManager;
    }
}