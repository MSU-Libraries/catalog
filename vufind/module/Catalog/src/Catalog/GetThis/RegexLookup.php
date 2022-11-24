<?php
namespace Catalog\GetThis;

/******************************
 * RegexLookup use example to check if $search matches the given regex patterns.
 *
 *  RegexLookup::STAT_AVAILABLE($search)
 *
 *  Returns true if at least 1 pattern matches, false otherwise
 *****************************/
class RegexLookup {
    public static function __callStatic($name, $args) {
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
    const IN_PROCESS            = ['/IN PROCESS/i'];
    const AVAILABLE             = ['/AVAILABLE/i'];
    const LIB_USE_ONLY          = ['/LIB USE ONLY/i'];
    const ON_DISPLAY            = ['/ON DISPLAY/i'];
    // Description
    const VINYL                 = ['/VINYL/i'];
    // Location
    const MAIN                  = ['/^MSU MAIN/i'];
    const MAKERSPACE            = ['/MAKERSPACE/i'];
    const ART                   = ['/^MSU ART LIBRARY/i'];
    const PERM                  = ['/PERM/i'];        # Legacy note: used for Art Reserves
    const BUSINESS              = ['/MSU BUSINESS/i'];
    const RESERVE               = ['/RESERVE/i'];
    const RESERV                = ['/RESERV/i'];  // TODO can we expire of the the RESERV checks?
    const BROWSING              = ['/BROWSING/i'];
    const CAREER                = ['/CAREER/i'];
    const RESERVE_DIGITAL       = ['/RESERVE DIGITAL/i'];
    const LIB_OF_MICH           = ['/LIB OF MICH/i'];
    const REFERENCE             = ['/REFERENCE/i'];
    const CESAR_CHAVEZ          = ['/CESAR CHAVEZ/i'];
    const DIGITAL_MEDIA         = ['/DIGITAL\/MEDIA\./i'];
    const KLINE_DMC             = ['/^MSU G\.M\.KLINE DIGITAL/i'];
    const FACULTY_BOOK          = ['/FACULTY BOOK/i'];
    const SCHAEFER              = ['/^MSU SCHAEFER/i'];
    const MICROFORMS            = ['/^MSU MICROFORMS/i'];
    const GOV                   = ['/^MSU GOV/i'];
    const MAP                   = ['/^MSU MAP/i'];
    const CIRCULATING           = ['/CIRCULATING/i', '/FOLD/i'];
    const MUSIC                 = ['/^MSU MUSIC LIBRARY/i'];
    const SPEC_COLL             = ['/^MSU SPEC COLL/i', '/SPECIAL COLLECTION/i'];
    const SPEC_COLL_REMOTE      = ['/SPEC COLL REMOTE/i'];
    const REMOTE                = ['/^MSU REMOTE/i'];
    const TRAVEL                = ['/TRAVEL/i'];
    const TURFGRASS             = ['/^MSU TURFGRASS/i', '/MSU BEARD/i'];
    const VINCENT_VOICE         = ['/^MSU VINCENT VOICE/i'];
}
