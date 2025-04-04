<?php

/**
 * Helper for the GetThis Loader containing
 * The action for when the button is clicked
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
 * @package  GetThis_Loader
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Catalog\Controller;

use Catalog\GetThis\GetThisLoader;
use Catalog\Search\SearchOrigin\SearchOriginFactory;
use Exception;

/**
 * Helper class for the GetThis Loader containing
 * The action for when the button is clicked
 *
 * @category VuFind
 * @package  GetThis_Loader
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RecordController extends \VuFind\Controller\RecordController
{
    /**
     * Display the "Get this" dialog content.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function getthisAction()
    {
        //TODO check hasILS(), otherwise HLM?
        $items = $this->getILS()->getHolding($this->params()->fromRoute('id'));
        $item_id = $this->params()->fromQuery('item_id');
        $view = $this->createViewModel();
        $view->setVariable('getthis', new GetThisLoader($view->driver, $items['holdings'], $item_id));
        // TODO what to about $item['electronic_holdings']
        // TODO what to about $item['page']; do we need multiple calls for this?
        $view->setTemplate('record/getthis');
        return $view;
    }

    /**
     * Redirect the user to the login screen.
     *
     * @param string $msg     Flash message to display on login screen
     * @param array  $extras  Associative array of extra fields to store
     * @param bool   $forward True to forward, false to redirect
     *
     * @return mixed
     */
    public function forceLogin($msg = null, $extras = [], $forward = true)
    {
        // Overriding parent with empty message to prevent error message (PC-972)
        return parent::forceLogin('', $extras, $forward);
    }

    /**
     * Display a particular tab.
     * // TODO PC-895 To remove after PR
     * // https://github.com/vufind-org/vufind/pull/3826
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        try {
            $searchOrigin = SearchOriginFactory::createObject($this->params()->fromQuery());
        } catch (Exception) {
            $searchOrigin = null;
        }
        $this->layout()->setVariable('searchOrigin', $searchOrigin);
        return parent::showTab($tab, $ajax);
    }
}
