<?php

/**
 * Configurable form.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category Catalog
 * @package  Form
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Catalog\Form;

/**
 * Configurable form.
 *
 * @category Catalog
 * @package  Form
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class Form extends \VuFind\Form\Form
{
    /**
     * Check if the original recipient for a form submission should be
     * CC'd on emails if the form submission is from the public.
     *
     * @return bool
     */
    public function ccOriginalOnPublic()
    {
        return (bool)($this->formConfig['recipient_cc_public'] ?? false);
    }

    /**
     * Check if the user (if logged in) should be sent a copy of the
     * form submission email.
     *
     * @return bool
     */
    public function copyUserOnEmail()
    {
        return (bool)($this->formConfig['recipient_copy_user'] ?? false);
    }

    /**
     * Return form recipient(s) for public submissions.
     *
     * @param array $postParams Posted form data
     *
     * @return array of reciepients, each consisting of an array with
     * name, email or null if not configured
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRecipientPublic($postParams = null)
    {
        $recipient = $this->formConfig['recipient_public'] ?? [null];
        $recipients = isset($recipient['email']) || isset($recipient['name'])
            ? [$recipient] : $recipient;

        foreach ($recipients as &$recipient) {
            $recipient['email'] ??= $this->defaultFormConfig['recipient_public_email'] ?? null;
            $recipient['name'] ??= $this->defaultFormConfig['recipient_public_name'] ?? null;
        }

        return $recipients;
    }

    /**
     * Return a list of field names to read from settings file.
     *
     * @return array
     */
    protected function getFormSettingFields()
    {
        return array_merge(
            parent::getFormSettingFields(),
            [
                'recipient_copy_user',
                'recipient_cc_public',
                'recipient_public',
            ]
        );
    }
}
