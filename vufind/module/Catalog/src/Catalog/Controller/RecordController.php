<?php

namespace Catalog\Controller;

class RecordController extends \VuFind\Controller\RecordController
{
    /**
     * Display the "Get this" dialog content.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function getthisAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('record/getthis');
        return $view;
    }
}
