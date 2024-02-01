<?php

/**
 * Class Email
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2022.
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
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\Form\Handler;

use Laminas\Mail\Address;
use VuFind\Exception\Mail as MailException;

/**
 * Class Email
 *
 * @category VuFind
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FeedbackEmail extends \VuFind\Form\Handler\Email
{
    /**
     * Get data from submitted form and process them.
     *
     * @param \VuFind\Form\Form                     $form   Submitted form
     * @param \Laminas\Mvc\Controller\Plugin\Params $params Request params
     * @param ?\VuFind\Db\Row\User                  $user   Authenticated user
     *
     * @return bool
     */
    public function handle(
        \VuFind\Form\Form $form,
        \Laminas\Mvc\Controller\Plugin\Params $params,
        ?\VuFind\Db\Row\User $user = null
    ): bool {
        $fields = $form->mapRequestParamsToFieldValues($params->fromPost());
        $emailMessage = $this->viewRenderer->partial(
            'Email/form.phtml',
            compact('fields')
        );

        [$senderName, $senderEmail] = $this->getSender($form);

        $formFromNameField = $params->fromPost(
            'name',
            $user ? trim($user->firstname . ' ' . $user->lastname) : null
        );
        $formFromEmailField = $params->fromPost(
            'email',
            $user ? $user->email : null
        );
        $recipients = $form->getRecipient($params->fromPost());
        $emailSubject = $form->getEmailSubject($params->fromPost());

        // MSUL Start

        // $senderName                   "Catalog Feedback Form"       Same for all emails sent
        // $senderEmail                  "noreply@catalog.lib.msu.edu" Same for all emails sent
        // $emailSubject                 "Catalog Feedback"            Same for all emails sent
        // $recipients[0]['name']        NULL
        // $recipients[0]['email']       "LIB.DL.cdawg@msu.edu"
        // $publicRecipients[0]['name']  NULL
        // $publicRecipients[0]['email'] "**discoveryservices**@**msu**" => to change to **support**@**libanswers**
        // $formFromNameField            "Robby From form"
        // $formFromEmailField           "roudonro@msu.edu From form"

        // Grab libstaff checkbox (for determining target email)
        $libstaff = array_filter($fields, function ($val) {
            return $val['name'] == 'libstaff';
        });
        $staffFeedback = !empty(array_shift($libstaff)['value']);

        $emails = [];
        //Always send feedback to library:
        if ($staffFeedback) {
            // Box Checked / Library Staff
            // When “Library Staff” checkbox is CHECKED, feedback is sent
            // To: LIB.DL.cdawg@msu.edu
            $emails[] = [
                'recipientName' => $recipients[0]['name'] ?? $recipients[0]['email'],
                'recipientEmail' => $recipients[0]['email'],
                'senderName' => $senderName,
                'senderEmail' => $senderEmail,
                'replyToName' => $formFromNameField,
                'replyToEmail' => $formFromEmailField,
                'emailSubject' => $recipients[0]['subject_prefix'] . $emailSubject,
                'emailMessage' => $emailMessage,
                'ccEmail' => null,
            ];
        } else {
            // Box Unchecked / Not Library Staff
            // When “Library staff checkbox is UNCHECKED, feedback is sent:
            // 1st email To: support@msu.libanswers.com
            // 2nd email To: LIB.DL.cdawg@msu.edu
            $publicRecipients = $form->getRecipientPublic($params->fromPost());
            $emails[] = [
                'recipientName' => $publicRecipients[0]['name'] ?? $publicRecipients[0]['email'],
                'recipientEmail' => $publicRecipients[0]['email'],
                'senderName' => $senderName,
                'senderEmail' => $senderEmail,
                'replyToName' => $senderName,
                'replyToEmail' => $senderEmail,
                'emailSubject' => $publicRecipients[0]['subject_prefix'] . $emailSubject,
                'emailMessage' => $emailMessage,
                'ccEmail' => null,
            ];

            $emails[] = [
                'recipientName' => $form->ccOriginalOnPublic() ? $recipients[0]['email'] : null,
                'recipientEmail' => $form->ccOriginalOnPublic() ? $recipients[0]['email'] : null,
                'senderName' => $senderName,
                'senderEmail' => $senderEmail,
                'replyToName' => $senderName,
                'replyToEmail' => $senderEmail,
                'emailSubject' => $publicRecipients[0]['subject_prefix'] . $emailSubject,
                'emailMessage' => $emailMessage,
                'ccEmail' => null,
            ];
        }
        // Copy feedback to user if they are logged in
        if ($form->copyUserOnEmail() && $formFromEmailField !== null && $user) {
            // If the user sending feedback is logged in and provided an email address:
            // Send an independent copy of the feedback to the user:
            // Only that user’s email address will be a recipient;
            // the email will NOT include any other recipients on this email (not CDAWG, not DS, not Libanswers)
            $emails[] = [
                'recipientName' => $formFromNameField ?? $formFromEmailField,
                'recipientEmail' => $formFromEmailField,
                'senderName' => $senderName,
                'senderEmail' => $senderEmail,
                'replyToName' => $formFromNameField,
                'replyToEmail' => $formFromEmailField,
                'emailSubject' => $emailSubject,
                'emailMessage' => $emailMessage,
                'ccEmail' => null,
            ];
        }

        $result = true;
        foreach ($emails as $email) {
            $success = $this->sendEmail(
                $email['recipientName'] ?? null,
                $email['recipientEmail'] ?? null,
                $email['senderName'] ?? null,
                $email['senderEmail'] ?? null,
                $email['replyToName'] ?? null,
                $email['replyToEmail'] ?? null,
                $email['emailSubject'] ?? null,
                $email['emailMessage'] ?? null,
                $email['ccEmail'] ?? null
            );
            $result = $result && $success;
        }
        // MSUL End
        return $result;
    }

    /**
     * Send form data as email.
     *
     * @param string $recipientName  Recipient name
     * @param string $recipientEmail Recipient email
     * @param string $senderName     Sender name
     * @param string $senderEmail    Sender email
     * @param string $replyToName    Reply-to name
     * @param string $replyToEmail   Reply-to email
     * @param string $emailSubject   Email subject
     * @param string $emailMessage   Email message
     * @param string $ccEmail        CC email
     *
     * @return bool
     */
    protected function sendEmail(
        $recipientName,
        $recipientEmail,
        $senderName,
        $senderEmail,
        $replyToName,
        $replyToEmail,
        $emailSubject,
        $emailMessage,
        $ccEmail = null,
    ): bool {
        try {
            $ccAddr = $ccEmail ? new Address($ccEmail) : null;
            $this->mailer->send(
                new Address($recipientEmail, $recipientName),
                new Address($senderEmail, $senderName),
                $emailSubject,
                $emailMessage,
                $ccAddr,
                !empty($replyToEmail)
                    ? new Address($replyToEmail, $replyToName) : null
            );
            return true;
        } catch (MailException $e) {
            $this->logError(
                "Failed to send email to '$recipientEmail': " . $e->getMessage()
            );
            return false;
        }
    }
}
