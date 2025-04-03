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
use VuFind\Config\YamlReader;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;
use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query as Query;

use function in_array;
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
     * EPF connection
     *
     * @var Connection
     */
    protected $epf;

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
     * Config for record display (ie: concurrent users to hide)
     *
     * @var array
     */
    protected $recordConfig;

    /**
     * Concurrent users to hide, fetched from recordConfig
     *
     * @var array
     */
    private $concurrentUsersToHide;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param Config            $config   Top-level configuration
     * @param Backend           $epf      EBSCO Publication finder backend
     * @param Connection        $ils      ILS connection
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(
        SessionSettings $ss,
        Config $config,
        \VuFindSearch\Backend\EDS\Backend $epf,
        Connection $ils,
        RendererInterface $renderer,
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->epf = $epf;
        $this->ils = $ils;
        $this->renderer = $renderer;
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

        if (empty($title)) {
            return $publishers; // only search for publishers if a title is provided
        }

        $this->debug('Calling EPF function to get publishers for ' . $title);
        try {
            $query = new Query($title);
            $params = new ParamBag();

            // The documentation says that 'view' is optional,
            // but omitting it causes an error.
            // https://connect.ebsco.com/s/article/Publication-Finder-API-Reference-Guide-Search
            $params->set('view', 'brief');

            $resp = $this->epf->search($query, 0, 3, $params);

            // Parse the publisher data to get the names
            if (!isset($resp->getRecords()[0])) {
                $this->logWarning('No FullTextHoldings XML element for ' . $title);
                return $publishers;
            }
            $fullTextHoldings = $resp->getRecords()[0]->getFullTextHoldings();
            foreach ($fullTextHoldings as $fullTextHolding) {
                if (!empty($fullTextHolding['Name'] ?? '')) {
                    $publishers[] = [
                        'publisher' => $fullTextHolding['Name'] ?? '',
                        'resource_url' => $fullTextHolding['URL'] ?? '',
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logError('Error occurred when searching EPF for publishers. ' . $e);
            return $publishers;
        }

        return $publishers;
    }

    /**
     * Given a title, retrieve the license agreeement information.
     *
     * @param String $title Title to search for
     *
     * @return array Data from the FOLIO license agreement call
     */
    protected function getLicenseAgreements(string $title): array
    {
        $licenseAgreements = [];

        // Call EDS's Publication Finder API with the title parameter
        $publisherData = $this->getPublishers($title);
        $arrayToIgnore = $this->getConcurrentUsersToIgnore();

        // Call the ILS's getLicenseAgreement method to add in additional data
        foreach ($publisherData as $pubRecord) {
            $record = $this->ils->getLicenseAgreement($pubRecord['publisher']);
            if (isset($record['ConcurrentUsers']) && in_array($record['ConcurrentUsers'], $arrayToIgnore)) {
                unset($record['ConcurrentUsers']);
            }
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

    /**
     * Return recordConfig property and load it if not loaded
     *
     * @return array
     */
    protected function getRecordConfig(): array
    {
        if (!isset($this->recordConfig)) {
            $yamlReader = new YamlReader();
            $this->recordConfig = $yamlReader->get('record.yaml') ?? [];
        }
        return $this->recordConfig;
    }

    /**
     * Return concurrentUsersToHide property and load it if not loaded
     *
     * @return array
     */
    protected function getConcurrentUsersToIgnore(): array
    {
        if (!isset($this->concurrentUsersToHide)) {
            $this->concurrentUsersToHide =
                $this->getRecordConfig()['licenseAgreement']['concurrent_users_to_hide'] ?? [];
        }
        return $this->concurrentUsersToHide;
    }
}
