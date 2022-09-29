<?php

namespace Catalog\Auth;

use VuFind\Exception\Auth as AuthException;

class LDAP extends \VuFind\Auth\LDAP
{

    /**
     * Communicate with LDAP and obtain user details.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    protected function checkLdap($username, $password)
    {
        // Establish a connection:
        $connection = $this->connect();

        // If necessary, bind in order to perform a search:
        $this->bindForSearch($connection, $username, $password);

        // Search for username
        $info = $this->findUsername($connection, $username);
        if ($info['count']) {
            $data = $this->validateCredentialsInLdap($connection, $info, $password);
            if ($data) {
                return $this->processLDAPUser($username, $data);
            }
        } else {
            $this->debug('user not found');
        }

        throw new AuthException('authentication_error_invalid');
    }

    /**
     * If configured, bind an administrative user in order to perform a search
     *
     * @param resource $connection LDAP connection
     *
     * @return void
     */
    protected function bindForSearch($connection, $username, $password)
    {
        // This is customized for MSU CampusAD
        if ($username != '' && $password != '') {
            $user = 'CAMPUSAD\\'.$username;
            $this->debug("binding as $user");
            $ldapBind = @ldap_bind($connection, $user, $password);
            if (!$ldapBind) {
                $this->debug('bind failed -- ' . ldap_error($connection));
                throw new AuthException('authentication_error_technical');
            }
        }
    }

}
