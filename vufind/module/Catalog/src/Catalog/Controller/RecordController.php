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
        $getthis = new GetThisLoader($this->params());

        $view = $this->createViewModel();
        $view->setTemplate('record/getthis');
        $view->addChild($getthis, 'getthis');
        return $view;
    }
}
