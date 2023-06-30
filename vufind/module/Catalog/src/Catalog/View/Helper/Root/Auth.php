<?php
namespace Catalog\View\Helper\Root;

use Laminas\Config\Reader\Ini as IniReader;
use Laminas\Config\Config;
use VuFind\Config\Locator as Locator;

class Auth extends \VuFind\View\Helper\Root\Auth
{
    /**
     * Determines if the given patron is a community borrower based 
     * on the username field from their patron data.
     * 
     * @return bool
     */
    public function isCommunityBorrower()
    {
        $isCommunityBorrower = false;
        $patron = $this->getILSPatron();

        if ($patron != null && array_key_exists('username', $patron)) {
            # If the username is a 9-digit string (barcode) then they are a community borrower
            if (preg_match('/[0-9]{9}/', $patron['username'])) {
                $isCommunityBorrower = true;
            }
        }

        return $isCommunityBorrower;
    }
    
    /**
     * Determines if the current request is comming from on campus
     * based on the client IP as compared to the configured IP ranges in
     * the permissions.ini for the EDS module.
     * 
     * @return bool
     */
    public function isOnCampus()
    {
        $isOnCampus = false;
        $ranges = [];
        $ip = ip2long($_SERVER['REMOTE_ADDR']);

        $fullpath = Locator::getConfigPath('permissions.ini', 'config/vufind');
        $config = new Config((new IniReader())->fromFile($fullpath, true));

        if ($config["default"]["EDSModule"] != null &&
            $config["default"]["EDSModule"]["ipRange"] != null) {

            $ranges = $config["default"]["EDSModule"]["ipRange"];
            foreach ($ranges as $range) {
                $ipParts = explode("-", $range);
                $lowIp = count($ipParts) > 0 ? ip2long($ipParts[0]) : "";
                $highIp = count($ipParts) > 1 ? ip2long($ipParts[1]) : "";
                if ($ip <= $highIp && $lowIp <= $ip) {
                    $isOnCampus = true;
                    break;
                }
            }
        }

        return $isOnCampus;
    }
}

