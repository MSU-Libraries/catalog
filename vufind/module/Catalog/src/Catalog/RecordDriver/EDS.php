<?php

/**
 * Extension of VuFind model for EDS records.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\RecordDriver;

use VuFind\RecordDriver\DefaultRecord;

/**
 * Model for EDS records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class EDS extends \VuFind\RecordDriver\EDS
{
    /**
     * Return a URL to a thumbnail preview of the record, if available; false
     * otherwise.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string
     */
    public function getThumbnail($size = 'small')
    {
        foreach ($this->fields['ImageInfo'] ?? [] as $image) {
            if ($size == ($image['Size'] ?? '')) {
                return $image['Target'] ?? '';
            }
        }
        if (!empty($this->fields['Items'] ?? []) || empty($this->getAccessLevel())) {
            return DefaultRecord::getThumbnail($size);
        } else {
            return false;
        }
    }
}
