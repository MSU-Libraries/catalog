<?php

/**
 * TODO
 *  COULD BE REMOVED WHEN PR 3826 IS ACCEPTED (PC-895)
 * AlphaBrowse Module Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */

namespace Catalog\Controller;

use Catalog\Search\SearchOrigin\AlphaBrowseSearchOrigin;
use Exception;
use Laminas\View\Model\ViewModel;

use function intval;

/**
 * AlphabrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class AlphabrowseController extends \VuFind\Controller\AlphabrowseController
{
    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return ViewModel
     */
    public function homeAction(): ViewModel
    {
        $view = parent::homeAction();

        // Process incoming parameters:
        $source = $this->params()->fromQuery('source', false);
        $from   = $this->params()->fromQuery('from', false);
        $page   = intval($this->params()->fromQuery('page', 0));
        try {
            $origin = new AlphaBrowseSearchOrigin(
                $this->getTypes($this->getConfig())[$source] ?? null,
                $source,
                $from,
                $page ?: null
            );
        } catch (Exception) {
            $origin = null;
        }
        $view->setVariable('origin', $origin);

        return $view;
    }
}
