<?php

/**
 * Custom factory for Catalog\Search\EDS\Results
 * based on VuFind\Search\Results\ResultsFactory
 * (needed because the default factory for Results is using the Results namespace to
 * find the namespace for the Params class)
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\Search\EDS;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Search\Factory\UrlQueryHelperFactory;

/**
 * Custom factory for Catalog\Search\EDS\Results
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EDSSearchResultsFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        // Replace trailing "Results" with "Params" to get the params service:
        // $paramsService = preg_replace('/Results$/', 'Params', $requestedName);
        $paramsService = 'VuFind\Search\EDS\Params';
        // Replace leading namespace with "VuFind" if service is not available:
        $paramsServiceAvailable = $container
            ->get(\VuFind\Search\Params\PluginManager::class)->has($paramsService);
        if (!$paramsServiceAvailable) {
            $paramsService = preg_replace('/^[^\\\]+/', 'VuFind', $paramsService);
        }
        $params = $container->get(\VuFind\Search\Params\PluginManager::class)
            ->get($paramsService);
        $searchService = $container->get(\VuFindSearch\Service::class);
        $recordLoader = $container->get(\VuFind\Record\Loader::class);
        $results = new $requestedName(
            $params,
            $searchService,
            $recordLoader,
            ...($options ?: [])
        );
        $results->setUrlQueryHelperFactory(
            $container->get(UrlQueryHelperFactory::class)
        );
        return $results;
    }
}
