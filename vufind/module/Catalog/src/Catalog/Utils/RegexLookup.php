<?php

/**
 * Helper class for the GetThis Loader containing
 * Regex patterns to match on for locations and statuses
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Backend_EDS
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\Utils;

use function constant;
use function count;

/**
 * RegexLookup use example to check if $search matches the given regex patterns.
 *
 *  RegexLookup::STAT_AVAILABLE($search)
 *
 *  Returns true if at least 1 pattern matches, false otherwise
 *
 * @category VuFind
 * @package  Backend_EDS
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class RegexLookup
{
    /**
     * Called when making a call to RegexLookup::[contant]($val), for example,
     * RegexLookup::AVAILABLE("available") and will perform the regex check on the
     * passed value against the set constant value
     *
     * @param string $name Constant name to use for regex matching
     * @param string $args Values to perform the regex matching against
     *
     * @return bool           If there was a match found
     */
    public static function __callStatic($name, $args)
    {
        $patterns = constant('self::' . $name);
        if (count($args) !== 1) {
            throw new ArgumentCountError("RegexLookup::$name() calls take exactly one argument.");
        }

        $match = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $args[0]) === 1) {
                $match = true;
                break;
            }
        }
        return $match;
    }

    // Status
    public const AVAILABLE             = ['/^AVAILABLE/i'];
    public const IN_PROCESS            = ['/IN PROCESS/i'];
    public const LIB_USE_ONLY          = ['/LIB USE ONLY/i', '/LIBRARY USE ONLY/i'];
    public const ON_DISPLAY            = ['/ON DISPLAY/i'];
    // Description / Call Number
    public const VINYL                 = ['/VINYL/i'];
    public const MICROPRINT            = ['/MICROFILM/i', '/MICROFICHE/i', '/MICROPRINT/i'];
    public const AV_MEDIA              = ['/DISC/i', '/VIDEO/i', '/CD/i', '/DVD/i', '/BLU-RAY/i', '/VINYL/i', '/AUDIOCASSETTE/i'];
    // Location
    public const MAIN                  = ['/^MSU MAIN/i'];
    public const AFRICANA              = ['/^MSU AFRICANA/i'];
    public const ART                   = ['/^MSU ART LIBRARY/i'];
    public const BOOK                  = ['/BOOK/i'];
    public const BROWSING              = ['/BROWSING/i'];
    public const BUSINESS              = ['/MSU BUSINESS/i'];
    public const CAREER                = ['/CAREER/i'];
    public const CESAR_CHAVEZ          = ['/CESAR CHAVEZ/i'];
    public const CIRCULATING           = ['/CIRCULATING/i', '/FOLD/i'];
    public const DIGITAL_MEDIA         = ['/DIGITAL\/MEDIA\./i'];
    public const FACULTY_BOOK          = ['/FACULTY BOOK/i'];
    public const GOV                   = ['/^MSU GOV/i'];
    public const GULL                  = ['/^MSU GULL/i'];
    public const KLINE_DMC             = ['/^MSU G\.M\.KLINE DIGITAL/i'];
    public const LAW_RARE_BOOK         = ['/LAW LIB RARE BOOK/i'];
    public const LAW_RESERVE           = ['/LAW LIBRARY RESERVE/i'];
    public const LIB_OF_MICH           = ['/LIB OF MICH/i'];
    public const MAKERSPACE            = ['/MAKERSPACE/i'];
    public const MAP                   = ['/^MSU MAP/i'];
    public const MICROFORMS            = ['/^MSU MICROFORMS/i'];
    public const MUSIC                 = ['/^MSU MUSIC LIBRARY/i'];
    public const ONLINE                = ['/ONLINE RESOURCE/i', '/ELECTRONIC RESOURCES/i', '/INTERNET/i'];
    public const PERM                  = ['/PERM/i'];        // Legacy note: used for Art Reserves
    public const READING_ROOM          = ['/READING ROOM/i'];
    public const REFERENCE             = ['/REFERENCE/i'];
    public const REF                   = ['/REF/i'];
    public const REMOTE                = ['/^MSU REMOTE/i', '/- REMOTE/i'];
    public const RESERV                = ['/RESERV/i'];
    public const RESERVE_DIGITAL       = ['/RESERVE DIGITAL/i', '/DIGITAL RESERVES/i'];
    public const ROVI                  = ['/^MSU ROVI/i'];
    public const SCHAEFER              = ['/^MSU SCHAEFER/i'];
    public const SPEC_COLL             = ['/^MSU SPEC COLL/i', '/SPECIAL COLLECTION/i'];
    public const SPEC_COLL_REMOTE      = ['/SPEC COLL REMOTE/i', '/SPECIAL COLLECTIONS.*REMOTE/i'];
    public const THESES_REMOTE         = ['/THESES REMOTE/i'];
    public const THESES_REMOTE_MICRO   = ['/THESES REMOTE MICROFORMS/i'];
    public const TRAVEL                = ['/TRAVEL/i'];
    public const TURFGRASS             = ['/^MSU TURFGRASS/i', '/MSU BEARD/i'];
    public const UNIV_ARCH             = ['/UNIV ARCH/i', '/MSU UNIVERSITY ARCHIVES/i'];
    public const VIDEO_GAME            = ['/VIDEO GAME/i'];
    public const VINCENT_VOICE         = ['/^MSU VINCENT VOICE/i'];
}
