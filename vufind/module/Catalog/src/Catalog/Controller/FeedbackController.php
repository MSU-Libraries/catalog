<?php
/**
 * Controller for configurable forms (feedback etc).
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Catalog\Controller;

use Laminas\Mail\Address;
use Laminas\View\Model\ViewModel;
use VuFind\Exception\Mail as MailException;
use VuFind\Form\Form;

/**
 * Controller for configurable forms (feedback etc).
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FeedbackController extends \VuFind\Controller\FeedbackController
{
    /**
     * Handles rendering and submit of dynamic forms.
     * Form configurations are specified in FeedbackForms.yaml.
     *
     * @return mixed
     */
    public function formAction()
    {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (!$formId) {
            $formId = 'FeedbackSite';
        }

        $user = $this->getUser();

        $form = $this->serviceLocator->get($this->formClass);
        $params = [];
        if ($refererHeader = $this->getRequest()->getHeader('Referer')) {
            $params['referrer'] = $refererHeader->getFieldValue();
        }
        if ($userAgentHeader = $this->getRequest()->getHeader('User-Agent')) {
            $params['userAgent'] = $userAgentHeader->getFieldValue();
        }
        $form->setFormId($formId, $params);

        if (!$form->isEnabled()) {
            throw new \VuFind\Exception\Forbidden("Form '$formId' is disabled");
        }

        if (!$user && $form->showOnlyForLoggedUsers()) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel(compact('form', 'formId', 'user'));
        $view->useCaptcha
            = $this->captcha()->active('feedback') && $form->useCaptcha();

        $params = $this->params();
        $form->setData($params->fromPost());

        if (!$this->formWasSubmitted('submit', $view->useCaptcha)) {
            $form = $this->prefillUserInfo($form, $user);
            return $view;
        }

        if (! $form->isValid()) {
            return $view;
        }

        [$messageParams, $template]
            = $form->formatEmailMessage($this->params()->fromPost());
        # MSUL: Add in user id if logged in
        $messageParams[] = [
            "type" => "hidden",
            "label" => "User ID",
            "value" => $user->id ?? "none",
        ];
        $emailMessage = $this->getViewRenderer()->partial(
            $template,
            ['fields' => $messageParams]
        );

        [$senderName, $senderEmail] = $this->getSender($form);

        $replyToName = $params->fromPost(
            'name',
            $user ? trim($user->firstname . ' ' . $user->lastname) : null
        );
        $replyToEmail = $params->fromPost(
            'email',
            $user ? $user->email : null
        );

        $recipients = $form->getRecipient($params->fromPost());
        # MSUL: Copy feedback to user if they are logged in
        if ($replyToEmail !== null && $user) {
            $recipients[] = [
                "name" => $replyToName ?? $replyToEmail,
                "email" => $replyToEmail,
            ];
        }

        $emailSubject = $form->getEmailSubject($params->fromPost());

        $sendSuccess = true;
        foreach ($recipients as $recipient) {
            [$success, $errorMsg] = $this->sendEmail(
                $recipient['name'],
                $recipient['email'],
                $senderName,
                $senderEmail,
                $replyToName,
                $replyToEmail,
                $emailSubject,
                $emailMessage
            );

            $sendSuccess = $sendSuccess && $success;
            if (!$success) {
                $this->showResponse(
                    $view,
                    $form,
                    false,
                    $errorMsg
                );
            }
        }

        if ($sendSuccess) {
            $this->showResponse($view, $form, true);
        }

        return $view;
    }
}
