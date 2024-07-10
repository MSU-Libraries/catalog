<?php

/**
 * TODO
 *  COULD BE REMOVED WHEN PR IS ACCEPTED (PC-895)
 * AbstractSearch with Solr-specific features added.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\View\Helper\Root;

use Catalog\Search\SearchOrigin\AbstractSearchOrigin;


/**
 * AlphaBrowse view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AlphaBrowse extends \VuFind\View\Helper\Root\AlphaBrowse
{
    /**
     * Get link to browse results (or null if no valid URL available)
     *
     * @param string $source AlphaBrowse index currently being used
     * @param array  $item   Item to link to
     *
     * @return string
     */
    public function getUrl($source, $item, ?AbstractSearchOrigin $origin = null)
    {
        if ($item['count'] <= 0) {
            return null;
        }

        $query = [
            'type' => ucwords($source) . 'Browse',
            'lookfor' => $this->escapeForSolr($item['heading']),
        ];
        if ($this->options['bypass_default_filters'] ?? true) {
            $query['dfApplied'] = 1;
        }
        if ($item['count'] == 1) {
            $query['jumpto'] = 1;
        }
        /* START MSU */
        if (isset($origin)) {
            $query += $origin->getSearchUrlParamsArray();
        }
        /* END MSU */
        return ($this->url)('search-results', [], ['query' => $query]);
    }
}
