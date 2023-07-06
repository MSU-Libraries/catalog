<?php
namespace Catalog\View\Helper\Root;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class RecordFactory implements FactoryInterface
{
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
