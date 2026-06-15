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
use CatalogTest\Feature\PathFixerTrait;
use Laminas\Http\Response;
use VuFind\Config\Config;

use function is_array;

/**
 * FOLIO ILS driver test
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FolioTest extends \VuFindTest\ILS\Driver\FolioTest
{
    use PathFixerTrait;

    protected $driverClass = \Catalog\ILS\Driver\Folio::class;

    protected $forceCatalogPath = null;

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
        $mockMsul = new Config($msulConfig ?? []);
        // Create a stub for the SomeClass class
        $this->driver = $this->getMockBuilder(Folio::class)
            ->setConstructorArgs([new \VuFind\Date\Converter(), $factory, $mockMsul])
            ->onlyMethods(['makeRequest', 'makeExternalRequest', 'makeRequestAsync'])
            ->getMock();
        // Mock the Guzzle Service
        $mockGuzzleService = $this->createMock(\VuFind\Http\GuzzleService::class);
        $mockGuzzleService->expects($this->any())
            ->method('createClient')
            ->willReturn(new \GuzzleHttp\Client());
        $this->driver->setGuzzleService($mockGuzzleService);
        // Configure the stub
        $this->driver->setConfig($config ?? $this->defaultDriverConfig);
        $cache = new \Laminas\Cache\Storage\Adapter\Memory();
        $cache->setOptions(['memory_limit' => -1]);
        $this->driver->setCacheStorage($cache);
        $this->driver->method('makeRequest')->willReturnCallback([$this, 'mockMakeRequest']);
        // For simplicity (and since it's just mocking a request and not actually making one)
        // we'll have calls to makeExternalRequest go to the same mock for makeRequest
        $this->driver->method('makeExternalRequest')->willReturnCallback([$this, 'mockMakeRequest']);
        // The Async request function is almost identical to make request, but it returns
        // a Guzzle promise instead
        $this->driver->method('makeRequestAsync')->willReturnCallback(function (...$args) {
            $laminasResponse = $this->mockMakeRequest('GET', ...$args);

            $content = $laminasResponse->getContent();
            if (is_array($content)) {
                $content = json_encode($content);
            }
            $guzzleResponse = new \GuzzleHttp\Psr7\Response(
                $laminasResponse->getStatusCode(),
                $laminasResponse->getHeaders()->toArray(),
                $content
            );

            return new \GuzzleHttp\Promise\FulfilledPromise($guzzleResponse);
        });
        $this->driver->init();
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
                    'loan_type_name' => '',
                    'material_type' => '',
                    'loan_type_id' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
    }

    /**
     * Make sure at least one loan gets returned
     *
     * @return void
     */
    public function testCheckValidToken(): void
    {
        $this->createConnector('check-valid-token');
        $result = $this->driver->getMyTransactions(['id' => 'whatever']);
        $this->assertCount(1, $result['records'], 'Expected 1 loan from fixture');
    }

    /**
     * Test patron login with Okapi (Legacy authentication)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokensWithLegacyAuth')]
    public function testSuccessfulPatronLoginWithOkapiLegacyAuth(): void
    {
        $config = $this->defaultDriverConfig;
        $config['API']['tenant'] = 'legacy_tenant';
        $config['API']['legacy_authentication'] = 1;
        $this->createConnector(
            'successful-patron-login-with-okapi-legacy',
            $config + ['User' => ['okapi_login' => true]]
        );
        $result = $this->driver->patronLogin('foo', 'bar');
        $expected = [
            'id' => 'fake-id',
            'username' => 'foo',
            'cat_username' => 'foo',
            'cat_password' => 'bar',
            'firstname' => 'first',
            'lastname' => 'last',
            'email' => 'fake@fake.com',
            'addressTypeIds' => [],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test patron login with Okapi (RTR authentication)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSuccessfulPatronLoginWithOkapi(): void
    {
        $this->createConnector(
            'successful-patron-login-with-okapi',
            $this->defaultDriverConfig + ['User' => ['okapi_login' => true]]
        );
        $result = $this->driver->patronLogin('foo', 'bar');
        $expected = [
            'id' => 'fake-id',
            'username' => 'foo',
            'cat_username' => 'foo',
            'cat_password' => 'bar',
            'firstname' => 'first',
            'lastname' => 'last',
            'email' => 'fake@fake.com',
            'addressTypeIds' => [],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getHolding with HRID-based lookup
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingWithHridLookup(): void
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
     * Get expected result of getHoldings(), used by testGetHoldingsWithMultipleIds().
     *
     * @return array
     */
    protected function getExpectedGetHoldingsWithMultipleIdsResult(): array
    {
        return [
            [
                'total' => 1,
                'holdings' => [
                    0 => [
                        'callnumber_prefix' => '',
                        'callnumber' => 'PS2394 .M643 1883',
                        'id' => 'foo',
                        'item_id' => 'itemid',
                        'holdings_id' => 'abbd2c2b-b2a1-4324-bd24-10f990cfc594',
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
                        'location' => 'Special Collections',
                        'location_code' => 'DCOC',
                        'reserve' => 'TODO',
                        'addLink' => 'check',
                        'bound_with_records' => [],
                        'folio_location_is_active' => true,
                        'loan_type_id' => '',
                        'loan_type_name' => '',
                        'issues' => [0 => 'foo', 1 => 'bar baz'],
                        'electronic_access' => [],
                        'material_type' => '',
                    ],
                ],
                'electronic_holdings' => [],
            ],
            [
                'total' => 2,
                'holdings' => [
                    0 => [
                        'callnumber_prefix' => '',
                        'callnumber' => 'PS3551.S5 R6 1983',
                        'id' => 'bar',
                        'item_id' => '3258389f-ed1a-406f-8627-97a78d832003',
                        'holdings_id' => '2216df84-b841-490c-8bde-b83076c5c4f4',
                        'number' => '2',
                        'enumchron' => '',
                        'barcode' => '12345678901234',
                        'status' => 'Available',
                        'duedate' => '',
                        'availability' => true,
                        'is_holdable' => true,
                        'holdings_notes' => null,
                        'item_notes' => null,
                        'summary' => [],
                        'supplements' => [],
                        'indexes' => [],
                        'location' => 'Main Library',
                        'location_code' => 'mnmn',
                        'reserve' => 'TODO',
                        'addLink' => 'check',
                        'bound_with_records' => [],
                        'folio_location_is_active' => true,
                        'loan_type_id' => 'd012791f-4e26-4dc2-a279-b6a42b1df315',
                        'loan_type_name' => 'Can Circulate',
                        'issues' => [],
                        'electronic_access' => [],
                        'material_type' => 'Printed Material',
                    ],
                    1 => [
                        'callnumber_prefix' => '',
                        'callnumber' => 'PS3551.S5 R6 1983b',
                        'id' => 'bar',
                        'item_id' => '393c1119-d9b8-4f69-bb44-4a44dbe16c3e',
                        'holdings_id' => '2216df84-b841-490c-8bde-b83076c5c4f4',
                        'number' => '1',
                        'enumchron' => '',
                        'barcode' => '',
                        'status' => 'Available',
                        'duedate' => '',
                        'availability' => true,
                        'is_holdable' => true,
                        'holdings_notes' => null,
                        'item_notes' => null,
                        'summary' => [],
                        'supplements' => [],
                        'indexes' => [],
                        'location' => 'Main Library',
                        'location_code' => 'mnmn',
                        'reserve' => 'TODO',
                        'addLink' => 'check',
                        'bound_with_records' => [],
                        'folio_location_is_active' => true,
                        'loan_type_id' => 'd012791f-4e26-4dc2-a279-b6a42b1df315',
                        'loan_type_name' => 'Can Circulate',
                        'issues' => [],
                        'electronic_access' => [],
                        'material_type' => 'Printed Material',
                    ],
                ],
                'electronic_holdings' => [],
            ],
        ];
    }

    /**
     * Test getStatuses.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetStatuses(): void
    {
        // getStatuses is just a wrapper around getHolding, so we can test it with
        // a minor variation of the test above.
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
        $this->assertEquals(
            [$this->getExpectedGetHoldingResult()['holdings']],
            $this->driver->getStatuses(['foo'])
        );
    }

    /**
     * Test getHolding with FOLIO-based sorting.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingWithFolioSorting(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['folio_sort'] = 'volume';
        $this->createConnector('get-holding-sorted', $driverConfig);
        $expected = [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => '1',
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => 'check',
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                    'issues' => [],
                    'electronic_access' => [],
                    'material_type' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getHolding filters empty holding statements appropriately.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingFilteringOfEmptyHoldingStatements(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['folio_sort'] = 'volume';
        $this->createConnector('get-holding-empty-statements', $driverConfig);
        $expected = [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => '1',
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => ['summ1', 'summ2'],
                    'supplements' => ['supp1', 'supp2'],
                    'indexes' => ['ind1', 'ind2'],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => 'check',
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                    'issues' => [0 => 'summ1', 1 => 'summ2'],
                    'electronic_access' => [],
                    'material_type' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getHolding with checked out item.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingWithDueDate(): void
    {
        $this->createConnector('get-holding-checkedout');
        $expected = [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => '1',
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Checked out',
                    'duedate' => '06-01-2023',
                    'availability' => false,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => 'check',
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                    'issues' => [],
                    'electronic_access' => [],
                    'material_type' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getHolding with VuFind-based sorting.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingMultiVolumeWithVuFindSorting(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['vufind_sort'] = 'enumchron';
        $this->createConnector('get-holding-multi-volume', $driverConfig);
        $expected = [
            'total' => 2,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid2',
                    'holdings_id' => 'holdingid',
                    'number' => '1',
                    'enumchron' => 'v.2',
                    'barcode' => 'barcode-test2',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => 'check',
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                    'issues' => [],
                    'electronic_access' => [],
                    'material_type' => '',
                ],
                1 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => '2',
                    'enumchron' => 'v.100',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => 'check',
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                    'issues' => [],
                    'electronic_access' => [],
                    'material_type' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test successful call to holds, one available item
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testAvailableItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-available');
        $patron = ['id' => 'foo'];

        $result = $this->driver->getMyHolds($patron);

        $expected[0] = [
            'type' => 'Page',
            'create' => '12-20-2022',
            'expire' => '',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Presentation secrets : do what you never thought possible with your presentations ',
            'available' => true,
            'in_transit' => false,
            'last_pickup_date' => '12-29-2022',
            'position' => 1,
            // --- MSUL Customizations ---
            'processed' => true,
            'location' => 'Item',
            'updateDetails' => 'fake-instance-id',
            'status' => 'Open - Awaiting pickup',
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, one available item placed for a proxy
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testAvailableProxyItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-available-proxy');
        $patron = [
            'id' => 'bar',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '12-20-2022',
            'expire' => '',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Presentation secrets : do what you never thought possible with your presentations ',
            'available' => true,
            'in_transit' => false,
            'last_pickup_date' => '12-29-2022',
            'position' => 1,
            'proxiedFor' => 'TestuserJohn, John',
            // --- MSUL Customizations ---
            'processed' => true,
            'location' => 'Item',
            'updateDetails' => 'fake-instance-id',
            'status' => 'Open - Awaiting pickup',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, one in_transit item
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testInTransitItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-in_transit');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '11-07-2022',
            'expire' => '',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Basic economics : a common sense guide to the economy ',
            'available' => false,
            'in_transit' => true,
            'last_pickup_date' => null,
            'position' => 1,
            // --- MSUL Customizations ---
            'processed' => true,
            'location' => 'Item',
            'updateDetails' => 'fake-instance-id',
            'status' => 'Open - In transit',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, item in queue, position x
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSingleItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-single');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Hold',
            'create' => '12-20-2022',
            'expire' => '12-28-2022',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Organic farming : everything you need to know ',
            'available' => false,
            'in_transit' => false,
            'last_pickup_date' => null,
            'position' => 3,
            // --- MSUL Customizations ---
            'processed' => false,
            'location' => 'Item',
            'updateDetails' => 'fake-instance-id',
            'status' => 'Open - Not yet filled',
        ];
        $this->assertEquals($expected, $result);
    }

    /*
     * ========================================================================
     * START OF MSUL CUSTOM TESTS
     * ========================================================================
     */

    /**
     * Test getHolding with a mnmn location code to add data from helm
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
        $expectedResults['holdings'][0]['msulLocation'] = 'That Room';
        $this->assertEquals($expectedResults, $this->driver->getHolding('foo'));
    }

    /**
     * Test getHolding with a mnmn location code, but no msul.ini config file
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
    }

    /**
     * Test getHolding with a mnmn location code, but missing data from helm
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
