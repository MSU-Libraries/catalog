<?php>

namespace Catalog\View\Helper\Root;

use Vufind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \Vufind\View\Helper\Root\RecordDataFormatterFactory
{
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultCoreSpecs());
        $spect->setLine('Publisher', 'getPublisher');
        return $spec->getArray();
    }
}

