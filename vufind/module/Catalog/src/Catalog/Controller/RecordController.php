<?php

/**
 * Helper for the GetThis Loader containing
 * The action for when the button is clicked
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  GetThis_Loader
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Catalog\Controller;

use Catalog\GetThis\GetThisLoader;

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
}
