<?php
/**
 * Okapi authentication module.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Authentication
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
namespace Catalog\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;

/**
 * Folio Okapi authentication module.
 * This is independant from the ILS/Folio authentication method, so that it can
 * use okapi_login=true while still using okapi_login=false for other login options.
 * It takes the Folio.ini config and only changes okapi_login.
 * As opposed to Folio.php, this only handles authentication, not ILS functions.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class Okapi extends \VuFind\Auth\AbstractBase
{ 
    /**
     * The Folio driver.
     *
     * @var object
     */
    protected $driver = null;

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection           $connection    ILS connection to set
     * @param \VuFind\ILS\Driver\PluginManager $driverManager Driver plugin manager
     * @param \VuFind\Config\PluginManager     $configReader  Configuration loader
     */
    public function __construct(
        \VuFind\ILS\Connection $connection,
        \VuFind\ILS\Driver\PluginManager $driverManager,
        \VuFind\Config\PluginManager $configReader
    ) {
        $this->catalog = $connection;
        $this->driver = clone $driverManager->get('Folio');
        $config = $configReader->get('Folio');
        $driverConfig = is_object($config) ? $config->toArray() : [];
        $driverConfig['User']['okapi_login'] = true;
        $this->driver->setConfig($driverConfig);
        $this->driver->init();
    }

    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $username = trim($request->getPost()->get('username'));
        $password = trim($request->getPost()->get('password'));
        $loginMethod = 'password';

        return $this->handleLogin($username, $password, $loginMethod);
    }

    /**
     * Handle the actual login with Folio.
     *
     * @param string $username    User name
     * @param string $password    Password
     * @param string $loginMethod Login method
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function handleLogin($username, $password, $loginMethod)
    {
        if ($username == '' || ('password' === $loginMethod && $password == '')) {
            throw new AuthException('authentication_error_blank');
        }

        // Connect to catalog:
        try {
            $patron = $this->driver->patronLogin($username, $password);
        } catch (AuthException $e) {
            // Pass Auth exceptions through
            throw $e;
        } catch (\Exception $e) {
            throw new AuthException('authentication_error_technical');
        }

        if ($patron) {
            return $this->processILSUser($patron);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
    }

    /**
     * Update the database using details from Folio, then return the User object.
     *
     * @param array $info User details returned by the Folio driver.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function processILSUser($info)
    {
        // Figure out which field of the response to use as an identifier; fail
        // if the expected field is missing or empty:
        $usernameField = 'cat_username';
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new AuthException('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $userTable = $this->getUserTable();
        if (!empty($info['id'])) {
            $user = $userTable->getByCatalogId($info['id']);
            if (empty($user)) {
                $user = $userTable->getByUsername($info[$usernameField]);
                $user->saveCatalogId($info['id']);
            }
        } else {
            $user = $userTable->getByUsername($info[$usernameField]);
        }

        // No need to store the ILS password in VuFind's main password field:
        $user->password = '';

        // Update user information based on ILS data:
        $fields = ['firstname', 'lastname', 'major', 'college'];
        foreach ($fields as $field) {
            $user->$field = $info[$field] ?? ' ';
        }
        $user->updateEmail($info['email'] ?? '');

        // Update the user in the database, then return it to the caller:
        $user->saveCredentials(
            $info['cat_username'] ?? ' ',
            $info['cat_password'] ?? ' '
        );

        return $user;
    }

}
