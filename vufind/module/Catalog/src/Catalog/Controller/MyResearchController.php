<?php

/**
 * MyResearch Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  EDS_Result
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Catalog\Controller;

use VuFind\Exception\Auth as AuthException;

/**
 * Controller for the user account area.
 *
 * @category VuFind
 * @package  EDS_Result
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{
    /**
     * Prepare and direct the home page where it needs to go
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Process login request, if necessary (either because a form has been
        // submitted or because we're using an external login provider):
        if (
            $this->params()->fromPost('processLogin')
            || $this->getSessionInitiator()
            || $this->params()->fromPost('auth_method')
            || $this->params()->fromQuery('auth_method')
        ) {
            try {
                if (!$this->getAuthManager()->getIdentity()) {
                    $this->getAuthManager()->login($this->getRequest());
                    // Return early to avoid unnecessary processing if we are being
                    // called from login lightbox and don't have a followup action or
                    // followup is set to referrer.
                    if (
                        $this->params()->fromPost('processLogin')
                        && $this->inLightbox()
                        // (MSULib) No longer checking for lack of followup url, as we always set it
                    ) {
                        $this->clearFollowupUrl();
                        return $this->getRefreshResponse();
                    }
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->getIdentity()) {
            if (
                $this->followup()->retrieve('lightboxParent')
                && $url = $this->getAndClearFollowupUrl(true)
            ) {
                return $this->redirect()->toUrl($url);
            }

            // Allow bypassing of post-login redirect
            if ($this->params()->fromQuery('redirect', true)) {
                $this->setFollowupUrlToReferer();
            }
            return $this->forwardTo('MyResearch', 'Login');
        }
        // Logged in?  Forward user to followup action
        // or default action (if no followup provided):
        if ($url = $this->getAndClearFollowupUrl(true)) {
            return $this->redirect()->toUrl($url);
        }

        $config = $this->getConfig();
        $page = $config->Site->defaultAccountPage ?? 'Favorites';

        // Default to search history if favorites are disabled:
        if ($page == 'Favorites' && !$this->listsEnabled()) {
            return $this->forwardTo('Search', 'History');
        }
        return $this->forwardTo('MyResearch', $page);
    }

    /**
     * User login action -- clear any previous follow-up information prior to
     * triggering a login process. This is used for explicit login links within
     * the UI to differentiate them from contextual login links that are triggered
     * by attempting to access protected actions.
     *
     * @return mixed
     */
    public function userloginAction()
    {
        // Don't log in if already logged in!
        if ($this->getAuthManager()->getIdentity()) {
            return $this->inLightbox()  // different behavior for lightbox context
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('home');
        }
        $this->clearFollowupUrl();
        // Set followup with the isReferrer flag so that the post-login process
        // can decide whether to use it:
        // (MSULib) Always set follow up URL, as we might end up linking to external SAML
        $this->setFollowupUrlToReferer();
        if ($si = $this->getSessionInitiator()) {
            return $this->redirect()->toUrl($si);
        }
        return $this->forwardTo('MyResearch', 'Login');
    }
}
