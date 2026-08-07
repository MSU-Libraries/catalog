<?php

/**
 * This file is to prevent code duplication
 * It overwrites the function getOpenUrlFormat from DefaultRecord
 *
 *  PHP version 8
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\RecordDriver;

use function in_array;
use function strlen;

/**
 * This file is to prevent code duplication
 * It overwrites the function getOpenUrlFormat from DefaultRecord
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait GetOpenUrlFormatTrait
{
    /**
     * PC-1652 Merge "Serial" and "Journal" formats
     * Extending from DefaultRecord
     * Support method for getOpenUrl() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenUrlFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('Book', $formats) || in_array('eBook', $formats)) {
            return 'Book';
        } elseif (in_array('Article', $formats)) {
            return 'Article';
        } elseif (in_array('Periodical', $formats) || in_array('Journal', $formats)) { // MSU
            return 'Journal';
        } elseif (strlen($this->getCleanISSN()) > 0) {
            // If the record has an ISSN and we have not already
            // decided it is an Article, we'll treat it as a Book
            // if it has an ISBN and is therefore likely part of a
            // monographic series. Otherwise, we'll treat it as a
            // Journal.
            // Anecdotally, some link resolvers do not return correct
            // results when given both ISBN and ISSN for a member of a
            // monographic series.
            return strlen($this->getCleanISBN()) > 0 ? 'Book' : 'Journal';
        } elseif (isset($formats[0])) {
            return $formats[0];
        } elseif (strlen($this->getCleanISBN()) > 0) {
            // Last ditch. Note that this is last by intention; if the
            // record has a format set and also has an ISBN, we don't
            // necessarily want to send the ISBN, as it may be a game
            // or a DVD that wouldn't typically be found in OpenURL
            // knowledgebases.
            return 'Book';
        }
        return 'UnknownFormat';
    }
}
