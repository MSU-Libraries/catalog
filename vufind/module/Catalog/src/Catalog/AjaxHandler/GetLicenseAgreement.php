<?php

/**
 * "Get License Agreement" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;

use function is_array;

/**
 * "Get License Agreement" AJAX handler
 *
 * This is responsible for querying the EDS and FOLIO APIs
 * for license agreement information and returning it in JSON format.
 *
 * @category VuFind
 * @package  AJAX
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetLicenseAgreement extends \VuFind\AjaxHandler\AbstractBase implements
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \VuFind\I18n\HasSorterInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\I18n\HasSorterTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Top-level configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * EDS Publication Finder configuration
     *
     * @var Config
     */
    protected $epfConfig;

    /**
     * EDS Publication Finder session
     *
     * @var Config
     */
    protected $epfSession;

    /**
     * EDS Publication Finder session token
     *
     * @var Config
     */
    protected $epfSessionToken;

    /**
     * EDS Publication Finder session expiration length
     *
     * @var Config
     */
    protected $epfSessionDuration;

    /**
     * EDS Publication Finder session creation time
     *
     * @var Config
     */
    protected $epfSessionCreation;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss        Session settings
     * @param Config            $config    Top-level configuration
     * @param Config            $epfConfig EBSCO Publication finder configuration
     * @param Connection        $ils       ILS connection
     * @param RendererInterface $renderer  View renderer
     */
    public function __construct(
        SessionSettings $ss,
        Config $config,
        Config $epfConfig,
        Connection $ils,
        RendererInterface $renderer,
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->epfConfig = $epfConfig;
        $this->ils = $ils;
        $this->renderer = $renderer;
    }

    /**
     * If not already created, will authenticate and create a new session with EDS
     *
     * @return null
     */
    protected function refreshEpfSession()
    {
        $token = null;
        $auth_url = $this->epfConfig?->General?->auth_url . '/uidauth';
        $session_url = $this->epfConfig?->General?->session_url . '/CreateSession';

        $user = $this->epfConfig?->EBSCO_Account?->user_name;
        $pass = $this->epfConfig?->EBSCO_Account?->password;
        $profile = $this->epfConfig?->EBSCO_Account?->profile;
        $org = $this->epfConfig?->EBSCO_Account?->organization_id;

        // The session expired or doesn't exist, create a new one
        if (
            null === $this->epfSessionCreation || null === $this->epfSessionDuration ||
            null === $this->epfSession || date('Y-m-d H:i:s') - $this->epfSessionCreation >= $this->epfSessionDuration
        ) {
            // Authenticate to get the token
            $curl = curl_init();
            curl_setopt_array($curl, [
              CURLOPT_URL => $auth_url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_TIMEOUT => 8,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => '{
                "UserId": "' . $user . '",
                "Password": "' . $pass . '"
              }',
              CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
              ],
            ]);
            $response = curl_exec($curl);

            // if there are no errors, attempt to decode the json response
            if ($e = curl_error($curl)) {
                $this->logError('Error occurred when authenticating to EDS. ' . $e);
            } else {
                $data = json_decode($response, true);
                if ($data === null) {
                    $this->logError('Error occured parsing the JSON data from authentication endpoint. Error: ' .
                                    json_last_error_msg() . '. Full Response: ' . $response);
                } else {
                    $this->epfSessionToken = $data['AuthToken'];
                    $this->epfSessionDuration = $data['AuthTimeout'];
                    $this->epfSessionCreation = date('Y-m-d H:i:s');
                }
            }
            curl_close($curl);

            // Create the session
            if (null !== $this->epfSessionToken) {
                $curl = curl_init();
                curl_setopt_array($curl, [
                  CURLOPT_URL => $session_url,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_TIMEOUT => 8,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_CUSTOMREQUEST => 'POST',
                  CURLOPT_POSTFIELDS => '{
                    "Profile": "' . $profile . '",
                    "Guest": "n",
                    "Org": "' . $org . '"
                  }',
                  CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'x-authenticationToken: ' . $this->epfSessionToken,
                  ],
                ]);
                $response = curl_exec($curl);
                // if there are no errors, attempt to decode the json response
                if ($e = curl_error($curl)) {
                    $this->logError('Error occurred when creating the session with EDS. ' . $e);
                } else {
                    $data = json_decode($response, true);
                    if ($data === null) {
                        $this->logError('Error occured parsing the JSON data from the session endpoint. Error: ' .
                                        json_last_error_msg() . '. Full Response: ' . $response);
                    } else {
                        $this->epfSession = $data['SessionToken'];
                    }
                }
                curl_close($curl);
            }
        }
    }

    /**
     * Get the list of publishers associated for a given title
     *
     * @param String $title Title to search for
     *
     * @return array The publisher names and resource URLs
     *               associated for that publisher and title
     */
    protected function getPublishers($title)
    {
        $publishers = [];
        $this->refreshEpfSession();
        $epf_url = $this->epfConfig?->General?->api_url . '/search';

        if (empty($title)) {
            return $publishers; // only search for publishers if a title is provided
        }

        $this->debug('Calling EPF function to get publishers for ' . $title);

        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $epf_url . '?query=' . urlencode($title),
          CURLOPT_HTTPGET => true,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 8,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-sessionToken: ' . $this->epfSession,
            'x-authenticationToken: ' . $this->epfSessionToken,
          ],
        ]);
        $response = curl_exec($curl);
        // if there are no errors, attempt to decode the json response
        if ($e = curl_error($curl)) {
            $this->logError('Error occurred when searching EPF for publishers. ' . $e);
        } else {
            $data = json_decode($response, true);
            if ($data === null) {
                $this->logError('Error occured parsing the JSON data from the EPF search endpoint. Error: ' .
                                json_last_error_msg() . '. Full Response: ' . $response);
            } else {
                // Parse the publisher data to get the names
                $fullTextHoldings = $data['SearchResult']['Data']['Records'][0]['FullTextHoldings'] ?? [];
                foreach ($fullTextHoldings as $fullTextHolding) {
                    if (!empty($fullTextHolding['Name'] ?? '')) {
                        $publishers[] = [
                            'publisher' => $fullTextHolding['Name'] ?? '',
                            'resource_url' => $fullTextHolding['URL'] ?? '',
                        ];
                    }
                }
            }
        }
        curl_close($curl);
        return $publishers;
    }

    /**
     * Given a title, retrieve the license agreeement information.
     *
     * @param String $title Title to search for
     *
     * @return array Data from the FOLIO license agreement call
     */
    protected function getLicenseAgreements($title)
    {
        $licenseAgreements = [];

        // Call EDS's Publication Finder API with the title parameter
        $publisherData = $this->getPublishers($title);

        // Call the ILS's getLicenseAgreement method to add in additional data
        foreach ($publisherData as $pubRecord) {
            $record = $this->ils->getLicenseAgreement($pubRecord['publisher']);
            $record['publisher'] = $pubRecord['publisher'];
            $record['resource_url'] = $pubRecord['resource_url'];
            $licenseAgreements[] = $record;
        }

        return $licenseAgreements;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $results = [];
        $licenseAgreements = [];
        $this->disableSessionWrites();  // avoid session write timing bug
        $title = $params->fromPost('title') ?? $params->fromQuery('title');

        try {
            $licenseAgreements = $this->getLicenseAgreements($title);
        } catch (ILSException $e) {
            // If the ILS fails, send an error response instead of a fatal
            // error; we don't want to confuse the end user unnecessarily.
            error_log($e->getMessage());
            $results[] = [
                [
                    'title' => $title,
                    'error' => 'An error has occurred',
                ],
            ];
        }

        if (!is_array($licenseAgreements)) {
            // If getLicenseAgreements returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = [];
        }

        // Render the data that came back
        foreach ($licenseAgreements as $licenseAgreement) {
            $results[] = [
                [
                    'title'                 => $title,
                    'publisher'             => $licenseAgreement['publisher'],
                    'resource_url'          => $licenseAgreement['resource_url'],
                    'accessibility_link'    => $this->renderer->render(
                        'ajax/accessibility-link.phtml',
                        ['licenseAgreement' => $licenseAgreement]
                    ),
                    'concurrent_users'      => $this->renderer->render(
                        'ajax/license-concurrent-users-data.phtml',
                        ['licenseAgreement' => $licenseAgreement]
                    ),
                    'authorized_users'      => $this->renderer->render(
                        'ajax/license-authorized-users-data.phtml',
                        ['licenseAgreement' => $licenseAgreement]
                    ),
                ],
            ];
        }

        return $this->formatResponse(compact('results'));
    }
}
