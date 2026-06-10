<?php

/**
 * Digital Repository Cover Loader for VuFind.
 *
 * This class handles fetching cover images from the MSU Digital Repository
 * by parsing record IDs with a 'dr.' prefix.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Content
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\Content\Covers;

/**
 * Digital Repository Cover Loader for VuFind.
 *
 * This class handles fetching cover images from the MSU Digital Repository
 * by parsing record IDs with a 'dr.' prefix.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Content
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class DigitalRepository extends \VuFind\Content\AbstractCover
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supportsRecordid = $this->cacheAllowed = $this->directUrls = true;
    }

    /**
     * Determine if this handler supports the provided identifiers.
     *
     * In this case, we look for the 'recordid' key and ensure it begins
     * with our specific repository prefix 'dr.'.
     *
     * @param array $ids Array of identifiers (recordid, isbn, etc.)
     *
     * @return bool
     */
    public function supports($ids)
    {
        // Check if our 'dr.' prefixed ID exists in the ids array
        return isset($ids['recordid']) && str_starts_with($ids['recordid'], 'dr.');
    }

    /**
     * Get metadata for the cover (the URL).
     *
     * This is called by the Cover Router to determine the image source.
     *
     * @param string $key  The identifier key being used
     * @param string $size Size of image requested (small/medium/large)
     * @param array  $ids  Array of identifiers for the record
     *
     * @return array
     */
    public function getMetadata($key, $size, $ids)
    {
        $recordId = $ids['recordid'] ?? '';

        if (str_starts_with($recordId, 'dr.')) {
            // Strip the 'dr.' prefix
            $repoId = substr($recordId, 3);

            return [
                'url' => "https://d.lib.msu.edu/{$repoId}/root/TN/view",
            ];
        }

        return [];
    }

    /**
     * Get an image URL for the specific record.
     *
     * This is the primary method used by the Cover Loader manager.
     *
     * @param string $size   Size of image requested
     * @param array  $ids    Array of identifiers
     * @param array  $params Metadata parameters (often includes recordid)
     *
     * @return string|bool   URL of the image or false if unavailable
     */
    public function getUrl($size, $ids, $params)
    {
        // Extract recordid from the params
        $recordId = $params['recordid'] ?? '';
        // Check if the record ID matches our digital repository prefix 'dr.'
        if (str_starts_with($recordId, 'dr.')) {
            // Remove the 'dr.' prefix
            $repoId = substr($recordId, 3);

            // Construct the target thumbnail URL
            $url = "https://d.lib.msu.edu/{$repoId}/root/TN/view";
            if ($this->testUrlFunction($url)) {
                return $url;
            }
        }

        return false;
    }

    /**
     * Test that the url is really working
     *
     * @param string $url image Url
     *
     * @return bool Http Client
     */
    protected function testUrlFunction($url)
    {
        try {
            $client = $this->createHttpClient($url);
            $resp = $client->send();
            $headers = $resp->getHeaders();
            if ($headers) {
                return true;
            }
        } catch (\Throwable $ex) {
            return false;
        }
        return false;
    }

    /**
     * Return a HTTP Client object
     *
     * @param string $url API Url
     *
     * @return HttpClient Http Client
     */
    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

        $client->setOptions(
            ['useragent' => 'VuFind', 'keepalive' => true]
        );

        return $client;
    }
}
