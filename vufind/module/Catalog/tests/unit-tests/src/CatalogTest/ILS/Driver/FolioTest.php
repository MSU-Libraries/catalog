<?php

/**
 * FOLIO ILS driver test
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/

namespace CatalogTest\ILS\Driver;

use Catalog\ILS\Driver\Folio;
use Laminas\Http\Response;

/**
 * FOLIO ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FolioTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'API' => [
            'base_url' => 'localhost',
            'tenant' => 'config_tenant',
            'username' => 'config_username',
            'password' => 'config_password',
            'legacy_authentication' => false,
        ],
    ];

    /**
     * Test data for simulated HTTP responses (reset by each test)
     *
     * @var array
     */
    protected $fixtureSteps = [];

    /**
     * Current fixture step
     *
     * @var int
     */
    protected $currentFixtureStep = 0;

    /**
     * Current fixture name
     *
     * @var string
     */
    protected $currentFixture = 'none';

    /**
     * Driver under test
     *
     * @var Folio
     */
    protected $driver = null;

    /**
     * Replace makeRequest to inject test returns
     *
     * @param string       $method  GET/POST/PUT/DELETE/etc
     * @param string       $path    API path (with a leading /)
     * @param string|array $params  Parameters object to be sent as data
     * @param array        $headers Additional headers
     *
     * @return Response
     */
    public function mockMakeRequest(
        string $method = 'GET',
        string $path = '/',
        $params = [],
        array $headers = []
    ): Response {
        // Run preRequest
        $httpHeaders = new \Laminas\Http\Headers();
        $httpHeaders->addHeaders($headers);
        [$httpHeaders, $params] = $this->driver->preRequest($httpHeaders, $params);

        // Get the next step of the test, and make assertions as necessary
        // (we'll skip making assertions if the next step is empty):
        $testData = $this->fixtureSteps[$this->currentFixtureStep] ?? [];
        $this->currentFixtureStep++;
        unset($testData['comment']);
        if (!empty($testData)) {
            $msg = "Error in step {$this->currentFixtureStep} of fixture: "
                . $this->currentFixture . '. Requested Path: '
                . $path . 'Expected Path: ' . $testData['expectedPath'];
            $this->assertEquals($testData['expectedMethod'] ?? 'GET', $method, $msg);
            $this->assertEquals($testData['expectedPath'] ?? '/', $path, $msg);
            if (isset($testData['expectedParamsRegEx'])) {
                $this->assertMatchesRegularExpression(
                    $testData['expectedParamsRegEx'],
                    $params,
                    $msg
                );
            } else {
                $this
                    ->assertEquals($testData['expectedParams'] ?? [], $params, $msg);
            }
            $actualHeaders = $httpHeaders->toArray();
            foreach ($testData['expectedHeaders'] ?? [] as $header => $expected) {
                $this->assertEquals($expected, $actualHeaders[$header]);
            }
        }

        // Create response
        $response = new \Laminas\Http\Response();
        $response->setStatusCode($testData['status'] ?? 200);
        $bodyType = $testData['bodyType'] ?? 'string';
        $rawBody = $testData['body'] ?? '';
        $body = $bodyType === 'json' ? json_encode($rawBody) : $rawBody;
        $response->setContent($body);
        $response->getHeaders()->addHeaders($testData['headers'] ?? []);
        return $response;
    }

    /**
     * Generate a new Folio driver to return responses set in a json fixture
     *
     * Overwrites $this->driver
     * Uses session cache
     *
     * @param string $test       Name of test fixture to load
     * @param array  $config     Driver configuration (null to use default)
     * @param array  $msulConfig msul.ini configuration (null to use empty config)
     *
     * @return void
     */
    protected function createConnector(string $test, array $config = null, array $msulConfig = null): void
    {
        // Setup test responses
        $this->fixtureSteps = $this->getJsonFixture("folio/responses/$test.json", 'Catalog');
        $this->currentFixture = $test;
        $this->currentFixtureStep = 0;
        // Session factory
        $factory = function ($namespace) {
            $manager = new \Laminas\Session\SessionManager();
            return new \Laminas\Session\Container("Folio_$namespace", $manager);
        };
        // Config reader for reading msul.ini
        $mockConfigReader = $this->getMockConfigPluginManager($msulConfig ?? []);
        // Create a stub for the SomeClass class
        $this->driver = $this->getMockBuilder(Folio::class)
            ->setConstructorArgs([new \VuFind\Date\Converter(), $factory, $mockConfigReader])
            ->onlyMethods(['makeRequest', 'makeExternalRequest'])
            ->getMock();
        // Configure the stub
        $this->driver->setConfig($config ?? $this->defaultDriverConfig);
        $cache = new \Laminas\Cache\Storage\Adapter\Memory();
        $cache->setOptions(['memory_limit' => -1]);
        $this->driver->setCacheStorage($cache);
        $this->driver->expects($this->any())
            ->method('makeRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        // For simplicity (and since it's just mocking a request and not actually making one)
        // we'll have calls to makeExternalRequest go to the same mock for makeRequest
        $this->driver->expects($this->any())
            ->method('makeExternalRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        $this->driver->init();
    }

    /**
     * Request a token where one does not exist (RTR authentication)
     *
     * @return void
     */
    public function testTokens(): void
    {
        $this->createConnector('get-tokens');
        $this->driver->getMyProfile(['id' => 'whatever']);
    }

    /**
     * Get expected result of get-holding fixture (shared by multiple tests).
     *
     * @return array
     */
    protected function getExpectedGetHoldingResult(): array
    {
        return [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'foo',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => '1',
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => null,
                    'item_notes' => null,
                    'summary' => ['foo', 'bar baz'],
                    'supplements' => [],
                    'indexes' => [],
                    'location' => 'MSU Main Library - 4 East',
                    'location_code' => 'mnmn',
                    'reserve' => 'TODO',
                    'addLink' => 'check',
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'issues' => ['foo', 'bar baz'],
                    'electronic_access' => [],
                    'temporary_loan_type' => null,
                ],
            ],
            'electronic_holdings' => [],
        ];
    }

    /**
     * Test getHolding with a mnmn location code to add data from helm
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithMnmnLocationCode(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $msulConfig = [
            'msul' => [
                'Locations' => [
                    'api_url' => 'https://helm.lib.msu.edu/api/callnumbers/%%callnumber%%',
                    'response_floor_key' => 'floor',
                ],
            ],
        ];
        $this->createConnector('get-holding', $driverConfig, $msulConfig);
        $this->assertEquals($this->getExpectedGetHoldingResult(), $this->driver->getHolding('foo'));
    }

    /**
     * Test getHolding with a mnmn location code to add data from helm
     * that includes floor and location
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithMnmnLocationCodeFloorAndLocation(): void
    {
        // Make a test call number that includes the floor and location data
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $msulConfig = [
            'msul' => [
                'Locations' => [
                    'api_url' => 'https://helm.lib.msu.edu/api/callnumbers/%%callnumber%%',
                    'response_floor_key' => 'floor',
                    'response_location_key' => 'location',
                ],
            ],
        ];
        $this->createConnector('get-holding', $driverConfig, $msulConfig);
        $expectedResults = $this->getExpectedGetHoldingResult();
        $expectedResults['holdings'][0]['location'] = 'MSU Main Library - 4 East (That Room)';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));
    }

    /**
     * Test getHolding with a mnmn location code, but no msul.ini config file
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithMnmnLocationCodeNoMsulConfig(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $this->createConnector('get-holding-no-helm', $driverConfig);
        $expectedResults = $this->getExpectedGetHoldingResult();
        $expectedResults['holdings'][0]['location'] = 'MSU Main Library';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));
    }

    /**
     * Test getHolding with a mnmn location code, but missing keys from the msul.ini
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithMnmnLocationCodeInvalidMsulConfig(): void
    {
        // Test missing API url
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $msulConfig = [
            'msul' => [
                'Locations' => [
                    'response_floor_key' => 'floor',
                    'response_location_key' => 'location',
                ],
            ],
        ];
        $this->createConnector('get-holding', $driverConfig, $msulConfig);
        $expectedResults = $this->getExpectedGetHoldingResult();
        $expectedResults['holdings'][0]['location'] = 'MSU Main Library';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));
    }

    /**
     * Test getHolding with a mnmn location code, but API errors from helm
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithMnmnLocationCodeHelmErrors(): void
    {
        // Test a non-200 response code from helm
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $msulConfig = [
            'msul' => [
                'Locations' => [
                    'api_url' => 'https://helm.lib.msu.edu/api/callnumbers/%%callnumber%%',
                    'response_floor_key' => 'floor',
                    'response_location_key' => 'location',
                ],
            ],
        ];
        $this->createConnector('get-holding-helm-error', $driverConfig, $msulConfig);
        $expectedResults = $this->getExpectedGetHoldingResult();
        $expectedResults['holdings'][0]['location'] = 'MSU Main Library';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));

        // TODO -- Test invalid json response from helm (need to find out how to do this)
    }

    /**
     * Test getHolding with a mnmn location code, but missing data from helm
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithMnmnLocationCodeHelmMissingData(): void
    {
        // Test missing response keys
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $msulConfig = [
            'msul' => [
                'Locations' => [
                    'api_url' => 'https://helm.lib.msu.edu/api/callnumbers/%%callnumber%%',
                    'response_floor_key' => 'floor',
                    'response_location_key' => 'location',
                ],
            ],
        ];
        $this->createConnector('get-holding-helm-missing-data', $driverConfig, $msulConfig);
        $expectedResults = $this->getExpectedGetHoldingResult();
        $expectedResults['holdings'][0]['location'] = 'MSU Main Library';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));
    }

    /**
     * Test getHolding with a non-mnmn location code
     *
     * @depends testTokens
     *
     * @return void
     */
    public function testGetHoldingWithNonMnmnLocationCode(): void
    {
        // Pass a non-mnmn location code to make sure the API is not called
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $msulConfig = [
            'msul' => [
                'Locations' => [
                    'api_url' => 'https://helm.lib.msu.edu/api/callnumbers/%%callnumber%%',
                    'response_floor_key' => 'floor',
                ],
            ],
        ];
        $this->createConnector('get-holding-non-mnmn', $driverConfig, $msulConfig);
        $expectedResults = $this->getExpectedGetHoldingResult();
        $expectedResults['holdings'][0]['location'] = 'MSU Main Library - Location';
        $expectedResults['holdings'][0]['location_code'] = 'mmmm';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));
    }
}
