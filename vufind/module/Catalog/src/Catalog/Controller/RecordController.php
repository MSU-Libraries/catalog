<?php
namespace Catalog\Controller;
use Catalog\GetThis\GetThisLoader;

class RecordController extends \VuFind\Controller\RecordController
{
    /**
     * Display the "Get this" dialog content.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function getthisAction()
    {
        //TODO check hasILS()
        $items = $this->getILS()->getHolding($this->params()->fromRoute('id'));
        $view = $this->createViewModel();
        $view->setTemplate('record/getthis');
        # TODO what to about $item['electronic_holdings']
        # TODO what to about $item['page']; do we need multiple calls for this?
        $view->setVariable(
            'getthis', new GetThisLoader($view->driver, $items['holdings']),
        );
        return $view;
    }
}
