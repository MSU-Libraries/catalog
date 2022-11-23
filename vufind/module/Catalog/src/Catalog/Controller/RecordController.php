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
        $items = $this->getILS()->getHolding($this->params()->fromRoute('id'));
        $view = $this->createViewModel();
        $view->setTemplate('record/getthis');
        $view->setVariable(
            'getthis', new GetThisLoader($view->driver, $items),
        );
        return $view;
    }
}
