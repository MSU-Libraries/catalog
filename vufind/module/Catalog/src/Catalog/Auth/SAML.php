<?php

/**
 * SAML authentication module.
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
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Exception\Auth as AuthException;

use function count;
use function is_array;

/**
 * SAML authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SAML extends \VuFind\Auth\AbstractBase
{
    /**
     * This is array of attributes which $this->authenticate()
     * method should check for.
     *
     * WARNING: can contain only such attributes, which are writeable to user table!
     *
     * @var array attribsToCheck
     */
    protected $attribsToCheck = [
        'cat_username', 'cat_password', 'email', 'lastname', 'firstname',
        'college', 'major', 'home_library',
    ];

    /**
     * Session manager
     *
     * @var \Laminas\Session\ManagerInterface
     */
    protected $sessionManager;

    /**
     * Http Request object
     *
     * @var \Laminas\Http\PhpEnvironment\Request
     */
    protected $request;

    /**
     * Simple SAMPLE Auth handler
     *
     * @var \SimpleSAML\Auth\Simple
     */
    protected $auth;

    /**
     * Constructor
     *
     * @param \VuFind\Auth\ILSAuthenticator        $ilsAuthenticator Not used
     * @param \Laminas\Session\ManagerInterface    $sessionManager   Session
     *                                                               manager
     * @param \Laminas\Http\PhpEnvironment\Request $request          Http
     *                                                               request
     *                                                               object
     */
    public function __construct(
        protected ILSAuthenticator $ilsAuthenticator,
        \Laminas\Session\ManagerInterface $sessionManager,
        \Laminas\Http\PhpEnvironment\Request $request
    ) {
        require_once(getenv('SIMPLESAMLPHP_HOME') . '/lib/_autoload.php');
        $this->sessionManager = $sessionManager;
        $this->request = $request;
        $this->auth = new \SimpleSAML\Auth\Simple('default-sp');
    }

    /**
     * Set configuration.
     *
     * @param \Laminas\Config\Config $config Configuration to set
     *
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
    }

    /**
     * Validate configuration parameters.  This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        // Throw an exception if the required username setting is missing.
        $saml = $this->config->SAML;
        if (!isset($saml->username) || empty($saml->username)) {
            throw new AuthException(
                'SAML username is missing in your configuration file.'
            );
        }
    }

    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        // validate config before authentication
        $this->validateConfig();

        // Check SAML authentication
        if (!$this->auth->isAuthenticated()) {
            throw new AuthException('authentication_error_denied');
        }

        // Check if username is set.
        $config = $this->config->SAML;
        $username = $this->getAttribute($config['username']);
        if (empty($username)) {
            $details = $this->auth->getAttributes();
            $this->debug(
                "No username attribute ({$config['username']}) present in request: "
                . print_r($details, true)
            );
            throw new AuthException('authentication_error_admin');
        }

        // Check if required attributes match up:
        foreach ($this->getRequiredAttributes() as $key => $value) {
            if (!preg_match("/$value/", $this->getAttribute($key))) {
                $details = $this->auth->getAttributes();
                $this->debug(
                    "Attribute '$key' does not match required value '$value' in"
                    . ' request: ' . print_r($details, true)
                );
                throw new AuthException('authentication_error_denied');
            }
        }

        // If we made it this far, we should log in the user!
        $user = $this->getOrCreateUserByUsername($username);

        // Variable to hold catalog password (handled separately from other
        // attributes since we need to use saveCredentials method to store it):
        $catPassword = null;

        // Has the user configured attributes to use for populating the user table?
        foreach ($this->attribsToCheck as $attribute) {
            if (isset($config[$attribute])) {
                $value = $this->getAttribute($config[$attribute]);
                if ($attribute == 'email') {
                    $this->getUserService()->updateUserEmail($user, $value);
                } elseif (
                    $attribute == 'cat_username' && isset($config['prefix'])
                    && !empty($value)
                ) {
                    $user->cat_username = $config['prefix'] . '.' . $value;
                } elseif ($attribute == 'cat_password') {
                    $catPassword = $value;
                } else {
                    $user->$attribute = $value;
                }
            }
        }

        // Save credentials if applicable. Note that we want to allow empty
        // passwords (see https://github.com/vufind-org/vufind/pull/532), but
        // we also want to be careful not to replace a non-blank password with a
        // blank one in case the auth mechanism fails to provide a password on
        // an occasion after the user has manually stored one. (For discussion,
        // see https://github.com/vufind-org/vufind/pull/612). Note that in the
        // (unlikely) scenario that a password can actually change from non-blank
        // to blank, additional work may need to be done here.
        if (!empty($user->cat_username)) {
            $this->ilsAuthenticator->saveUserCatalogCredentials(
                $user,
                $user->cat_username,
                empty($catPassword) ? $this->ilsAuthenticator->getCatPasswordForUser($user) : $catPassword
            );
        }

        // Save and return the user object:
        $user->save();
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        $config = $this->config->SAML;
        $samlTarget = $config->target ?? $target;
        $append = (str_contains($samlTarget, '?')) ? '&' : '?';
        // Adding the auth_method parameter makes it possible to handle logins when
        // using an auth method that proxies others.
        $samlTarget .= $append . 'auth_method=SAML';
        return $this->auth->getLoginURL($samlTarget);
    }

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired()
    {
        $config = $this->config->SAML;
        if (
            !($config->singleLogout ?? false)
            || !($config->checkExpiredSession ?? true)
        ) {
            return false;
        }
        return !$this->auth->isAuthenticated();
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @return string     Redirect URL (usually same as $url, but modified in
     * some authentication modules).
     */
    public function logout($url)
    {
        // If single log-out is enabled, use a special URL:
        $config = $this->config->SAML;
        if ($config->singleLogout ?? false) {
            $url = $this->auth->getLogoutURL($url);
        }
        // Send back the redirect URL (possibly modified):
        return $url;
    }

    /**
     * Connect user authenticated by SAML to library card.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request        Request object
     * containing account credentials.
     * @param \VuFind\Db\Row\User                  $connectingUser Connect newly
     * created library card to this user.
     *
     * @return void
     */
    public function connectLibraryCard($request, $connectingUser)
    {
        $config = $this->config->SAML;
        $username = $this->getAttribute($config['cat_username']);
        if (!$username) {
            throw new \VuFind\Exception\LibraryCard('Missing username');
        }
        $prefix = $config['prefix'] ?? '';
        if (!empty($prefix)) {
            $username = $config['prefix'] . '.' . $username;
        }
        $password = $config['cat_password'] ?? null;
        $this->getDbService(UserCardServiceInterface::class)->persistLibraryCardData(
            $connectingUser,
            null,
            $config['prefix'],
            $username,
            $password
        );
    }

    /**
     * Extract required user attributes from the configuration.
     *
     * @return array      Only username and attribute-related values
     * @throws AuthException
     */
    protected function getRequiredAttributes()
    {
        $config = $this->config->SAML;

        // Special case -- store username as-is to establish return array:
        $sortedUserAttributes = [];

        // Now extract user attribute values:
        foreach ($config as $key => $value) {
            if (preg_match('/userattribute_[0-9]{1,}/', $key)) {
                $valueKey = 'userattribute_value_' . substr($key, 14);
                $sortedUserAttributes[$value] = $config[$valueKey] ?? null;

                // Throw an exception if attributes are missing/empty.
                if (empty($sortedUserAttributes[$value])) {
                    throw new AuthException(
                        'User attribute value of ' . $value . ' is missing!'
                    );
                }
            }
        }

        return $sortedUserAttributes;
    }

    /**
     * Extract attribute from request.
     *
     * @param string $attribute Attribute name
     *
     * @return string attribute value
     */
    protected function getAttribute($attribute)
    {
        $attrs = $this->auth->getAttributes();
        $value = $attrs[$attribute];
        if (is_array($value)) {
            if (count($value) > 0) {
                $value = $value[0];
            } else {
                $value = null;
            }
        }
        return $value;
    }
}
