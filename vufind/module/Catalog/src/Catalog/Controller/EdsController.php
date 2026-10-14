<?php

/**
 * Eds Controller.
 * Extended to support redirecting to a particular record in the search results after saving the search.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Controller;

/**
 * EDS Controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EdsController extends \VuFind\Controller\EdsController
{
    /**
     * Perform a search and send results to a results view.
     *
     * @param callable $setupCallback Optional setup callback that overrides the
     * default one
     *
     * @return Response|ViewModel
     */
    protected function getSearchResultsView($setupCallback = null)
    {
        $view = parent::getSearchResultsView($setupCallback);
        $recordList = $view->results->getResults();
        $id = $this->params()->fromQuery('goto');
        if (!empty($id)) {
            foreach ($recordList as $record) {
                if ($record->getUniqueID() == $id) {
                    return $this->getRedirectForRecord($record, ['sid' => $view->results->getSearchId()]);
                }
            }
        }
        return $view;
    }
}
