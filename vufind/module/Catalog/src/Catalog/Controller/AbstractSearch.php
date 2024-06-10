<?php

/**
 * VuFind Search Controller
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
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Controller;

use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ViewModel;

/**
 * VuFind Search Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AbstractSearch extends \VuFind\Controller\AbstractSearch
{
    /**
     * Perform a search and send results to a results view
     *
     * @param callable $setupCallback Optional setup callback that overrides the
     * default one
     *
     * @return Response|ViewModel
     */
    protected function getSearchResultsView($setupCallback = null)
    {
        $view = parent::getSearchResultsView($setupCallback);
        $config = $this->getConfig('facets');
        $view->multiFacetsSelection = $config?->get('Advanced_Settings')?->get('multiFacetsSelection') === '1';
        return $view;
    }
}
