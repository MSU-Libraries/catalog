<?php
namespace Catalog\Controller;
use VuFind\Exception\Mail as MailException;
use VuFind\Mailer\Mailer;
use Catalog\GetThis\GetThisLoader;

class RecordController extends \VuFind\Controller\RecordController
{
    protected $getthis_email_targets = [
        'remotestorage' => "remote\u{0040}lib.msu.edu",
        'circulation' => "uncats\u{0040}lib.msu.edu",
    ];

    /**
     * Display the "Get this" dialog content.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function getthisAction()
    {
        //TODO check hasILS(), otherwise HLM?
        $items = $this->getILS()->getHolding($this->params()->fromRoute('id'));
        $view = $this->createViewModel();
        $user = $this->getUser();
        $view->setVariable('getthis', new GetThisLoader($view->driver, $items['holdings']));
        $view->setVariable('userrow', $user);  // VuFind\Db\Row\User
        if ($user === false) {
            $view->setTemplate('record/getthis/login');
        }
        else {
            # TODO what to about $item['electronic_holdings']
            # TODO what to about $item['page']; do we need multiple calls for this?
            $view->setTemplate('record/getthis');
        }
        return $view;
    }

    public function getthissendrequestAction()
    {
        //TODO check hasILS(), otherwise HLM?
        $items = $this->getILS()->getHolding($this->params()->fromRoute('id'));
        $view = $this->createViewModel();
        $user = $this->getUser();
        $user_email = $this->params()->fromPost('email', $user->email); // default to user email in db
        $getthis = new GetThisLoader($view->driver, $items['holdings']);
        $view->setVariable('getthis', $getthis);
        $view->setVariable('userrow', $user);  // VuFind\Db\Row\User
        if ($user === false) {
            $view->setTemplate('record/getthis/login');
        }
        elseif ($this->getRequest()->isPost()) {
            $cc_target = $this->params()->fromQuery('target');
            if (!in_array($cc_target,array_keys($this->getthis_email_targets))) {
                //TODO handle getting a bad target variable
            }

            // Render email and attempt to send
            $cc_email = $this->getthis_email_targets[$cc_target];
            $renderer = $this->getViewRenderer();
            $txt_msg = $renderer->render('Email/getthis-request-confirm-txt.phtml',
                [
                    'driver'  => $view->driver,
                    'getthis' => $getthis,
                    'userrow' => $user,
                ]
            );
            $html_msg = $renderer->render('Email/getthis-request-confirm-html.phtml',
                [
                    'driver'  => $view->driver,
                    'getthis' => $getthis,
                    'userrow' => $user,
                ]
            );
            try {
                $mailer = $this->serviceLocator->get(Mailer::class);
                $body = $mailer->buildMultipartBody($txt_msg, $html_msg);
                $mailer->send(
                    $user_email,
                    $this->getConfig()->Site->email,   // from
                    "MSU Library Retrieval Request Confirmation",
                    $body,
                    $cc_email,  // cc
                    $cc_email,  // reply-to
                );
                $view->setTemplate('record/getthis/sendsuccess');
            } catch (MailException $e) {
                $view->setVariable('cc_email', $cc_email);
                $view->setTemplate('record/getthis/sendfailure');
            }
        }
        else {
            // Confirmation form for request (allows changing email)
            $view->setTemplate('record/getthis/sendrequest');
            $view->setVariable('user_email', $user_email);
        }

        return $view;
    }
}
