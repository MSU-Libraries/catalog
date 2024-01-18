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
        // MSUL Start
        // Add in user id if logged in
        $messageParams[] = [
            'type' => 'hidden',
            'label' => 'VFUserID',
            'value' => $user->id ?? 'none',
        ];
        // Grab libstaff checkbox (for determining target email)
        $libstaff = array_filter($fields, function ($val) {
            return $val['name'] == 'libstaff';
        });
        $staffFeedback = !empty(array_shift($libstaff)['value']);
        // MSUL End
        $emailMessage = $this->viewRenderer->partial(
            'Email/form.phtml',
            compact('fields')
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
        // MSUL Start
        // Non staff email goes to Discovery services (cc'ing original)
        $publicRecipients = $form->getRecipientPublic($params->fromPost());
        $ccEmail = null;
        if (!$staffFeedback) {
            // Can be set to cc original recipient address
            if ($form->ccOriginalOnPublic()) {
                $ccEmail = $recipients[0]['email'];
            }
            $recipients[0] = $publicRecipients[0];
        }
        // Copy feedback to user if they are logged in
        if ($form->copyUserOnEmail() && $replyToEmail !== null && $user) {
            $recipients[] = [
                'name' => $replyToName ?? $replyToEmail,
                'email' => $replyToEmail,
            ];
        }
        // MSUL End
        $emailSubject = $form->getEmailSubject($params->fromPost());

        $result = true;
        foreach ($recipients as $recipient) {
            $success = $this->sendEmail(
                $recipient['name'],
                $recipient['email'],
                $senderName,
                $senderEmail,
                $replyToName,
                $replyToEmail,
                $emailSubject,
                $emailMessage,
                $ccEmail
            );
            // MSUL Start: Only CC once
            $ccEmail = null;
            // MSUL End

            $result = $result && $success;
        }
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
