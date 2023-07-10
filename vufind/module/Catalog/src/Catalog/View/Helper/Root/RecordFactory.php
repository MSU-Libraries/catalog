<?php

/**
 * Factory for the Record
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Record_Factory
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Catalog\View\Helper\Root;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory class for the Record implementing the FactoryInterface
 *
 * @category VuFind
 * @package  Record_Factory
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RecordFactory implements FactoryInterface
{
    /**
     * Invoke the factory for the record
     *
     * @param ContainerInterface $container     Container to get configs and invoke
     * @param string             $requestedName Record request to make
     * @param array              $options       Options to pass to the request
     *
     * @return Results from the request
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $helper = new $requestedName($config);
        $helper->setCoverRouter($container->get(\VuFind\Cover\Router::class));
        return $helper;
    }
}
