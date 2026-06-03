<?php

/**
 * Trait that allows prefering fixture loading from Catalog module
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/

namespace CatalogTest\Feature;

/**
 * Trait that allows prefering fixture loading from Catalog module
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/
trait PathFixerTrait
{
    /**
     * The absolute path to the vufind root directory.
     *
     * @return string
     */
    protected function getVufindRoot(): string
    {
        return '/usr/local/vufind';
    }

    /**
     * Override the main entry point for fixtures.
     *
     * @param string $file   Partial path to the requested fixture
     * @param string $module Name of the module the fixture file is in
     *
     * @return string
     */
    public function getFixture($file, $module = 'VuFind'): string
    {
        $root = $this->getVufindRoot();

        // Define search locations
        $locations = [
            $root . '/module/Catalog/tests/fixtures/' . $file,
            $root . '/module/' . $module . '/tests/fixtures/' . $file,
            $root . '/module/VuFind/tests/fixtures/' . $file,
        ];

        foreach ($locations as $location) {
            if (file_exists($location)) {
                return file_get_contents($location);
            }
        }

        throw new \Exception(
            "Could not find fixture '$file'. Checked: " . implode(', ', $locations)
        );
    }

    /**
     * We must override these because the parent createConnector()
     * uses them to build paths for Guzzle mocks.
     *
     * @param string $module Name of the module the fixture file is in
     *
     * @return string
     */
    protected function getFixtureDir($module = 'VuFind'): string
    {
        return $this->getVufindRoot() . '/module/' . $module . '/tests/fixtures';
    }

    /**
     * Define the search path for fixtures prioritizing Catalog module first
     *
     * @param string $file   Partial path to the requested fixture
     * @param string $module Name of the module the fixture file is in
     *
     * @return string
     */
    protected function getFixturePath($file, $module = 'VuFind'): string
    {
        // Check our Catalog override first
        $catalogPath = $this->getVufindRoot() . '/module/Catalog/tests/fixtures/' . $file;
        if (file_exists($catalogPath)) {
            return $catalogPath;
        }
        return $file;
    }
}
