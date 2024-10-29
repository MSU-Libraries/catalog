<?php

/**
 * Record view helper Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/

namespace CatalogTest\View\Helper\Root;

use Catalog\View\Helper\Root\Record;
use Laminas\Config\Config;
use Laminas\View\Resolver\ResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Cover\Loader;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Tags\TagsService;
use VuFind\View\Helper\Root\Context;
use VuFind\View\Helper\Root\SearchTabs;
use VuFindTheme\ThemeInfo;

use function is_array;

/**
 * Record view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

class RecordTest extends \PHPUnit\Framework\TestCase
{
    use \Catalog\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Theme to use for testing purposes.
     *
     * @var string
     */
    protected $testTheme = 'msul';

    /**
     * Test getLinkDetails
     *
     * @return null
     */
    public function testLoadingInvalidFixture()
    {
        $this->expectException(\Exception::class);
        $record = $this->getRecord($this->loadRecordFixture('invalid.json'));
    }

    /**
     * Test getLinkDetails
     *
     * @return null
     */
    public function testGetLinkDetails()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals([], $record->getLinkDetails());
    }

    /**
     * Test getLinkTargetLabel
     *
     * @return null
     */
    public function testGetLinkTargetLabelEmpty()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(['desc' => 'Access Content Online'], $record->getLinkTargetLabel([]));
    }

    /**
     * Test getLinkTargetLabel with bookplate url
     *
     * @return null
     */
    public function testGetLinkTargetLabelBookplate()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(
            ['desc' => 'Book Plate: Test', 'url' => 'https://bookplate'],
            $record->getLinkTargetLabel(['url' => 'https://bookplate', 'desc' => 'Test'])
        );
    }

    /**
     * Test getLinkTargetLabel with link labels
     *
     * @return null
     */
    public function testGetLinkTargetLabelLinkLabels()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));

        // missing required 'label' key
        $this->setProperty($record, 'linkLabels', [['test' => 'value']]);
        $this->assertEquals(['desc' => 'Access Content Online'], $record->getLinkTargetLabel([]));

        $this->setProperty($record, 'linkLabels', [['label' => 'value', 'desc' => '#test#']]);
        $this->assertEquals(['desc' => 'test (value)'], $record->getLinkTargetLabel(['desc' => 'test']));
    }

    /**
     * Test getThumbnail empty
     *
     * @return null
     */
    public function testGetThumbnail()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(
            'http://foo/bar?author=&callnumber=&size=small&title=Bollettino+della+' .
            'Unione+matematica+italiana.&recordid=000105196&source=Solr&issn=0041-7084',
            $record->getThumbnail()
        );
    }

    /**
     * Test getStatus not providing a holding record
     *
     * @return null
     */
    public function testGetStatusWithNoHolding()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('Unavailable (Unknown)', $record->getStatus(null, false));
    }

    /**
     * Test getStatus providing a holding record missing required keys
     *
     * @return null
     */
    public function testGetStatusWithMissingKey()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('Unavailable (Unknown)', $record->getStatus([], false));
    }

    /**
     * Test getStatus with an available status
     *
     * @return null
     */
    public function testGetStatusAvailable()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('Available', $record->getStatus(['status' => 'Available'], false));
    }

    /**
     * Test getStatus with an unavailable status
     *
     * @return null
     */
    public function testGetStatusUnavailable()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('Unavailable (In process)', $record->getStatus(['status' => 'In process'], false));
    }

    /**
     * Test getStatus with an restricted status
     *
     * @return null
     */
    public function testGetStatusRestricted()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('Library Use Only', $record->getStatus(['status' => 'Restricted'], false));
    }

    /**
     * Test getStatus with a checked out status
     *
     * @return null
     */
    public function testGetStatusCheckedOut()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(
            'Checked Out (Awaiting pickup)',
            $record->getStatus(['status' => 'Awaiting pickup'], false)
        );
        $this->assertEquals(
            'Checked Out (Awaiting delivery)',
            $record->getStatus(['status' => 'Awaiting delivery'], false)
        );
        $this->assertEquals(
            'Checked Out (In transit)',
            $record->getStatus(['status' => 'In transit'], false)
        );
        $this->assertEquals(
            'Checked Out (Paged)',
            $record->getStatus(['status' => 'Paged'], false)
        );
    }

    /**
     * Test getStatus with a reserve flag
     *
     * @return null
     */
    public function testGetStatusReserve()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('On Reserve', $record->getStatus(['status' => 'Available', 'reserve' => 'Y'], false));
    }

    /**
     * Test getStatus with unknown status
     *
     * @return null
     */
    public function testGetStatusUnknown()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals('Unknown status (test)', $record->getStatus(['status' => 'test'], false));
    }

    /**
     * Test getStatus with return date
     *
     * @return null
     */
    public function testGetStatusReturnDate()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(
            'Checked Out (Paged) - 1/1/2000',
            $record->getStatus(['status' => 'Paged', 'returnDate' => '1/1/2000'], false)
        );
    }

    /**
     * Test getStatus with due date
     *
     * @return null
     */
    public function testGetStatusDueDate()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(
            'Checked Out (Paged) - Due:1/1/2000',
            $record->getStatus(['status' => 'Paged', 'duedate' => '1/1/2000'], false)
        );
    }

    /**
     * Test getStatus with due date
     *
     * @return null
     */
    public function testGetStatusLoanType()
    {
        $record = $this->getRecord($this->loadRecordFixture('record1.json'));
        $this->assertEquals(
            'Checked Out (Paged) (LoanType)',
            $record->getStatus(['status' => 'Paged', 'temporary_loan_type' => 'LoanType'], false)
        );
    }

    /**
     * Test loadLibKeyConfigs
     *
     * @return null
     */
    public function testloadLibKeyConfigs()
    {
        $record = $this->getRecord(
            $this->loadRecordFixture('record1.json'),
            browzineConfig: ['General' => ['access_token' => '123', 'library_id' => '9']]
        );
        // Verify it is empty before the method call
        $this->assertEquals('', $this->getProperty($record, 'libkeyAccessToken'));
        $this->assertEquals('', $this->getProperty($record, 'libkeyLibraryId'));
        $this->assertEquals(null, $this->callMethod($record, 'loadLibKeyConfigs', []));
        // Verify it is correctly populated after the method call
        $this->assertEquals('123', $this->getProperty($record, 'libkeyAccessToken'));
        $this->assertEquals('9', $this->getProperty($record, 'libkeyLibraryId'));
    }

    /**
     * Test loadLibKeyConfigs with config already loaded
     *
     * @return null
     */
    public function testloadLibKeyConfigsAlreadySet()
    {
        $record = $this->getRecord(
            $this->loadRecordFixture('record1.json'),
            browzineConfig: ['General' => ['access_token' => '123', 'library_id' => '9']]
        );
        $this->setProperty($record, 'libkeyAccessToken', '456');
        $this->setProperty($record, 'libkeyLibraryId', '8');
        $this->assertEquals(null, $this->callMethod($record, 'loadLibKeyConfigs', []));
        // Verify it is correctly populated after the method call
        $this->assertEquals('456', $this->getProperty($record, 'libkeyAccessToken'));
        $this->assertEquals('8', $this->getProperty($record, 'libkeyLibraryId'));
    }

    /**
     * Test getLibKeyJSON
     *
     * @return null
     */
    public function testGetLibKeyJSONNoAccess()
    {
        $record = $this->getRecord(
            $this->loadRecordFixture('record1.json'),
            browzineConfig: ['General' => ['access_token' => '123', 'library_id' => '9']]
        );
        $this->assertEquals(
            [
                'status' => 401,
                'error' => 'invalid_token',
                'error_description' => 'unknown_token',
            ],
            $this->callMethod($record, 'getLibKeyJSON', ['123'])
        );
    }

    /**
     * Test getLibKeyJSON
     *
     * @return null
     */
    public function testGetLibKeyJSONNoConfig()
    {
        $record = $this->getRecord(
            $this->loadRecordFixture('record1.json')
        );
        $this->assertEquals('', $this->callMethod($record, 'getLibKeyJSON', ['123']));
    }

    /**
     * Test getLibKeyData
     *
     * @return null
     */
    public function testGetLibKeyDataNoConfig()
    {
        $record = $this->getRecord(
            $this->loadRecordFixture('record1.json')
        );
        $this->assertEquals(
            ['pdf' => '', 'article' => '', 'issue' => '', 'openAccess' => false],
            $this->callMethod($record, 'getLibKeyData', ['123'])
        );
    }

    /**
     * Test getLibKeyData
     *
     * @return null
     */
    public function testGetLibKeyDataNoAccess()
    {
        $record = $this->getRecord(
            $this->loadRecordFixture('record1.json'),
            browzineConfig: ['General' => ['access_token' => '123', 'library_id' => '9']]
        );
        $this->assertEquals(
            ['pdf' => '', 'article' => '', 'issue' => '', 'openAccess' => false],
            $this->callMethod($record, 'getLibKeyData', ['123'])
        );
    }

    /**
     * Get a Record object ready for testing.
     *
     * @param RecordDriver $driver                   Record driver
     * @param array|Config $config                   Configuration
     * @param array|Config $browzineConfig           Configuration for BrowZine
     * @param Context      $context                  Context helper
     * @param bool|string  $url                      Should we add a URL helper? False if no, expected route if yes.
     * @param bool         $serverurl                Should we add a ServerURL helper?
     * @param bool         $setSearchTabExpectations Should we set default search tab expectations?
     *
     * @return Record
     */
    protected function getRecord(
        RecordDriver $driver,
        array|Config $config = [],
        $browzineConfig = [],
        Context $context = null,
        bool|string $url = false,
        bool $serverurl = false,
        bool $setSearchTabExpectations = true
    ) {
        if (null === $context) {
            $context = $this->getMockContext();
        }
        $container = new \VuFindTest\Container\MockViewHelperContainer($this);
        $view = $container->get(
            \Laminas\View\Renderer\PhpRenderer::class,
            ['render', 'resolver']
        );
        $container->set('context', $context);
        $container->set('serverurl', $serverurl ? $this->getMockServerUrl() : false);
        $container->set('url', $url ? $this->getMockUrl($url) : $url);
        $container->set('searchTabs', $this->getMockSearchTabs($setSearchTabExpectations));
        $view->setHelperPluginManager($container);
        $view->expects($this->any())->method('resolver')
            ->willReturn($this->getMockResolver());
        $config = is_array($config) ? new \Laminas\Config\Config($config) : $config;
        $browzineConfig = is_array($browzineConfig) ? new \Laminas\Config\Config($browzineConfig) : $browzineConfig;
        $record = new Record($this->createMock(TagsService::class), $config, $browzineConfig);
        $record->setCoverRouter(new \VuFind\Cover\Router('http://foo/bar', $this->getCoverLoader()));
        $record->setView($view);

        return $record($driver);
    }

    /**
     * Get a mock resolver object
     *
     * @return MockObject&ResolverInterface
     */
    protected function getMockResolver(): MockObject&ResolverInterface
    {
        return $this->createMock(ResolverInterface::class);
    }

    /**
     * Get a mock context object
     *
     * @return MockObject&Context
     */
    protected function getMockContext(): MockObject&Context
    {
        $context = $this->createMock(\VuFind\View\Helper\Root\Context::class);
        $context->expects($this->any())->method('__invoke')->willReturn($context);
        return $context;
    }

    /**
     * Get a mock URL helper
     *
     * @param string $expectedRoute Route expected by mock helper
     *
     * @return MockObject&Url
     */
    protected function getMockUrl($expectedRoute): MockObject&Url
    {
        $url = $this->createMock(Url::class);
        $url->expects($this->once())->method('__invoke')
            ->with($this->equalTo($expectedRoute))
            ->willReturn('http://foo/bar');
        return $url;
    }

    /**
     * Get a mock server URL helper
     *
     * @return MockObject&ServerUrl
     */
    protected function getMockServerUrl(): MockObject&ServerUrl
    {
        $url = $this->createMock(ServerUrl::class);
        $url->expects($this->once())->method('__invoke')->willReturn('http://server-foo/baz');
        return $url;
    }

    /**
     * Get a mock search tabs view helper
     *
     * @param bool $setDefaultExpectations Should we set up default expectations?
     *
     * @return MockObject&SearchTabs
     */
    protected function getMockSearchTabs($setDefaultExpectations = true)
    {
        $searchTabs = $this->getMockBuilder(SearchTabs::class)
            ->disableOriginalConstructor()->getMock();
        if ($setDefaultExpectations) {
            $searchTabs->expects($this->any())->method('getCurrentHiddenFilterParams')->willReturn('');
        }
        return $searchTabs;
    }

    /**
     * Load a fixture file.
     *
     * @param string $file File to load from fixture directory.
     *
     * @return array
     */
    protected function loadRecordFixture(string $file): object
    {
        $json = $this->getJsonFixture('misc/' . $file, 'Catalog');
        $record = new \VuFind\RecordDriver\SolrMarc();
        $record->setRawData($json['response']['docs'][0]);
        return $record;
    }

    /**
     * Get a loader object to test.
     *
     * @param array                                $config      Configuration
     * @param \VuFind\Content\Covers\PluginManager $manager     Plugin manager (null to create mock)
     * @param ThemeInfo                            $theme       Theme info object (null to create default)
     * @param \VuFindHttp\HttpService              $httpService HTTP client factory
     * @param array|bool                           $mock        Array of functions to mock, or false for real object
     *
     * @return Loader
     */
    protected function getCoverLoader(
        array $config = [],
        \VuFind\Content\Covers\PluginManager $manager = null,
        ThemeInfo $theme = null,
        \VuFindHttp\HttpService $httpService = null,
        array|bool $mock = false
    ): Loader {
        $config = new Config($config);
        if (null === $manager) {
            $manager = $this->createMock(\VuFind\Content\Covers\PluginManager::class);
        }
        if (null === $theme) {
            $theme = new ThemeInfo($this->getThemeDir(), $this->testTheme);
        }
        if (null === $httpService) {
            $httpService = $this->getMockBuilder(\VuFindHttp\HttpService::class)->getMock();
        }
        if ($mock) {
            return $this->getMockBuilder(__NAMESPACE__ . '\MockLoader')
                ->onlyMethods($mock)
                ->setConstructorArgs([$config, $manager, $theme, $httpService])
                ->getMock();
        }
        return new Loader($config, $manager, $theme, $httpService);
    }

    /**
     * Get the theme directory.
     *
     * @return string
     */
    protected function getThemeDir(): string
    {
        return realpath('/usr/local/vufind/themes');
    }
}
